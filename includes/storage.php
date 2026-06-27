<?php
/**
 * ============================================================
 * STORAGE HELPER - SUPABASE & LOCAL FALLBACK
 * ============================================================
 */

require_once __DIR__ . '/../config/database.php';

class StorageHelper {
    /**
     * Cek apakah Supabase Storage dikonfigurasi di env/config
     */
    public static function isSupabaseEnabled(): bool {
        return defined('SUPABASE_URL') && !empty(SUPABASE_URL) && defined('SUPABASE_KEY') && !empty(SUPABASE_KEY);
    }

    /**
     * Mendapatkan mime type berdasarkan ekstensi berkas
     */
    public static function getMimeType(string $filename): string {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        switch ($ext) {
            case 'pdf':  return 'application/pdf';
            case 'webp': return 'image/webp';
            case 'png':  return 'image/png';
            case 'jpg':
            case 'jpeg': return 'image/jpeg';
            default:     return 'application/octet-stream';
        }
    }

    /**
     * Mengunggah berkas ke Supabase Storage atau penyimpanan lokal
     */
    public static function upload(string $localPath, string $fileName, string $bucket): bool {
        if (self::isSupabaseEnabled()) {
            $url = rtrim(constant('SUPABASE_URL'), '/') . "/storage/v1/object/" . $bucket . "/" . $fileName;
            $content = file_get_contents($localPath);
            if ($content === false) return false;
            
            $opts = [
                'http' => [
                    'method' => 'POST',
                    'header' => [
                        "apikey: " . constant('SUPABASE_KEY'),
                        "Authorization: Bearer " . constant('SUPABASE_KEY'),
                        "Content-Type: " . self::getMimeType($fileName),
                        "Content-Length: " . strlen($content)
                    ],
                    'content' => $content,
                    'ignore_errors' => true
                ]
            ];
            $context = stream_context_create($opts);
            $result = @file_get_contents($url, false, $context);
            
            // Periksa HTTP Status Code dari response header
            if (isset($http_response_header)) {
                foreach ($http_response_header as $header) {
                    if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/i', $header, $matches)) {
                        $code = intval($matches[1]);
                        return $code === 200 || $code === 201;
                    }
                }
            }
            return $result !== false;
        } else {
            // Penyimpanan Lokal (Docker/XAMPP)
            $destDir = ($bucket === 'pdfs') ? PDF_STORAGE : COVER_STORAGE;
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }
            return move_uploaded_file($localPath, $destDir . '/' . $fileName);
        }
    }

    /**
     * Mendapatkan URL berkas (public URL untuk Supabase, local asset URL untuk lokal)
     */
    public static function getUrl(string $fileName, string $bucket): string {
        if (self::isSupabaseEnabled()) {
            return rtrim(constant('SUPABASE_URL'), '/') . "/storage/v1/object/public/" . $bucket . "/" . $fileName;
        } else {
            if ($bucket === 'covers') {
                return ASSET_URL . '/assets/covers/' . $fileName;
            } else {
                return BASE_URL . '/read.php?id=' . $fileName . '&stream=1';
            }
        }
    }

    /**
     * Mendapatkan Signed URL untuk berkas privat di Supabase Storage
     */
    public static function getSignedUrl(string $fileName, string $bucket, int $expiresIn = 300): string|false {
        if (self::isSupabaseEnabled()) {
            $url = rtrim(constant('SUPABASE_URL'), '/') . "/storage/v1/object/sign/" . $bucket . "/" . $fileName;
            $body = json_encode(['expiresIn' => $expiresIn]);
            
            $opts = [
                'http' => [
                    'method' => 'POST',
                    'header' => [
                        "apikey: " . constant('SUPABASE_KEY'),
                        "Authorization: Bearer " . constant('SUPABASE_KEY'),
                        "Content-Type: application/json",
                        "Content-Length: " . strlen($body)
                    ],
                    'content' => $body,
                    'ignore_errors' => true
                ]
            ];
            $context = stream_context_create($opts);
            $response = @file_get_contents($url, false, $context);
            if ($response === false) return false;
            
            $data = json_decode($response, true);
            $signedUrl = $data['signedURL'] ?? $data['signedUrl'] ?? null;
            
            if ($signedUrl) {
                // Jika itu relative path, gabungkan dengan SUPABASE_URL
                if (strpos($signedUrl, 'http') !== 0) {
                    // Pastikan path memiliki prefix /storage/v1/
                    if (strpos($signedUrl, '/storage/v1/') !== 0 && strpos($signedUrl, 'storage/v1/') !== 0) {
                        $signedUrl = rtrim(constant('SUPABASE_URL'), '/') . '/storage/v1/' . ltrim($signedUrl, '/');
                    } else {
                        $signedUrl = rtrim(constant('SUPABASE_URL'), '/') . '/' . ltrim($signedUrl, '/');
                    }
                }
                return $signedUrl;
            }
        }
        return false;
    }

    /**
     * Mengalirkan (streaming) berkas PDF secara aman ke browser
     */
    public static function streamPdf(string $fileName) {
        if (self::isSupabaseEnabled()) {
            // Mengambil berkas PDF privat dari Supabase Storage
            $url = rtrim(constant('SUPABASE_URL'), '/') . "/storage/v1/object/authenticated/pdfs/" . $fileName;
            $opts = [
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        "apikey: " . constant('SUPABASE_KEY'),
                        "Authorization: Bearer " . constant('SUPABASE_KEY')
                    ],
                    'follow_location' => 0, // Menonaktifkan redirect otomatis
                    'ignore_errors' => true
                ]
            ];
            $context = stream_context_create($opts);
            $content = @file_get_contents($url, false, $context);
            
            $statusCode = 0;
            $redirectUrl = null;
            if (isset($http_response_header)) {
                foreach ($http_response_header as $header) {
                    if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/i', $header, $matches)) {
                        $statusCode = intval($matches[1]);
                    }
                    if (preg_match('/^Location:\s*(.+)$/i', $header, $matches)) {
                        $redirectUrl = trim($matches[1]);
                    }
                }
            }

            // Jika mendapatkan respon redirect (301, 302, 307) dan ada Location header
            if (($statusCode === 301 || $statusCode === 302 || $statusCode === 307) && !empty($redirectUrl)) {
                // Ambil file dari URL redirect (S3/R2 pre-signed URL) TANPA header authentication
                $optsRedirect = [
                    'http' => [
                        'method' => 'GET',
                        'ignore_errors' => true
                    ]
                ];
                $contextRedirect = stream_context_create($optsRedirect);
                $content = @file_get_contents($redirectUrl, false, $contextRedirect);
                
                $statusCode = 0;
                if (isset($http_response_header)) {
                    foreach ($http_response_header as $header) {
                        if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/i', $header, $matches)) {
                            $statusCode = intval($matches[1]);
                            break;
                        }
                    }
                }
            }

            if ($statusCode === 200 && $content !== false) {
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_write_close(); // Simpan dan tutup session agar tidak menulis cookie baru setelah body dikirim
                }
                header('Content-Type: application/pdf');
                header('Content-Length: ' . strlen($content));
                echo $content;
                exit;
            } else {
                http_response_code($statusCode ?: 500);
                die("Gagal mengambil file PDF dari Supabase Storage (Status Code: " . $statusCode . ").");
            }
        } else {
            // Streaming lokal
            $filePath = PDF_STORAGE . '/' . $fileName;
            if (!file_exists($filePath)) {
                die("File PDF tidak ditemukan di server lokal.");
            }
            header('Content-Type: application/pdf');
            header('Content-Length: ' . filesize($filePath));
            @readfile($filePath);
            exit;
        }
    }

    /**
     * Menghapus berkas dari Supabase Storage atau penyimpanan lokal
     */
    public static function delete(string $fileName, string $bucket): bool {
        if (self::isSupabaseEnabled()) {
            $url = rtrim(constant('SUPABASE_URL'), '/') . "/storage/v1/object/" . $bucket . "/" . $fileName;
            $opts = [
                'http' => [
                    'method' => 'DELETE',
                    'header' => [
                        "apikey: " . constant('SUPABASE_KEY'),
                        "Authorization: Bearer " . constant('SUPABASE_KEY')
                    ],
                    'ignore_errors' => true
                ]
            ];
            $context = stream_context_create($opts);
            $result = @file_get_contents($url, false, $context);
            if (isset($http_response_header)) {
                foreach ($http_response_header as $header) {
                    if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/i', $header, $matches)) {
                        return intval($matches[1]) === 200;
                    }
                }
            }
            return $result !== false;
        } else {
            // Penghapusan Lokal
            $destDir = ($bucket === 'pdfs') ? PDF_STORAGE : COVER_STORAGE;
            $filePath = $destDir . '/' . $fileName;
            if (file_exists($filePath)) {
                return @unlink($filePath);
            }
            return false;
        }
    }
}

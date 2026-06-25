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
                        "Authorization: Bearer " . constant('SUPABASE_KEY')
                    ]
                ]
            ];
            $context = stream_context_create($opts);
            $content = @file_get_contents($url, false, $context);
            if ($content !== false) {
                header('Content-Type: application/pdf');
                header('Content-Length: ' . strlen($content));
                echo $content;
                exit;
            } else {
                die("Gagal mengambil file PDF dari Supabase Storage.");
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

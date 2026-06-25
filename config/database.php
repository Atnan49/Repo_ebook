<?php
// ============================================================
// Konfigurasi Database
// Mendukung ENV dari Docker maupun default untuk InfinityFree & Supabase
// ============================================================

$dbConn = getenv('DB_CONNECTION');
$dbHost = getenv('DB_HOST');
$dbPort = getenv('DB_PORT');
$dbName = getenv('DB_NAME');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');

if (getenv('POSTGRES_URL')) {
    $parsedUrl = parse_url(getenv('POSTGRES_URL'));
    if ($parsedUrl) {
        $dbConn = 'pgsql';
        $dbHost = $parsedUrl['host'] ?? $dbHost;
        $dbPort = $parsedUrl['port'] ?? $dbPort;
        $dbUser = $parsedUrl['user'] ?? $dbUser;
        $dbPass = $parsedUrl['pass'] ?? $dbPass;
        if (isset($parsedUrl['path'])) {
            $dbName = ltrim($parsedUrl['path'], '/');
        }
    }
} elseif (getenv('POSTGRES_HOST')) {
    $dbConn = 'pgsql';
    $dbHost = getenv('POSTGRES_HOST');
    $dbPort = getenv('POSTGRES_PORT') ?: '5432';
    $dbName = getenv('POSTGRES_DATABASE');
    $dbUser = getenv('POSTGRES_USER');
    $dbPass = getenv('POSTGRES_PASSWORD');
}

define('DB_CONNECTION', $dbConn ?: 'mysql');
define('DB_HOST',       $dbHost ?: 'sql308.infinityfree.com');
define('DB_PORT',       $dbPort ?: '3306');
define('DB_NAME',       $dbName ?: 'if0_41988896_repo_ebook');
define('DB_USER',       $dbUser ?: 'if0_41988896');
define('DB_PASS',       $dbPass ?: 'Atnan231');
define('DB_CHARSET', 'utf8mb4');

// Supabase Configuration for Storage
define('SUPABASE_URL', getenv('SUPABASE_URL') ?: getenv('NEXT_PUBLIC_SUPABASE_URL') ?: '');
define('SUPABASE_KEY', getenv('SUPABASE_KEY') ?: getenv('SUPABASE_ANON_KEY') ?: getenv('SUPABASE_SERVICE_ROLE_KEY') ?: '');

// Base URL & Path — kosongkan untuk deployment root level atau biarkan terdeteksi otomatis
if (getenv('BASE_URL') !== false) {
    define('BASE_URL', getenv('BASE_URL'));
} else {
    // Deteksi BASE_URL secara otomatis jika tidak diset di environment (mendukung subdirektori lokal seperti XAMPP)
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath = dirname($scriptName);
    $basePath = str_replace('\\', '/', $basePath);
    // Jika script berada di public/index.php atau admin/index.php, kita ambil path induknya
    if (str_ends_with($basePath, '/public')) {
        $basePath = substr($basePath, 0, -7);
    } elseif (str_ends_with($basePath, '/admin')) {
        $basePath = substr($basePath, 0, -6);
    }
    $basePath = rtrim($basePath, '/');
    define('BASE_URL', $basePath);
}

define('ASSET_URL',  getenv('ASSET_URL') !== false ? getenv('ASSET_URL') : BASE_URL);
define('ROOT_PATH',  dirname(__DIR__));

define('STORAGE_PATH', ROOT_PATH . '/storage');
define('PDF_STORAGE', STORAGE_PATH . '/pdfs');
define('COVER_STORAGE', ROOT_PATH . '/assets/covers');

/**
 * Class Database
 * Singleton pattern untuk koneksi PDO
 */
class Database
{
    private static ?PDO $instance = null;

    /**
     * Mendapatkan instance koneksi PDO (Singleton)
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $connectionType = DB_CONNECTION;
            
            try {
                if ($connectionType === 'pgsql') {
                    $dsn = sprintf(
                        'pgsql:host=%s;port=%s;dbname=%s',
                        DB_HOST,
                        DB_PORT,
                        DB_NAME
                    );
                    $options = [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ];
                    self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
                } else {
                    $dsn = sprintf(
                        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                        DB_HOST,
                        DB_PORT,
                        DB_NAME,
                        DB_CHARSET
                    );
                    $options = [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
                    ];
                    self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
                }
            } catch (PDOException $e) {
                // Tampilkan error hanya saat development
                error_log('Database Connection Error: ' . $e->getMessage());
                die('<div style="padding:20px;font-family:sans-serif;color:#c0392b;">
                    <h2>⚠ Koneksi Database Gagal</h2>
                    <p>Pastikan MySQL/Postgres sudah berjalan dan database <strong>' . DB_NAME . '</strong> sudah dibuat.</p>
                    <p><small>' . $e->getMessage() . '</small></p>
                </div>');
            }
        }

        return self::$instance;
    }

    /**
     * Shortcut untuk mendapatkan koneksi
     */
    public static function connect(): PDO
    {
        return self::getConnection();
    }

    // Prevent cloning & unserialization
    private function __construct() {}
    private function __clone() {}
}

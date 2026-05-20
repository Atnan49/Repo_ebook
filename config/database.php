<?php
/**
 * ============================================================
 * DATABASE CONNECTION - PDO
 * ============================================================
 * Koneksi ke MySQL menggunakan PDO dengan error handling,
 * charset UTF-8, dan prepared statements sebagai default.
 */

// Konfigurasi Database — mendukung ENV dari Docker maupun default lokal
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_PORT',    getenv('DB_PORT')    ?: '3306');
define('DB_NAME',    getenv('DB_NAME')    ?: 'repo_ebook');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_CHARSET', 'utf8mb4');

// Base URL & Path
define('BASE_URL',   getenv('BASE_URL')   ?: '/Projek/Repo_ebook/public');
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

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Tampilkan error hanya saat development
                error_log('Database Connection Error: ' . $e->getMessage());
                die('<div style="padding:20px;font-family:sans-serif;color:#c0392b;">
                    <h2>⚠ Koneksi Database Gagal</h2>
                    <p>Pastikan MySQL sudah berjalan dan database <strong>' . DB_NAME . '</strong> sudah dibuat.</p>
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

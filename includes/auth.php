<?php

require_once __DIR__ . '/../config/database.php';

class CookieSessionHandler implements SessionHandlerInterface {
    private $cookieName = 'repo_ebook_session';
    private $key;

    public function __construct($key) {
        $this->key = hash('sha256', $key, true);
    }

    public function open(string $path, string $name): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read(string $id): string|false {
        if (!isset($_COOKIE[$this->cookieName])) {
            return '';
        }
        $data = $this->decrypt($_COOKIE[$this->cookieName]);
        return $data !== false ? $data : '';
    }

    public function write(string $id, string $data): bool {
        $encrypted = $this->encrypt($data);
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        return setcookie($this->cookieName, $encrypted, [
            'expires' => time() + 7 * 24 * 3600, // 1 week
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    public function destroy(string $id): bool {
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        return setcookie($this->cookieName, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    public function gc(int $max_lifetime): int|false {
        return 0;
    }

    private function encrypt($data) {
        $cipher = "AES-256-CBC";
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertext = openssl_encrypt($data, $cipher, $this->key, OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $ciphertext, $this->key, true);
        return base64_encode($iv . $hmac . $ciphertext);
    }

    private function decrypt($encryptedData) {
        $cipher = "AES-256-CBC";
        $raw = base64_decode($encryptedData);
        $ivlen = openssl_cipher_iv_length($cipher);
        if (strlen($raw) < $ivlen + 32) return false;
        $iv = substr($raw, 0, $ivlen);
        $hmac = substr($raw, $ivlen, 32);
        $ciphertext = substr($raw, $ivlen + 32);
        
        $calculatedHmac = hash_hmac('sha256', $ciphertext, $this->key, true);
        if (!hash_equals($hmac, $calculatedHmac)) {
            return false;
        }
        
        return openssl_decrypt($ciphertext, $cipher, $this->key, OPENSSL_RAW_DATA, $iv);
    }
}

if (session_status() === PHP_SESSION_NONE) {
    $sessionSecret = (defined('SUPABASE_KEY') && SUPABASE_KEY) ? SUPABASE_KEY : 'RepoEbookDefaultSecretKey123!';
    $handler = new CookieSessionHandler($sessionSecret);
    session_set_save_handler($handler, true);
    session_start();
}

// Mencegah browser melakukan caching pada halaman dinamis berbasis session
if (!headers_sent()) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
}

/**
 * Cek apakah user sudah login
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Cek apakah user adalah admin
 */
function isAdmin(): bool
{
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Cek apakah user adalah member
 */
function isMember(): bool
{
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'member';
}

/**
 * Mendapatkan data user yang sedang login
 */
function currentUser(): ?array
{
    if (!isLoggedIn()) return null;

    return [
        'id'     => $_SESSION['user_id'],
        'name'   => $_SESSION['user_name'] ?? '',
        'email'  => $_SESSION['user_email'] ?? '',
        'role'   => $_SESSION['role'] ?? 'member',
        'avatar' => $_SESSION['user_avatar'] ?? null,
    ];
}

/**
 * Login user - set session data
 */
function loginUser(array $user): void
{
    $_SESSION['user_id']     = $user['id'];
    $_SESSION['user_name']   = $user['name'];
    $_SESSION['user_email']  = $user['email'];
    $_SESSION['role']        = $user['role'];
    $_SESSION['user_avatar'] = $user['avatar'] ?? null;
    session_regenerate_id(true); // Cegah session fixation
}

/**
 * Logout user - destroy session
 */
function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();

    // Mulai session baru yang bersih agar flash message setelah logout bisa disimpan
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Redirect ke halaman lain
 */
function redirect(string $url): void
{
    header("Location: $url");
    exit;
}

/**
 * Require login - redirect ke login jika belum
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        $_SESSION['flash_error'] = 'Silakan login terlebih dahulu.';
        redirect(BASE_URL . '/login.php');
    }
}

/**
 * Require admin - redirect jika bukan admin
 */
function requireAdmin(): void
{
    requireLogin();
    if (!isAdmin()) {
        $_SESSION['flash_error'] = 'Akses ditolak. Anda bukan admin.';
        redirect(BASE_URL . '/index.php');
    }
}

/**
 * Set flash message
 */
function setFlash(string $type, string $message): void
{
    $_SESSION["flash_{$type}"] = $message;
}

/**
 * Get & clear flash message
 */
function getFlash(string $type): ?string
{
    $key = "flash_{$type}";
    if (isset($_SESSION[$key])) {
        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $msg;
    }
    return null;
}

/**
 * Generate CSRF token
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validasi CSRF token
 */
function validateCsrf(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize string output (anti XSS)
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

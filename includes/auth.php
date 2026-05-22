<?php


if (session_status() === PHP_SESSION_NONE) {
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
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

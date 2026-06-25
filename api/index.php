<?php
/**
 * ============================================================
 * VERCEL SERVERLESS ENTRYPOINT ROUTER
 * ============================================================
 */

// Parse the request URI and remove trailing slashes
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/');

// Route mapping to original monolithic files
if ($uri === '' || $uri === '/index.php') {
    require __DIR__ . '/../public/index.php';
} elseif ($uri === '/read.php') {
    require __DIR__ . '/../public/read.php';
} elseif ($uri === '/upload.php') {
    require __DIR__ . '/../public/upload.php';
} elseif ($uri === '/bookmark.php') {
    require __DIR__ . '/../public/bookmark.php';
} elseif ($uri === '/detail.php') {
    require __DIR__ . '/../public/detail.php';
} elseif ($uri === '/delete.php') {
    require __DIR__ . '/../public/delete.php';
} elseif ($uri === '/login.php') {
    require __DIR__ . '/../public/login.php';
} elseif ($uri === '/logout.php') {
    require __DIR__ . '/../public/logout.php';
} elseif ($uri === '/register.php') {
    require __DIR__ . '/../public/register.php';
} elseif ($uri === '/admin') {
    require __DIR__ . '/../admin/index.php';
} elseif ($uri === '/admin/moderasi.php') {
    require __DIR__ . '/../admin/moderasi.php';
} elseif ($uri === '/admin/action.php') {
    require __DIR__ . '/../admin/action.php';
} else {
    // Return 404 for unmatched routes
    http_response_code(404);
    echo "<div style='padding:20px;font-family:sans-serif;'>
        <h2>404 - Halaman Tidak Ditemukan</h2>
        <p>Maaf, halaman yang Anda cari tidak tersedia.</p>
    </div>";
}

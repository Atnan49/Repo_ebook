<?php
/**
 * ============================================================
 * READ EBOOK - File PDF Streaming Securely
 * ============================================================
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Akses ditolak: ID ebook tidak valid.");
}

$id = (int)$_GET['id'];

try {
    $db = Database::connect();
    $stmt = $db->prepare("SELECT * FROM ebooks WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $book = $stmt->fetch();

    if (!$book) {
        die("Ebook tidak ditemukan.");
    }

    // Check authorization
    $isAuthorized = false;
    
    // Require login to read (opsional, bisa dimatikan jika ingin publik, tapi lebih baik secure)
    requireLogin();
    
    if ($book['status'] === 'approved') {
        $isAuthorized = true;
    } elseif (isAdmin()) {
        $isAuthorized = true; // Admin can read pending/rejected books
    } elseif ($book['uploaded_by'] === $_SESSION['user_id']) {
        $isAuthorized = true; // Uploader can read their own books
    }

    if (!$isAuthorized) {
        die("Akses ditolak: Anda tidak memiliki izin untuk membaca ebook ini.");
    }

    $filePath = PDF_STORAGE . '/' . $book['pdf_file'];
    
    if (!file_exists($filePath)) {
        die("File PDF tidak ditemukan di server.");
    }

    // Update download/read count (only count once per session)
    if (!isset($_SESSION['read_' . $id]) && !isAdmin()) {
        $db->prepare("UPDATE ebooks SET downloads = downloads + 1 WHERE id = :id")->execute([':id' => $id]);
        $_SESSION['read_' . $id] = true;
    }

    // Serve the file safely
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($book['title']) . '.pdf"');
    header('Content-Transfer-Encoding: binary');
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . filesize($filePath));
    @readfile($filePath);
    exit;

} catch (PDOException $e) {
    die("Terjadi kesalahan sistem saat memuat ebook.");
}

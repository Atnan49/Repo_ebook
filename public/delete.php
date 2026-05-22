<?php
/**
 * ============================================================
 * EBOOK DELETE PROCESSOR (CONTROLLER)
 * ============================================================
 * Menghapus ebook beserta file fisik terkait (PDF & Cover).
 * Akses dibatasi hanya untuk Uploader ebook atau Administrator.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// 1. Validasi Metode Request wajib POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('error', 'Metode request tidak valid.');
    redirect(BASE_URL . '/index.php');
}

// 2. Validasi Login
requireLogin();

// 3. Validasi Token CSRF untuk Keamanan
if (!isset($_POST['csrf_token']) || !validateCsrf($_POST['csrf_token'])) {
    setFlash('error', 'Token keamanan tidak valid. Silakan coba lagi.');
    redirect(BASE_URL . '/index.php');
}

// 4. Validasi Parameter ID
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    setFlash('error', 'ID Ebook tidak valid.');
    redirect(BASE_URL . '/index.php');
}

try {
    $db = Database::connect();

    // 5. Ambil data ebook dari database
    $stmt = $db->prepare("SELECT * FROM ebooks WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $book = $stmt->fetch();

    // Jika ebook tidak ditemukan
    if (!$book) {
        setFlash('error', 'Ebook tidak ditemukan.');
        redirect(BASE_URL . '/index.php');
    }

    // 6. Validasi Hak Akses (Pemilik ebook atau Admin)
    if ($book['uploaded_by'] != $_SESSION['user_id'] && !isAdmin()) {
        setFlash('error', 'Akses ditolak. Anda tidak memiliki wewenang untuk menghapus ebook ini.');
        redirect(BASE_URL . '/detail.php?id=' . $id);
    }

    // 7. Hapus file fisik PDF dari server
    if (!empty($book['pdf_file'])) {
        $pdfPath = PDF_STORAGE . '/' . $book['pdf_file'];
        if (file_exists($pdfPath)) {
            if (!unlink($pdfPath)) {
                error_log("Gagal menghapus file PDF: " . $pdfPath);
            }
        }
    }

    // 8. Hapus file fisik Cover dari server
    if (!empty($book['cover_image'])) {
        $coverPath = COVER_STORAGE . '/' . $book['cover_image'];
        if (file_exists($coverPath)) {
            if (!unlink($coverPath)) {
                error_log("Gagal menghapus file Cover: " . $coverPath);
            }
        }
    }

    // 9. Hapus data ebook dari database
    // Tabel bookmarks akan terhapus otomatis karena foreign key menggunakan ON DELETE CASCADE
    $deleteStmt = $db->prepare("DELETE FROM ebooks WHERE id = :id");
    $deleteStmt->execute([':id' => $id]);

    setFlash('success', 'Ebook "' . $book['title'] . '" berhasil dihapus secara permanen.');

    // Redirect berdasarkan role
    if (isAdmin()) {
        redirect(BASE_URL . '/index.php');
    } else {
        redirect(BASE_URL . '/index.php?my_uploads=1');
    }

} catch (PDOException $e) {
    error_log("Database error saat menghapus ebook: " . $e->getMessage());
    setFlash('error', 'Terjadi kesalahan sistem saat mencoba menghapus ebook.');
    redirect(BASE_URL . '/detail.php?id=' . $id);
}

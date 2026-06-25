<?php
/**
 * ============================================================
 * ADMIN ACTION (Approve/Reject)
 * ============================================================
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

if (!validateCsrf($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Token keamanan tidak valid.');
    redirect('index.php');
}

$id = (int)$_POST['id'];
$action = $_POST['action'] ?? '';

if ($id > 0 && in_array($action, ['approve', 'reject'])) {
    try {
        $db = Database::connect();
        $status = ($action === 'approve') ? 'approved' : 'rejected';
        
        $stmt = $db->prepare("UPDATE ebooks SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $status, ':id' => $id]);
        
        setFlash('success', "Ebook berhasil di-" . $status);
    } catch (PDOException $e) {
        setFlash('error', 'Terjadi kesalahan sistem.');
    }
}

$redirectPage = $_POST['redirect'] ?? 'index.php';
// Restrict redirect to safe local admin pages to prevent Open Redirect vulnerability
$allowedRedirects = ['index.php', 'moderasi.php'];
if (!in_array($redirectPage, $allowedRedirects)) {
    $redirectPage = 'index.php';
}
redirect($redirectPage);

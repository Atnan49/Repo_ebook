<?php
/**
 * ============================================================
 * BOOKMARK AJAX ENDPOINT
 * ============================================================
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid Method']);
    exit;
}

// Read JSON input
$data = json_decode(file_get_contents('php://input'), true);
$ebookId = isset($data['ebook_id']) ? (int)$data['ebook_id'] : 0;

if ($ebookId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Ebook ID']);
    exit;
}

try {
    $db = Database::connect();
    
    // Check if bookmark exists
    $stmt = $db->prepare("SELECT id FROM bookmarks WHERE user_id = :uid AND ebook_id = :eid");
    $stmt->execute([':uid' => $_SESSION['user_id'], ':eid' => $ebookId]);
    $bookmark = $stmt->fetch();

    if ($bookmark) {
        // Delete bookmark
        $del = $db->prepare("DELETE FROM bookmarks WHERE id = :id");
        $del->execute([':id' => $bookmark['id']]);
        echo json_encode(['success' => true, 'action' => 'removed']);
    } else {
        // Insert bookmark
        $ins = $db->prepare("INSERT INTO bookmarks (user_id, ebook_id) VALUES (:uid, :eid)");
        $ins->execute([':uid' => $_SESSION['user_id'], ':eid' => $ebookId]);
        echo json_encode(['success' => true, 'action' => 'added']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error']);
}

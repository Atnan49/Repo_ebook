<?php
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $db = Database::connect();
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 1;
    $stmt = $db->prepare("SELECT * FROM ebooks WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $book = $stmt->fetch();
    echo json_encode([
        'status' => 'success',
        'book' => $book
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}

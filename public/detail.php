<?php
/**
 * ============================================================
 * EBOOK DETAIL PAGE
 * ============================================================
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirect(BASE_URL . '/index.php');
}

$id = (int)$_GET['id'];

try {
    $db = Database::connect();
    
    // Fetch ebook with category and uploader info
    $sql = "SELECT e.*, c.name as category_name, u.name as uploader_name 
            FROM ebooks e
            LEFT JOIN categories c ON e.category_id = c.id
            LEFT JOIN users u ON e.uploaded_by = u.id
            WHERE e.id = :id";
            
    // If not admin and not the uploader, it must be approved
    if (!isAdmin()) {
        if (isLoggedIn()) {
            $sql .= " AND (e.status = 'approved' OR e.uploaded_by = " . $_SESSION['user_id'] . ")";
        } else {
            $sql .= " AND e.status = 'approved'";
        }
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $id]);
    $book = $stmt->fetch();

    if (!$book) {
        setFlash('error', 'Ebook tidak ditemukan atau belum disetujui.');
        redirect(BASE_URL . '/index.php');
    }

    // Update Views (only once per session)
    if (!isset($_SESSION['view_' . $id]) && !isAdmin()) {
        $db->prepare("UPDATE ebooks SET views = views + 1 WHERE id = :id")->execute([':id' => $id]);
        $_SESSION['view_' . $id] = true;
        $book['views']++;
    }

    // Check if bookmarked
    $isBookmarked = false;
    if (isLoggedIn()) {
        $checkBm = $db->prepare("SELECT 1 FROM bookmarks WHERE user_id = :uid AND ebook_id = :eid");
        $checkBm->execute([':uid' => $_SESSION['user_id'], ':eid' => $id]);
        $isBookmarked = (bool)$checkBm->fetch();
    }

} catch (PDOException $e) {
    die("Terjadi kesalahan sistem.");
}

$pageTitle = e($book['title']) . ' - RepoBook';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📚</text></svg>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/../assets/css/style.css">
    <style>
        .detail-container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        .detail-card { display: flex; flex-direction: column; background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.06); overflow: hidden; margin-bottom: 30px; }
        @media (min-width: 768px) { .detail-card { flex-direction: row; } }
        .detail-cover { flex: 0 0 300px; padding: 30px; background: #f8fafc; display: flex; justify-content: center; align-items: flex-start; border-right: 1px solid #f1f5f9; }
        .detail-cover img { width: 100%; max-width: 260px; border-radius: 8px; box-shadow: 0 10px 20px rgba(0,0,0,0.1); object-fit: cover; aspect-ratio: 2/3; }
        .detail-info { flex: 1; padding: 40px 30px; }
        .badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 16px; margin-right: 8px; }
        .badge.category { background: #e0e7ff; color: #4338ca; }
        .badge.status-pending { background: #fef3c7; color: #d97706; }
        .badge.status-rejected { background: #fee2e2; color: #b91c1c; }
        .detail-title { font-size: 32px; font-weight: 800; color: #0f172a; margin-bottom: 8px; line-height: 1.2; }
        .detail-author { font-size: 18px; color: #475569; margin-bottom: 24px; font-weight: 500; }
        .detail-stats { display: flex; gap: 24px; margin-bottom: 32px; padding-bottom: 24px; border-bottom: 1px solid #e2e8f0; }
        .stat-box { display: flex; align-items: center; gap: 8px; color: #64748b; font-size: 15px; }
        .stat-box svg { width: 20px; height: 20px; color: #94a3b8; }
        .detail-desc { color: #334155; line-height: 1.7; font-size: 16px; margin-bottom: 32px; white-space: pre-wrap; }
        .detail-actions { display: flex; gap: 16px; flex-wrap: wrap; }
        .btn-read { background: #3b82f6; color: white; padding: 14px 28px; border-radius: 10px; font-weight: 600; font-size: 16px; text-decoration: none; display: flex; align-items: center; gap: 10px; transition: background 0.2s; border: none; cursor: pointer; }
        .btn-read:hover { background: #2563eb; }
        .btn-bookmark { background: #f1f5f9; color: #475569; padding: 14px 28px; border-radius: 10px; font-weight: 600; font-size: 16px; border: none; cursor: pointer; display: flex; align-items: center; gap: 10px; transition: all 0.2s; }
        .btn-bookmark:hover { background: #e2e8f0; }
        .btn-bookmark.saved { background: #fce7f3; color: #be185d; }
        .uploader-info { margin-top: 30px; font-size: 14px; color: #94a3b8; display: flex; align-items: center; gap: 8px; }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <div class="main-wrapper">
            <?php include __DIR__ . '/../includes/header.php'; ?>
            <main class="content-area">
                
                <div class="detail-container">
                    <a href="<?= BASE_URL ?>/index.php" style="display:inline-flex; align-items:center; gap:8px; color:#64748b; text-decoration:none; font-weight:500; margin-bottom:20px; transition:color 0.2s;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                        Kembali ke Katalog
                    </a>

                    <div class="detail-card">
                        <div class="detail-cover">
                            <img src="<?= $book['cover_image'] ? BASE_URL . '/../assets/covers/' . e($book['cover_image']) : BASE_URL . '/../assets/img/default-cover.jpg' ?>" alt="<?= e($book['title']) ?>">
                        </div>
                        <div class="detail-info">
                            <div>
                                <span class="badge category"><?= e($book['category_name'] ?? 'Umum') ?></span>
                                <?php if ($book['status'] === 'pending'): ?>
                                    <span class="badge status-pending">Menunggu Persetujuan</span>
                                <?php elseif ($book['status'] === 'rejected'): ?>
                                    <span class="badge status-rejected">Ditolak</span>
                                <?php endif; ?>
                            </div>
                            
                            <h1 class="detail-title"><?= e($book['title']) ?></h1>
                            <div class="detail-author">Oleh <?= e($book['author']) ?></div>
                            
                            <div class="detail-stats">
                                <div class="stat-box" title="Jumlah Dilihat">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    <?= number_format($book['views']) ?>
                                </div>
                                <div class="stat-box" title="Jumlah Dibaca">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>
                                    <?= number_format($book['downloads']) ?>
                                </div>
                                <div class="stat-box" title="Ukuran File">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>
                                    <?= round($book['file_size'] / 1024 / 1024, 2) ?> MB
                                </div>
                            </div>

                            <div class="detail-desc">
                                <?= $book['description'] ? nl2br(e($book['description'])) : '<i>Tidak ada sinopsis untuk buku ini.</i>' ?>
                            </div>

                            <div class="detail-actions">
                                <a href="<?= BASE_URL ?>/read.php?id=<?= $book['id'] ?>" target="_blank" class="btn-read">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>
                                    Baca Sekarang
                                </a>
                                
                                <?php if (isLoggedIn()): ?>
                                    <button class="btn-bookmark bookmark-btn-detail <?= $isBookmarked ? 'saved' : '' ?>" data-id="<?= $book['id'] ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="<?= $isBookmarked ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>
                                        <span class="bm-text"><?= $isBookmarked ? 'Tersimpan' : 'Simpan' ?></span>
                                    </button>
                                <?php endif; ?>
                            </div>

                            <div class="uploader-info">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                Diunggah oleh <?= e($book['uploader_name']) ?> pada <?= date('d M Y', strtotime($book['created_at'])) ?>
                            </div>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>
    
    <script>
        const BASE_URL = '<?= BASE_URL ?>';
    </script>
    <script src="<?= BASE_URL ?>/../assets/js/app.js"></script>
</body>
</html>

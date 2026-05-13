<?php
/**
 * ============================================================
 * ADMIN MODERASI
 * ============================================================
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$pageTitle = 'Moderasi Ebook - RepoBook';

try {
    $db = Database::connect();

    // Fetch Pending Ebooks
    $stmt = $db->query("SELECT e.*, c.name as category_name, u.name as uploader_name 
                        FROM ebooks e
                        LEFT JOIN categories c ON e.category_id = c.id
                        LEFT JOIN users u ON e.uploaded_by = u.id
                        WHERE e.status = 'pending'
                        ORDER BY e.created_at ASC");
    $pendingBooks = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Terjadi kesalahan database.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📚</text></svg>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/../assets/css/style.css">
    <style>
        .admin-container { padding: 30px; max-width: 1200px; margin: 0 auto; }
        .admin-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        
        .pending-list { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow: hidden; }
        .pending-list h3 { padding: 20px; border-bottom: 1px solid #f1f5f9; margin: 0; }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 16px 20px; text-align: left; border-bottom: 1px solid #f1f5f9; }
        th { background: #f8fafc; font-weight: 600; color: #475569; font-size: 14px; }
        td { color: #334155; font-size: 15px; }
        tr:last-child td { border-bottom: none; }
        
        .btn-action { padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 14px; border: none; cursor: pointer; text-decoration: none; display: inline-block; margin-right: 8px; }
        .btn-approve { background: #10b981; color: white; }
        .btn-reject { background: #ef4444; color: white; }
        .btn-view { background: #3b82f6; color: white; }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <div class="main-wrapper">
            <?php include __DIR__ . '/../includes/header.php'; ?>
            <main class="content-area">
                
                <div class="admin-container">
                    <div class="admin-header">
                        <h2>Moderasi Ebook</h2>
                    </div>

                    <?php if ($msg = getFlash('success')): ?>
                        <div class="flash-msg success" style="margin-bottom:20px; padding:16px; background:#ecfdf5; color:#047857; border-radius:8px; border-left:4px solid #10b981;">
                            <?= e($msg) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($msg = getFlash('error')): ?>
                        <div class="flash-msg error" style="margin-bottom:20px; padding:16px; background:#fef2f2; color:#b91c1c; border-radius:8px; border-left:4px solid #ef4444;">
                            <?= e($msg) ?>
                        </div>
                    <?php endif; ?>

                    <div class="pending-list">
                        <h3>Antrean Persetujuan Ebook</h3>
                        <div class="table-responsive">
                            <?php if (empty($pendingBooks)): ?>
                                <div style="padding: 40px 20px; text-align: center; color: #64748b;">
                                    Tidak ada ebook yang menunggu persetujuan saat ini.
                                </div>
                            <?php else: ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Judul Buku</th>
                                            <th>Pengunggah</th>
                                            <th>Kategori</th>
                                            <th>Tanggal</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pendingBooks as $book): ?>
                                            <tr>
                                                <td style="font-weight: 600;"><?= e($book['title']) ?></td>
                                                <td><?= e($book['uploader_name']) ?></td>
                                                <td><?= e($book['category_name'] ?? 'Umum') ?></td>
                                                <td><?= date('d M Y', strtotime($book['created_at'])) ?></td>
                                                <td>
                                                    <a href="<?= BASE_URL ?>/detail.php?id=<?= $book['id'] ?>" class="btn-action btn-view" target="_blank">Lihat</a>
                                                    
                                                    <form action="action.php" method="POST" style="display:inline;">
                                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                        <input type="hidden" name="id" value="<?= $book['id'] ?>">
                                                        <input type="hidden" name="action" value="approve">
                                                        <input type="hidden" name="redirect" value="moderasi.php">
                                                        <button type="submit" class="btn-action btn-approve" onclick="return confirm('Setujui ebook ini?')">Setujui</button>
                                                    </form>
                                                    
                                                    <form action="action.php" method="POST" style="display:inline;">
                                                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                                        <input type="hidden" name="id" value="<?= $book['id'] ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                        <input type="hidden" name="redirect" value="moderasi.php">
                                                        <button type="submit" class="btn-action btn-reject" onclick="return confirm('Tolak ebook ini?')">Tolak</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
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

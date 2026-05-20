<?php
/**
 * ============================================================
 * ADMIN DASHBOARD
 * ============================================================
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$pageTitle = 'Admin Dashboard - RepoBook';

try {
    $db = Database::connect();
    
    // Stats
    $totalUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalEbooks = $db->query("SELECT COUNT(*) FROM ebooks WHERE status='approved'")->fetchColumn();
    $totalPending = $db->query("SELECT COUNT(*) FROM ebooks WHERE status='pending'")->fetchColumn();

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
    <link rel="icon" type="image/x-icon" href="<?= ASSET_URL ?>/favicon.ico">
    <link rel="stylesheet" href="<?= ASSET_URL ?>/assets/css/style.css">
    <style>
        .admin-container { padding: 30px; max-width: 1200px; margin: 0 auto; }
        .admin-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 24px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .stat-card h4 { color: #64748b; font-size: 14px; margin-bottom: 8px; text-transform: uppercase; }
        .stat-card .num { font-size: 32px; font-weight: 700; color: #0f172a; }
        
        .welcome-panel { background: linear-gradient(135deg, #4f46e5, #3b82f6); color: white; padding: 40px; border-radius: 16px; margin-bottom: 40px; box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3); }
        .welcome-panel h3 { font-size: 28px; margin-bottom: 12px; }
        .welcome-panel p { font-size: 16px; opacity: 0.9; max-width: 600px; line-height: 1.6; }
        .btn-check-mod { display: inline-block; margin-top: 20px; padding: 12px 24px; background: white; color: #4f46e5; font-weight: bold; border-radius: 8px; text-decoration: none; transition: 0.2s; }
        .btn-check-mod:hover { background: #f8fafc; transform: translateY(-2px); }
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
                        <h2>Dashboard Admin</h2>
                    </div>

                    <div class="welcome-panel">
                        <h3>Selamat Datang, <?= e($user['name']) ?>!</h3>
                        <p>Kelola koleksi repositori ebook dengan mudah. Anda memiliki kendali penuh atas persetujuan buku dan pengguna.</p>
                        <?php if ($totalPending > 0): ?>
                            <a href="moderasi.php" class="btn-check-mod">Cek Moderasi (<?= $totalPending ?> Menunggu)</a>
                        <?php endif; ?>
                    </div>

                    <div class="stats-grid">
                        <div class="stat-card">
                            <h4>Total Pengguna</h4>
                            <div class="num"><?= number_format($totalUsers) ?></div>
                        </div>
                        <div class="stat-card">
                            <h4>Ebook Disetujui</h4>
                            <div class="num"><?= number_format($totalEbooks) ?></div>
                        </div>
                        <div class="stat-card" style="border-left: 4px solid #f59e0b;">
                            <h4>Menunggu Persetujuan</h4>
                            <div class="num"><?= number_format($totalPending) ?></div>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>
    <script>
        const BASE_URL = '<?= BASE_URL ?>';
    </script>
    <script src="<?= ASSET_URL ?>/assets/js/app.js"></script>
</body>
</html>

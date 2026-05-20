<?php
/**
 * ============================================================
 * HOME PAGE - Katalog Ebook
 * ============================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$pageTitle = 'Home - RepoBook';

// ---- Fetch Categories ----
try {
    $db = Database::connect();
    $stmtCat = $db->query("SELECT * FROM categories ORDER BY name ASC");
    $categories = $stmtCat->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// ---- Filter & Search ----
$search     = trim($_GET['q'] ?? '');
$categoryId = intval($_GET['cat'] ?? 0);
$page       = max(1, intval($_GET['page'] ?? 1));
$perPage    = 12;
$offset     = ($page - 1) * $perPage;

// View mode: popular, latest, saved, categories, or default (home)
$viewMode   = $_GET['view'] ?? '';
$isPopular  = isset($_GET['popular']);
$isLatest   = isset($_GET['latest']);
$isSaved    = isset($_GET['saved']);

// Set page title based on view
if ($isPopular) { $pageTitle = 'Populer - RepoBook'; }
elseif ($isLatest) { $pageTitle = 'Terbaru - RepoBook'; }
elseif ($isSaved)  { $pageTitle = 'Tersimpan - RepoBook'; }
elseif ($viewMode === 'categories') { $pageTitle = 'Kategori - RepoBook'; }
elseif ($categoryId > 0) { $pageTitle = 'Kategori - RepoBook'; }

try {
    $where  = ["e.status = 'approved'"];
    $params = [];
    $orderBy = "e.created_at DESC"; // default

    if ($search !== '') {
        $where[]  = "(e.title LIKE :q OR e.author LIKE :q)";
        $params[':q'] = "%{$search}%";
    }
    if ($categoryId > 0) {
        $where[]  = "e.category_id = :cat";
        $params[':cat'] = $categoryId;
    }

    // Sort mode
    if ($isPopular) {
        $orderBy = "e.views DESC, e.created_at DESC";
    } elseif ($isLatest) {
        $orderBy = "e.created_at DESC";
    }

    // Saved: only if logged in
    if ($isSaved && isLoggedIn()) {
        $where[] = "e.id IN (SELECT ebook_id FROM bookmarks WHERE user_id = :uid)";
        $params[':uid'] = $_SESSION['user_id'];
    }

    $whereSql = implode(' AND ', $where);

    // Count total
    $countStmt = $db->prepare("SELECT COUNT(*) FROM ebooks e WHERE {$whereSql}");
    $countStmt->execute($params);
    $totalBooks = $countStmt->fetchColumn();
    $totalPages = max(1, ceil($totalBooks / $perPage));

    // Fetch books
    $sql = "SELECT e.*, c.name as category_name, u.name as uploader_name
            FROM ebooks e
            LEFT JOIN categories c ON e.category_id = c.id
            LEFT JOIN users u ON e.uploaded_by = u.id
            WHERE {$whereSql}
            ORDER BY {$orderBy}
            LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $books = $stmt->fetchAll();

    // Popular books for hero
    $popStmt = $db->query("SELECT * FROM ebooks WHERE status = 'approved' ORDER BY views DESC LIMIT 3");
    $popularBooks = $popStmt->fetchAll();

    // Category counts (for categories view)
    $catCounts = [];
    if ($viewMode === 'categories') {
        $ccStmt = $db->query("SELECT category_id, COUNT(*) as cnt FROM ebooks WHERE status='approved' GROUP BY category_id");
        foreach ($ccStmt->fetchAll() as $cc) {
            $catCounts[$cc['category_id']] = $cc['cnt'];
        }
    }

    // Fetch user bookmarks for icon state
    $savedBooks = [];
    if (isLoggedIn()) {
        $bmStmt = $db->prepare("SELECT ebook_id FROM bookmarks WHERE user_id = :uid");
        $bmStmt->execute([':uid' => $_SESSION['user_id']]);
        $savedBooks = $bmStmt->fetchAll();
    }

} catch (PDOException $e) {
    $books = [];
    $popularBooks = [];
    $totalBooks = 0;
    $totalPages = 1;
    $catCounts = [];
    $savedBooks = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="RepoBook - Repositori Ebook Digital. Baca dan bagikan ribuan ebook gratis secara online.">
    <title><?= e($pageTitle) ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= ASSET_URL ?>/favicon.ico">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= ASSET_URL ?>/assets/css/style.css">
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="main-wrapper">
            <!-- Header -->
            <?php include __DIR__ . '/../includes/header.php'; ?>

            <!-- Content -->
            <main class="content-area">

                <!-- Flash Messages -->
                <?php if ($msg = getFlash('success')): ?>
                    <div class="flash-msg success"><?= e($msg) ?></div>
                <?php endif; ?>
                <?php if ($msg = getFlash('error')): ?>
                    <div class="flash-msg error"><?= e($msg) ?></div>
                <?php endif; ?>

                <!-- Category Filter Pills -->
                <div class="category-bar">
                    <a href="<?= BASE_URL ?>/index.php" 
                       class="category-pill <?= $categoryId === 0 ? 'active' : '' ?>">All</a>
                    <?php foreach ($categories as $cat): ?>
                        <a href="<?= BASE_URL ?>/index.php?cat=<?= $cat['id'] ?>" 
                           class="category-pill <?= $categoryId === (int)$cat['id'] ? 'active' : '' ?>">
                            <?= e($cat['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Hero Section - Popular Bestsellers (only on home) -->
                <?php if (empty($search) && $categoryId === 0 && !$isPopular && !$isLatest && !$isSaved && $viewMode !== 'categories'): ?>
                <section class="hero-section">
                    <div class="hero-text">
                        <h2>POPULAR<br>BESTSELLERS</h2>
                        <p>Kami mengumpulkan ebook paling populer untuk Anda. Jelajahi koleksi terbaik kami!</p>
                        <a href="<?= BASE_URL ?>/index.php?popular=1" class="btn-hero">
                            Lihat Semua
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                        </a>
                    </div>
                    <div class="hero-books">
                        <?php if (!empty($popularBooks)): ?>
                            <?php foreach ($popularBooks as $pop): ?>
                                <a href="<?= BASE_URL ?>/detail.php?id=<?= $pop['id'] ?>" class="hero-book-card">
                                    <img src="<?= $pop['cover_image'] ? ASSET_URL . '/assets/covers/' . e($pop['cover_image']) : ASSET_URL . '/assets/img/default-cover.jpg' ?>" 
                                         alt="<?= e($pop['title']) ?>">
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="hero-book-card" style="background:linear-gradient(135deg,#D4872C,#F0A845);display:flex;align-items:center;justify-content:center;width:140px;height:200px;border-radius:12px;">
                                <span style="color:white;font-weight:700;text-align:center;padding:10px;font-size:0.8rem;">Belum ada buku</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
                <?php endif; ?>

                <!-- Categories Grid View -->
                <?php if ($viewMode === 'categories' && $categoryId === 0): ?>
                <section class="categories-grid-section">
                    <div class="section-header">
                        <div>
                            <h3>Jelajahi Kategori</h3>
                            <p>Pilih kategori untuk menemukan ebook yang Anda cari</p>
                        </div>
                    </div>
                    <div class="categories-grid">
                        <?php foreach ($categories as $cat): ?>
                            <a href="<?= BASE_URL ?>/index.php?cat=<?= $cat['id'] ?>" class="category-card" id="cat-<?= $cat['id'] ?>">
                                <div class="category-card-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                                    </svg>
                                </div>
                                <div class="category-card-name"><?= e($cat['name']) ?></div>
                                <div class="category-card-count"><?= $catCounts[$cat['id']] ?? 0 ?> ebook</div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <!-- Books Section -->
                <section>
                    <div class="section-header">
                        <div>
                            <h3>
                                <?php if ($search): ?>
                                    Hasil Pencarian "<?= e($search) ?>"
                                <?php elseif ($isPopular): ?>
                                    🔥 Ebook Paling Populer
                                <?php elseif ($isLatest): ?>
                                    🕐 Baru Ditambahkan
                                <?php elseif ($isSaved): ?>
                                    📌 Ebook Tersimpan
                                <?php elseif ($categoryId > 0): ?>
                                    <?php
                                        $catName = '';
                                        foreach ($categories as $c) {
                                            if ((int)$c['id'] === $categoryId) { $catName = $c['name']; break; }
                                        }
                                    ?>
                                    Kategori: <?= e($catName) ?>
                                <?php else: ?>
                                    Bisa Jadi Menarik
                                <?php endif; ?>
                            </h3>
                            <p><?= $totalBooks ?> ebook ditemukan</p>
                        </div>
                    </div>

                    <?php if (!empty($books)): ?>
                        <div class="books-grid">
                            <?php foreach ($books as $book): ?>
                                <a href="<?= BASE_URL ?>/detail.php?id=<?= $book['id'] ?>" class="book-card" id="book-<?= $book['id'] ?>">
                                    <div class="book-cover">
                                        <img src="<?= $book['cover_image'] ? ASSET_URL . '/assets/covers/' . e($book['cover_image']) : ASSET_URL . '/assets/img/default-cover.jpg' ?>" 
                                             alt="<?= e($book['title']) ?>"
                                             loading="lazy">
                                        <?php if (isLoggedIn()): ?>
                                        <button class="bookmark-btn <?= in_array($book['id'], array_column($savedBooks ?? [], 'ebook_id')) ? 'saved' : '' ?>" title="Simpan" data-id="<?= $book['id'] ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                                            </svg>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                    <div class="book-info">
                                        <div class="book-title"><?= e($book['title']) ?></div>
                                        <div class="book-author"><?= e($book['author']) ?></div>
                                        <div class="book-meta">
                                            <span>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                                <?= number_format($book['views']) ?>
                                            </span>
                                            <span><?= e($book['category_name'] ?? 'Umum') ?></span>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <div style="display:flex;justify-content:center;gap:8px;margin-top:36px;">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <?php
                                    $params = $_GET;
                                    $params['page'] = $i;
                                    $qs = http_build_query($params);
                                ?>
                                <a href="<?= BASE_URL ?>/index.php?<?= $qs ?>" 
                                   class="category-pill <?= $i === $page ? 'active' : '' ?>"
                                   style="min-width:38px;text-align:center;">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Empty State -->
                        <div class="empty-state">
                            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                            </svg>
                            <h4>Belum Ada Ebook</h4>
                            <p>
                                <?php if ($search): ?>
                                    Tidak ditemukan ebook untuk "<?= e($search) ?>". Coba kata kunci lain.
                                <?php else: ?>
                                    Koleksi ebook masih kosong. Jadilah yang pertama mengunggah!
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </section>
            </main>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="<?= ASSET_URL ?>/assets/js/app.js"></script>
</body>
</html>

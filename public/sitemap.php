<?php
/**
 * ============================================================
 * DYNAMIC XML SITEMAP GENERATOR
 * ============================================================
 */
header("Content-Type: application/xml; charset=utf-8");

require_once __DIR__ . '/../config/database.php';

// Get base domain
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'repo-ebook.vercel.app';
$baseUrl = $protocol . $host;

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <!-- Static Pages -->
    <url>
        <loc><?= htmlspecialchars($baseUrl) ?>/</loc>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc><?= htmlspecialchars($baseUrl) ?>/index.php</loc>
        <changefreq>daily</changefreq>
        <priority>0.8</priority>
    </url>

    <?php
    try {
        $db = Database::connect();
        
        // Fetch Categories
        $stmtCat = $db->query("SELECT id FROM categories");
        while ($cat = $stmtCat->fetch(PDO::FETCH_ASSOC)) {
            ?>
    <url>
        <loc><?= htmlspecialchars($baseUrl) ?>/index.php?cat=<?= $cat['id'] ?></loc>
        <changefreq>weekly</changefreq>
        <priority>0.6</priority>
    </url>
            <?php
        }
        
        // Fetch Approved Ebooks
        $stmtEbook = $db->query("SELECT id, updated_at FROM ebooks WHERE status = 'approved' ORDER BY updated_at DESC");
        while ($book = $stmtEbook->fetch(PDO::FETCH_ASSOC)) {
            $lastMod = date('c', strtotime($book['updated_at']));
            ?>
    <url>
        <loc><?= htmlspecialchars($baseUrl) ?>/detail.php?id=<?= $book['id'] ?></loc>
        <lastmod><?= $lastMod ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
            <?php
        }
    } catch (Exception $e) {
        // Fallback silently if DB is not available
    }
    ?>
</urlset>

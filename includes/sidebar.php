<?php
/**
 * ============================================================
 * SIDEBAR COMPONENT
 * ============================================================
 * Navigasi utama di sisi kiri.
 * - Desktop: Permanen ~250px
 * - Tablet: Collapsed (icon only), hover/klik expand
 * - Mobile: Off-canvas slide dari kiri
 */

$currentPage = basename($_SERVER['PHP_SELF']);
$user = currentUser();
?>

<!-- Sidebar Overlay (Mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar Navigation -->
<aside class="sidebar" id="sidebar">
    <!-- Logo / Brand -->
    <div class="sidebar-brand">
        <div class="brand-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
            </svg>
        </div>
        <span class="brand-text">RepoBook</span>
    </div>

    <!-- Navigation Links -->
    <nav class="sidebar-nav">
        <div class="nav-section">
            <span class="nav-label">MENU</span>

            <a href="<?= BASE_URL ?>/index.php" 
               class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                <span class="nav-text">Home</span>
            </a>

            <a href="<?= BASE_URL ?>/index.php?view=categories" 
               class="nav-link <?= ($currentPage === 'index.php' && isset($_GET['view']) && $_GET['view'] === 'categories') ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                </svg>
                <span class="nav-text">Kategori</span>
            </a>

            <a href="<?= BASE_URL ?>/index.php?popular=1" 
               class="nav-link <?= ($currentPage === 'index.php' && isset($_GET['popular'])) ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                </svg>
                <span class="nav-text">Populer</span>
            </a>

            <a href="<?= BASE_URL ?>/index.php?latest=1" 
               class="nav-link <?= ($currentPage === 'index.php' && isset($_GET['latest'])) ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                <span class="nav-text">Terbaru</span>
            </a>
        </div>

        <?php if (isLoggedIn()): ?>
        <div class="nav-section">
            <span class="nav-label">PERPUSTAKAAN</span>

            <a href="<?= BASE_URL ?>/index.php?saved=1" 
               class="nav-link <?= ($currentPage === 'index.php' && isset($_GET['saved'])) ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                </svg>
                <span class="nav-text">Tersimpan</span>
            </a>

            <a href="<?= BASE_URL ?>/upload.php" 
               class="nav-link <?= $currentPage === 'upload.php' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="17 8 12 3 7 8"></polyline>
                    <line x1="12" y1="3" x2="12" y2="15"></line>
                </svg>
                <span class="nav-text">Upload</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (isAdmin()): ?>
        <div class="nav-section">
            <span class="nav-label">ADMIN</span>

            <a href="<?= BASE_URL ?>/../admin/index.php" 
               class="nav-link <?= $currentPage === 'index.php' && strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="3" y1="9" x2="21" y2="9"></line>
                    <line x1="9" y1="21" x2="9" y2="9"></line>
                </svg>
                <span class="nav-text">Dashboard</span>
            </a>

            <a href="<?= BASE_URL ?>/../admin/moderasi.php" 
               class="nav-link <?= $currentPage === 'moderasi.php' ? 'active' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                </svg>
                <span class="nav-text">Moderasi</span>
            </a>
        </div>
        <?php endif; ?>
    </nav>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <?php if (isLoggedIn()): ?>
            <a href="<?= BASE_URL ?>/logout.php" class="nav-link logout-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                <span class="nav-text">Logout</span>
            </a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/login.php" class="nav-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                    <polyline points="10 17 15 12 10 7"></polyline>
                    <line x1="15" y1="12" x2="3" y2="12"></line>
                </svg>
                <span class="nav-text">Masuk</span>
            </a>
        <?php endif; ?>
    </div>
</aside>

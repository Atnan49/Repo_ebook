<?php
/**
 * ============================================================
 * HEADER COMPONENT
 * ============================================================
 * Top navigation bar dengan search, hamburger menu (mobile),
 * dan user profile dropdown.
 */

$user = currentUser();
$pageTitle = $pageTitle ?? 'Repositori Ebook';
?>
<!-- Header Top Bar -->
<header class="header-bar" id="headerBar">
    <div class="header-left">
        <!-- Hamburger Button (Mobile & Tablet) -->
        <button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle Menu">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>
        <!-- Search Bar -->
        <form class="search-form" action="<?= BASE_URL ?>/index.php" method="GET">
            <div class="search-wrapper">
                <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input 
                    type="text" 
                    name="q" 
                    class="search-input" 
                    placeholder="Cari judul, penulis, atau sinopsis..." 
                    value="<?= e($_GET['q'] ?? '') ?>"
                    autocomplete="off"
                >
            </div>
        </form>
    </div>
    <div class="header-right">
        <?php if (isLoggedIn()): ?>
            <!-- User Profile Dropdown -->
            <div class="user-dropdown" id="userDropdown">
                <button class="user-btn" id="userDropdownBtn">
                    <div class="user-avatar">
                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                    </div>
                    <span class="user-name"><?= e($user['name']) ?></span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>
                <div class="dropdown-menu" id="dropdownMenu">
                    <?php if (isAdmin()): ?>
                        <a href="<?= BASE_URL ?>/admin/index.php" class="dropdown-item">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect></svg>
                            Dashboard Admin
                        </a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/upload.php" class="dropdown-item">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                        Upload Ebook
                    </a>
                    <hr class="dropdown-divider">
                    <a href="<?= BASE_URL ?>/logout.php" class="dropdown-item text-danger">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1-2 2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                        Logout
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Guest Buttons -->
            <a href="<?= BASE_URL ?>/login.php" class="btn-login">Masuk</a>
            <a href="<?= BASE_URL ?>/register.php" class="btn-register">Daftar</a>
        <?php endif; ?>
    </div>
</header>

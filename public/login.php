<?php
/**
 * ============================================================
 * LOGIN PAGE - Halaman Masuk
 * ============================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Redirect jika sudah login
if (isLoggedIn()) {
    redirect(BASE_URL . '/index.php');
}

$pageTitle = 'Masuk - RepoBook';
$errors = [];
$oldEmail = '';

// Handle Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!validateCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan tidak valid. Silakan coba lagi.';
    }

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $oldEmail = $email;

    if (empty($email)) {
        $errors[] = 'Email wajib diisi.';
    }
    if (empty($password)) {
        $errors[] = 'Password wajib diisi.';
    }

    if (empty($errors)) {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                loginUser($user);

                // Redirect berdasarkan role
                if ($user['role'] === 'admin') {
                    redirect(ASSET_URL . '/admin/index.php');
                } else {
                    redirect(BASE_URL . '/index.php');
                }
            } else {
                $errors[] = 'Email atau password salah.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Terjadi kesalahan sistem.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Masuk ke akun RepoBook Anda.">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" type="image/x-icon" href="<?= ASSET_URL ?>/favicon.ico">
    <link rel="stylesheet" href="<?= ASSET_URL ?>/assets/css/style.css">
</head>
<body>
    <div class="auth-page">
        <div class="auth-container">
            <!-- Left - Branding -->
            <div class="auth-brand">
                <div class="auth-brand-inner">
                    <div class="brand-icon" style="width:56px;height:56px;margin-bottom:20px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                        </svg>
                    </div>
                    <h1>RepoBook</h1>
                    <p>Selamat datang kembali! Masuk ke akun Anda untuk melanjutkan membaca koleksi ebook favorit.</p>
                    <div class="auth-stats">
                        <?php
                        try {
                            $db = Database::connect();
                            $totalEbooks = $db->query("SELECT COUNT(*) FROM ebooks WHERE status='approved'")->fetchColumn();
                            $totalUsers  = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
                            $totalCats   = $db->query("SELECT COUNT(*) FROM categories")->fetchColumn();
                        } catch (PDOException $e) {
                            $totalEbooks = 0; $totalUsers = 0; $totalCats = 0;
                        }
                        ?>
                        <div class="stat-item">
                            <span class="stat-number"><?= number_format($totalEbooks) ?></span>
                            <span class="stat-label">Ebook</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?= number_format($totalUsers) ?></span>
                            <span class="stat-label">Pengguna</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?= number_format($totalCats) ?></span>
                            <span class="stat-label">Kategori</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right - Form -->
            <div class="auth-form-section">
                <div class="auth-form-wrapper">
                    <h2>Masuk ke Akun</h2>
                    <p class="auth-subtitle">Gunakan email dan password Anda</p>

                    <?php if ($msg = getFlash('success')): ?>
                        <div class="flash-msg success">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                            <?= e($msg) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($msg = getFlash('error')): ?>
                        <div class="flash-msg error">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                            <?= e($msg) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="flash-msg error">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                            <div>
                                <?php foreach ($errors as $err): ?>
                                    <div><?= e($err) ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" class="auth-form" id="loginForm">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

                        <div class="form-group">
                            <label for="email">Email</label>
                            <div class="input-wrapper">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                <input type="email" id="email" name="email" placeholder="contoh@email.com" value="<?= e($oldEmail) ?>" required autofocus>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="input-wrapper">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                <input type="password" id="password" name="password" placeholder="Masukkan password" required>
                                <button type="button" class="toggle-password" data-target="password" aria-label="Tampilkan password">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn-submit">
                            Masuk
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                        </button>
                    </form>

                    <p class="auth-footer-text">
                        Belum punya akun? <a href="<?= BASE_URL ?>/register.php" class="auth-link">Daftar gratis</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('.toggle-password').forEach(btn => {
            btn.addEventListener('click', function() {
                const input = document.getElementById(this.dataset.target);
                input.type = input.type === 'password' ? 'text' : 'password';
            });
        });
    </script>
</body>
</html>

<?php
/**
 * ============================================================
 * UPLOAD EBOOK PAGE
 * ============================================================
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$pageTitle = 'Upload Ebook - RepoBook';
$errors = [];

// Fetch Categories
try {
    $db = Database::connect();
    $stmtCat = $db->query("SELECT * FROM categories ORDER BY name ASC");
    $categories = $stmtCat->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan tidak valid. Silakan coba lagi.';
    }

    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    if (empty($title)) $errors[] = 'Judul wajib diisi.';
    if (empty($author)) $errors[] = 'Penulis wajib diisi.';
    if ($category_id <= 0) $errors[] = 'Kategori wajib dipilih.';
    if (empty($_FILES['pdf_file']['name'])) $errors[] = 'File PDF wajib diunggah.';

    if (empty($errors)) {
        // PDF File Upload Handling
        $pdf = $_FILES['pdf_file'];
        $pdfExt = strtolower(pathinfo($pdf['name'], PATHINFO_EXTENSION));
        
        if ($pdf['error'] !== UPLOAD_ERR_OK) {
             $errors[] = 'Gagal mengunggah file PDF.';
        } elseif ($pdfExt !== 'pdf') {
             $errors[] = 'File harus berformat PDF.';
        } elseif ($pdf['size'] > 50 * 1024 * 1024) { // 50MB Max
             $errors[] = 'Ukuran file PDF maksimal 50MB.';
        }

        // Cover Image Upload Handling (Optional)
        $coverName = null;
        if (!empty($_FILES['cover_image']['name'])) {
             $img = $_FILES['cover_image'];
             $imgExt = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
             $allowedImg = ['jpg', 'jpeg', 'png', 'webp'];

             if ($img['error'] !== UPLOAD_ERR_OK) {
                 $errors[] = 'Gagal mengunggah cover image.';
             } elseif (!in_array($imgExt, $allowedImg)) {
                 $errors[] = 'Format cover harus JPG, PNG, atau WEBP.';
             } elseif ($img['size'] > 5 * 1024 * 1024) { // 5MB Max
                 $errors[] = 'Ukuran cover maksimal 5MB.';
             } else {
                 $coverName = uniqid('cover_') . '.webp';
             }
        }

        if (empty($errors)) {
            // Move PDF
            $pdfName = uniqid('ebook_') . '.pdf';
            $pdfDest = PDF_STORAGE . '/' . $pdfName;
            
            if (!is_dir(PDF_STORAGE)) {
                mkdir(PDF_STORAGE, 0755, true);
            }
            
            if (move_uploaded_file($pdf['tmp_name'], $pdfDest)) {
                // Move Cover
                if ($coverName && !empty($_FILES['cover_image']['tmp_name'])) {
                    if (!is_dir(COVER_STORAGE)) mkdir(COVER_STORAGE, 0755, true);
                    
                    $coverTmpPath = $_FILES['cover_image']['tmp_name'];
                    $coverDestPath = COVER_STORAGE . '/' . $coverName;
                    
                    // Auto convert to WebP
                    $image = false;
                    if (in_array($imgExt, ['jpg', 'jpeg'])) {
                        $image = @imagecreatefromjpeg($coverTmpPath);
                    } elseif ($imgExt === 'png') {
                        $image = @imagecreatefrompng($coverTmpPath);
                        if ($image) {
                            imagepalettetotruecolor($image);
                            imagealphablending($image, true);
                            imagesavealpha($image, true);
                        }
                    } elseif ($imgExt === 'webp') {
                        $image = @imagecreatefromwebp($coverTmpPath);
                    }
                    
                    if ($image !== false) {
                        imagewebp($image, $coverDestPath, 85); // 85 is quality
                        imagedestroy($image);
                    } else {
                        // Fallback if GD fails
                        move_uploaded_file($coverTmpPath, $coverDestPath);
                    }
                }

                // Generate slug
                $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($title));
                $slug = trim($slug, '-') . '-' . uniqid();

                try {
                    $stmt = $db->prepare("INSERT INTO ebooks (title, slug, author, description, cover_image, pdf_file, file_size, category_id, uploaded_by, status) VALUES (:title, :slug, :author, :description, :cover_image, :pdf_file, :file_size, :category_id, :uploaded_by, 'pending')");
                    
                    $stmt->execute([
                        ':title' => $title,
                        ':slug' => $slug,
                        ':author' => $author,
                        ':description' => $description,
                        ':cover_image' => $coverName,
                        ':pdf_file' => $pdfName,
                        ':file_size' => $pdf['size'],
                        ':category_id' => $category_id,
                        ':uploaded_by' => $_SESSION['user_id']
                    ]);
                    
                    setFlash('success', 'Ebook berhasil diunggah dan sedang menunggu persetujuan admin.');
                    redirect(BASE_URL . '/upload.php');
                } catch (PDOException $e) {
                    $errors[] = 'Gagal menyimpan ke database.';
                }
            } else {
                $errors[] = 'Gagal memindahkan file PDF ke storage.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📚</text></svg>">
    <link rel="stylesheet" href="<?= ASSET_URL ?>/assets/css/style.css">
    <style>
        .upload-container { max-width: 800px; margin: 40px auto; padding: 40px; background: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .upload-container h2 { margin-bottom: 8px; color: #1e293b; font-size: 28px; font-weight: 700; }
        .upload-desc { color: #64748b; margin-bottom: 32px; font-size: 15px; }
        .form-row { display: flex; gap: 24px; flex-wrap: wrap; margin-bottom: 20px; }
        .form-row .form-group { flex: 1; min-width: 250px; margin-bottom: 0; }
        label { display: block; margin-bottom: 8px; color: #334155; font-weight: 600; font-size: 14px; }
        .upload-input { width: 100%; padding: 14px 16px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-family: inherit; font-size: 15px; color: #1e293b; transition: all 0.2s ease; background-color: #f8fafc; }
        .upload-input:focus { outline: none; border-color: #3b82f6; background-color: #fff; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); }
        textarea.upload-input { min-height: 140px; resize: vertical; line-height: 1.5; }
        .file-drop-area { display: flex; flex-direction: column; align-items: center; justify-content: center; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 40px 20px; text-align: center; background: #f8fafc; cursor: pointer; transition: all 0.2s ease; margin-top: 8px; }
        .file-drop-area:hover { border-color: #3b82f6; background: #eff6ff; }
        .file-drop-area svg { color: #64748b; margin-bottom: 16px; width: 48px; height: 48px; transition: color 0.2s ease; }
        .file-drop-area:hover svg { color: #3b82f6; }
        .file-drop-area span { color: #475569; font-weight: 500; }
        .file-drop-area small { color: #94a3b8; margin-top: 8px; font-size: 13px; }
        .file-drop-area input[type="file"] { display: none; }
        .btn-upload-submit { background: #3b82f6; color: white; padding: 16px 32px; border: none; border-radius: 10px; font-weight: 600; font-size: 16px; cursor: pointer; transition: background 0.2s ease; margin-top: 32px; width: 100%; display: flex; justify-content: center; align-items: center; gap: 10px; }
        .btn-upload-submit:hover { background: #2563eb; }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <div class="main-wrapper">
            <?php include __DIR__ . '/../includes/header.php'; ?>
            <main class="content-area">
                <div class="upload-container">
                    <h2>Upload Ebook Baru</h2>
                    <p class="upload-desc">Bagikan koleksi buku digital Anda. Semua ebook yang diunggah akan ditinjau oleh admin sebelum diterbitkan.</p>
                    
                    <?php if ($msg = getFlash('success')): ?>
                        <div class="flash-msg success" style="margin-bottom:24px; padding:16px; background:#ecfdf5; color:#047857; border-radius:8px; border-left:4px solid #10b981;">
                            <?= e($msg) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="flash-msg error" style="margin-bottom:24px; padding:16px; background:#fef2f2; color:#b91c1c; border-radius:8px; border-left:4px solid #ef4444;">
                            <ul style="margin:0; padding-left:20px;">
                                <?php foreach ($errors as $err): ?>
                                    <li><?= e($err) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="title">Judul Buku *</label>
                                <input type="text" id="title" name="title" class="upload-input" value="<?= e($_POST['title'] ?? '') ?>" placeholder="Masukkan judul buku" required>
                            </div>
                            <div class="form-group">
                                <label for="author">Penulis *</label>
                                <input type="text" id="author" name="author" class="upload-input" value="<?= e($_POST['author'] ?? '') ?>" placeholder="Nama penulis asli" required>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 20px;">
                            <label for="category_id">Kategori *</label>
                            <select id="category_id" name="category_id" class="upload-input" required>
                                <option value="">-- Pilih Kategori --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                        <?= e($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom: 24px;">
                            <label for="description">Sinopsis / Deskripsi</label>
                            <textarea id="description" name="description" class="upload-input" placeholder="Ceritakan secara singkat isi buku ini..."><?= e($_POST['description'] ?? '') ?></textarea>
                        </div>

                        <div class="form-row" style="margin-bottom: 0;">
                            <div class="form-group" style="flex:2;">
                                <label>File Ebook (PDF) *</label>
                                <label class="file-drop-area">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="15" y2="15"></line></svg>
                                    <span id="pdfNameDisplay">Klik untuk memilih file PDF</span>
                                    <small>Ukuran maksimal 50MB</small>
                                    <input type="file" name="pdf_file" id="pdf_file" accept=".pdf" required onchange="document.getElementById('pdfNameDisplay').innerText = this.files[0] ? this.files[0].name : 'Klik untuk memilih file PDF'">
                                </label>
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label>Cover Buku (Opsional)</label>
                                <label class="file-drop-area">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                                    <span id="coverNameDisplay">Klik untuk memilih Gambar Cover</span>
                                    <small>Format JPG/PNG, maks 5MB</small>
                                    <input type="file" name="cover_image" id="cover_image" accept="image/jpeg,image/png,image/webp" onchange="document.getElementById('coverNameDisplay').innerText = this.files[0] ? this.files[0].name : 'Klik untuk memilih Gambar Cover'">
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn-upload-submit">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                            Unggah Ebook Sekarang
                        </button>
                    </form>
                </div>
            </main>
        </div>
    </div>
    <script src="<?= ASSET_URL ?>/assets/js/app.js"></script>
</body>
</html>

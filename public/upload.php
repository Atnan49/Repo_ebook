<?php

/**
 * ============================================================
 * UPLOAD EBOOK PAGE
 * ============================================================
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/storage.php';

requireLogin();

$pageTitle = 'Upload Ebook - RepoBook';
$errors = [];

$db = Database::connect();

// Fetch Categories
try {
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

    // Check if client-side upload was used
    $clientPdfName = trim($_POST['client_pdf_name'] ?? '');
    $clientCoverName = trim($_POST['client_cover_name'] ?? '');
    $clientPdfSize = intval($_POST['client_pdf_size'] ?? 0);
    
    $isClientUpload = StorageHelper::isSupabaseEnabled() && !empty($clientPdfName);

    if (!$isClientUpload && empty($_FILES['pdf_file']['name'])) {
        $errors[] = 'File PDF wajib diunggah.';
    }

    if (empty($errors)) {
        if ($isClientUpload) {
            $pdfName = $clientPdfName;
            $pdfSize = $clientPdfSize;
            $coverName = !empty($clientCoverName) ? $clientCoverName : null;
            $pdfUploaded = true;
        } else {
            // Standard server-side upload
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
            $imgExt = '';
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
                    // Check if WebP conversion is supported by GD library
                    if (function_exists('imagewebp')) {
                        $coverName = uniqid('cover_') . '.webp';
                    } else {
                        $coverName = uniqid('cover_') . '.' . $imgExt;
                    }
                }
            }
        }

        if (empty($errors)) {
            if (!$isClientUpload) {
                // Move PDF
                $pdfName = uniqid('ebook_') . '.pdf';
                $pdfUploaded = StorageHelper::upload($pdf['tmp_name'], $pdfName, 'pdfs');
                $pdfSize = $pdf['size'];

                if ($pdfUploaded) {
                    // Move Cover
                    if ($coverName && !empty($_FILES['cover_image']['tmp_name'])) {
                        $coverTmpPath = $_FILES['cover_image']['tmp_name'];
                        
                        // Temp destination dir
                        $tempDestDir = StorageHelper::isSupabaseEnabled() ? sys_get_temp_dir() : COVER_STORAGE;
                        if (!is_dir($tempDestDir)) {
                            mkdir($tempDestDir, 0755, true);
                        }
                        $coverDestPath = $tempDestDir . '/' . $coverName;

                        // Auto convert to WebP if supported by GD
                        $image = false;
                        $isConverted = false;

                        if (function_exists('imagewebp')) {
                            if (in_array($imgExt, ['jpg', 'jpeg'])) {
                                $image = @imagecreatefromjpeg($coverTmpPath);
                            } elseif ($imgExt === 'png') {
                                $image = @imagecreatefrompng($coverTmpPath);
                                if ($image) {
                                    imagepalettetotruecolor($image);
                                    imagealphablending($image, false);
                                }
                            } elseif ($imgExt === 'webp') {
                                $image = @imagecreatefromwebp($coverTmpPath);
                            }

                            if ($image !== false) {
                                $isConverted = @imagewebp($image, $coverDestPath, 85);
                                imagedestroy($image);
                            }
                        }

                        if ($isConverted) {
                            if (StorageHelper::isSupabaseEnabled()) {
                                StorageHelper::upload($coverDestPath, $coverName, 'covers');
                                @unlink($coverDestPath);
                            }
                        } else {
                            // Fallback if GD fails or WebP is not supported
                            if (function_exists('imagewebp')) {
                                $coverName = pathinfo($coverName, PATHINFO_FILENAME) . '.' . $imgExt;
                                $coverDestPath = $tempDestDir . '/' . $coverName;
                            }
                            
                            if (StorageHelper::isSupabaseEnabled()) {
                                StorageHelper::upload($coverTmpPath, $coverName, 'covers');
                            } else {
                                move_uploaded_file($coverTmpPath, $coverDestPath);
                            }
                        }
                    }
                }
            }

            if ($pdfUploaded) {
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
                        ':file_size' => $pdfSize,
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
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" type="image/x-icon" href="<?= ASSET_URL ?>/favicon.ico">
    <link rel="stylesheet" href="<?= ASSET_URL ?>/assets/css/style.css">
    
    <script>
        const SUPABASE_ENABLED = <?= StorageHelper::isSupabaseEnabled() ? 'true' : 'false' ?>;
        const SUPABASE_URL = <?= json_encode(SUPABASE_URL) ?>;
        const SUPABASE_KEY = <?= json_encode(SUPABASE_KEY) ?>;
    </script>
    <style>
        .upload-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 40px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        }

        .upload-container h2 {
            margin-bottom: 8px;
            color: #1e293b;
            font-size: 28px;
            font-weight: 700;
        }

        .upload-desc {
            color: #64748b;
            margin-bottom: 32px;
            font-size: 15px;
        }

        .form-row {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .form-row .form-group {
            flex: 1;
            min-width: 250px;
            margin-bottom: 0;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #334155;
            font-weight: 600;
            font-size: 14px;
        }

        .upload-input {
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-family: inherit;
            font-size: 15px;
            color: #1e293b;
            transition: all 0.2s ease;
            background-color: #f8fafc;
        }

        .upload-input:focus {
            outline: none;
            border-color: #3b82f6;
            background-color: #fff;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        textarea.upload-input {
            min-height: 140px;
            resize: vertical;
            line-height: 1.5;
        }

        .file-drop-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 8px;
        }

        .file-drop-area:hover {
            border-color: #3b82f6;
            background: #eff6ff;
        }

        .file-drop-area svg {
            color: #64748b;
            margin-bottom: 16px;
            width: 48px;
            height: 48px;
            transition: color 0.2s ease;
        }

        .file-drop-area:hover svg {
            color: #3b82f6;
        }

        .file-drop-area span {
            color: #475569;
            font-weight: 500;
        }

        .file-drop-area small {
            color: #94a3b8;
            margin-top: 8px;
            font-size: 13px;
        }

        .file-drop-area input[type="file"] {
            display: none;
        }

        .btn-upload-submit {
            background: #3b82f6;
            color: white;
            padding: 16px 32px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.2s ease;
            margin-top: 32px;
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }

        .btn-upload-submit:hover {
            background: #2563eb;
        }
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
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                        <polyline points="14 2 14 8 20 8"></polyline>
                                        <line x1="12" y1="18" x2="12" y2="12"></line>
                                        <line x1="9" y1="15" x2="15" y2="15"></line>
                                    </svg>
                                    <span id="pdfNameDisplay">Klik untuk memilih file PDF</span>
                                    <small>Ukuran maksimal 50MB</small>
                                    <input type="file" name="pdf_file" id="pdf_file" accept=".pdf" required onchange="document.getElementById('pdfNameDisplay').innerText = this.files[0] ? this.files[0].name : 'Klik untuk memilih file PDF'">
                                </label>
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label>Cover Buku (Opsional)</label>
                                <label class="file-drop-area">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                        <polyline points="21 15 16 10 5 21"></polyline>
                                    </svg>
                                    <span id="coverNameDisplay">Klik untuk memilih Gambar Cover</span>
                                    <small>Format JPG/PNG, maks 5MB</small>
                                    <input type="file" name="cover_image" id="cover_image" accept="image/jpeg,image/png,image/webp" onchange="document.getElementById('coverNameDisplay').innerText = this.files[0] ? this.files[0].name : 'Klik untuk memilih Gambar Cover'">
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn-upload-submit">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="17 8 12 3 7 8"></polyline>
                                <line x1="12" y1="3" x2="12" y2="15"></line>
                            </svg>
                            Unggah Ebook Sekarang
                        </button>
                    </form>
                </div>
            </main>
        </div>
    </div>
    <script src="<?= ASSET_URL ?>/assets/js/app.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('form');
            if (!form) return;

            form.addEventListener('submit', async (e) => {
                if (typeof SUPABASE_ENABLED !== 'undefined' && SUPABASE_ENABLED) {
                    const pdfInput = document.getElementById('pdf_file');
                    const coverInput = document.getElementById('cover_image');

                    if (document.getElementById('client_pdf_name')) {
                        return; // Form has already uploaded files, submit normally
                    }

                    if (pdfInput && pdfInput.files.length > 0) {
                        e.preventDefault(); // Stop native submit

                        const submitBtn = form.querySelector('.btn-upload-submit');
                        const originalText = submitBtn.innerHTML;
                        submitBtn.disabled = true;
                        
                        // Spinner styling
                        submitBtn.innerHTML = `
                            <svg class="spinner" style="animation: spin 1s linear infinite; margin-right: 8px; width: 18px; height: 18px; display: inline-block; vertical-align: middle;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <circle cx="12" cy="12" r="10" stroke="rgba(255,255,255,0.2)"></circle>
                                <path d="M4 12a8 8 0 0 1 8-8" stroke="#fff"></path>
                            </svg>
                            Mengunggah langsung ke Cloud...
                        `;

                        try {
                            const pdfFile = pdfInput.files[0];
                            const pdfName = 'ebook_' + Math.random().toString(36).substring(2, 15) + '_' + Date.now() + '.pdf';
                            
                            // Upload PDF directly to Supabase
                            const pdfUrl = `${SUPABASE_URL.replace(/\/$/, '')}/storage/v1/object/pdfs/${pdfName}`;
                            const pdfResponse = await fetch(pdfUrl, {
                                method: 'POST',
                                headers: {
                                    'apikey': SUPABASE_KEY,
                                    'Authorization': `Bearer ${SUPABASE_KEY}`,
                                    'Content-Type': 'application/pdf'
                                },
                                body: pdfFile
                            });

                            if (!pdfResponse.ok) {
                                const errorData = await pdfResponse.json().catch(() => ({}));
                                throw new Error(errorData.message || 'Gagal mengunggah file PDF ke Cloud Storage.');
                            }

                            // Upload Cover directly to Supabase if provided
                            let coverName = '';
                            if (coverInput && coverInput.files.length > 0) {
                                const coverFile = coverInput.files[0];
                                const coverExt = coverFile.name.split('.').pop().toLowerCase();
                                coverName = 'cover_' + Math.random().toString(36).substring(2, 15) + '_' + Date.now() + '.' + coverExt;

                                const coverUrl = `${SUPABASE_URL.replace(/\/$/, '')}/storage/v1/object/covers/${coverName}`;
                                const coverResponse = await fetch(coverUrl, {
                                    method: 'POST',
                                    headers: {
                                        'apikey': SUPABASE_KEY,
                                        'Authorization': `Bearer ${SUPABASE_KEY}`,
                                        'Content-Type': coverFile.type
                                    },
                                    body: coverFile
                                });

                                if (!coverResponse.ok) {
                                    const errorData = await coverResponse.json().catch(() => ({}));
                                    throw new Error(errorData.message || 'Gagal mengunggah Cover Image ke Cloud Storage.');
                                }
                            }

                            // Append hidden fields to the form
                            const hiddenPdf = document.createElement('input');
                            hiddenPdf.type = 'hidden';
                            hiddenPdf.id = 'client_pdf_name';
                            hiddenPdf.name = 'client_pdf_name';
                            hiddenPdf.value = pdfName;
                            form.appendChild(hiddenPdf);

                            const hiddenPdfSize = document.createElement('input');
                            hiddenPdfSize.type = 'hidden';
                            hiddenPdfSize.name = 'client_pdf_size';
                            hiddenPdfSize.value = pdfFile.size;
                            form.appendChild(hiddenPdfSize);

                            if (coverName) {
                                const hiddenCover = document.createElement('input');
                                hiddenCover.type = 'hidden';
                                hiddenCover.name = 'client_cover_name';
                                hiddenCover.value = coverName;
                                form.appendChild(hiddenCover);
                            }

                            // Bypass HTML required validators and clear file payloads to prevent Vercel 413 error
                            pdfInput.removeAttribute('required');
                            pdfInput.value = '';
                            if (coverInput) {
                                coverInput.removeAttribute('required');
                                coverInput.value = '';
                            }

                            // Submit the form containing only metadata and filenames
                            form.submit();
                        } catch (error) {
                            console.error('Error during upload:', error);
                            alert('Terjadi kesalahan saat mengunggah: ' + error.message);
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }
                    }
                }
            });
        });
    </script>
    <style>
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</body>

</html>
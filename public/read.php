<?php
/**
 * ============================================================
 * READ EBOOK - 3D Page Flip Reader & Secure Streaming
 * ============================================================
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Akses ditolak: ID ebook tidak valid.");
}

$id = (int)$_GET['id'];

try {
    $db = Database::connect();
    $stmt = $db->prepare("SELECT * FROM ebooks WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $book = $stmt->fetch();

    if (!$book) {
        die("Ebook tidak ditemukan.");
    }

    // Check authorization (Guests are allowed to read approved books)
    $isAuthorized = false;
    if ($book['status'] === 'approved') {
        $isAuthorized = true;
    } elseif (isLoggedIn()) {
        if (isAdmin()) {
            $isAuthorized = true; // Admin can read pending/rejected books
        } elseif ((int)$book['uploaded_by'] === (int)$_SESSION['user_id']) {
            $isAuthorized = true; // Uploader can read their own books
        }
    }

    if (!$isAuthorized) {
        if (!isLoggedIn()) {
            $_SESSION['flash_error'] = 'Silakan login terlebih dahulu untuk membaca ebook privat.';
            redirect(BASE_URL . '/login.php');
        } else {
            die("Akses ditolak: Anda tidak memiliki izin untuk membaca ebook ini.");
        }
    }

    $filePath = PDF_STORAGE . '/' . $book['pdf_file'];
    if (!file_exists($filePath)) {
        die("File PDF tidak ditemukan di server.");
    }

    // Mode 1: PDF Binary Streaming (for PDF.js consumer)
    if (isset($_GET['stream']) && $_GET['stream'] == 1) {
        // Update download/read count (only count once per session)
        if (!isset($_SESSION['read_' . $id]) && !isAdmin()) {
            $db->prepare("UPDATE ebooks SET downloads = downloads + 1 WHERE id = :id")->execute([':id' => $id]);
            $_SESSION['read_' . $id] = true;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . str_replace('"', '', basename($book['title'])) . '.pdf"');
        header('Content-Transfer-Encoding: binary');
        header('Accept-Ranges: bytes');
        header('Content-Length: ' . filesize($filePath));
        @readfile($filePath);
        exit;
    }

} catch (PDOException $e) {
    die("Terjadi kesalahan sistem saat memuat ebook.");
}

// Mode 2: Interactive 3D Book Page-Flip Reader HTML Page
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membaca: <?= e($book['title']) ?> - RepoBook</title>
    <link rel="icon" type="image/x-icon" href="<?= ASSET_URL ?>/favicon.ico">
    
    <!-- PDF.js Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            user-select: none;
        }

        body {
            background: radial-gradient(circle at center, #1e293b 0%, #0f172a 100%);
            color: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* Top Bar */
        .top-bar {
            height: 60px;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            z-index: 100;
        }

        .book-title {
            font-size: 16px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 50vw;
        }

        .btn-close {
            background: rgba(255, 255, 255, 0.08);
            color: #f1f5f9;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            cursor: pointer;
        }

        .btn-close:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.25);
            transform: translateX(-2px);
        }

        /* Viewport */
        .viewport {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            perspective: 2000px;
            overflow: hidden;
            padding: 20px;
        }

        /* Loading Spinner Overlay */
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.85);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 50;
            transition: opacity 0.5s ease;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255, 255, 255, 0.1);
            border-top: 4px solid #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }

        .loading-progress {
            font-size: 14px;
            color: #94a3b8;
            margin-top: 8px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* 3D Book Container */
        .book-container {
            width: 900px;
            height: 600px;
            position: relative;
            transform-style: preserve-3d;
            transition: transform 0.1s ease;
            transform-origin: center center;
        }

        /* Background Book Shadow / Thickness Depth */
        .book-depth-shadow {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            background: rgba(0, 0, 0, 0.35);
            filter: blur(25px);
            border-radius: 12px;
            transform: translate3d(0, 15px, -50px);
            pointer-events: none;
        }

        /* Spine Fold (Center crease) */
        .book-spine-line {
            position: absolute;
            width: 2px;
            height: 100%;
            left: 50%;
            top: 0;
            background: rgba(0, 0, 0, 0.25);
            box-shadow: 0 0 8px rgba(0, 0, 0, 0.6);
            z-index: 40;
            pointer-events: none;
            transform: translateZ(1px);
        }

        /* Sheets (Pair of Pages) */
        .sheet {
            position: absolute;
            width: 50%; /* half width */
            height: 100%;
            top: 0;
            right: 0;
            transform-origin: left center;
            transform-style: preserve-3d;
            transition: transform 0.8s cubic-bezier(0.645, 0.045, 0.355, 1);
            z-index: 1;
            pointer-events: none;
        }

        .sheet.active, .sheet.flipping-forward, .sheet.flipping-backward {
            pointer-events: auto;
        }

        /* Flipped state */
        .sheet.flipped {
            transform: rotateY(-180deg);
        }

        /* Dynamic Paper Flex/Curl Animations */
        @keyframes flip-forward {
            0% {
                transform: rotateY(0deg) skewY(0deg) scale(1);
            }
            30% {
                transform: rotateY(-50deg) skewY(-2.5deg) scaleX(0.95) scaleY(1.02);
            }
            50% {
                transform: rotateY(-90deg) skewY(-4deg) scaleX(0.9) scaleY(1.04);
            }
            70% {
                transform: rotateY(-130deg) skewY(-2.5deg) scaleX(0.95) scaleY(1.02);
            }
            100% {
                transform: rotateY(-180deg) skewY(0deg) scale(1);
            }
        }

        @keyframes flip-backward {
            0% {
                transform: rotateY(-180deg) skewY(0deg) scale(1);
            }
            30% {
                transform: rotateY(-130deg) skewY(2.5deg) scaleX(0.95) scaleY(1.02);
            }
            50% {
                transform: rotateY(-90deg) skewY(4deg) scaleX(0.9) scaleY(1.04);
            }
            70% {
                transform: rotateY(-50deg) skewY(2.5deg) scaleX(0.95) scaleY(1.02);
            }
            100% {
                transform: rotateY(0deg) skewY(0deg) scale(1);
            }
        }

        .sheet.flipping-forward {
            animation: flip-forward 0.8s cubic-bezier(0.645, 0.045, 0.355, 1) forwards;
        }

        .sheet.flipping-backward {
            animation: flip-backward 0.8s cubic-bezier(0.645, 0.045, 0.355, 1) forwards;
        }

        /* Moving shadow overlays during page-flip */
        .page-shadow {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 15;
            pointer-events: none;
            opacity: 0;
        }

        @keyframes shadow-forward-front {
            0% { opacity: 0; background: linear-gradient(to right, rgba(0,0,0,0) 0%, rgba(0,0,0,0.1) 100%); }
            50% { opacity: 0.6; background: linear-gradient(to right, rgba(0,0,0,0.3) 0%, rgba(0,0,0,0.4) 50%, rgba(0,0,0,0) 100%); }
            100% { opacity: 0; background: linear-gradient(to right, rgba(0,0,0,0.2) 0%, rgba(0,0,0,0) 100%); }
        }

        @keyframes shadow-forward-back {
            0% { opacity: 0.6; background: linear-gradient(to left, rgba(0,0,0,0.2) 0%, rgba(0,0,0,0) 100%); }
            50% { opacity: 0.5; background: linear-gradient(to left, rgba(0,0,0,0.3) 0%, rgba(0,0,0,0.4) 50%, rgba(0,0,0,0) 100%); }
            100% { opacity: 0; background: linear-gradient(to left, rgba(0,0,0,0) 0%, rgba(0,0,0,0.1) 100%); }
        }

        .sheet.flipping-forward .page-face.front .page-shadow {
            animation: shadow-forward-front 0.8s ease-in-out forwards;
        }

        .sheet.flipping-forward .page-face.back .page-shadow {
            animation: shadow-forward-back 0.8s ease-in-out forwards;
        }

        @keyframes shadow-backward-front {
            0% { opacity: 0.6; background: linear-gradient(to right, rgba(0,0,0,0.2) 0%, rgba(0,0,0,0) 100%); }
            50% { opacity: 0.5; background: linear-gradient(to right, rgba(0,0,0,0.3) 0%, rgba(0,0,0,0.4) 50%, rgba(0,0,0,0) 100%); }
            100% { opacity: 0; background: linear-gradient(to right, rgba(0,0,0,0) 0%, rgba(0,0,0,0.1) 100%); }
        }

        @keyframes shadow-backward-back {
            0% { opacity: 0; background: linear-gradient(to left, rgba(0,0,0,0) 0%, rgba(0,0,0,0.1) 100%); }
            50% { opacity: 0.6; background: linear-gradient(to left, rgba(0,0,0,0.3) 0%, rgba(0,0,0,0.4) 50%, rgba(0,0,0,0) 100%); }
            100% { opacity: 0; background: linear-gradient(to left, rgba(0,0,0,0.2) 0%, rgba(0,0,0,0) 100%); }
        }

        .sheet.flipping-backward .page-face.front .page-shadow {
            animation: shadow-backward-front 0.8s ease-in-out forwards;
        }

        .sheet.flipping-backward .page-face.back .page-shadow {
            animation: shadow-backward-back 0.8s ease-in-out forwards;
        }

        /* Page faces */
        .page-face {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            backface-visibility: hidden;
            background-color: #fcfbf9;
            box-shadow: inset 0 0 20px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        /* Spine shadow overlay to add 3D depth inside pages */
        .page-face::after {
            content: '';
            position: absolute;
            top: 0;
            width: 40px;
            height: 100%;
            z-index: 10;
            pointer-events: none;
        }

        .page-face.front {
            transform: rotateY(0deg);
            z-index: 2;
            border-radius: 0 8px 8px 0;
            box-shadow: 5px 5px 15px rgba(0,0,0,0.15), inset 3px 0 10px rgba(0,0,0,0.05);
        }

        .page-face.front::after {
            left: 0;
            background: linear-gradient(to right, rgba(0,0,0,0.15) 0%, rgba(0,0,0,0) 100%);
        }

        .page-face.back {
            transform: rotateY(180deg);
            z-index: 1;
            border-radius: 8px 0 0 8px;
            box-shadow: -5px 5px 15px rgba(0,0,0,0.15), inset -3px 0 10px rgba(0,0,0,0.05);
        }

        .page-face.back::after {
            right: 0;
            background: linear-gradient(to left, rgba(0,0,0,0.15) 0%, rgba(0,0,0,0) 100%);
        }

        /* Loading spinner inside individual pages during lazy load */
        .loading-page::before {
            content: '';
            position: absolute;
            width: 30px;
            height: 30px;
            border: 3px solid rgba(0, 0, 0, 0.1);
            border-top: 3px solid #3b82f6;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            z-index: 5;
        }

        canvas {
            display: block;
            opacity: 0;
            transition: opacity 0.3s ease;
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        /* Blank cover styling for single-page look */
        .page-face.blank-face {
            background-color: rgba(15, 23, 42, 0.2);
            box-shadow: none;
        }
        .page-face.blank-face::after {
            display: none;
        }

        /* Navigation Click Zones */
        .click-zone {
            position: absolute;
            top: 0;
            width: 25%;
            height: 100%;
            z-index: 80;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .click-zone:hover {
            opacity: 0.15;
            background: linear-gradient(to var(--direction), rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 100%);
        }

        .click-zone-left {
            left: 0;
            --direction: right;
        }

        .click-zone-right {
            right: 0;
            --direction: left;
        }

        .click-zone svg {
            width: 48px;
            height: 48px;
            color: #fff;
            filter: drop-shadow(0 2px 5px rgba(0,0,0,0.5));
        }

        /* Floating Helper Hint */
        .swipe-hint {
            position: absolute;
            bottom: 90px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255,255,255,0.15);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            color: #cbd5e1;
            pointer-events: none;
            z-index: 90;
            animation: fadeOut 5s forwards;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        @keyframes fadeOut {
            0% { opacity: 0; transform: translate(-50%, 10px); }
            10% { opacity: 1; transform: translate(-50%, 0); }
            80% { opacity: 1; }
            100% { opacity: 0; transform: translate(-50%, -10px); display: none; }
        }

        /* Bottom Control Panel */
        .controls-panel {
            height: 70px;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(12px);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            padding: 0 20px;
            z-index: 100;
        }

        .control-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-control {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #f1f5f9;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .btn-control:hover:not(:disabled) {
            background: rgba(255, 255, 255, 0.18);
            border-color: rgba(255, 255, 255, 0.3);
            color: #fff;
        }

        .btn-control:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }

        .page-indicator {
            font-size: 14px;
            font-weight: 500;
            color: #94a3b8;
            min-width: 100px;
            text-align: center;
        }

        .page-indicator strong {
            color: #f1f5f9;
        }

        /* Fullscreen styles */
        body:fullscreen {
            background: #090d16;
        }
    </style>
</head>
<body>

    <!-- Top Navigation Bar -->
    <header class="top-bar">
        <button class="btn-close" onclick="closeReader()">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Kembali
        </button>
        <div class="book-title"><?= e($book['title']) ?></div>
        <div style="width: 80px;"></div> <!-- Spacer to keep title centered -->
    </header>

    <!-- Interactive Viewport -->
    <main class="viewport" id="viewport">
        <!-- Click navigation zones -->
        <div class="click-zone click-zone-left" onclick="bookFlip.prev()" title="Halaman Sebelumnya">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
        </div>
        <div class="click-zone click-zone-right" onclick="bookFlip.next()" title="Halaman Selanjutnya">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </div>

        <!-- Initial loading state -->
        <div class="loading-overlay" id="loadingOverlay">
            <div class="spinner"></div>
            <h3>Memuat Dokumen...</h3>
            <div class="loading-progress" id="loadingProgress">Menghubungkan ke penyimpanan...</div>
        </div>

        <!-- Dynamic 3D Book Layout -->
        <div class="book-container" id="book">
            <div class="book-depth-shadow"></div>
            <div class="book-spine-line"></div>
            <!-- Sheets will be injected here via JavaScript -->
        </div>

        <!-- Floating Swipe Help Hint -->
        <div class="swipe-hint">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="21" y1="12" x2="9" y2="12"></line><polyline points="15 18 9 12 15 6"></polyline></svg>
            <span>Klik halaman atau gunakan panah keyboard untuk membalik</span>
        </div>
    </main>

    <!-- Bottom Control Panel -->
    <footer class="controls-panel">
        <div class="control-group">
            <button class="btn-control" id="prevBtn" onclick="bookFlip.prev()" title="Halaman Sebelumnya">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
            </button>
            <div class="page-indicator">
                Halaman <strong id="currentPageIndicator">1</strong> dari <strong id="totalPagesIndicator">-</strong>
            </div>
            <button class="btn-control" id="nextBtn" onclick="bookFlip.next()" title="Halaman Selanjutnya">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
        </div>

        <div style="width: 2px; height: 30px; background: rgba(255,255,255,0.1);"></div>

        <div class="control-group">
            <button class="btn-control" id="zoomOutBtn" onclick="zoomBook(0.9)" title="Perkecil">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            </button>
            <button class="btn-control" id="zoomInBtn" onclick="zoomBook(1.1)" title="Perbesar">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            </button>
            <button class="btn-control" onclick="toggleFullscreen()" title="Layar Penuh">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path></svg>
            </button>
        </div>
    </footer>

    <script>
        // Set worker path dynamically from Cloudflare CDN matching library version
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        const pdfUrl = '<?= BASE_URL ?>/read.php?id=<?= $id ?>&stream=1';
        let pdfDoc = null;
        let totalPages = 0;
        let bookFlip = null;
        let currentScale = 1.0;

        // 3D Page Flipping Core Engine
        class Book3D {
            constructor(containerEl, numPages) {
                this.container = containerEl;
                this.numPages = numPages;
                
                // Construct dynamic sheets.
                // Page 1 is Cover (right side), left side is blank.
                // Page 2 & 3: Sheet 1 (Back = Page 2, Front = Page 3)
                // Page 4 & 5: Sheet 2 (Back = Page 4, Front = Page 5)
                // If odd number of pages, pad with a blank page at the end.
                this.sheets = [];
                this.currentSheetIndex = 0; // index of sheet currently in focus

                this.initLayout();
                this.updateZIndices();
                this.updateButtons();
            }

            initLayout() {
                // Clear initial book contents except shadows
                const shadows = this.container.querySelectorAll('.book-depth-shadow, .book-spine-line');
                this.container.innerHTML = '';
                shadows.forEach(el => this.container.appendChild(el));

                // Sheet 0 (Cover Sheet)
                // Left page: Blank, Right page: Page 1 (Cover)
                this.createSheet(0, null, 1);

                // Sheets 1 to M
                let sheetIdx = 1;
                for (let p = 2; p <= this.numPages; p += 2) {
                    const leftPage = p;
                    const rightPage = (p + 1 <= this.numPages) ? p + 1 : null;
                    this.createSheet(sheetIdx, leftPage, rightPage);
                    sheetIdx++;
                }

                // Add active state to sheet 0
                if (this.sheets.length > 0) {
                    this.sheets[0].classList.add('active');
                }
            }

            createSheet(index, leftPageNum, rightPageNum) {
                const sheetEl = document.createElement('div');
                sheetEl.className = 'sheet';
                sheetEl.id = `sheet-${index}`;

                // Back side of sheet (shows on the LEFT when flipped)
                const backFace = document.createElement('div');
                backFace.className = 'page-face back';
                if (leftPageNum !== null) {
                    backFace.classList.add('loading-page');
                    const canvas = document.createElement('canvas');
                    canvas.id = `page-canvas-${leftPageNum}`;
                    backFace.appendChild(canvas);

                    // Add shadow overlay
                    const shadow = document.createElement('div');
                    shadow.className = 'page-shadow';
                    backFace.appendChild(shadow);
                } else {
                    backFace.classList.add('blank-face');
                }

                // Front side of sheet (shows on the RIGHT when unflipped)
                const frontFace = document.createElement('div');
                frontFace.className = 'page-face front';
                if (rightPageNum !== null) {
                    frontFace.classList.add('loading-page');
                    const canvas = document.createElement('canvas');
                    canvas.id = `page-canvas-${rightPageNum}`;
                    frontFace.appendChild(canvas);

                    // Add shadow overlay
                    const shadow = document.createElement('div');
                    shadow.className = 'page-shadow';
                    frontFace.appendChild(shadow);
                } else {
                    frontFace.classList.add('blank-face');
                }

                sheetEl.appendChild(backFace);
                sheetEl.appendChild(frontFace);
                this.container.appendChild(sheetEl);
                this.sheets.push(sheetEl);

                // Trigger lazy render for initially visible pages (Page 1)
                if (index === 0) {
                    if (rightPageNum) this.lazyRenderPage(rightPageNum);
                }
            }

            lazyRenderPage(pageNum) {
                if (pageNum < 1 || pageNum > this.numPages) return;
                const canvas = document.getElementById(`page-canvas-${pageNum}`);
                if (!canvas || canvas.dataset.rendered === 'true') return;

                canvas.dataset.rendered = 'true';
                const ctx = canvas.getContext('2d');
                const pageFace = canvas.parentElement;

                pdfDoc.getPage(pageNum).then(page => {
                    const viewport = page.getViewport({ scale: 1 });
                    const containerHeight = 600; // Book height
                    
                    // High DPI rendering scale
                    const scale = (containerHeight / viewport.height) * 1.5;
                    const scaledViewport = page.getViewport({ scale: scale });

                    canvas.width = scaledViewport.width;
                    canvas.height = scaledViewport.height;
                    
                    canvas.style.width = '100%';
                    canvas.style.height = '100%';

                    const renderContext = {
                        canvasContext: ctx,
                        viewport: scaledViewport
                    };

                    page.render(renderContext).promise.then(() => {
                        pageFace.classList.remove('loading-page');
                        canvas.style.opacity = 1;
                    });
                }).catch(err => {
                    console.error('Error rendering page:', err);
                    pageFace.classList.remove('loading-page');
                    pageFace.innerHTML = '<div style="color:#ef4444;font-size:12px;padding:20px;">Gagal memuat</div>';
                });
            }

            // Lazy loads pages in the vicinity of the active sheet
            preloadVicinity() {
                // Determine pages visible for the current sheet index
                // Sheet index 'i' displays:
                // - Left side (from Sheet i-1 back if flipped, i.e., Page 2i)
                // - Right side (from Sheet i front, i.e., Page 2i + 1)
                
                // Let's render current visible spread
                const currentRightPage = 2 * this.currentSheetIndex + 1;
                const currentLeftPage = currentRightPage - 1;

                this.lazyRenderPage(currentLeftPage);
                this.lazyRenderPage(currentRightPage);

                // Preload next spread
                this.lazyRenderPage(currentRightPage + 1);
                this.lazyRenderPage(currentRightPage + 2);

                // Preload previous spread
                this.lazyRenderPage(currentLeftPage - 1);
                this.lazyRenderPage(currentLeftPage - 2);
            }

            updateZIndices() {
                const totalSheets = this.sheets.length;

                this.sheets.forEach((sheet, idx) => {
                    sheet.classList.remove('active', 'flipping-forward', 'flipping-backward');
                    
                    if (idx < this.currentSheetIndex) {
                        // Sheets flipped to the left
                        sheet.classList.add('flipped');
                        sheet.style.zIndex = idx + 1;
                    } else if (idx === this.currentSheetIndex) {
                        // Current active sheet
                        sheet.classList.remove('flipped');
                        sheet.classList.add('active');
                        sheet.style.zIndex = totalSheets + 5;
                    } else {
                        // Sheets resting on the right
                        sheet.classList.remove('flipped');
                        sheet.style.zIndex = totalSheets - idx;
                    }
                });
            }

            next() {
                if (this.currentSheetIndex >= this.sheets.length - 1) return;
                
                const sheet = this.sheets[this.currentSheetIndex];
                sheet.classList.remove('flipping-backward');
                sheet.classList.add('flipping-forward');
                sheet.style.zIndex = this.sheets.length + 10; // Elevate Z-index in mid-air

                this.currentSheetIndex++;
                this.preloadVicinity();

                setTimeout(() => {
                    sheet.classList.remove('flipping-forward');
                    this.updateZIndices();
                    this.updateButtons();
                }, 800); // match transition speed (0.8s)
            }

            prev() {
                if (this.currentSheetIndex <= 0) return;

                this.currentSheetIndex--;
                const sheet = this.sheets[this.currentSheetIndex];
                sheet.classList.remove('flipping-forward');
                sheet.classList.add('flipping-backward');
                sheet.style.zIndex = this.sheets.length + 10; // Elevate Z-index in mid-air

                this.preloadVicinity();

                setTimeout(() => {
                    sheet.classList.remove('flipping-backward');
                    this.updateZIndices();
                    this.updateButtons();
                }, 800);
            }

            updateButtons() {
                const currentRightPage = 2 * this.currentSheetIndex + 1;
                const currentLeftPage = currentRightPage - 1;
                
                // Indicators
                const prevBtn = document.getElementById('prevBtn');
                const nextBtn = document.getElementById('nextBtn');
                const curIndicator = document.getElementById('currentPageIndicator');

                prevBtn.disabled = (this.currentSheetIndex === 0);
                nextBtn.disabled = (this.currentSheetIndex === this.sheets.length - 1);

                // Set page text (e.g. "1" or "2-3")
                if (this.currentSheetIndex === 0) {
                    curIndicator.textContent = "1";
                } else {
                    const lastPageVal = Math.min(currentRightPage, this.numPages);
                    curIndicator.textContent = `${currentLeftPage}-${lastPageVal}`;
                }
            }
        }

        // Initialize PDF Document
        function initPdfReader() {
            const overlay = document.getElementById('loadingOverlay');
            const progress = document.getElementById('loadingProgress');

            progress.textContent = "Mengunduh file PDF...";

            pdfjsLib.getDocument(pdfUrl).promise.then(pdf => {
                pdfDoc = pdf;
                totalPages = pdf.numPages;
                
                document.getElementById('totalPagesIndicator').textContent = totalPages;
                overlay.style.opacity = '0';
                setTimeout(() => overlay.remove(), 500);

                // Build Book flip layout
                bookFlip = new Book3D(document.getElementById('book'), totalPages);
                
                // Scale container to fit screen size
                adjustBookScale();
            }).catch(err => {
                console.error("Gagal membuka dokumen PDF:", err);
                progress.innerHTML = '<span style="color:#ef4444;font-weight:600;">Gagal memuat dokumen. Pastikan file PDF valid.</span>';
            });
        }

        // Book scaling handler to fit viewport sizes nicely
        function adjustBookScale() {
            const book = document.getElementById('book');
            if (!book) return;
            const viewport = document.getElementById('viewport');
            
            const padding = 60;
            const vw = viewport.clientWidth - padding;
            const vh = viewport.clientHeight - padding;
            
            const naturalWidth = 900; // double page width
            const naturalHeight = 600; // page height
            
            let scale = Math.min(vw / naturalWidth, vh / naturalHeight);
            if (scale > 1.4) scale = 1.4; // cap upscale
            
            currentScale = scale;
            book.style.transform = `scale(${currentScale})`;
        }

        function zoomBook(factor) {
            const book = document.getElementById('book');
            if (!book) return;
            currentScale *= factor;
            
            // Limit scale between 0.3x and 2.5x
            currentScale = Math.max(0.3, Math.min(2.5, currentScale));
            book.style.transform = `scale(${currentScale})`;
        }

        // Navigation keyboard arrows
        document.addEventListener('keydown', (e) => {
            if (!bookFlip) return;
            if (e.key === 'ArrowRight' || e.key === ' ') {
                bookFlip.next();
            } else if (e.key === 'ArrowLeft') {
                bookFlip.prev();
            }
        });

        // Fullscreen Mode Handler
        function toggleFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(err => {
                    console.error(`Gagal masuk mode layar penuh: ${err.message}`);
                });
            } else {
                document.exitFullscreen();
            }
        }

        // Handle browser window resize
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(adjustBookScale, 100);
        });

        function closeReader() {
            window.location.href = '<?= BASE_URL ?>/detail.php?id=<?= $id ?>';
        }

        // Run initializer on load
        window.addEventListener('DOMContentLoaded', initPdfReader);
    </script>
</body>
</html>

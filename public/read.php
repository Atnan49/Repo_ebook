<?php
/**
 * ============================================================
 * READ EBOOK - 3D Page Flip Reader & Secure Streaming
 * ============================================================
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/storage.php';

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

    if (!StorageHelper::isSupabaseEnabled()) {
        $filePath = PDF_STORAGE . '/' . $book['pdf_file'];
        if (!file_exists($filePath)) {
            die("File PDF tidak ditemukan di server.");
        }
    }

    // Mode 1: PDF Binary Streaming (for PDF.js consumer)
    if (isset($_GET['stream']) && $_GET['stream'] == 1) {
        // Update download/read count (only count once per session)
        if (!isset($_SESSION['read_' . $id]) && !isAdmin()) {
            $db->prepare("UPDATE ebooks SET downloads = downloads + 1 WHERE id = :id")->execute([':id' => $id]);
            $_SESSION['read_' . $id] = true;
        }

        header('Content-Disposition: inline; filename="' . str_replace('"', '', basename($book['title'])) . '.pdf"');
        header('Content-Transfer-Encoding: binary');
        header('Accept-Ranges: bytes');
        StorageHelper::streamPdf($book['pdf_file']);
        exit;
    }

} catch (PDOException $e) {
    die("Terjadi kesalahan sistem saat memuat ebook.");
}

// Mode 2: Responsive Flat PDF Reader HTML Page
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
            background: #0f172a;
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
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            z-index: 100;
            flex-shrink: 0;
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
            border-color: rgba(255, 255, 255, 0.2);
        }

        /* Viewport */
        .viewport {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: auto; /* allow scroll when zoomed */
            padding: 20px;
            background: #090d16;
            -webkit-overflow-scrolling: touch; /* smooth touch scrolling for iOS */
        }

        /* Page Container (Flat & Simple) */
        .page-container {
            position: relative;
            background-color: #f8f6f0; /* Warm paper tone */
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4), 0 1px 8px rgba(0, 0, 0, 0.2);
            border-radius: 4px;
            transition: transform 0.2s cubic-bezier(0.2, 0.8, 0.2, 1), opacity 0.2s ease;
            transform-origin: center center;
            display: flex;
            justify-content: center;
            align-items: center;
            max-width: 100%;
            max-height: 100%;
        }

        .page-container.transitioning {
            opacity: 0;
            transform: scale(0.98);
        }

        canvas {
            display: block;
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            border-radius: 4px;
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
            transition: opacity 0.3s ease;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(255, 255, 255, 0.1);
            border-top: 3px solid #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 16px;
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

        /* Navigation Buttons at side of viewport on Desktop */
        .nav-btn-side {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #f1f5f9;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            z-index: 80;
        }

        .nav-btn-side:hover:not(:disabled) {
            background: rgba(59, 130, 246, 0.8);
            border-color: #3b82f6;
            color: white;
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.4);
        }

        .nav-btn-side:disabled {
            opacity: 0.2;
            cursor: not-allowed;
        }

        .nav-btn-prev {
            left: 20px;
        }

        .nav-btn-next {
            right: 20px;
        }

        /* Swipe Help Hint */
        .swipe-hint {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid rgba(255,255,255,0.1);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            color: #cbd5e1;
            pointer-events: none;
            z-index: 90;
            animation: fadeOut 6s forwards;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        @keyframes fadeOut {
            0% { opacity: 0; transform: translate(-50%, 10px); }
            10% { opacity: 1; transform: translate(-50%, 0); }
            85% { opacity: 1; }
            100% { opacity: 0; transform: translate(-50%, -10px); visibility: hidden; }
        }

        /* Bottom Control Panel */
        .controls-panel {
            height: 70px;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(12px);
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            z-index: 100;
            flex-shrink: 0;
        }

        .control-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-control {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.08);
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
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.2);
            color: #fff;
        }

        .btn-control:disabled {
            opacity: 0.2;
            cursor: not-allowed;
        }

        /* Interactive Range Slider (Scrubber) */
        .scrubber-container {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
            max-width: 400px;
            margin: 0 15px;
        }

        .page-scrubber {
            -webkit-appearance: none;
            appearance: none;
            width: 100%;
            height: 6px;
            border-radius: 3px;
            background: rgba(255, 255, 255, 0.15);
            outline: none;
            cursor: pointer;
            transition: background 0.2s;
        }

        .page-scrubber:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        .page-scrubber::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #3b82f6;
            border: 2px solid #ffffff;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
            cursor: pointer;
            transition: transform 0.1s;
        }

        .page-scrubber::-webkit-slider-thumb:hover {
            transform: scale(1.2);
        }

        .page-scrubber::-moz-range-thumb {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #3b82f6;
            border: 2px solid #ffffff;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
            cursor: pointer;
            transition: transform 0.1s;
        }

        .page-scrubber::-moz-range-thumb:hover {
            transform: scale(1.2);
        }

        .scrubber-tooltip {
            position: absolute;
            bottom: 35px;
            left: 50%;
            transform: translateX(-50%) scale(0.9);
            background: #3b82f6;
            color: #fff;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            pointer-events: none;
            opacity: 0;
            transition: transform 0.15s, opacity 0.15s;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            z-index: 120;
            white-space: nowrap;
        }

        .scrubber-tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border-width: 5px;
            border-style: solid;
            border-color: #3b82f6 transparent transparent transparent;
        }

        .scrubber-container:hover .scrubber-tooltip,
        .scrubber-container:focus-within .scrubber-tooltip,
        .scrubber-container.dragging .scrubber-tooltip {
            opacity: 1;
            transform: translateX(-50%) scale(1);
        }

        .page-indicator {
            font-size: 14px;
            font-weight: 500;
            color: #94a3b8;
            min-width: 90px;
            text-align: center;
        }

        .page-indicator strong {
            color: #f1f5f9;
        }

        /* Fullscreen styles */
        body:fullscreen {
            background: #090d16;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .nav-btn-side {
                display: none; /* hide side arrows on mobile/tablet, use swipes & bottom controls */
            }

            .scrubber-container {
                max-width: 150px;
            }

            .top-bar .book-title {
                font-size: 14px;
            }

            .viewport {
                padding: 10px;
            }

            .controls-panel {
                padding: 0 10px;
                gap: 5px;
            }

            .control-group {
                gap: 6px;
            }
            
            .btn-control {
                width: 36px;
                height: 36px;
            }
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
        <!-- Left Side Arrow Button (Desktop only) -->
        <button class="nav-btn-side nav-btn-prev" id="prevBtnSide" onclick="flatReader.prev()" title="Halaman Sebelumnya">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
        </button>

        <!-- Right Side Arrow Button (Desktop only) -->
        <button class="nav-btn-side nav-btn-next" id="nextBtnSide" onclick="flatReader.next()" title="Halaman Selanjutnya">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </button>

        <!-- Initial loading state -->
        <div class="loading-overlay" id="loadingOverlay">
            <div class="spinner"></div>
            <h3>Memuat Dokumen...</h3>
            <div class="loading-progress" id="loadingProgress">Menghubungkan ke penyimpanan...</div>
        </div>

        <!-- Flat Page Viewer Layout -->
        <div class="page-container" id="pageContainer">
            <canvas id="pageCanvas"></canvas>
        </div>

        <!-- Floating Swipe Help Hint -->
        <div class="swipe-hint">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="21" y1="12" x2="9" y2="12"></line><polyline points="15 18 9 12 15 6"></polyline></svg>
            <span>Geser halaman atau gunakan tombol panah keyboard</span>
        </div>
    </main>

    <!-- Bottom Control Panel -->
    <footer class="controls-panel" id="controlsPanel">
        <div class="control-group">
            <button class="btn-control" id="prevBtn" onclick="flatReader.prev()" title="Halaman Sebelumnya">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
            </button>
        </div>

        <!-- Scrubber Range Bar -->
        <div class="scrubber-container" id="scrubberContainer">
            <input type="range" id="pageScrubber" min="1" max="1" value="1" class="page-scrubber" title="Geser untuk mencari halaman">
            <div class="scrubber-tooltip" id="scrubberTooltip">Halaman 1</div>
        </div>

        <div class="control-group">
            <div class="page-indicator">
                Halaman <strong id="currentPageIndicator">1</strong> dari <strong id="totalPagesIndicator">-</strong>
            </div>
            <button class="btn-control" id="nextBtn" onclick="flatReader.next()" title="Halaman Selanjutnya">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>
        </div>

        <div style="width: 2px; height: 30px; background: rgba(255,255,255,0.1);"></div>

        <div class="control-group">
            <button class="btn-control" id="zoomOutBtn" onclick="zoomBook(0.8)" title="Perkecil">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            </button>
            <button class="btn-control" id="zoomInBtn" onclick="zoomBook(1.2)" title="Perbesar">
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
        let flatReader = null;

        // Flat Single-Page Reader Controller
        class FlatReader {
            constructor(containerEl, canvasEl, totalPages) {
                this.container = containerEl;
                this.canvas = canvasEl;
                this.ctx = this.canvas.getContext('2d');
                this.totalPages = totalPages;
                this.currentPage = 1;
                this.zoomLevel = 1.0;
                this.renderTask = null;
                this.isRendering = false;

                this.initGestures();
                this.jumpToPage(1);
            }

            renderPage(pageNum) {
                if (pageNum < 1 || pageNum > this.totalPages) return;
                
                // Prevent concurrent rendering tasks on same canvas
                if (this.renderTask) {
                    this.renderTask.cancel();
                    this.renderTask = null;
                }

                this.isRendering = true;
                this.currentPage = pageNum;
                this.container.classList.add('transitioning');

                pdfDoc.getPage(pageNum).then(page => {
                    const viewport = page.getViewport({ scale: 1 });
                    
                    // Fit inside viewport (with padding)
                    const padding = window.innerWidth <= 768 ? 20 : 60;
                    const vw = document.getElementById('viewport').clientWidth - padding;
                    const vh = document.getElementById('viewport').clientHeight - padding;
                    
                    const scaleWidth = vw / viewport.width;
                    const scaleHeight = vh / viewport.height;
                    const fitScale = Math.min(scaleWidth, scaleHeight);
                    
                    // Dynamic styling size adjusted by zoom level
                    const visualWidth = viewport.width * fitScale * this.zoomLevel;
                    const visualHeight = viewport.height * fitScale * this.zoomLevel;
                    
                    this.container.style.width = `${visualWidth}px`;
                    this.container.style.height = `${visualHeight}px`;

                    // Rendering resolution scales with high-dpi ratio and zoom factor for crisp text
                    const renderScale = fitScale * this.zoomLevel * Math.min(window.devicePixelRatio || 1, 2.0);
                    const scaledViewport = page.getViewport({ scale: renderScale });

                    this.canvas.width = scaledViewport.width;
                    this.canvas.height = scaledViewport.height;

                    const renderContext = {
                        canvasContext: this.ctx,
                        viewport: scaledViewport
                    };

                    this.renderTask = page.render(renderContext);
                    this.renderTask.promise.then(() => {
                        this.renderTask = null;
                        this.isRendering = false;
                        
                        setTimeout(() => {
                            this.container.classList.remove('transitioning');
                        }, 50);

                        this.updateUI();
                    }).catch(err => {
                        if (err.name === 'RenderingCancelledException') {
                            return;
                        }
                        console.error('Error rendering page:', err);
                        this.isRendering = false;
                        this.container.classList.remove('transitioning');
                    });
                }).catch(err => {
                    console.error('Error loading page:', err);
                    this.isRendering = false;
                    this.container.classList.remove('transitioning');
                });
            }

            next() {
                if (this.currentPage >= this.totalPages || this.isRendering) return;
                this.renderPage(this.currentPage + 1);
            }

            prev() {
                if (this.currentPage <= 1 || this.isRendering) return;
                this.renderPage(this.currentPage - 1);
            }

            jumpToPage(pageNum) {
                if (pageNum < 1 || pageNum > this.totalPages) return;
                this.renderPage(pageNum);
            }

            zoom(factor) {
                const oldZoom = this.zoomLevel;
                // Clamp zoom between 0.5x and 3.0x
                this.zoomLevel = Math.max(0.5, Math.min(3.0, this.zoomLevel * factor));
                if (oldZoom !== this.zoomLevel) {
                    this.renderPage(this.currentPage);
                }
            }

            updateUI() {
                const prevBtn = document.getElementById('prevBtn');
                const nextBtn = document.getElementById('nextBtn');
                const prevBtnSide = document.getElementById('prevBtnSide');
                const nextBtnSide = document.getElementById('nextBtnSide');
                const curIndicator = document.getElementById('currentPageIndicator');
                const scrubber = document.getElementById('pageScrubber');
                const tooltip = document.getElementById('scrubberTooltip');

                const hasPrev = this.currentPage > 1;
                const hasNext = this.currentPage < this.totalPages;

                if (prevBtn) prevBtn.disabled = !hasPrev;
                if (nextBtn) nextBtn.disabled = !hasNext;
                if (prevBtnSide) prevBtnSide.disabled = !hasPrev;
                if (nextBtnSide) nextBtnSide.disabled = !hasNext;

                if (curIndicator) curIndicator.textContent = this.currentPage;

                if (scrubber) {
                    scrubber.value = this.currentPage;
                    if (tooltip) {
                        tooltip.textContent = `Halaman ${this.currentPage}`;
                        const pct = (this.currentPage - scrubber.min) / (scrubber.max - scrubber.min || 1);
                        tooltip.style.left = `calc(${pct * 100}% - ${(pct - 0.5) * 16}px)`;
                    }
                }
            }

            initGestures() {
                let startX = 0;
                let startY = 0;
                const threshold = 50; // min swipe distance in px
                const angleThreshold = 30; // max angle in degrees for horizontal swipe filter

                const viewport = document.getElementById('viewport');

                viewport.addEventListener('touchstart', (e) => {
                    if (e.touches.length === 1) {
                        startX = e.touches[0].clientX;
                        startY = e.touches[0].clientY;
                    }
                }, { passive: true });

                viewport.addEventListener('touchend', (e) => {
                    if (e.changedTouches.length === 1) {
                        const diffX = e.changedTouches[0].clientX - startX;
                        const diffY = e.changedTouches[0].clientY - startY;
                        const angle = Math.abs(Math.atan2(diffY, diffX) * 180 / Math.PI);

                        // Only allow swipe transitions when at 1.0x scale or lower to prevent gesture conflict with zoom-panning
                        if (this.zoomLevel <= 1.0 && Math.abs(diffX) > threshold && (angle < angleThreshold || angle > 180 - angleThreshold)) {
                            if (diffX > 0) {
                                this.prev();
                            } else {
                                this.next();
                            }
                        }
                    }
                }, { passive: true });
            }
        }

        // Initialize PDF Document
        function initPdfReader() {
            const overlay = document.getElementById('loadingOverlay');
            const progress = document.getElementById('loadingProgress');
            const scrubber = document.getElementById('pageScrubber');
            const tooltip = document.getElementById('scrubberTooltip');
            const scrubberContainer = document.getElementById('scrubberContainer');

            progress.textContent = "Mengunduh file PDF...";

            pdfjsLib.getDocument(pdfUrl).promise.then(pdf => {
                pdfDoc = pdf;
                totalPages = pdf.numPages;
                
                document.getElementById('totalPagesIndicator').textContent = totalPages;
                
                if (scrubber) {
                    scrubber.max = totalPages;
                    scrubber.min = 1;
                    scrubber.value = 1;
                }

                overlay.style.opacity = '0';
                setTimeout(() => {
                    overlay.remove();
                }, 300);

                // Build Flat Reader
                flatReader = new FlatReader(
                    document.getElementById('pageContainer'),
                    document.getElementById('pageCanvas'),
                    totalPages
                );

                // Setup Scrubber event handlers
                if (scrubber && tooltip && scrubberContainer) {
                    scrubber.addEventListener('input', () => {
                        scrubberContainer.classList.add('dragging');
                        const val = parseInt(scrubber.value);
                        tooltip.textContent = `Halaman ${val}`;
                        
                        const pct = (scrubber.value - scrubber.min) / (scrubber.max - scrubber.min || 1);
                        tooltip.style.left = `calc(${pct * 100}% - ${(pct - 0.5) * 16}px)`;
                    });

                    scrubber.addEventListener('change', () => {
                        scrubberContainer.classList.remove('dragging');
                        if (flatReader) {
                            flatReader.jumpToPage(parseInt(scrubber.value));
                        }
                    });
                }

            }).catch(err => {
                console.error("Gagal membuka dokumen PDF:", err);
                progress.innerHTML = '<span style="color:#ef4444;font-weight:600;">Gagal memuat dokumen. Pastikan file PDF valid.</span>';
            });
        }

        function zoomBook(factor) {
            if (flatReader) {
                flatReader.zoom(factor);
            }
        }

        // Navigation keyboard arrows
        document.addEventListener('keydown', (e) => {
            if (!flatReader) return;
            if (e.key === 'ArrowRight' || e.key === ' ') {
                flatReader.next();
            } else if (e.key === 'ArrowLeft') {
                flatReader.prev();
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
            resizeTimer = setTimeout(() => {
                if (flatReader) {
                    flatReader.renderPage(flatReader.currentPage);
                }
            }, 150);
        });

        function closeReader() {
            window.location.href = '<?= BASE_URL ?>/detail.php?id=<?= $id ?>';
        }

        // Run initializer on load
        window.addEventListener('DOMContentLoaded', initPdfReader);
    </script>
</body>
</html>

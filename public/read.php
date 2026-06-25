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
            transition: cursor 0.3s;
        }

        body.hud-hidden {
            cursor: none;
        }

        /* Top Bar */
        .top-bar {
            height: 60px;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            z-index: 100;
            transition: transform 0.4s cubic-bezier(0.2, 1, 0.3, 1), opacity 0.4s;
        }

        body.hud-hidden .top-bar {
            transform: translateY(-100%);
            opacity: 0;
            pointer-events: none;
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
            perspective: 2500px;
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
            transition: transform 0.8s cubic-bezier(0.2, 1, 0.3, 1);
            transform-origin: center center;
        }

        /* Visual paper thickness stack mimicking physical book pages depth */
        .book-container::before, .book-container::after {
            content: '';
            position: absolute;
            top: 4px;
            bottom: 4px;
            width: 8px;
            background: #e2e8f0;
            border: 1px solid #cbd5e1;
            z-index: -1;
            transition: transform 0.4s cubic-bezier(0.2, 1, 0.3, 1);
            pointer-events: none;
        }

        /* Left paper stack thickness */
        .book-container::before {
            left: -8px;
            border-radius: 4px 0 0 4px;
            box-shadow: -2px 5px 12px rgba(0,0,0,0.3), inset -2px 0 4px rgba(0,0,0,0.15);
            transform: scaleX(var(--left-thickness-scale, 0.5));
            transform-origin: right center;
        }

        /* Right paper stack thickness */
        .book-container::after {
            right: -8px;
            border-radius: 0 4px 4px 0;
            box-shadow: 2px 5px 12px rgba(0,0,0,0.3), inset 2px 0 4px rgba(0,0,0,0.15);
            transform: scaleX(var(--right-thickness-scale, 0.5));
            transform-origin: left center;
        }

        /* Single Page Mobile Mode overrides */
        .book-container.single-page-mode {
            width: 450px; /* single page width */
        }

        .book-container.single-page-mode::before,
        .book-container.single-page-mode::after {
            display: none;
        }

        .book-container.single-page-mode .sheet {
            width: 100%;
            left: 0;
            right: auto;
        }

        .book-container.single-page-mode .page-face.back {
            display: none;
        }

        .book-container.single-page-mode .page-face.front {
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.25);
        }

        .book-container.single-page-mode .page-face.front::after {
            left: 0;
            background: linear-gradient(to right, rgba(0,0,0,0.12) 0%, rgba(0,0,0,0) 100%);
        }

        /* Background Book Shadow / Thickness Depth */
        .book-depth-shadow {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            background: rgba(0, 0, 0, 0.4);
            filter: blur(30px);
            border-radius: 12px;
            transform: translate3d(0, 20px, -60px);
            pointer-events: none;
        }

        /* Spine Fold (Center crease shadow ganda) */
        .book-spine-line {
            position: absolute;
            width: 12px;
            height: 100%;
            left: 50%;
            top: 0;
            transform: translateX(-50%) translateZ(2px);
            background: linear-gradient(to right, 
                rgba(0, 0, 0, 0) 0%, 
                rgba(0, 0, 0, 0.15) 20%, 
                rgba(0, 0, 0, 0.4) 45%, 
                rgba(0, 0, 0, 0.55) 50%, 
                rgba(0, 0, 0, 0.4) 55%, 
                rgba(0, 0, 0, 0.15) 80%, 
                rgba(0, 0, 0, 0) 100%
            );
            z-index: 45;
            pointer-events: none;
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
            transition: transform 0.8s cubic-bezier(0.2, 1, 0.3, 1);
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

        /* Google Play Books 3D Page Turn Keyframes with realistic paper bending/lifting and Z-tilt */
        @keyframes flip-forward {
            0% {
                transform: rotateY(0deg) translateZ(0px) skewY(0deg) rotateZ(0deg) scale(1);
            }
            30% {
                transform: rotateY(-60deg) translateZ(100px) skewY(-5deg) rotateZ(-4deg) scale(0.96);
            }
            60% {
                transform: rotateY(-120deg) translateZ(100px) skewY(-3deg) rotateZ(-3deg) scale(0.96);
            }
            100% {
                transform: rotateY(-180deg) translateZ(0px) skewY(0deg) rotateZ(0deg) scale(1);
            }
        }

        @keyframes flip-backward {
            0% {
                transform: rotateY(-180deg) translateZ(0px) skewY(0deg) rotateZ(0deg) scale(1);
            }
            30% {
                transform: rotateY(-120deg) translateZ(100px) skewY(5deg) rotateZ(4deg) scale(0.96);
            }
            60% {
                transform: rotateY(-60deg) translateZ(100px) skewY(3deg) rotateZ(3deg) scale(0.96);
            }
            100% {
                transform: rotateY(0deg) translateZ(0px) skewY(0deg) rotateZ(0deg) scale(1);
            }
        }

        .sheet.flipping-forward {
            animation: flip-forward 0.8s cubic-bezier(0.15, 0.85, 0.35, 1) forwards;
        }

        .sheet.flipping-backward {
            animation: flip-backward 0.8s cubic-bezier(0.15, 0.85, 0.35, 1) forwards;
        }

        /* Cast shadows on underlying sheets during transition */
        @keyframes cast-shadow-right-uncover {
            0% { opacity: 0.75; background: linear-gradient(to right, rgba(0,0,0,0.5) 0%, rgba(0,0,0,0.2) 20%, rgba(0,0,0,0) 65%); }
            40% { opacity: 0.45; background: linear-gradient(to right, rgba(0,0,0,0.3) 0%, rgba(0,0,0,0.15) 30%, rgba(0,0,0,0) 80%); }
            100% { opacity: 0; }
        }

        @keyframes cast-shadow-left-cover {
            0% { opacity: 0; }
            40% { opacity: 0.35; background: linear-gradient(to left, rgba(0,0,0,0.18) 0%, rgba(0,0,0,0.06) 50%, rgba(0,0,0,0) 100%); }
            100% { opacity: 0.75; background: linear-gradient(to left, rgba(0,0,0,0.4) 0%, rgba(0,0,0,0.15) 25%, rgba(0,0,0,0) 70%); }
        }

        @keyframes cast-shadow-left-uncover {
            0% { opacity: 0.75; background: linear-gradient(to left, rgba(0,0,0,0.5) 0%, rgba(0,0,0,0.2) 20%, rgba(0,0,0,0) 65%); }
            40% { opacity: 0.45; background: linear-gradient(to left, rgba(0,0,0,0.3) 0%, rgba(0,0,0,0.15) 30%, rgba(0,0,0,0) 80%); }
            100% { opacity: 0; }
        }

        @keyframes cast-shadow-right-cover {
            0% { opacity: 0; }
            40% { opacity: 0.35; background: linear-gradient(to right, rgba(0,0,0,0.18) 0%, rgba(0,0,0,0.06) 50%, rgba(0,0,0,0) 100%); }
            100% { opacity: 0.75; background: linear-gradient(to right, rgba(0,0,0,0.4) 0%, rgba(0,0,0,0.15) 25%, rgba(0,0,0,0) 70%); }
        }

        .cast-shadow-forward-right .page-face.front .page-shadow {
            animation: cast-shadow-right-uncover 0.8s cubic-bezier(0.15, 0.85, 0.35, 1) forwards;
        }

        .cast-shadow-forward-left .page-face.back .page-shadow {
            animation: cast-shadow-left-cover 0.8s cubic-bezier(0.15, 0.85, 0.35, 1) forwards;
        }

        .cast-shadow-backward-left .page-face.back .page-shadow {
            animation: cast-shadow-left-uncover 0.8s cubic-bezier(0.15, 0.85, 0.35, 1) forwards;
        }

        .cast-shadow-backward-right .page-face.front .page-shadow {
            animation: cast-shadow-right-cover 0.8s cubic-bezier(0.15, 0.85, 0.35, 1) forwards;
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

        /* Dynamic Paper Lighting/Highlight Overlays */
        .page-gradient-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 18;
            pointer-events: none;
            opacity: 0;
        }

        @keyframes gradient-forward-front {
            0% { opacity: 0; background: linear-gradient(to right, rgba(0,0,0,0) 0%, rgba(0,0,0,0) 100%); }
            30% { opacity: 0.35; background: linear-gradient(to right, rgba(0,0,0,0.15) 0%, rgba(255,255,255,0.12) 50%, rgba(0,0,0,0.25) 100%); }
            70% { opacity: 0.2; background: linear-gradient(to left, rgba(0,0,0,0.2) 0%, rgba(255,255,255,0.08) 50%, rgba(0,0,0,0.08) 100%); }
            100% { opacity: 0; }
        }

        @keyframes gradient-forward-back {
            0% { opacity: 0; }
            30% { opacity: 0.2; background: linear-gradient(to right, rgba(0,0,0,0.2) 0%, rgba(255,255,255,0.08) 50%, rgba(0,0,0,0.08) 100%); }
            70% { opacity: 0.35; background: linear-gradient(to left, rgba(0,0,0,0.15) 0%, rgba(255,255,255,0.12) 50%, rgba(0,0,0,0.25) 100%); }
            100% { opacity: 0; }
        }

        @keyframes gradient-backward-front {
            0% { opacity: 0; }
            30% { opacity: 0.2; background: linear-gradient(to left, rgba(0,0,0,0.2) 0%, rgba(255,255,255,0.08) 50%, rgba(0,0,0,0.08) 100%); }
            70% { opacity: 0.35; background: linear-gradient(to right, rgba(0,0,0,0.15) 0%, rgba(255,255,255,0.12) 50%, rgba(0,0,0,0.25) 100%); }
            100% { opacity: 0; }
        }

        @keyframes gradient-backward-back {
            0% { opacity: 0; background: linear-gradient(to left, rgba(0,0,0,0) 0%, rgba(0,0,0,0) 100%); }
            30% { opacity: 0.35; background: linear-gradient(to left, rgba(0,0,0,0.15) 0%, rgba(255,255,255,0.12) 50%, rgba(0,0,0,0.25) 100%); }
            70% { opacity: 0.2; background: linear-gradient(to right, rgba(0,0,0,0.2) 0%, rgba(255,255,255,0.08) 50%, rgba(0,0,0,0.08) 100%); }
            100% { opacity: 0; }
        }

        .sheet.flipping-forward .page-face.front .page-gradient-overlay {
            animation: gradient-forward-front 0.8s ease-in-out forwards;
        }

        .sheet.flipping-forward .page-face.back .page-gradient-overlay {
            animation: gradient-forward-back 0.8s ease-in-out forwards;
        }

        .sheet.flipping-backward .page-face.front .page-gradient-overlay {
            animation: gradient-backward-front 0.8s ease-in-out forwards;
        }

        .sheet.flipping-backward .page-face.back .page-gradient-overlay {
            animation: gradient-backward-back 0.8s ease-in-out forwards;
        }

        /* Page faces (Cream-colored Warm Paper Theme) */
        .page-face {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            backface-visibility: hidden;
            background-color: #f5f2eb; /* Warm paper tone */
            box-shadow: inset 0 0 30px rgba(0, 0, 0, 0.06), 0 2px 5px rgba(0,0,0,0.1);
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
            width: 45px;
            height: 100%;
            z-index: 10;
            pointer-events: none;
        }

        .page-face.front {
            transform: rotateY(0deg);
            z-index: 2;
            border-radius: 0 8px 8px 0;
            box-shadow: 5px 5px 15px rgba(0,0,0,0.15), inset 3px 0 10px rgba(0,0,0,0.04);
        }

        .page-face.front::after {
            left: 0;
            background: linear-gradient(to right, rgba(0,0,0,0.15) 0%, rgba(0,0,0,0) 100%);
        }

        .page-face.back {
            transform: rotateY(180deg);
            z-index: 1;
            border-radius: 8px 0 0 8px;
            box-shadow: -5px 5px 15px rgba(0,0,0,0.15), inset -3px 0 10px rgba(0,0,0,0.04);
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
            transition: transform 0.4s cubic-bezier(0.2, 1, 0.3, 1), opacity 0.4s;
        }

        body.hud-hidden .controls-panel {
            transform: translateY(100%);
            opacity: 0;
            pointer-events: none;
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

        /* Interactive Range Slider (Scrubber) */
        .scrubber-container {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
            max-width: 400px;
        }

        .page-scrubber {
            -webkit-appearance: none;
            width: 100%;
            height: 6px;
            border-radius: 3px;
            background: rgba(255, 255, 255, 0.2);
            outline: none;
            cursor: pointer;
            transition: background 0.2s;
        }

        .page-scrubber:hover {
            background: rgba(255, 255, 255, 0.3);
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
            transform: scale(1.25);
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
            transform: scale(1.25);
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

        @media (max-width: 768px) {
            .scrubber-container {
                max-width: 180px;
            }
            .top-bar .book-title {
                font-size: 14px;
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
    <footer class="controls-panel" id="controlsPanel">
        <div class="control-group">
            <button class="btn-control" id="prevBtn" onclick="bookFlip.prev()" title="Halaman Sebelumnya">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
            </button>
        </div>

        <!-- Google Play Books Scrubber Range Bar -->
        <div class="scrubber-container" id="scrubberContainer">
            <input type="range" id="pageScrubber" min="1" max="1" value="1" class="page-scrubber" title="Geser untuk mencari halaman">
            <div class="scrubber-tooltip" id="scrubberTooltip">Halaman 1</div>
        </div>

        <div class="control-group">
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
                this.sheets = [];
                this.currentSheetIndex = 0; // index of sheet currently in focus

                // Detect single page mode (mobile screen)
                this.isSinglePage = window.innerWidth <= 768;

                this.initLayout();
                this.updateZIndices();
                this.updateButtons();
            }

            initLayout() {
                // Clear initial book contents except shadows
                const shadows = this.container.querySelectorAll('.book-depth-shadow, .book-spine-line');
                this.container.innerHTML = '';
                shadows.forEach(el => this.container.appendChild(el));

                this.sheets = [];

                if (this.isSinglePage) {
                    // Single Page Mode: 1 page per sheet
                    this.container.classList.add('single-page-mode');
                    
                    const spine = this.container.querySelector('.book-spine-line');
                    if (spine) spine.style.display = 'none';

                    for (let p = 1; p <= this.numPages; p++) {
                        this.createSingleSheet(p - 1, p);
                    }
                } else {
                    // Double Page Mode
                    this.container.classList.remove('single-page-mode');
                    
                    const spine = this.container.querySelector('.book-spine-line');
                    if (spine) spine.style.display = 'block';

                    // Sheet 0 (Cover Sheet)
                    this.createSheet(0, null, 1);

                    // Sheets 1 to M
                    let sheetIdx = 1;
                    for (let p = 2; p <= this.numPages; p += 2) {
                        const leftPage = p;
                        const rightPage = (p + 1 <= this.numPages) ? p + 1 : null;
                        this.createSheet(sheetIdx, leftPage, rightPage);
                        sheetIdx++;
                    }
                }

                // Add active state to current sheet
                if (this.sheets.length > 0) {
                    this.sheets[this.currentSheetIndex].classList.add('active');
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

                    // Add lighting overlay
                    const gradient = document.createElement('div');
                    gradient.className = 'page-gradient-overlay';
                    backFace.appendChild(gradient);
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

                    // Add lighting overlay
                    const gradient = document.createElement('div');
                    gradient.className = 'page-gradient-overlay';
                    frontFace.appendChild(gradient);
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

            createSingleSheet(index, pageNum) {
                const sheetEl = document.createElement('div');
                sheetEl.className = 'sheet';
                sheetEl.id = `sheet-${index}`;

                // In single page mode, back face is hidden via CSS
                const backFace = document.createElement('div');
                backFace.className = 'page-face back blank-face';

                const frontFace = document.createElement('div');
                frontFace.className = 'page-face front';
                frontFace.classList.add('loading-page');
                const canvas = document.createElement('canvas');
                canvas.id = `page-canvas-${pageNum}`;
                frontFace.appendChild(canvas);

                const shadow = document.createElement('div');
                shadow.className = 'page-shadow';
                frontFace.appendChild(shadow);

                const gradient = document.createElement('div');
                gradient.className = 'page-gradient-overlay';
                frontFace.appendChild(gradient);

                sheetEl.appendChild(backFace);
                sheetEl.appendChild(frontFace);
                this.container.appendChild(sheetEl);
                this.sheets.push(sheetEl);

                // Trigger lazy render for initially visible page
                if (index === 0) {
                    this.lazyRenderPage(pageNum);
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

            preloadVicinity() {
                if (this.isSinglePage) {
                    const current = this.currentSheetIndex + 1; // page number (1-based)
                    this.lazyRenderPage(current);
                    this.lazyRenderPage(current + 1);
                    this.lazyRenderPage(current + 2);
                    this.lazyRenderPage(current - 1);
                    this.lazyRenderPage(current - 2);
                } else {
                    const currentRightPage = 2 * this.currentSheetIndex + 1;
                    const currentLeftPage = currentRightPage - 1;

                    this.lazyRenderPage(currentLeftPage);
                    this.lazyRenderPage(currentRightPage);

                    this.lazyRenderPage(currentRightPage + 1);
                    this.lazyRenderPage(currentRightPage + 2);
                    this.lazyRenderPage(currentLeftPage - 1);
                    this.lazyRenderPage(currentLeftPage - 2);
                }
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

                // Cast shadow on the sheet underneath on the right (being uncovered)
                const nextSheet = this.sheets[this.currentSheetIndex + 1];
                if (nextSheet) {
                    nextSheet.classList.add('cast-shadow-forward-right');
                }
                // Cast shadow on the sheet underneath on the left (being covered)
                const prevCoveredSheet = this.sheets[this.currentSheetIndex - 1];
                if (prevCoveredSheet) {
                    prevCoveredSheet.classList.add('cast-shadow-forward-left');
                }

                this.currentSheetIndex++;
                this.preloadVicinity();
                updateBookTransform(); // Slide book immediately

                setTimeout(() => {
                    sheet.classList.remove('flipping-forward');
                    if (nextSheet) {
                        nextSheet.classList.remove('cast-shadow-forward-right');
                    }
                    if (prevCoveredSheet) {
                        prevCoveredSheet.classList.remove('cast-shadow-forward-left');
                    }
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

                // Cast shadow on the sheet underneath on the left (being uncovered)
                const prevSheet = this.sheets[this.currentSheetIndex - 1];
                if (prevSheet) {
                    prevSheet.classList.add('cast-shadow-backward-left');
                }
                // Cast shadow on the sheet underneath on the right (being covered)
                const nextCoveredSheet = this.sheets[this.currentSheetIndex + 1];
                if (nextCoveredSheet) {
                    nextCoveredSheet.classList.add('cast-shadow-backward-right');
                }

                this.preloadVicinity();
                updateBookTransform(); // Slide book immediately

                setTimeout(() => {
                    sheet.classList.remove('flipping-backward');
                    if (prevSheet) {
                        prevSheet.classList.remove('cast-shadow-backward-left');
                    }
                    if (nextCoveredSheet) {
                        nextCoveredSheet.classList.remove('cast-shadow-backward-right');
                    }
                    this.updateZIndices();
                    this.updateButtons();
                }, 800);
            }

            updateButtons() {
                const prevBtn = document.getElementById('prevBtn');
                const nextBtn = document.getElementById('nextBtn');
                const curIndicator = document.getElementById('currentPageIndicator');
                const scrubber = document.getElementById('pageScrubber');
                const tooltip = document.getElementById('scrubberTooltip');

                prevBtn.disabled = (this.currentSheetIndex === 0);
                nextBtn.disabled = (this.currentSheetIndex === this.sheets.length - 1);

                let activePage = 1;
                if (this.isSinglePage) {
                    activePage = this.currentSheetIndex + 1;
                    curIndicator.textContent = `${activePage}`;
                } else {
                    const currentRightPage = 2 * this.currentSheetIndex + 1;
                    const currentLeftPage = currentRightPage - 1;

                    if (this.currentSheetIndex === 0) {
                        activePage = 1;
                        curIndicator.textContent = "1";
                    } else {
                        const lastPageVal = Math.min(currentRightPage, this.numPages);
                        activePage = currentLeftPage;
                        curIndicator.textContent = `${currentLeftPage}-${lastPageVal}`;
                    }
                }

                // Update Scrubber range slider value and tooltip
                if (scrubber) {
                    scrubber.value = activePage;
                    if (tooltip) {
                        tooltip.textContent = `Halaman ${activePage}`;
                        const pct = (activePage - scrubber.min) / (scrubber.max - scrubber.min || 1);
                        tooltip.style.left = `calc(${pct * 100}% - ${(pct - 0.5) * 16}px)`;
                    }
                }

                // Update physical page thickness stack dynamically based on progress
                const total = this.sheets.length;
                const leftScale = total > 1 ? (this.currentSheetIndex / (total - 1)) : 0.5;
                const rightScale = total > 1 ? (1 - (this.currentSheetIndex / (total - 1))) : 0.5;
                this.container.style.setProperty('--left-thickness-scale', leftScale);
                this.container.style.setProperty('--right-thickness-scale', rightScale);
            }

            checkResponsiveMode() {
                const currentlySingle = window.innerWidth <= 768;
                if (currentlySingle !== this.isSinglePage) {
                    this.isSinglePage = currentlySingle;
                    const activePage = this.getActivePageNumber();
                    this.initLayout();
                    this.jumpToPage(activePage);
                }
            }

            getActivePageNumber() {
                if (this.isSinglePage) {
                    return this.currentSheetIndex + 1;
                } else {
                    if (this.currentSheetIndex === 0) return 1;
                    return 2 * this.currentSheetIndex; // return left page of spread
                }
            }

            jumpToPage(pageNum) {
                if (this.isSinglePage) {
                    this.currentSheetIndex = Math.min(Math.max(0, pageNum - 1), this.numPages - 1);
                } else {
                    if (pageNum === 1) {
                        this.currentSheetIndex = 0;
                    } else {
                        this.currentSheetIndex = Math.min(Math.floor(pageNum / 2), this.sheets.length - 1);
                    }
                }
                this.preloadVicinity();
                this.updateZIndices();
                this.updateButtons();
                updateBookTransform();
            }
        }

        // HUD (Heads-Up Display) Auto-hide distraction-free mode
        let hudTimeout;
        function showHUD() {
            document.body.classList.remove('hud-hidden');
            clearTimeout(hudTimeout);
            
            // Only schedule hide if not dragging range slider and spinner overlay is not present
            const isDragging = document.getElementById('scrubberContainer').classList.contains('dragging');
            const isOverlayPresent = document.getElementById('loadingOverlay');
            
            if (!isDragging && !isOverlayPresent) {
                hudTimeout = setTimeout(hideHUD, 3000);
            }
        }

        function hideHUD() {
            const isDragging = document.getElementById('scrubberContainer').classList.contains('dragging');
            if (isDragging) return;
            document.body.classList.add('hud-hidden');
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
                
                // Initialize range scrubber values
                if (scrubber) {
                    scrubber.max = totalPages;
                    scrubber.min = 1;
                    scrubber.value = 1;
                }

                overlay.style.opacity = '0';
                setTimeout(() => {
                    overlay.remove();
                    // Schedule first HUD timeout after load is complete
                    showHUD();
                }, 500);

                // Build Book flip layout
                bookFlip = new Book3D(document.getElementById('book'), totalPages);
                
                // Scale container to fit screen size
                adjustBookScale();

                // Setup Scrubber event handlers
                if (scrubber && tooltip && scrubberContainer) {
                    scrubber.addEventListener('input', () => {
                        scrubberContainer.classList.add('dragging');
                        const val = parseInt(scrubber.value);
                        tooltip.textContent = `Halaman ${val}`;
                        
                        // Centered offset calculation
                        const pct = (scrubber.value - scrubber.min) / (scrubber.max - scrubber.min || 1);
                        tooltip.style.left = `calc(${pct * 100}% - ${(pct - 0.5) * 16}px)`;
                        
                        showHUD(); // Keep HUD open while scrubbing
                    });

                    scrubber.addEventListener('change', () => {
                        scrubberContainer.classList.remove('dragging');
                        if (bookFlip) {
                            bookFlip.jumpToPage(parseInt(scrubber.value));
                        }
                        showHUD();
                    });
                }

            }).catch(err => {
                console.error("Gagal membuka dokumen PDF:", err);
                progress.innerHTML = '<span style="color:#ef4444;font-weight:600;">Gagal memuat dokumen. Pastikan file PDF valid.</span>';
            });

            // Set up HUD Event Listeners on Viewport
            const viewport = document.getElementById('viewport');
            viewport.addEventListener('mousemove', showHUD);
            viewport.addEventListener('click', (e) => {
                // Ignore click toggle if click was on navigators, top bar, or bottom control panels
                if (e.target.closest('.click-zone') || e.target.closest('.controls-panel') || e.target.closest('.top-bar')) {
                    return;
                }
                
                if (document.body.classList.contains('hud-hidden')) {
                    showHUD();
                } else {
                    hideHUD();
                }
            });
        }

        // Book scaling handler to fit viewport sizes nicely
        function adjustBookScale() {
            const viewport = document.getElementById('viewport');
            if (!viewport) return;
            
            const padding = 60;
            const vw = viewport.clientWidth - padding;
            const vh = viewport.clientHeight - padding;
            
            const isSingle = window.innerWidth <= 768;
            const naturalWidth = isSingle ? 450 : 900;
            const naturalHeight = 600; // page height
            
            let scale = Math.min(vw / naturalWidth, vh / naturalHeight);
            if (scale > 1.4) scale = 1.4; // cap upscale
            
            currentScale = scale;
            updateBookTransform();
        }

        function updateBookTransform() {
            const book = document.getElementById('book');
            if (!book) return;
            
            let tx = '0%'; // Default to centered (single page)
            if (bookFlip && !bookFlip.isSinglePage) {
                if (bookFlip.currentSheetIndex === 0) {
                    tx = '-25%'; // Center cover
                } else if (bookFlip.currentSheetIndex === bookFlip.sheets.length - 1) {
                    tx = '25%'; // Center back cover
                } else {
                    tx = '0%'; // Center open book
                }
            }
            
            book.style.transform = `scale(${currentScale}) translateX(${tx})`;
        }

        function zoomBook(factor) {
            currentScale *= factor;
            
            // Limit scale between 0.3x and 2.5x
            currentScale = Math.max(0.3, Math.min(2.5, currentScale));
            updateBookTransform();
            showHUD(); // Keep HUD open during active zoom clicks
        }

        // Navigation keyboard arrows
        document.addEventListener('keydown', (e) => {
            if (!bookFlip) return;
            showHUD(); // Reset timeout on key activities
            if (e.key === 'ArrowRight' || e.key === ' ') {
                bookFlip.next();
            } else if (e.key === 'ArrowLeft') {
                bookFlip.prev();
            }
        });

        // Fullscreen Mode Handler
        function toggleFullscreen() {
            showHUD();
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
                adjustBookScale();
                if (bookFlip) {
                    bookFlip.checkResponsiveMode();
                }
            }, 100);
        });

        function closeReader() {
            window.location.href = '<?= BASE_URL ?>/detail.php?id=<?= $id ?>';
        }

        // Run initializer on load
        window.addEventListener('DOMContentLoaded', initPdfReader);
    </script>
</body>
</html>

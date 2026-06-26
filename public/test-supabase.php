<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/storage.php';

header('Content-Type: text/plain');

try {
    $db = Database::connect();
    $stmt = $db->query("SELECT id, title, pdf_file, status FROM ebooks ORDER BY id DESC LIMIT 5");
    $books = $stmt->fetchAll();
    echo "LATEST BOOKS IN DATABASE:\n";
    foreach ($books as $b) {
        echo "ID: {$b['id']} | Title: {$b['title']} | PDF: {$b['pdf_file']} | Status: {$b['status']}\n";
    }
} catch (Exception $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}

$fileName = $_GET['file'] ?? '';
if (empty($fileName) && !empty($books)) {
    $fileName = $books[0]['pdf_file'];
}

if (!empty($fileName)) {
    echo "\nTesting download for file: $fileName\n";
    
    // Test 1: Authenticated
    $urlAuth = rtrim(SUPABASE_URL, '/') . "/storage/v1/object/authenticated/pdfs/" . $fileName;
    echo "1. Testing Authenticated URL: $urlAuth\n";
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => [
                "Authorization: Bearer " . SUPABASE_KEY
            ],
            'ignore_errors' => true
        ]
    ];
    $context = stream_context_create($opts);
    $content = file_get_contents($urlAuth, false, $context);
    echo "Response Code: " . ($http_response_header[0] ?? 'Unknown') . "\n";
    foreach ($http_response_header as $h) {
        echo "  $h\n";
    }
    echo "Body length: " . strlen($content) . " bytes\n";
    echo "Body snippet: " . substr($content, 0, 200) . "\n\n";

    // Test 2: Public
    $urlPublic = rtrim(SUPABASE_URL, '/') . "/storage/v1/object/public/pdfs/" . $fileName;
    echo "2. Testing Public URL: $urlPublic\n";
    $opts = [
        'http' => [
            'method' => 'GET',
            'ignore_errors' => true
        ]
    ];
    $context = stream_context_create($opts);
    $contentPublic = @file_get_contents($urlPublic, false, $context);
    echo "Response Code: " . ($http_response_header[0] ?? 'Unknown') . "\n";
    foreach ($http_response_header as $h) {
        echo "  $h\n";
    }
    echo "Body length: " . strlen($contentPublic) . " bytes\n";
    echo "Body snippet: " . substr($contentPublic, 0, 200) . "\n\n";

    // Test 3: Authenticated with both apikey and Authorization headers
    $urlAuthBoth = rtrim(SUPABASE_URL, '/') . "/storage/v1/object/authenticated/pdfs/" . $fileName;
    echo "3. Testing Authenticated with apikey + Auth headers URL: $urlAuthBoth\n";
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => [
                "apikey: " . SUPABASE_KEY,
                "Authorization: Bearer " . SUPABASE_KEY
            ],
            'ignore_errors' => true
        ]
    ];
    $context = stream_context_create($opts);
    $contentBoth = file_get_contents($urlAuthBoth, false, $context);
    echo "Response Code: " . ($http_response_header[0] ?? 'Unknown') . "\n";
    foreach ($http_response_header as $h) {
        echo "  $h\n";
    }
    echo "Body length: " . strlen($contentBoth) . " bytes\n";
    echo "Body snippet: " . substr($contentBoth, 0, 200) . "\n\n";
} else {
    echo "\nNo files to test.\n";
}
?>

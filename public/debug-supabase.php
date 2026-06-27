<?php
/**
 * ============================================================
 * DEBUG SUPABASE STORAGE - Diagnostic Script
 * ============================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/storage.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNOSTIC SUPABASE STORAGE ===\n\n";

echo "1. CONFIGURATION CHECK:\n";
echo "SUPABASE_URL: " . (defined('SUPABASE_URL') ? SUPABASE_URL : 'NOT DEFINED') . "\n";
echo "SUPABASE_KEY length: " . (defined('SUPABASE_KEY') ? strlen(SUPABASE_KEY) : '0') . " characters\n";
echo "isSupabaseEnabled: " . (StorageHelper::isSupabaseEnabled() ? 'YES' : 'NO') . "\n\n";

if (!StorageHelper::isSupabaseEnabled()) {
    echo "ERROR: Supabase is not enabled. Please check if SUPABASE_URL and SUPABASE_KEY are set in your environment variables.\n";
    exit;
}

$fileName = $_GET['file'] ?? '';
if (empty($fileName)) {
    echo "ERROR: Please specify a file name to test in the URL, for example: ?file=your-file-name.pdf\n";
    exit;
}

echo "2. TESTING DOWNLOAD FOR FILE: $fileName\n";
$url = rtrim(SUPABASE_URL, '/') . "/storage/v1/object/authenticated/pdfs/" . $fileName;
echo "Target URL: $url\n\n";

echo "Making request with manual redirect handling (same as streamPdf)...\n";

$opts = [
    'http' => [
        'method' => 'GET',
        'header' => [
            "apikey: " . SUPABASE_KEY,
            "Authorization: Bearer " . SUPABASE_KEY
        ],
        'follow_location' => 0,
        'ignore_errors' => true
    ]
];

$context = stream_context_create($opts);
$content = @file_get_contents($url, false, $context);

$statusCode = 0;
$redirectUrl = null;

echo "Initial Response Headers:\n";
if (isset($http_response_header)) {
    foreach ($http_response_header as $header) {
        echo "  $header\n";
        if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/i', $header, $matches)) {
            $statusCode = intval($matches[1]);
        }
        if (preg_match('/^Location:\s*(.+)$/i', $header, $matches)) {
            $redirectUrl = trim($matches[1]);
        }
    }
}

echo "\nInitial Status Code: $statusCode\n";
echo "Redirect URL (Location): " . ($redirectUrl ?: 'NONE') . "\n\n";

if (($statusCode === 301 || $statusCode === 302 || $statusCode === 307) && !empty($redirectUrl)) {
    echo "Following redirect to S3/R2 storage without auth headers...\n";
    
    $optsRedirect = [
        'http' => [
            'method' => 'GET',
            'ignore_errors' => true
        ]
    ];
    $contextRedirect = stream_context_create($optsRedirect);
    $contentRedirect = @file_get_contents($redirectUrl, false, $contextRedirect);
    
    $redirectStatusCode = 0;
    echo "Redirect Response Headers:\n";
    if (isset($http_response_header)) {
        foreach ($http_response_header as $header) {
            echo "  $header\n";
            if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/i', $header, $matches)) {
                $redirectStatusCode = intval($matches[1]);
            }
        }
    }
    
    echo "\nRedirect Status Code: $redirectStatusCode\n";
    echo "Downloaded Body Length: " . strlen($contentRedirect) . " bytes\n";
    if ($redirectStatusCode !== 200) {
        echo "Error response snippet:\n" . substr($contentRedirect, 0, 500) . "\n";
    } else {
        echo "SUCCESS: File successfully downloaded from storage!\n";
    }
} else {
    echo "No redirect occurred. Raw response body:\n";
    echo substr($content, 0, 500) . "\n";
}

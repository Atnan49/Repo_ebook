<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/storage.php';

header('Content-Type: text/html; charset=utf-8');
echo "<h3>Supabase Environment & Bucket Diagnostics</h3>";
echo "isSupabaseEnabled(): " . (StorageHelper::isSupabaseEnabled() ? 'TRUE' : 'FALSE') . "<br><br>";

if (StorageHelper::isSupabaseEnabled()) {
    $url = rtrim(SUPABASE_URL, '/') . '/storage/v1/bucket';
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => [
                "Authorization: Bearer " . SUPABASE_KEY,
                "apikey: " . SUPABASE_KEY
            ],
            'ignore_errors' => true
        ]
    ];
    $context = stream_context_create($opts);
    $result = file_get_contents($url, false, $context);

    echo "<b>Supabase Storage API response:</b><br>";
    echo "<pre>" . htmlspecialchars($result) . "</pre>";
} else {
    echo "Supabase is not enabled.";
}

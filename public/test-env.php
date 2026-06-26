<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/storage.php';

header('Content-Type: text/html; charset=utf-8');
echo "<h3>Supabase Environment Diagnostics</h3>";
echo "SUPABASE_URL constant: " . (defined('SUPABASE_URL') ? SUPABASE_URL : 'NOT_DEFINED') . "<br>";
echo "SUPABASE_KEY constant length: " . (defined('SUPABASE_KEY') ? strlen(SUPABASE_KEY) : 'NOT_DEFINED') . "<br>";
echo "isSupabaseEnabled(): " . (StorageHelper::isSupabaseEnabled() ? 'TRUE' : 'FALSE') . "<br>";
echo "<br>PHP getenv('SUPABASE_URL'): " . (getenv('SUPABASE_URL') ?: 'NOT_FOUND') . "<br>";
echo "PHP getenv('SUPABASE_ANON_KEY'): " . (getenv('SUPABASE_ANON_KEY') ? 'FOUND' : 'NOT_FOUND') . "<br>";
echo "PHP getenv('SUPABASE_KEY'): " . (getenv('SUPABASE_KEY') ? 'FOUND' : 'NOT_FOUND') . "<br>";

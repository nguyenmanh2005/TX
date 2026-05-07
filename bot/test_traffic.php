<?php
/**
 * Traffic Simulator - Local Testing Only
 * Security: Restricted to 127.0.0.1
 */

// 1. Security Check: Only allow execution from localhost
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    header('HTTP/1.0 403 Forbidden');
    die('Error: This script is restricted to local development testing only.');
}

// 2. Secret Token Check
if (($_GET['token'] ?? '') !== 'your_secret_token') {
    header('HTTP/1.0 403 Forbidden');
    die('Error: Missing or invalid secret token.');
}

require_once __DIR__ . '/../db_connect.php';
set_time_limit(120); // 2 minutes is plenty for 500 requests

echo "<h1>🚜 Local Traffic Simulator Active</h1>";

$count = isset($_GET['count']) ? (int)$_GET['count'] : 100;
// 2. Safety Limit: Max 500 requests to prevent self-DDoS
if ($count > 500) $count = 500;

// 3. Page Discovery
$directory = __DIR__ . '/../';
$files = glob($directory . "*.php");
$pages = [];

$blacklist = ['db_connect.php', 'tracking.php', 'admin_', 'api_', 'process_'];

foreach ($files as $file) {
    $name = basename($file);
    $isBlacklisted = false;
    foreach ($blacklist as $b) {
        if (strpos($name, $b) !== false) {
            $isBlacklisted = true;
            break;
        }
    }
    if (!$isBlacklisted) $pages[] = $name;
}

if (empty($pages)) die("No visitable pages found.");

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
if (strpos($_SERVER['REQUEST_URI'], '/1/') !== false) { $baseUrl .= "/1"; }

echo "<p>Starting simulation of $count visits across " . count($pages) . " pages...</p><hr>";

// 4. Execution
for ($i = 0; $i < $count; $i++) {
    $page = $pages[array_rand($pages)];
    $url = $baseUrl . "/" . $page;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (TrafficSim/2.0; LocalOnly)");
    curl_exec($ch);
    $ch = null;
    
    if ($i % 10 == 0) {
        echo "Sent $i requests...<br>";
        flush();
    }
    
    // Tiny sleep to avoid CPU spikes
    usleep(50000); // 0.05s
}

echo "<hr>✅ Simulation finished successfully.";

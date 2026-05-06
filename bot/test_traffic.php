<?php
/**
 * Professional Traffic Simulation Bot
 * Used for testing analytics dashboard with large datasets
 */

set_time_limit(0); // No time limit for long runs
header('Content-Type: text/html; charset=utf-8');

// Configuration
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/1";

// Scan all PHP files in the root directory
$allFiles = glob(__DIR__ . '/../' . "*.php");
$pages = [];
$excludedFiles = ['db_connect.php', 'tracking.php', 'admin_helper.php', '403.php', 'test_traffic.php', 'check_db.php', 'ensure_db_connect.php'];

foreach ($allFiles as $file) {
    $filename = basename($file);
    // Skip admin files and specific excluded files
    if (strpos($filename, 'admin_') === 0) continue;
    if (in_array($filename, $excludedFiles)) continue;
    
    $pages[] = '/' . $filename;
}
// Add root / as well
if (!in_array('/', $pages)) $pages[] = '/';
$referrers = ['https://google.com', 'https://facebook.com', 'https://youtube.com', 'https://twitter.com', 'Direct'];

$userAgents = [
    'Chrome (Windows)' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Edge (Windows)'   => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
    'Firefox (Linux)'  => 'Mozilla/5.0 (X11; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/115.0',
    'Safari (Mac)'     => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_1) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15',
    'iPhone (iOS)'     => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1',
    'Android (Pixel)'  => 'Mozilla/5.0 (Linux; Android 14; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Mobile Safari/537.36',
    'iPad'             => 'Mozilla/5.0 (iPad; CPU OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1',
    'Opera (Windows)'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36 OPR/105.0.0.0'
];

$count = isset($_GET['count']) ? (int)$_GET['count'] : 10;
if ($count > 2000) $count = 2000; // Limit per run for safety

echo "<h2>🚀 Bot đang giả lập $count lượt truy cập...</h2>";
echo "<div style='background:#000; color:#0f0; padding:15px; font-family:monospace; border-radius:8px; height:400px; overflow:auto;'>";

for ($i = 1; $i <= $count; $i++) {
    $page = $pages[array_rand($pages)];
    $uaName = array_rand($userAgents);
    $ua = $userAgents[$uaName];
    $ref = $referrers[array_rand($referrers)];
    $url = $baseUrl . $page;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERAGENT, $ua);
    curl_setopt($ch, CURLOPT_REFERER, $ref);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    
    $start = microtime(true);
    curl_exec($ch);
    $time = round((microtime(true) - $start) * 1000, 2);
    
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "[$i] Truy cập: <span style='color:#fff'>$page</span> | Thiết bị: <span style='color:#34d399'>$uaName</span> | Phản hồi: <span style='color:#fbbf24'>{$status}</span> ({$time}ms)<br>";
    
    // Tiny sleep to prevent server overload if running thousands
    if ($i % 50 == 0) {
        echo "<script>window.scrollTo(0,document.body.scrollHeight);</script>";
        ob_flush();
        flush();
        usleep(100000); // 0.1s
    }
}

echo "</div>";
echo "<h3 style='color:#34d399'>✅ Hoàn thành! Đã gửi $count yêu cầu.</h3>";
echo "<p><a href='../admin_analytics.php' style='padding:10px 20px; background:#4f8dff; color:#fff; text-decoration:none; border-radius:5px;'>Xem kết quả trên Dashboard</a></p>";
echo "<p><a href='?count=100'>Tiếp tục chạy 100 lượt</a> | <a href='?count=500'>Chạy 500 lượt</a></p>";
?>

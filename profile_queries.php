<?php
require_once 'db_connect.php';

echo "=== PROFILE ANALYTICS_TRAFFIC.PHP ===" . PHP_EOL;
$t0 = microtime(true);

// 1. Tbl check
$t = microtime(true);
$tblCheck = $conn->query("SHOW TABLES LIKE 'site_analytics'");
echo "1. Table check: " . round((microtime(true) - $t)*1000, 2) . "ms\n";

// 2. Core metrics
$t = microtime(true);
$rTot = ($res = $conn->query("SELECT COUNT(*) as tot_req, COUNT(DISTINCT ip_address) as tot_vis, SUM(CASE WHEN page_url NOT LIKE '%api_%' THEN 1 ELSE 0 END) as tot_pv, SUM(CASE WHEN page_url LIKE '%api_%' THEN 1 ELSE 0 END) as tot_api FROM site_analytics")) ? $res->fetch_assoc() : [];
echo "2. Core metrics: " . round((microtime(true) - $t)*1000, 2) . "ms\n";

// 3. Comparison
$t = microtime(true);
$comp_periods = [
    '24h' => date('Y-m-d H:i:s', strtotime('-24 hours')),
    '7d'  => date('Y-m-d 00:00:00', strtotime('-7 days')),
    '30d' => date('Y-m-d 00:00:00', strtotime('-30 days')),
    '1y'  => date('Y-m-d 00:00:00', strtotime('-1 year')),
    'all' => '2000-01-01 00:00:00'
];
foreach ($comp_periods as $p => $start) {
    $conn->query("SELECT COUNT(*) FROM site_analytics WHERE visited_at >= '$start'");
    $conn->query("SELECT COUNT(DISTINCT ip_address) FROM site_analytics WHERE visited_at >= '$start'");
}
echo "3. Comparison 10 queries: " . round((microtime(true) - $t)*1000, 2) . "ms\n";

// 4. Tables data (8 Group By queries)
$t = microtime(true);
$countries    = $conn->query("SELECT country, COUNT(*) as cnt FROM site_analytics GROUP BY country ORDER BY cnt DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
$top_pages    = $conn->query("SELECT page_url, COUNT(*) as cnt FROM site_analytics WHERE page_url NOT LIKE '%api_%' GROUP BY page_url ORDER BY cnt DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
$sources      = $conn->query("SELECT source, COUNT(*) as cnt FROM site_analytics GROUP BY source ORDER BY cnt DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
$os_dist      = $conn->query("SELECT os, COUNT(*) as cnt FROM site_analytics GROUP BY os ORDER BY cnt DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);
$browser_dist = $conn->query("SELECT browser, COUNT(*) as cnt FROM site_analytics GROUP BY browser ORDER BY cnt DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);
$devices      = $conn->query("SELECT device, COUNT(*) as cnt FROM site_analytics GROUP BY device ORDER BY cnt DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);
$http_ver     = $conn->query("SELECT http_version, COUNT(*) as cnt FROM site_analytics GROUP BY http_version ORDER BY cnt DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
$recent       = $conn->query("SELECT ip_address, country, page_url, browser, visited_at FROM site_analytics ORDER BY visited_at DESC LIMIT 15")->fetch_all(MYSQLI_ASSOC);
echo "4. 8 GROUP BY queries: " . round((microtime(true) - $t)*1000, 2) . "ms\n";

echo "Total Traffic DB time: " . round((microtime(true) - $t0)*1000, 2) . "ms\n\n";

echo "=== PROFILE ANALYTICS_REVENUE.PHP ===" . PHP_EOL;
$t0 = microtime(true);
$t = microtime(true);
$conn->query("SELECT SUM(price * 0.05) as fees FROM marketplace_listings WHERE status = 'sold'");
echo "1. Marketplace: " . round((microtime(true) - $t)*1000, 2) . "ms\n";

$t = microtime(true);
$conn->query("SELECT SUM(gtlm_spent) as spent FROM crafting_logs");
echo "2. Crafting logs: " . round((microtime(true) - $t)*1000, 2) . "ms\n";

$t = microtime(true);
$conn->query("SELECT COUNT(*) * 500000 as fees FROM guilds");
echo "3. Guilds: " . round((microtime(true) - $t)*1000, 2) . "ms\n";

$t = microtime(true);
$conn->query("SELECT COUNT(*) * 1000000 as revenue FROM bp_stats WHERE has_premium = 1");
echo "4. BP stats: " . round((microtime(true) - $t)*1000, 2) . "ms\n";

$t = microtime(true);
$conn->query("SELECT game_name, SUM(bet_amount - win_amount) as house_profit FROM game_history GROUP BY game_name ORDER BY house_profit DESC");
echo "5. Game History 125k rows GROUP BY: " . round((microtime(true) - $t)*1000, 2) . "ms\n";

$t = microtime(true);
$conn->query("SELECT COUNT(*) FROM user_vip WHERE vip_level > 1");
echo "6. User VIP: " . round((microtime(true) - $t)*1000, 2) . "ms\n";

$t = microtime(true);
$conn->query("SELECT COUNT(*) FROM users WHERE last_active IS NOT NULL AND last_active < DATE_SUB(NOW(), INTERVAL 7 DAY)");
echo "7. Churn: " . round((microtime(true) - $t)*1000, 2) . "ms\n";

echo "Total Revenue DB time: " . round((microtime(true) - $t0)*1000, 2) . "ms\n";

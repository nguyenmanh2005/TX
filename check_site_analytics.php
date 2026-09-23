<?php
require_once 'db_connect.php';

$t = microtime(true);
$cnt30 = $conn->query("SELECT COUNT(*) FROM site_analytics WHERE visited_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_row()[0];
echo "30d rows: $cnt30 in " . round((microtime(true) - $t)*1000, 2) . "ms\n";

$t = microtime(true);
$cnt7 = $conn->query("SELECT COUNT(*) FROM site_analytics WHERE visited_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_row()[0];
echo "7d rows: $cnt7 in " . round((microtime(true) - $t)*1000, 2) . "ms\n";

$t = microtime(true);
$cnt1 = $conn->query("SELECT COUNT(*) FROM site_analytics WHERE visited_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch_row()[0];
echo "24h rows: $cnt1 in " . round((microtime(true) - $t)*1000, 2) . "ms\n";

$minMax = $conn->query("SELECT MIN(visited_at), MAX(visited_at) FROM site_analytics")->fetch_row();
echo "Min date: {$minMax[0]}, Max date: {$minMax[1]}\n";

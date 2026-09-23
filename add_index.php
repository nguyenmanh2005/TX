<?php
require_once 'db_connect.php';

$t = microtime(true);
$chk = $conn->query("SHOW INDEX FROM site_analytics WHERE Key_name = 'idx_visited_ip'");
if (!$chk || $chk->num_rows === 0) {
    $conn->query("ALTER TABLE site_analytics ADD INDEX idx_visited_ip (visited_at, ip_address)");
    echo "Added index idx_visited_ip in " . round((microtime(true) - $t)*1000, 2) . "ms\n";
} else {
    echo "Index idx_visited_ip already exists.\n";
}

// Thêm index cho page_url nếu cần
$chk2 = $conn->query("SHOW INDEX FROM site_analytics WHERE Key_name = 'idx_page_url'");
if (!$chk2 || $chk2->num_rows === 0) {
    $conn->query("ALTER TABLE site_analytics ADD INDEX idx_page_url (page_url(100))");
    echo "Added index idx_page_url\n";
}

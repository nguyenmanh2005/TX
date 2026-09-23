<?php
/**
 * 💰 Phân Hệ 1: Kinh Tế Vĩ Mô, Doanh Thu, RTP & Giám Sát Gian Lận
 * Hợp nhất từ: admin_economy.php, admin_analytics.php, analytics_revenue.php, financial_monitor.php
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../admin_helper.php';
require_once __DIR__ . '/navbar.php';

$currentUserId = (int)($_SESSION['Iduser'] ?? 0);
if (!isAdmin($conn, $currentUserId)) {
    header("Location: ../Shared/403/403.php");
    exit();
}

// AJAX actions for financial monitor
if (isset($_GET['ajax'])) {
    require __DIR__ . '/financial_monitor.php';
    exit();
}

$sub = $_GET['sub'] ?? ($_GET['tab'] ?? 'overview');
$range = $_GET['range'] ?? '7d';

if ($sub === 'analytics') {
    require __DIR__ . '/analytics_traffic.php';
} elseif ($sub === 'revenue') {
    require __DIR__ . '/analytics_revenue.php';
} else {
    // 'overview', 'games', 'billionaires', 'anomalies' đều được render mượt mà bởi economy_core.php
    $_GET['tab'] = $sub;
    require __DIR__ . '/economy_core.php';
}


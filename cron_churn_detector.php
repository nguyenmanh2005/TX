<?php
require_once 'db_connect.php';

/**
 * 📉 Churn Spike Detector
 * Compares active users in the last 4 hours with the same period yesterday.
 */

$now = date('Y-m-d H:i:s');
$fourHoursAgo = date('Y-m-d H:i:s', strtotime('-4 hours'));
$yesterdayFourHoursAgo = date('Y-m-d H:i:s', strtotime('-28 hours'));
$yesterdayNow = date('Y-m-d H:i:s', strtotime('-24 hours'));

// Current active users (last 4h)
$res = $conn->query("SELECT COUNT(DISTINCT Iduser) as cnt FROM users WHERE last_active >= '$fourHoursAgo'");
$currentActive = $res->fetch_assoc()['cnt'];

// Yesterday active users (same 4h window)
// Note: This assumes we track historical last_active, which we might not perfectly.
// A better way would be using the site_analytics table if it stores user_id.
$res = $conn->query("SELECT COUNT(DISTINCT ip_address) as cnt FROM site_analytics WHERE visited_at BETWEEN '$yesterdayFourHoursAgo' AND '$yesterdayNow'");
$yesterdayActive = $res->fetch_assoc()['cnt'];

if ($yesterdayActive > 10) { // Only alert if we have enough data
    $dropPercent = (($yesterdayActive - $currentActive) / $yesterdayActive) * 100;
    
    if ($dropPercent >= 15) { // 15% drop is a "spike"
        $msg = "Churn spike detected: Active users dropped by " . round($dropPercent, 1) . "% compared to yesterday ($yesterdayActive -> $currentActive)";
        $conn->query("INSERT INTO admin_alerts (type, message, severity) VALUES ('churn_spike', '$msg', 'warning')");
        echo "Alert created: $msg";
    } else {
        echo "Stable activity: " . round($dropPercent, 1) . "% change.";
    }
} else {
    echo "Not enough data for churn detection.";
}
?>

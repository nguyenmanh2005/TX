<?php
/**
 * 🔍 Database Structure Checker
 * Security: Only accessible from localhost
 */
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    die('Forbidden: Access denied.');
}

require_once '../db_connect.php';

echo "<h2>Database Table: users</h2><pre>";
$res = $conn->query("DESCRIBE users");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
echo "</pre>";
?>

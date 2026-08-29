<?php
require '../db_connect.php';

// First, fix any existing negative balances to 0
$conn->query("UPDATE users SET Money = 0 WHERE Money < 0");

$sql = "ALTER TABLE users MODIFY COLUMN Money BIGINT UNSIGNED NOT NULL DEFAULT 0";

if ($conn->query($sql)) {
    echo "SUCCESS: Table altered to UNSIGNED.";
} else {
    echo "ERROR: " . $conn->error;
}

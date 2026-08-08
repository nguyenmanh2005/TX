<?php
require_once __DIR__ . '/db_connect.php';
$sql1 = "ALTER TABLE tower_user_progress ADD COLUMN IF NOT EXISTS selected_character VARCHAR(50) DEFAULT 'kiem_thanh';";
$sql2 = "ALTER TABLE tower_user_progress ADD COLUMN IF NOT EXISTS shield_count INT DEFAULT 0;";

if ($conn->query($sql1) && $conn->query($sql2)) {
    echo "SUCCESS\n";
} else {
    echo "ERROR: " . $conn->error . "\n";
}
?>

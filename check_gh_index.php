<?php
require_once 'db_connect.php';

$indexes = $conn->query("SHOW INDEX FROM game_history")->fetch_all(MYSQLI_ASSOC);
echo "Indexes on game_history:\n";
foreach ($indexes as $idx) {
    echo "- Key_name: " . $idx['Key_name'] . " on Column: " . $idx['Column_name'] . "\n";
}

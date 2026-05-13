<?php
require_once 'db_connect.php';

$sql = [
    "CREATE TABLE IF NOT EXISTS material_daily_caps (
        user_id INT NOT NULL,
        date DATE NOT NULL,
        common_count INT DEFAULT 0,
        uncommon_count INT DEFAULT 0,
        rare_count INT DEFAULT 0,
        epic_count INT DEFAULT 0,
        PRIMARY KEY (user_id, date)
    )",
    "ALTER TABLE user_materials ADD COLUMN IF NOT EXISTS acquired_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
];

foreach ($sql as $q) {
    if ($conn->query($q)) {
        echo "Successfully executed: " . substr($q, 0, 50) . "...\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
}

echo "Bugs fixed and DB updated!";
?>

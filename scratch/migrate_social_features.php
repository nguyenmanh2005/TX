<?php
require_once 'db_connect.php';

$queries = [
    "CREATE TABLE IF NOT EXISTS guild_challenges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        challenger_id INT NOT NULL,
        challenged_id INT NOT NULL,
        start_time DATETIME,
        end_time DATETIME,
        challenger_score BIGINT DEFAULT 0,
        challenged_score BIGINT DEFAULT 0,
        status INT DEFAULT 0 COMMENT '0: pending, 1: active, 2: finished, 3: rejected',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS guild_trophies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        guild_id INT NOT NULL,
        trophy_name VARCHAR(255) NOT NULL,
        awarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS live_streams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        game_type VARCHAR(50),
        status VARCHAR(20) DEFAULT 'live',
        started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE TABLE IF NOT EXISTS stream_tips (
        id INT AUTO_INCREMENT PRIMARY KEY,
        stream_id INT NOT NULL,
        from_user_id INT NOT NULL,
        amount BIGINT NOT NULL,
        message TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "ALTER TABLE guilds ADD COLUMN IF NOT EXISTS trophies_count INT DEFAULT 0"
];

foreach ($queries as $sql) {
    if ($conn->query($sql)) {
        echo "Successfully executed: " . substr($sql, 0, 50) . "...\n";
    } else {
        echo "Error executing query: " . $conn->error . "\n";
    }
}
?>

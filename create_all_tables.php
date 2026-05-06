<?php
include 'db_connect.php';

$tables = [
    'history_war',
    'history_dragontiger',
    'history_baccarat',
    'history_threecard',
    'history_letitride',
    'history_paigow',
    'history_sicbo',
    'history_craps',
    'history_videopoker',
    'history_fantan',
    'history_mahjong'
];

foreach ($tables as $table) {
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($check->num_rows == 0) {
        echo "Creating $table...\n";
        $sql = "CREATE TABLE $table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            Iduser INT NOT NULL,
            Bet DECIMAL(15,2) NOT NULL,
            Result VARCHAR(255) NOT NULL,
            WinAmount DECIMAL(15,2) NOT NULL,
            Time DATETIME NOT NULL
        )";
        if ($conn->query($sql)) {
            echo "Table $table created.\n";
        } else {
            echo "Error creating $table: " . $conn->error . "\n";
        }
    } else {
        echo "Table $table already exists.\n";
    }
}
?>

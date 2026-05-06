<?php
include 'db_connect.php';
$table = 'history_dragontiger';
$check = $conn->query("SHOW TABLES LIKE '$table'");
if ($check->num_rows == 0) {
    echo "Table $table does not exist. Creating it...\n";
    $sql = "CREATE TABLE $table (
        id INT AUTO_INCREMENT PRIMARY KEY,
        Iduser INT NOT NULL,
        Bet DECIMAL(15,2) NOT NULL,
        Result VARCHAR(255) NOT NULL,
        WinAmount DECIMAL(15,2) NOT NULL,
        Time DATETIME NOT NULL
    )";
    if ($conn->query($sql)) {
        echo "Table $table created successfully.\n";
    } else {
        echo "Error creating table: " . $conn->error . "\n";
    }
} else {
    echo "Table $table already exists.\n";
}
?>

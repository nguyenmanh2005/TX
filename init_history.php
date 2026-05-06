<?php
$c = new mysqli('localhost', 'root', '', 'casino');
$tables = [
    'history_mines' => "CREATE TABLE IF NOT EXISTS history_mines (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, bet DECIMAL(15,2), win DECIMAL(15,2), mines INT, spots INT, result TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
    'history_limbo' => "CREATE TABLE IF NOT EXISTS history_limbo (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, bet DECIMAL(15,2), win DECIMAL(15,2), target DECIMAL(10,2), multiplier DECIMAL(10,2), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
    'history_tower' => "CREATE TABLE IF NOT EXISTS history_tower (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, bet DECIMAL(15,2), win DECIMAL(15,2), level INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
    'history_scratch' => "CREATE TABLE IF NOT EXISTS history_scratch (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, bet DECIMAL(15,2), win DECIMAL(15,2), result TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
    'history_plinko' => "CREATE TABLE IF NOT EXISTS history_plinko (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, bet DECIMAL(15,2), win DECIMAL(15,2), multiplier DECIMAL(10,2), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
    'history_crash' => "CREATE TABLE IF NOT EXISTS history_crash (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, bet DECIMAL(15,2), win DECIMAL(15,2), multiplier DECIMAL(10,2), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)"
];

foreach($tables as $name => $sql) {
    if($c->query($sql)) echo "Table $name checked/created.\n";
    else echo "Error creating $name: " . $c->error . "\n";
}
$c->close();
?>

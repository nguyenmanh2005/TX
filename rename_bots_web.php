<?php
require 'db_connect.php';

$res = $conn->query("SELECT Iduser, Email FROM users WHERE Email REGEXP '^bot[0-9]+@'");
$count = 0;
if($res) {
    while($row = $res->fetch_assoc()) {
        $email = $row['Email'];
        // Extract number from botXX@gmail.com
        if (preg_match('/^bot(\d+)@/', $email, $matches)) {
            $number = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $botName = "Bot $number";
            
            $id = $row['Iduser'];
            $conn->query("UPDATE users SET Name = '$botName' WHERE Iduser = $id");
            $count++;
        }
    }
}
echo "Reverted $count bots back to Bot XX.\n";

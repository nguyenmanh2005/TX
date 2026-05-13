<?php
require '../db_connect.php';
$res = $conn->query("SELECT id, username, message FROM chat_messages ORDER BY id DESC LIMIT 20");
while($row = $res->fetch_assoc()) {
    echo $row['id'] . " | " . $row['username'] . " | " . substr($row['message'], 0, 100) . "\n";
}
?>

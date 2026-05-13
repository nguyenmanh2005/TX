<?php
require 'db_connect.php';
$res = $conn->query("DESCRIBE chat_messages");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
?>

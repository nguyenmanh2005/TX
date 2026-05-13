<?php
require 'db_connect.php';
$res = $conn->query("SHOW TABLES LIKE 'chat_messages%'");
while($row = $res->fetch_array()) {
    echo $row[0] . "\n";
}
?>

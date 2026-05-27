<?php
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1') die('Forbidden');
require_once '../db_connect.php';
$res = $conn->query("SHOW TABLES");
while($row = $res->fetch_row()) {
    echo $row[0] . "\n";
}

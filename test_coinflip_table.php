<?php
include 'db_connect.php';
$res = $conn->query("SHOW CREATE TABLE history_coinflip");
if ($res) {
    echo $res->fetch_assoc()['Create Table'];
} else {
    echo "Table does not exist.";
}

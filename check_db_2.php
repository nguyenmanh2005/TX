<?php
require 'db_connect.php';
$result = $conn->query("SHOW COLUMNS FROM history_baucua");
$columns = [];
if ($result) {
    while($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
} else {
    $columns = ["error" => $conn->error];
}
file_put_contents('db_info.json', json_encode($columns));
?>

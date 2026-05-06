<?php
require 'db_connect.php';
$result = $conn->query("SHOW COLUMNS FROM history_baucua");
$columns = [];
while($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
}
echo json_encode($columns);
?>

<?php
require '../db_connect.php';
$res = $conn->query("SELECT id, message FROM chat_messages WHERE username = 'Admin Tester Bot' AND message LIKE '%Quét hoàn tất%' ORDER BY id DESC LIMIT 1");
if ($row = $res->fetch_assoc()) {
    echo "ID: " . $row['id'] . "\n" . $row['message'] . "\n";
} else {
    echo "Summary not found\n";
}
?>

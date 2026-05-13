<?php
require '../db_connect.php';
$conn->query("DELETE FROM chat_messages WHERE username = 'Admin Tester Bot'");
echo "Bot logs cleared\n";
?>

<?php
require '../db_connect.php';
$email = 'cumanhpt@gmail.com';
$res = $conn->query("SELECT * FROM users WHERE Email = '$email'");
if ($res && $res->num_rows > 0) {
    echo "User exists\n";
    $user = $res->fetch_assoc();
    echo "Name: " . $user['Name'] . "\n";
    echo "Role: " . $user['Role'] . "\n";
} else {
    echo "User NOT found\n";
}
?>

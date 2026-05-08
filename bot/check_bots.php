<?php
require_once '../db_connect.php';
$email = 'botatchoigai@gmail.com';
$res = $conn->query("SELECT '$email' REGEXP '^bot[0-9]+@' as matched");
$row = $res->fetch_assoc();
echo "Email: $email | Matched: " . ($row['matched'] ? 'YES' : 'NO') . "\n";

$res = $conn->query("SELECT Email FROM users WHERE Email REGEXP '^bot[0-9]+@' AND Email = '$email'");
if ($res->num_rows > 0) {
    echo "Found in DB with regex!\n";
} else {
    echo "NOT found in DB with regex.\n";
}

<?php
// Kết nối tới database
$servername = "103.57.223.36";
$username = "vdkmnuaahosting_cumanhpt";
$password = "Alohaka3@";
$dbname = "vdkmnuaahosting_taixiu";
$database = $dbname;

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8");

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}?>

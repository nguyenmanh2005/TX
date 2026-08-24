<?php
require __DIR__ . '/db_connect.php';
$stmt = $conn->prepare("SELECT page_url, title FROM livestream WHERE id=15");
$stmt->execute();
$res = $stmt->get_result();
print_r($res->fetch_assoc());

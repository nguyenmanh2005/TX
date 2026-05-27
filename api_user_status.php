<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['_session_expired' => true]);
    exit;
}

$userId = $_SESSION['Iduser'];
$res = $conn->query("SELECT Iduser, Name, Money, Role FROM users WHERE Iduser = $userId");
$user = $res->fetch_assoc();

if (!$user) {
    echo json_encode(['_session_expired' => true]);
    exit;
}

echo json_encode([
    'status' => 'success',
    'Iduser' => $user['Iduser'],
    'Name' => $user['Name'],
    'Money' => $user['Money'],
    'Role' => $user['Role']
]);
?>

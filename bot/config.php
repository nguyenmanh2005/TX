<?php
/**
 * 🛡️ Bot Army Configuration v3.0
 * Dynamic loading from Database
 */
require_once __DIR__ . '/../db_connect.php';

// Tự động lấy danh sách bot từ DB
$botEmails = [];
$res = $conn->query("SELECT Email FROM users WHERE Email REGEXP '^bot[0-9]+@' ORDER BY Iduser DESC");
while($row = $res->fetch_assoc()) {
    $botEmails[] = $row['Email'];
}

return [
    'bot_password' => '12345678@@A',
    'bot_emails' => $botEmails, // Luôn cập nhật mới nhất từ DB
    'settings' => [
        'max_bots_per_cycle' => 25,
        'session_lifetime' => 86400,
        'timeout' => 600
    ]
];

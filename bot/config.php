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
        'max_bots_per_cycle' => 100,
        'session_lifetime' => 86400,
        'timeout' => 600
    ],
    'rivalries' => [
        ['bot1@gmail.com', 'bot5@gmail.com'],
        ['bot3@gmail.com', 'bot10@gmail.com'],
        ['bot7@gmail.com', 'bot2@gmail.com']
    ],
    'alliances' => [
        ['bot2@gmail.com', 'bot8@gmail.com'],
        ['bot4@gmail.com', 'bot6@gmail.com']
    ],
    'announcer_emails' => [
        'bot_mc@gmail.com',
        'bot_announcer@gmail.com'
    ]
];

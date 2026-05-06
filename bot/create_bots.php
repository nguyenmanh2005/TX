<?php
/**
 * One-time script to create 15 bot accounts in the database
 */

require_once __DIR__ . '/../db_connect.php';

$password = '12345678@@A';
$hashedPass = password_hash($password, PASSWORD_DEFAULT);
$bots = [];

for ($i = 1; $i <= 15; $i++) {
    $num = str_pad($i, 2, '0', STR_PAD_LEFT);
    $bots[] = [
        'name' => "Bot $num",
        'email' => "bot$num@gmail.com"
    ];
}

echo "<h2>🛠 Đang khởi tạo 15 tài khoản Bot...</h2>";

foreach ($bots as $bot) {
    // Check if exists
    $check = $conn->prepare("SELECT Iduser FROM users WHERE Email = ?");
    $check->bind_param("s", $bot['email']);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo "- Tài khoản {$bot['email']} đã tồn tại, bỏ qua.<br>";
        continue;
    }

    // Insert
    $stmt = $conn->prepare("INSERT INTO users (Name, Email, Pass, Money, Role, created_at) VALUES (?, ?, ?, 10000000, 0, NOW())");
    $stmt->bind_param("sss", $bot['name'], $bot['email'], $hashedPass);
    
    if ($stmt->execute()) {
        echo "✅ Đã thêm: <b>{$bot['name']}</b> ({$bot['email']})<br>";
    } else {
        echo "❌ Lỗi thêm {$bot['name']}: " . $conn->error . "<br>";
    }
}

echo "<h3>✨ Hoàn thành! Bây giờ bạn có thể chạy Bot Army.</h3>";
?>

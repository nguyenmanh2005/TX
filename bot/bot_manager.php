<?php
/**
 * 🥚 Bot Manager v3.0 - Professional Standard
 * Features: Duplicate Check, Secure DB Insert, Dynamic Config
 */
require_once __DIR__ . '/../db_connect.php';

function spawnNewBot(mysqli $conn) {
    // 1. Lấy số lượng bot hiện tại từ DB để tính số tiếp theo
    $res = $conn->query("SELECT COUNT(*) as total FROM users WHERE Email LIKE '%bot%'");
    $currentBotCount = $res->fetch_assoc()['total'];
    $nextNumber = $currentBotCount + 1;
    
    $newName = "Bot " . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
    $email = "bot" . $nextNumber . "@gmail.com";
    $passText = "12345678@@A";
    $passHash = password_hash($passText, PASSWORD_DEFAULT);
    $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($newName) . "&background=random";
    
    // 2. Kiểm tra trùng lặp Email (Security Check)
    $check = $conn->prepare("SELECT Iduser FROM users WHERE Email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        return "Duplicate Email: $email";
    }
    
    // 3. Insert bảo mật (Prepared Statement)
    $stmt = $conn->prepare("INSERT INTO users (Name, Email, Pass, Money, ImageURL) VALUES (?, ?, ?, 1000000, ?)");
    $stmt->bind_param("ssss", $newName, $email, $passHash, $avatarUrl);
    
    if ($stmt->execute()) {
        return $newName;
    }
    return false;
}

// Handle Web Requests
if (isset($_GET['action'])) {
    if ($_GET['action'] == 'spawn') {
        $name = spawnNewBot($conn);
        header("Location: index.php?msg=Result: " . urlencode($name));
        exit;
    }
    
    if ($_GET['action'] == 'mass_spawn' && isset($_GET['target'])) {
        $target = (int)$_GET['target'];
        for ($i = 0; $i < 10; $i++) { // Spawn theo đợt nhỏ để tránh timeout
            spawnNewBot($conn);
        }
        header("Location: index.php?msg=Mass Spawn Cycle Completed");
        exit;
    }
}
echo "Bot Manager v3.0 is active.";

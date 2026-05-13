<?php
/**
 * 🥚 Bot Manager v3.0 - Professional Standard
 * Features: Duplicate Check, Secure DB Insert, Dynamic Config
 */
session_start();
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../admin_helper.php';

// Security Check: Use isAdmin helper for robust validation
if (!isAdmin($conn, (int)($_SESSION['Iduser'] ?? 0))) {
    if (php_sapi_name() !== 'cli') {
        header("Location: ../login.php?error=unauthorized");
        exit("Unauthorized access.");
    }
}

$env = file_exists(__DIR__ . '/../.env.php') ? require __DIR__ . '/../.env.php' : [];

/**
 * Tạo mới một bot trong hệ thống
 * @param mysqli $conn Kết nối CSDL
 * @param array $env Cấu hình môi trường
 * @return string|bool Tên bot mới hoặc false nếu thất bại
 */
function spawnNewBot(mysqli $conn, array $env) {
    // 1. Lấy số lượng bot hiện tại từ DB để tính số tiếp theo
    $res = $conn->query("SELECT COUNT(*) as total FROM users WHERE Email REGEXP '^bot[0-9]+@'");
    $currentBotCount = $res->fetch_assoc()['total'];
    $nextNumber = $currentBotCount + 1;
    
    $newName = "Bot " . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
    $email = "bot" . $nextNumber . "@gmail.com";
    $passText = $env['BOT_PASSWORD'] ?? throw new RuntimeException('BOT_PASSWORD chưa được cấu hình!');


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
        $count = isset($_GET['count']) ? max(1, (int)$_GET['count']) : 1;
        $count = min($count, 50); // Giới hạn tối đa 50 con mỗi lần để tránh treo server
        
        $spawned = [];
        for ($i = 0; $i < $count; $i++) {
            $name = spawnNewBot($conn, $env);
            if ($name && !str_contains($name, 'Duplicate')) {
                $spawned[] = $name;
            }
        }

        
        $msg = "Đã sinh thành công " . count($spawned) . " bot.";
        if (count($spawned) > 0) $msg .= " (Từ " . $spawned[0] . " đến " . end($spawned) . ")";
        
        header("Location: index.php?msg=" . urlencode($msg));
        exit;
    }
    
    if ($_GET['action'] == 'mass_spawn' && isset($_GET['target'])) {
        $target = (int)$_GET['target'];
        
        $res = $conn->query("SELECT COUNT(*) as total FROM users WHERE Email REGEXP '^bot[0-9]+@'");
        $current = (int)$res->fetch_assoc()['total'];
        
        // Tối đa 10 bot mỗi đợt để tránh timeout
        $toSpawn = min($target - $current, 10);
        
        if ($toSpawn > 0) {
            for ($i = 0; $i < $toSpawn; $i++) {
                spawnNewBot($conn, $env);
            }
            $msg = "Spawned $toSpawn bots. Current total: " . ($current + $toSpawn);

        } else {
            $msg = "Target $target already reached or exceeded.";
        }
        
        header("Location: index.php?msg=" . urlencode($msg));
        exit;
    }
}
echo "Bot Manager v3.0 is active.";

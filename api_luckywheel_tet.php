<?php
session_start();
require 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập!']);
    exit();
}

$userId = $_SESSION['Iduser'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $cuoc = (int) str_replace(",", "", $_POST["cuoc"] ?? "0");

    // Lấy số dư hiện tại
    $sql = "SELECT Money, Name FROM users WHERE Iduser = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $soDu = $user['Money'];
    $tenNguoiChoi = $user['Name'];

    if ($cuoc > $soDu || $cuoc <= 0) {
        echo json_encode(['success' => false, 'message' => 'Số dư không đủ hoặc cược không hợp lệ!']);
        exit();
    }

    $segments = [
        ["label" => "Lì Xì Nhỏ (x0.5)", "multiplier" => 0.5, "color" => "#ff4d4d", "id" => 0],
        ["label" => "Chúc Mừng Năm Mới (x1)", "multiplier" => 1, "color" => "#ffd700", "id" => 1],
        ["label" => "Bánh Chưng Xanh (x2)", "multiplier" => 2, "color" => "#2ecc71", "id" => 2],
        ["label" => "Cành Mai Vàng (x3)", "multiplier" => 3, "color" => "#f1c40f", "id" => 3],
        ["label" => "Bao Lì Xì Đỏ (x5)", "multiplier" => 5, "color" => "#e74c3c", "id" => 4],
        ["label" => "Thỏi Vàng May Mắn (x10)", "multiplier" => 10, "color" => "#f39c12", "id" => 5],
        ["label" => "Mất Lộc (x0)", "multiplier" => 0, "color" => "#7f8c8d", "id" => 6],
        ["label" => "Hạt Dưa (x1.5)", "multiplier" => 1.5, "color" => "#d35400", "id" => 7]
    ];

    // Logic quay
    $index = array_rand($segments);
    // Nếu muốn jackpot (x10) hiếm hơn:
    $rand = rand(1, 100);
    if ($rand <= 50) $index = 6; // 50% mất lộc hoặc x0.5 (giả sử)
    elseif ($rand <= 80) $index = array_rand([0, 1, 7]); // 30% x0.5, x1, x1.5
    elseif ($rand <= 95) $index = array_rand([2, 3]); // 15% x2, x3
    else $index = array_rand([4, 5]); // 5% x5, x10

    $segment = $segments[$index];
    $multi = $segment['multiplier'];
    $ketQua = $segment['label'];
    $thang = floor($cuoc * $multi);
    
    // Cập nhật số dư
    $newBalance = $soDu - $cuoc + $thang;
    
    $update = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
    $update->bind_param("di", $newBalance, $userId);
    $update->execute();

    // Thông báo thắng lớn
    if ($thang >= 5000000) {
        $msg = "🧧 " . htmlspecialchars($tenNguoiChoi) . " vừa nhận lộc " . number_format($thang) . " gtlm từ Vòng Quay Tết! 🧨";
        $expiresAt = date('Y-m-d H:i:s', time() + 30);
        $insertNoti = $conn->prepare("INSERT INTO server_notifications (user_id, user_name, message, amount, notification_type, expires_at) VALUES (?, ?, ?, ?, 'big_win', ?)");
        if ($insertNoti) {
            $insertNoti->bind_param("issds", $userId, $tenNguoiChoi, $msg, $thang, $expiresAt);
            $insertNoti->execute();
        }
    }

    echo json_encode([
        'success' => true,
        'index' => $index,
        'label' => $ketQua,
        'bet' => $cuoc,
        'winAmount' => $thang,
        'newBalance' => $newBalance,
        'formattedBalance' => number_format($newBalance, 0, ',', '.') . " gtlm"
    ]);
}
?>

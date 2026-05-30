<?php
session_start();
include 'db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$userId = $_SESSION['Iduser'];
$betAmount = (int)$_POST['betAmount'];
$choice = $_POST['choice']; // 'sấp' hoặc 'ngửa'

if ($betAmount < 1000) {
    echo json_encode(['success' => false, 'message' => 'Cược tối thiểu 1.000đ']);
    exit;
}

if (!in_array($choice, ['sấp', 'ngửa'])) {
    echo json_encode(['success' => false, 'message' => 'Lựa chọn không hợp lệ']);
    exit;
}

// Kiểm tra số dư
$stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || $user['Money'] < $betAmount) {
    echo json_encode(['success' => false, 'message' => 'Số dư không đủ!']);
    exit;
}

// Random kết quả (50/50)
$isHeads = (rand(0, 1) === 0);
$resultChoice = $isHeads ? 'sấp' : 'ngửa';
$isWin = ($choice === $resultChoice);

$winAmount = 0;
$moneyUpdate = -$betAmount; // Trừ tiền cược
$resultStatus = 'Thua';

if ($isWin) {
    $winAmount = $betAmount * 2; // Tỷ lệ ăn x2
    $moneyUpdate += $winAmount;
    $resultStatus = 'Thắng';
}

$conn->begin_transaction();
try {
    // Cập nhật số dư
    $stmtUpdate = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
    $stmtUpdate->bind_param("di", $moneyUpdate, $userId);
    $stmtUpdate->execute();
    
    // Ghi lịch sử
    $time = date('Y-m-d H:i:s');
    $stmtHist = $conn->prepare("INSERT INTO history_coinflip (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, ?)");
    $stmtHist->bind_param("iisds", $userId, $betAmount, $resultChoice, $winAmount, $time);
    $stmtHist->execute();
    
    $conn->commit();
    
    // Lấy số dư mới
    $stmtBal = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
    $stmtBal->bind_param("i", $userId);
    $stmtBal->execute();
    $newBalance = $stmtBal->get_result()->fetch_assoc()['Money'];
    
    echo json_encode([
        'success' => true,
        'is_win' => $isWin,
        'result_choice' => $resultChoice,
        'win_amount' => $winAmount,
        'new_balance' => $newBalance,
        'message' => $isWin ? 'Bạn đã thắng ' . number_format($winAmount) . 'đ!' : 'Bạn đã thua ' . number_format($betAmount) . 'đ!'
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra, vui lòng thử lại!']);
}
?>

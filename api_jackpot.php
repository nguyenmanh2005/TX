<?php
$isDirectCall = (isset($_SERVER['SCRIPT_FILENAME']) && basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']));

if ($isDirectCall) {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    require_once 'db_connect.php';

// 1. Khởi tạo Database Jackpot
$sql = "CREATE TABLE IF NOT EXISTS global_jackpot (
    id INT PRIMARY KEY,
    amount BIGINT DEFAULT 100000000, -- Khởi tạo 100 triệu
    last_winner_id INT NULL,
    last_win_amount BIGINT NULL,
    last_win_at TIMESTAMP NULL
);

INSERT IGNORE INTO global_jackpot (id, amount) VALUES (1, 100000000);
";
// $conn->query($sql);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_status':
        $jackpot = $conn->query("SELECT j.*, u.Name as winner_name 
                                FROM global_jackpot j 
                                LEFT JOIN users u ON j.last_winner_id = u.Iduser 
                                WHERE j.id = 1")->fetch_assoc();
        echo json_encode(['success' => true, 'amount' => $jackpot['amount'], 'last_winner' => $jackpot['winner_name'], 'last_amount' => $jackpot['last_win_amount']]);
        break;
}
} // End $isDirectCall check

// Hàm để cộng  Gtlm vào hũ (gọi từ game_history_helper)
function contributeToJackpot(mysqli $conn, float $betAmount) {
    if ($betAmount <= 0) return;
    $contribution = $betAmount * 0.001; // 0.1% mỗi lượt cược
    
    // FIX: Prepared statement + GREATEST(0) để tránh lỗi số âm hoặc race condition
    $stmt = $conn->prepare("UPDATE global_jackpot SET amount = GREATEST(0, amount + ?) WHERE id = 1");
    $stmt->bind_param("d", $contribution);
    $stmt->execute();
    $stmt->close();
}

// Hàm để kiểm tra nổ hũ (Ví dụ: tỉ lệ 1/10,000)
function checkJackpotWin(mysqli $conn, int $userId) {
    if (rand(1, 10000) === 777) { // Con số may mắn
        $conn->begin_transaction();
        try {
            // FIX: Khóa quỹ hũ để tránh nổ hũ kép (Double Drain)
            $stmt = $conn->prepare("SELECT amount FROM global_jackpot WHERE id = 1 FOR UPDATE");
            $stmt->execute();
            $jackpot = $stmt->get_result()->fetch_assoc();
            $winAmount = $jackpot['amount'];

            if ($winAmount < 100000000) $winAmount = 100000000; // Sàn tối thiểu
            
            // Cộng  Gtlm cho user (Dùng prepared statement)
            $stmt = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
            $stmt->bind_param("di", $winAmount, $userId);
            $stmt->execute();
            
            // Reset hũ về 100tr
            $stmt = $conn->prepare("UPDATE global_jackpot SET amount = 100000000, last_winner_id = ?, last_win_amount = ?, last_win_at = NOW() WHERE id = 1");
            $stmt->bind_param("id", $userId, $winAmount);
            $stmt->execute();
            
            $conn->commit();
            return $winAmount;
        } catch (Exception $e) {
            $conn->rollback();
            return 0;
        }
    }
    return 0;
}

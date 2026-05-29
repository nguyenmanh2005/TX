<?php
$isDirectCall = (isset($_SERVER['SCRIPT_FILENAME']) && basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']));

if ($isDirectCall) {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    require_once 'db_connect.php';

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

// HÃ m Ä‘á»ƒ cá»™ng  Gtlm vÃ o hÅ© (gá»i tá»« game_history_helper)
function contributeToJackpot(mysqli $conn, float $betAmount) {
    if ($betAmount <= 0) return;
    $contribution = $betAmount * 0.001; // 0.1% má»—i lÆ°á»£t cÆ°á»£c
    
    // FIX: Prepared statement + GREATEST(0) Ä‘á»ƒ trÃ¡nh lá»—i sá»‘ Ã¢m hoáº·c race condition
    $stmt = $conn->prepare("UPDATE global_jackpot SET amount = GREATEST(0, amount + ?) WHERE id = 1");
    $stmt->bind_param("d", $contribution);
    $stmt->execute();
    $stmt->close();
}

// HÃ m Ä‘á»ƒ kiá»ƒm tra ná»• hÅ© (VÃ­ dá»¥: tá»‰ lá»‡ 1/10,000)
function checkJackpotWin(mysqli $conn, int $userId) {
    if (rand(1, 10000) === 777) { // Con sá»‘ may máº¯n
        $conn->begin_transaction();
        try {
            // FIX: KhÃ³a quá»¹ hÅ© Ä‘á»ƒ trÃ¡nh ná»• hÅ© kÃ©p (Double Drain)
            $stmt = $conn->prepare("SELECT amount FROM global_jackpot WHERE id = 1 FOR UPDATE");
            $stmt->execute();
            $jackpot = $stmt->get_result()->fetch_assoc();
            $winAmount = $jackpot['amount'];

            if ($winAmount < 100000000) $winAmount = 100000000; // SÃ n tá»‘i thiá»ƒu
            
            // Cá»™ng  Gtlm cho user (DÃ¹ng prepared statement)
            $stmt = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
            $stmt->bind_param("di", $winAmount, $userId);
            $stmt->execute();
            
            // Reset hÅ© vá» 100tr
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

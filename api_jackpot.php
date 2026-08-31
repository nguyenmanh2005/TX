<?php
$isDirectCall = (isset($_SERVER['SCRIPT_FILENAME']) && basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']));

if ($isDirectCall) {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    require_once 'db_connect.php';

    /*
    $sql = "INSERT IGNORE INTO global_jackpot (id, amount) VALUES (1, 100000000);";
    $conn->query($sql);
    */

    $action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_status':
        $jackpot = $conn->query("SELECT j.*, u.Name as winner_name 
                                FROM global_jackpot j 
                                LEFT JOIN users u ON j.last_winner_id = u.Iduser 
                                WHERE j.id = 1")->fetch_assoc();
        echo json_encode(['success' => true, 'amount' => $jackpot['amount'], 'last_winner' => $jackpot['winner_name'], 'last_amount' => $jackpot['last_win_amount']]);
        break;

    case 'admin_withdraw':
        require_once 'admin_helper.php';
        $userId = $_SESSION['Iduser'] ?? 0;
        if (!isAdmin($conn, $userId)) {
            echo json_encode(['success' => false, 'message' => 'Không đủ quyền Admin!']);
            exit;
        }

        $type = $_POST['type'] ?? '';
        $target = $_POST['target'] ?? '';

        $conn->begin_transaction();
        try {
            // Khóa hũ
            $stmt = $conn->prepare("SELECT amount FROM global_jackpot WHERE id = 1 FOR UPDATE");
            $stmt->execute();
            $jackpot = $stmt->get_result()->fetch_assoc();
            $winAmount = (float)$jackpot['amount'];

            if ($winAmount <= 0) {
                echo json_encode(['success' => false, 'message' => 'Hũ trống rỗng!']);
                $conn->rollback();
                exit;
            }

            $winners = [];

            if ($type === 'self') {
                $winners[] = $userId;
            } else if ($type === 'individual') {
                $tgt = (int)$target;
                if ($tgt <= 0) throw new Exception("ID không hợp lệ");
                $winners[] = $tgt;
            } else if ($type === 'group') {
                $ids = explode(',', $target);
                foreach ($ids as $id) {
                    $id = (int)trim($id);
                    if ($id > 0) $winners[] = $id;
                }
                if (empty($winners)) throw new Exception("Không có ID hợp lệ");
            } else if ($type === 'random') {
                $count = (int)$target;
                if ($count <= 0) throw new Exception("Số lượng không hợp lệ");
                $res = $conn->query("SELECT Iduser FROM users ORDER BY RAND() LIMIT $count");
                while ($r = $res->fetch_assoc()) {
                    $winners[] = $r['Iduser'];
                }
                if (empty($winners)) throw new Exception("Không tìm thấy người chơi nào");
            } else {
                throw new Exception("Loại phân phát không hợp lệ");
            }

            $splitAmount = $winAmount / count($winners);

            // Cập nhật tiền cho người chơi
            foreach ($winners as $wId) {
                $upd = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
                $upd->bind_param("di", $splitAmount, $wId);
                $upd->execute();
                
                // Lưu log bot_transactions
                $checkTable = $conn->query("SHOW TABLES LIKE 'bot_transactions'");
                if ($checkTable && $checkTable->num_rows > 0) {
                    $trans = $conn->prepare("INSERT INTO bot_transactions (user_id, amount, type, reason) VALUES (?, ?, 'receive', 'Admin rải lộc từ Hũ Rồng Thần')");
                    $trans->bind_param("id", $wId, $splitAmount);
                    $trans->execute();
                }
            }

            // Reset Hũ
            $reset = $conn->prepare("UPDATE global_jackpot SET amount = 100000000, last_winner_id = ?, last_win_amount = ?, last_win_at = NOW() WHERE id = 1");
            $reset->bind_param("id", $userId, $winAmount);
            $reset->execute();

            $conn->commit();
            echo json_encode(['success' => true, 'message' => "Đã phân phát " . number_format($winAmount) . " GTLM cho " . count($winners) . " người!"]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
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

function checkJackpotWin(mysqli $conn, int $userId) {
    // Yêu cầu: Hũ GTLM sẽ được tích dần theo cơ chế cũ nhưng không chia cho bất kỳ ai.
    // Tắt tính năng nổ hũ.
    return 0;
}

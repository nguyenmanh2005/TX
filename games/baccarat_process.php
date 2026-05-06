<?php
session_start();
require_once '../db_connect.php';

// Cấu hình: Tắt hiển thị lỗi ra màn hình, bật ghi log vào file
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

/**
 * Hàm ghi log lỗi kèm thời gian vào file php_errors.log
 */
function logError($message) {
    $logFile = '../php_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] Baccarat Error: $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

try {
    if (!isset($_SESSION['Iduser'])) {
        echo json_encode(['error' => 'Chưa đăng nhập']);
        exit();
    }

    $userId = $_SESSION['Iduser'];
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        throw new Exception("Dữ liệu nhận từ trình duyệt bị rỗng hoặc sai định dạng JSON.");
    }

    $betPlayer = intval($data['player'] ?? 0);
    $betBanker = intval($data['banker'] ?? 0);
    $betTie = intval($data['tie'] ?? 0);
    $totalBet = $betPlayer + $betBanker + $betTie;

    if ($totalBet <= 0) {
        echo json_encode(['error' => 'Mức thách đấu không hợp lệ']);
        exit();
    }

    // 1. Kiểm tra ngân khố
    $sql = "SELECT Money FROM users WHERE Iduser = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Lỗi chuẩn bị SQL (Check Money): " . $conn->error);
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $userData = $stmt->get_result()->fetch_assoc();
    
    if (!$userData) throw new Exception("Không tìm thấy thông tin người dùng ID: $userId");
    $userMoney = $userData['Money'];

    if ($userMoney < $totalBet) {
        echo json_encode(['error' => 'Ngân khố không đủ để thách đấu']);
        exit();
    }

    // 2. Tạm khấu trừ ngân khố
    $conn->begin_transaction();
    
    $updateSql = "UPDATE users SET Money = Money - ? WHERE Iduser = ?";
    $updateStmt = $conn->prepare($updateSql);
    if (!$updateStmt) throw new Exception("Lỗi chuẩn bị SQL (Update Money): " . $conn->error);
    
    $updateStmt->bind_param("ii", $totalBet, $userId);
    if (!$updateStmt->execute()) throw new Exception("Lỗi thực thi SQL (Deduct Money): " . $updateStmt->error);

    // 3. Logic Royale Baccarat (Server side)
    $result = simulateBaccarat();
    
    // 4. Tính toán phần thưởng hoàng gia
    $winAmount = 0;
    if ($result['winner'] === 'player' && $betPlayer > 0) $winAmount += $betPlayer * 2;
    if ($result['winner'] === 'banker' && $betBanker > 0) $winAmount += $betBanker * 1.95;
    if ($result['winner'] === 'tie' && $betTie > 0) $winAmount += $betTie * 9;

    // 5. Trao thưởng (nếu có)
    if ($winAmount > 0) {
        $winSql = "UPDATE users SET Money = Money + ? WHERE Iduser = ?";
        $winStmt = $conn->prepare($winSql);
        if (!$winStmt) throw new Exception("Lỗi chuẩn bị SQL (Reward): " . $conn->error);
        
        $winStmt->bind_param("di", $winAmount, $userId);
        if (!$winStmt->execute()) throw new Exception("Lỗi thực thi SQL (Reward): " . $winStmt->error);
    }

    // 6. Cập nhật số dư cuối cùng
    $finalSql = "SELECT Money FROM users WHERE Iduser = ?";
    $finalStmt = $conn->prepare($finalSql);
    $finalStmt->bind_param("i", $userId);
    $finalStmt->execute();
    $newBalance = $finalStmt->get_result()->fetch_assoc()['Money'];

    $conn->commit();

    echo json_encode([
        'success' => true,
        'gameResult' => $result,
        'winAmount' => $winAmount,
        'newBalance' => $newBalance
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->connect_errno == 0) $conn->rollback();
    logError($e->getMessage()); // Ghi lỗi vào file php_errors.log
    echo json_encode(['error' => 'Hệ thống đang bận, vui lòng thử lại sau!']);
}

function simulateBaccarat() {
    $cards = [];
    for ($i = 1; $i <= 13; $i++) {
        for ($j = 0; $j < 4; $j++) $cards[] = $i;
    }
    shuffle($cards);

    $pCards = [array_pop($cards), array_pop($cards)];
    $bCards = [array_pop($cards), array_pop($cards)];

    $pScore = calculateScore($pCards);
    $bScore = calculateScore($bCards);

    $pThird = null;
    $bThird = null;

    if ($pScore < 8 && $bScore < 8) {
        if ($pScore <= 5) {
            $pThird = array_pop($cards);
            $pCards[] = $pThird;
        }

        if (shouldBankerDraw($bScore, $pThird ? getVal($pThird) : -1)) {
            $bThird = array_pop($cards);
            $bCards[] = $bThird;
        }
    }

    $finalP = calculateScore($pCards);
    $finalB = calculateScore($bCards);
    $winner = $finalP > $finalB ? 'player' : ($finalB > $finalP ? 'banker' : 'tie');

    return [
        'playerCards' => formatCards($pCards),
        'bankerCards' => formatCards($bCards),
        'playerScore' => $finalP,
        'bankerScore' => $finalB,
        'winner' => $winner
    ];
}

function calculateScore($cards) {
    $total = 0;
    foreach ($cards as $c) $total += getVal($c);
    return $total % 10;
}

function getVal($c) {
    return ($c >= 10) ? 0 : $c;
}

function shouldBankerDraw($b, $p3) {
    if ($p3 === -1) return $b <= 5;
    if ($b <= 2) return true;
    if ($b == 3) return $p3 != 8;
    if ($b == 4) return in_array($p3, [2,3,4,5,6,7]);
    if ($b == 5) return in_array($p3, [4,5,6,7]);
    if ($b == 6) return in_array($p3, [6,7]);
    return false;
}

function formatCards($cards) {
    $suits = ['hearts', 'diamonds', 'clubs', 'spades'];
    $res = [];
    foreach ($cards as $c) {
        $res[] = ['value' => $c, 'suit' => $suits[rand(0,3)]];
    }
    return $res;
}
?>

<?php
session_start();
include 'db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập!']);
    exit;
}

$userId = $_SESSION['Iduser'];
$action = $_GET['action'] ?? '';

const MAX_AFK_HOURS = 24;

// 1. Lấy danh sách Mỏ Sơ Hở
if ($action === 'vulnerable_list') {
    // Tìm các user có mỏ đã đầy (thời gian claim > 24h)
    // Để tối ưu, ta JOIN bảng users để lấy tên
    $nowStr = (new DateTime())->modify('-' . MAX_AFK_HOURS . ' hours')->format('Y-m-d H:i:s');
    
    // Loại trừ chính mình
    $sql = "SELECT m.user_id, u.Name, MAX(m.miner_level) as max_level, MIN(m.last_claim_time) as oldest_claim
            FROM user_miners m 
            JOIN users u ON m.user_id = u.Iduser
            WHERE m.last_claim_time <= ? AND m.user_id != ?
            GROUP BY m.user_id 
            LIMIT 10";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $nowStr, $userId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $list = [];
    foreach ($res as $row) {
        $list[] = [
            'id' => $row['user_id'],
            'name' => $row['Name'],
            'level' => $row['max_level']
        ];
    }
    
    echo json_encode(['success' => true, 'list' => $list]);
    exit;
}

// 2. Mua Chó Bảo Vệ
if ($action === 'buy_guard') {
    $cost = 500000; // Nửa triệu GTLM
    $durationHours = 24; // Bảo vệ 24 tiếng
    
    $conn->begin_transaction();
    try {
        $stmtLock = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
        $stmtLock->bind_param("i", $userId);
        $stmtLock->execute();
        $userRow = $stmtLock->get_result()->fetch_assoc();
        $stmtLock->close();
        
        if ($userRow['Money'] < $cost) {
            throw new Exception("Bạn không đủ GTLM mua Chó Canh Gác!");
        }
        
        // Trừ GTLM
        $conn->query("UPDATE users SET Money = Money - $cost WHERE Iduser = $userId");
        
        // Cập nhật/Thêm chó
        $expires = (new DateTime())->modify('+' . $durationHours . ' hours')->format('Y-m-d H:i:s');
        $stmt = $conn->prepare("INSERT INTO user_mine_guards (user_id, guard_type, expires_at) VALUES (?, 'dog', ?) ON DUPLICATE KEY UPDATE expires_at = ?");
        $stmt->bind_param("iss", $userId, $expires, $expires);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => "Đã thuê Chó Canh Gác trong $durationHours giờ!"]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// 3. Tiến hành Cướp (Raid)
if ($action === 'raid') {
    $targetId = (int)($_POST['target_id'] ?? 0);
    
    if ($targetId === $userId || $targetId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Mục tiêu không hợp lệ!']);
        exit;
    }
    
    $conn->begin_transaction();
    try {
        // Kiểm tra xem nạn nhân có chó không
        $stmtGuard = $conn->prepare("SELECT expires_at FROM user_mine_guards WHERE user_id = ? FOR UPDATE");
        $stmtGuard->bind_param("i", $targetId);
        $stmtGuard->execute();
        $guard = $stmtGuard->get_result()->fetch_assoc();
        $stmtGuard->close();
        
        if ($guard && new DateTime($guard['expires_at']) > new DateTime()) {
            throw new Exception("Ối! Nạn nhân có Chó Canh Gác. Bạn bị cắn chạy té khói và không cướp được gì!");
        }

        // Lock mỏ nạn nhân
        $stmtLock = $conn->prepare("SELECT slot_index, miner_level, last_claim_time FROM user_miners WHERE user_id = ? FOR UPDATE");
        $stmtLock->bind_param("i", $targetId);
        $stmtLock->execute();
        $miners = $stmtLock->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtLock->close();

        if (empty($miners)) {
            throw new Exception("Mục tiêu không có thợ mỏ!");
        }

        // Miner config (Hardcoded matching api_mining.php to avoid complex inclusion issues if included multiple times)
        $minerRates = [1 => 1000, 2 => 15000, 3 => 200000];

        $totalAccumulated = 0;
        $now = new DateTime();
        
        $stolenSlots = [];

        foreach ($miners as $m) {
            $level = (int)$m['miner_level'];
            $lastClaim = new DateTime($m['last_claim_time']);
            $diffSeconds = $now->getTimestamp() - $lastClaim->getTimestamp();
            
            // Nếu đủ 24h
            $maxSeconds = MAX_AFK_HOURS * 3600;
            if ($diffSeconds >= $maxSeconds) {
                // Đủ điều kiện bị cướp
                $accumulated = floor($maxSeconds * ($minerRates[$level] / 3600));
                // Cướp 15% số GTLM của mỏ đó
                $stolenAmount = floor($accumulated * 0.15);
                
                if ($stolenAmount > 0) {
                    $totalAccumulated += $stolenAmount;
                    $stolenSlots[] = $m['slot_index'];
                    
                    // Trừ thời gian claim của nạn nhân tương ứng với số GTLM bị cướp
                    // Lấy đi 15% thời gian
                    $timeReduced = floor($maxSeconds * 0.15);
                    $newClaimTimestamp = $lastClaim->getTimestamp() + $timeReduced;
                    $newClaimStr = date('Y-m-d H:i:s', $newClaimTimestamp);
                    
                    $conn->query("UPDATE user_miners SET last_claim_time = '$newClaimStr' WHERE user_id = $targetId AND slot_index = " . $m['slot_index']);
                }
            }
        }
        
        if ($totalAccumulated <= 0) {
            throw new Exception("Mỏ mục tiêu chưa đầy hoặc vừa bị người khác cướp!");
        }

        // Cộng GTLM cho người cướp
        $conn->query("UPDATE users SET Money = Money + $totalAccumulated WHERE Iduser = $userId");
        
        // Log vào bảng mine_steals_log
        $stmtLog = $conn->prepare("INSERT INTO mine_steals_log (attacker_id, victim_id, amount_stolen, time) VALUES (?, ?, ?, NOW())");
        $stmtLog->bind_param("iid", $userId, $targetId, $totalAccumulated);
        $stmtLog->execute();
        $stmtLog->close();

        $conn->commit();
        
        $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];
        echo json_encode([
            'success' => true,
            'message' => 'Bạn đã cướp thành công ' . number_format($totalAccumulated) . ' GTLM!',
            'new_money' => number_format($newMoney)
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);

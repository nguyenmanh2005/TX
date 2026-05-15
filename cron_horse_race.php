<?php
/**
 * Horse Race State Manager & Settlement
 * Run every 10-15 seconds
 */
include 'db_connect.php';
require_once 'notification_helper.php';

$now = time();

// 1. Tìm phiên hiện tại
$race = $conn->query("SELECT * FROM horse_races ORDER BY created_at DESC LIMIT 1")->fetch_assoc();

if (!$race) {
    createNewRace($conn);
    exit("Created first race.\n");
}

switch ($race['status']) {
    case 'betting':
        // Hết giờ cược -> Chuyển sang racing
        $waitEnd = strtotime($race['close_at']);
        if ($now >= $waitEnd) {
            startRace($race['id'], $conn);
        }
        break;

    case 'racing':
        // Chạy xong 30s -> Chuyển sang result
        $runEnd = strtotime($race['start_at']) + 30;
        if ($now >= $runEnd) {
            finishRace($race['id'], $conn);
        }
        break;

    case 'result':
        // Xem kết quả 15s -> Settle và mở phiên mới (Tổng 45s từ lúc start_at)
        $settleTime = strtotime($race['start_at']) + 45;
        if ($now >= $settleTime) {
            settleAndRepeat($race['id'], $conn);
        }
        break;
}

function createNewRace($conn) {
    // Tạo phiên mới, cho phép cược trong 3 phút (180s)
    $closeAt = date('Y-m-d H:i:s', time() + 180);
    $conn->query("INSERT INTO horse_races (status, close_at) VALUES ('betting', '$closeAt')");
}

function startRace($raceId, $conn) {
    // Pick winner
    $pools = array_fill(1, 6, 0);
    $totalPool = 0;
    $betsRes = $conn->query("SELECT horse_num, SUM(amount) as total FROM horse_bets WHERE race_id = $raceId GROUP BY horse_num");
    while ($b = $betsRes->fetch_assoc()) {
        $pools[$b['horse_num']] = (float)$b['total'];
        $totalPool += (float)$b['total'];
    }

    $weights = [];
    for ($i = 1; $i <= 6; $i++) {
        $weights[$i] = ($totalPool > 0) ? (1 - ($pools[$i] / ($totalPool + 1))) + 0.5 : 1;
    }

    $winner = weightedRandom($weights);
    
    // Cập nhật trạng thái racing và thời điểm bắt đầu start_at
    $conn->query("UPDATE horse_races SET status='racing', winner_horse=$winner, start_at=NOW() WHERE id = $raceId");
    echo "Race $raceId racing. Winner: $winner\n";
}

function finishRace($raceId, $conn) {
    $conn->query("UPDATE horse_races SET status='result' WHERE id = $raceId");
    echo "Race $raceId finished (result phase).\n";
}

function settleAndRepeat($raceId, $conn) {
    $race = $conn->query("SELECT * FROM horse_races WHERE id = $raceId")->fetch_assoc();
    $winner = $race['winner_horse'];

    $totalPool = 0;
    $winnerPool = 0;
    $poolsRes = $conn->query("SELECT horse_num, SUM(amount) as total FROM horse_bets WHERE race_id = $raceId GROUP BY horse_num");
    while ($p = $poolsRes->fetch_assoc()) {
        $totalPool += (float)$p['total'];
        if ($p['horse_num'] == $winner) $winnerPool = (float)$p['total'];
    }

    if ($winnerPool > 0) {
        $winnersRes = $conn->query("SELECT * FROM horse_bets WHERE race_id = $raceId AND horse_num = $winner");
        while ($bet = $winnersRes->fetch_assoc()) {
            $payout = ($bet['amount'] / $winnerPool) * $totalPool * 0.95;
            
            $conn->begin_transaction();
            try {
                $conn->query("UPDATE horse_bets SET payout = $payout WHERE id = {$bet['id']}");
                $conn->query("UPDATE users SET Money = Money + $payout WHERE Iduser = {$bet['user_id']}");
                
                // Gửi Notification qua Helper
                $msg = "Phiên #$raceId: Ngựa số $winner đã về nhất! Bạn nhận được " . number_format($payout) . " GTLM.";
                sendNotification($conn, $bet['user_id'], "🏆 Thắng cược đua ngựa!", $msg, "system");
                
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
            }
        }
    }

    // Tạo phiên mới
    createNewRace($conn);
    echo "Race $raceId settled. New race created.\n";
}

function weightedRandom($weights) {
    $total = array_sum($weights);
    $rand = mt_rand(0, $total * 100) / 100;
    $current = 0;
    foreach ($weights as $i => $w) {
        $current += $w;
        if ($rand <= $current) return $i;
    }
    return mt_rand(1, 6);
}
?>

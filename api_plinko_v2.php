<?php
session_start();
include 'db_connect.php';
require_once 'game_history_helper.php';

if (!isset($_SESSION['Iduser'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập!']);
    exit;
}

$userId = $_SESSION['Iduser'];

// Cấu hình Multipliers theo Rows và Risk
$plinkoConfig = [
    8 => [
        'low' => [5.6, 2.1, 1.1, 1, 0.5, 1, 1.1, 2.1, 5.6],
        'medium' => [13, 3, 1.3, 0.7, 0.4, 0.7, 1.3, 3, 13],
        'high' => [29, 4, 1.5, 0.3, 0.2, 0.3, 1.5, 4, 29]
    ],
    12 => [
        'low' => [10, 3, 1.6, 1.4, 1.1, 1, 0.5, 1, 1.1, 1.4, 1.6, 3, 10],
        'medium' => [33, 11, 4, 2, 1.1, 0.6, 0.3, 0.6, 1.1, 2, 4, 11, 33],
        'high' => [170, 24, 8.1, 2, 0.7, 0.2, 0.2, 0.2, 0.7, 2, 8.1, 24, 170]
    ],
    16 => [
        'low' => [16, 9, 2, 1.4, 1.4, 1.2, 1.1, 1, 0.5, 1, 1.1, 1.2, 1.4, 1.4, 2, 9, 16],
        'medium' => [110, 41, 10, 5, 3, 1.5, 1, 0.5, 0.3, 0.5, 1, 1.5, 3, 5, 10, 41, 110],
        'high' => [1000, 130, 26, 9, 4, 2, 0.2, 0.2, 0.2, 0.2, 0.2, 2, 4, 9, 26, 130, 1000]
    ]
];

$action = $_GET['action'] ?? '';

if ($action === 'config') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'config' => $plinkoConfig]);
    exit;
}

if ($action === 'drop') {
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid method']);
        exit;
    }

    $totalBet = (float)($_POST['bet'] ?? 0);
    $ballCount = (int)($_POST['ballCount'] ?? 1);
    $rows = (int)($_POST['rows'] ?? 8);
    $risk = $_POST['risk'] ?? 'low';

    // Validate inputs
    if ($totalBet < 1000) {
        echo json_encode(['success' => false, 'message' => 'Cược tối thiểu 1,000 GTLM']);
        exit;
    }
    if ($ballCount < 1 || $ballCount > 50) {
        echo json_encode(['success' => false, 'message' => 'Số bóng phải từ 1 đến 50']);
        exit;
    }
    if (!isset($plinkoConfig[$rows])) {
        echo json_encode(['success' => false, 'message' => 'Số hàng không hợp lệ']);
        exit;
    }
    if (!isset($plinkoConfig[$rows][$risk])) {
        echo json_encode(['success' => false, 'message' => 'Mức rủi ro không hợp lệ']);
        exit;
    }

    $multipliers = $plinkoConfig[$rows][$risk];
    $betPerBall = $totalBet / $ballCount;

    $conn->begin_transaction();
    try {
        // Lock row user
        $stmtLock = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
        $stmtLock->bind_param("i", $userId);
        $stmtLock->execute();
        $userRow = $stmtLock->get_result()->fetch_assoc();
        $stmtLock->close();

        if (!$userRow || $totalBet > $userRow['Money']) {
            throw new Exception("Số dư không đủ!");
        }

        // Trừ GTLM cược
        $conn->query("UPDATE users SET Money = Money - $totalBet WHERE Iduser = $userId");

        $results = [];
        $totalWin = 0;

        for ($b = 0; $b < $ballCount; $b++) {
            $path = [];
            $slot = 0;
            // Generate path
            for ($i = 0; $i < $rows; $i++) {
                $dir = rand(0, 1); // 0 = left, 1 = right
                $path[] = $dir;
                if ($dir === 1) {
                    $slot++;
                }
            }
            
            $mult = $multipliers[$slot];
            $win = round($betPerBall * $mult);
            $totalWin += $win;

            $results[] = [
                'path' => $path,
                'slot' => $slot,
                'multiplier' => $mult,
                'winAmount' => $win
            ];
        }

        // Cộng GTLM thắng
        if ($totalWin > 0) {
            $conn->query("UPDATE users SET Money = Money + $totalWin WHERE Iduser = $userId");
        }

        // Lưu lịch sử
        $profit = $totalWin - $totalBet;
        $resStr = "R:$rows|Risk:$risk|Balls:$ballCount|AvgX:" . round($totalWin / $totalBet, 2);
        
        $his = $conn->prepare("INSERT INTO history_plinko (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())");
        if ($his) {
            $his->bind_param("idss", $userId, $totalBet, $resStr, $profit);
            $his->execute();
            $his->close();
        } else {
            // Log lỗi nếu table history_plinko không tồn tại
            error_log("Missing history_plinko table. Please run SQL migration.");
        }

        // Log using core helper for gamification
        if (function_exists('logGameHistoryWithAll')) {
            logGameHistoryWithAll($conn, $userId, 'Plinko V2', $totalBet, $totalWin, $totalWin > $totalBet);
        } else if (function_exists('logGameHistory')) {
            logGameHistory($conn, $userId, 'Plinko V2', $totalBet, $totalWin, $totalWin > $totalBet);
        }

        $conn->commit();

        $newMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];

        echo json_encode([
            'success' => true,
            'results' => $results,
            'totalWin' => $totalWin,
            'money' => $newMoney,
            'betPerBall' => $betPerBall
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action not found']);

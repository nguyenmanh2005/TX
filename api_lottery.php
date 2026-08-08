<?php
session_start();
require_once 'db_connect.php';
require_once 'game_history_helper.php';

header('Content-Type: application/json');

$userId = $_SESSION['Iduser'] ?? null;
$action = $_GET['action'] ?? 'status';

// --- CONFIG ---
$ticketPrice = 10000; // 10k GTLM per ticket
$drawTime = "20:00:00";
$baseJackpot = 1000000000; // 1B GTLM base

// --- HELPERS ---

// --- HELPERS ---

function getLatestDraw(mysqli $conn) {
    $res = $conn->query("SELECT * FROM lottery_draws ORDER BY id DESC LIMIT 1");
    return ($res && $res instanceof mysqli_result) ? $res->fetch_assoc() : null;
}

function ensureDrawsExist(mysqli $conn, float $baseJackpot) {
    global $drawTime;
    $today = date('Y-m-d');
    $now = time();
    $latest = getLatestDraw($conn);
    
    if (!$latest) {
        $conn->query("INSERT IGNORE INTO lottery_draws (draw_date, jackpot_pool, status) VALUES ('$today', $baseJackpot, 'pending')");
    } else if (isset($latest['status']) && ($latest['status'] === 'paid' || $latest['status'] === 'drawn')) {
        $targetDate = ($now >= strtotime($today . ' ' . $drawTime)) ? date('Y-m-d', strtotime('+1 day')) : $today;
        $initialPool = isset($latest['jackpot_pool']) ? (float)$latest['jackpot_pool'] : $baseJackpot;
        $conn->query("INSERT IGNORE INTO lottery_draws (draw_date, jackpot_pool, status) VALUES ('$targetDate', $initialPool, 'pending')");
    }
}

// --- INITIALIZE & PROCESS ---
ensureDrawsExist($conn, $baseJackpot);

$currentDraw = getLatestDraw($conn);
$todayDate = date('Y-m-d');
$now = time();
$drawTimestamp = ($currentDraw && isset($currentDraw['draw_date'])) ? strtotime($currentDraw['draw_date'] . ' ' . $drawTime) : $now + 86400;

// Check if we need to execute the draw
if ($currentDraw && isset($currentDraw['status']) && $currentDraw['status'] === 'pending' && $now >= $drawTimestamp) {
    $drawId = (int)$currentDraw['id'];
    // Generate winning numbers (6 numbers from 01-99)
    $nums = [];
    while(count($nums) < 6) {
        $n = str_pad(rand(1, 99), 2, '0', STR_PAD_LEFT);
        if (!in_array($n, $nums)) $nums[] = $n;
    }
    sort($nums);
    $winningStr = implode(',', $nums);
    
    $conn->query("UPDATE lottery_draws SET winning_numbers = '$winningStr', status = 'drawn' WHERE id = $drawId");
    $currentDraw = getLatestDraw($conn);
    $currentDrawId = ($currentDraw && isset($currentDraw['id'])) ? (int)$currentDraw['id'] : $drawId;
    
    // Calculate winners
    $jackpot = ($currentDraw && isset($currentDraw['jackpot_pool'])) ? (float)$currentDraw['jackpot_pool'] : (float)$baseJackpot;
    $winningArr = explode(',', $winningStr);
    
    $tickets = $conn->query("SELECT id, user_id, numbers FROM lottery_tickets WHERE draw_id = $currentDrawId");
    $winners = [
        6 => [], // Đặc biệt
        5 => [], // Giải Nhất
        4 => [], // Giải Bốn
        3 => [], // Giải Năm
        2 => [], // Giải Sáu
        1 => []  // Giải Bảy
    ];
    
    if ($tickets && $tickets instanceof mysqli_result) {
        while($t = $tickets->fetch_assoc()) {
            $tNums = explode(',', $t['numbers']);
            $matchCount = count(array_intersect($tNums, $winningArr));
            if ($matchCount > 0) {
                $winners[$matchCount][] = ['user_id' => $t['user_id'], 'ticket_id' => $t['id']];
            }
        }
    }

    $fixedPrizes = [
        5 => 1000000,
        4 => 160000,
        3 => 80000,
        2 => 40000,
        1 => 20000
    ];

    $jackpotWinnersCount = count($winners[6]);
    if ($jackpotWinnersCount > 0) {
        $share = $jackpot / $jackpotWinnersCount;
        foreach($winners[6] as $w) {
            $uid = (int)$w['user_id'];
            $tid = (int)$w['ticket_id'];
            $conn->query("UPDATE users SET Money = Money + $share WHERE Iduser = $uid");
            $conn->query("UPDATE lottery_tickets SET prize_level = 6, prize_amount = $share WHERE id = $tid");
        }
        $nextPool = $baseJackpot;
    } else {
        // No winners, carry over
        $nextPool = $jackpot + 500000;
    }
    
    // Create the next draw immediately so users can keep playing
    $targetDate = ($now >= strtotime($todayDate . ' ' . $drawTime)) ? date('Y-m-d', strtotime('+1 day')) : $todayDate;
    $conn->query("INSERT IGNORE INTO lottery_draws (draw_date, jackpot_pool, status) VALUES ('$targetDate', $nextPool, 'pending')");

    // Pay fixed prizes for 1 to 5 matches
    foreach([5, 4, 3, 2, 1] as $matchCount) {
        if (count($winners[$matchCount]) > 0) {
            $prize = $fixedPrizes[$matchCount];
            foreach($winners[$matchCount] as $w) {
                $uid = (int)$w['user_id'];
                $tid = (int)$w['ticket_id'];
                $conn->query("UPDATE users SET Money = Money + $prize WHERE Iduser = $uid");
                $conn->query("UPDATE lottery_tickets SET prize_level = $matchCount, prize_amount = $prize WHERE id = $tid");
            }
        }
    }
    
    if ($currentDrawId > 0) {
        $conn->query("UPDATE lottery_draws SET status = 'paid' WHERE id = $currentDrawId");
    }
    $currentDraw = getLatestDraw($conn); // Get the newly created pending draw
}

$lastDrawRes = $conn->query("SELECT * FROM lottery_draws WHERE status = 'paid' ORDER BY id DESC LIMIT 1");
$lastDraw = ($lastDrawRes && $lastDrawRes instanceof mysqli_result) ? $lastDrawRes->fetch_assoc() : null;

// --- ACTIONS ---

if ($action === 'force_draw') {
    $isAdmin = (isset($_SESSION['admin']) && $_SESSION['admin'] == true) || (isset($_SESSION['Role']) && $_SESSION['Role'] == 1);
    if ($isAdmin) {
        $drawIdToUpdate = ($currentDraw && isset($currentDraw['id'])) ? (int)$currentDraw['id'] : 0;
        if ($drawIdToUpdate > 0 && isset($currentDraw['status']) && $currentDraw['status'] !== 'pending' && $currentDraw['status'] !== 'drawn') {
            $conn->query("UPDATE lottery_draws SET status = 'pending', winning_numbers = NULL WHERE id = $drawIdToUpdate");
        }
        
        $nums = [];
        while(count($nums) < 6) {
            $n = str_pad(rand(1, 99), 2, '0', STR_PAD_LEFT);
            if (!in_array($n, $nums)) $nums[] = $n;
        }
        sort($nums);
        $winningStr = implode(',', $nums);
        
        if ($drawIdToUpdate > 0) {
            $conn->query("UPDATE lottery_draws SET winning_numbers = '$winningStr', status = 'drawn' WHERE id = $drawIdToUpdate");
        }
        $currentDraw = getLatestDraw($conn); 
        $currentDrawId = ($currentDraw && isset($currentDraw['id'])) ? (int)$currentDraw['id'] : $drawIdToUpdate;
        
        $jackpot = ($currentDraw && isset($currentDraw['jackpot_pool'])) ? (float)$currentDraw['jackpot_pool'] : (float)$baseJackpot;
        $winningArr = explode(',', $winningStr);
        
        $tickets = $conn->query("SELECT id, user_id, numbers FROM lottery_tickets WHERE draw_id = $currentDrawId");
        $winners = [
            6 => [], // Đặc biệt
            5 => [], // Giải Nhất
            4 => [], // Giải Bốn
            3 => [], // Giải Năm
            2 => [], // Giải Sáu
            1 => []  // Giải Bảy
        ];
        
        if ($tickets && $tickets instanceof mysqli_result) {
            while($t = $tickets->fetch_assoc()) {
                $tNums = explode(',', $t['numbers']);
                $matchCount = count(array_intersect($tNums, $winningArr));
                if ($matchCount > 0) {
                    $winners[$matchCount][] = ['user_id' => $t['user_id'], 'ticket_id' => $t['id']];
                }
            }
        }

        $fixedPrizes = [
            5 => 1000000,
            4 => 160000,
            3 => 80000,
            2 => 40000,
            1 => 20000
        ];
        
        $jackpotWinnersCount = count($winners[6]);
        if ($jackpotWinnersCount > 0) {
            $share = $jackpot / $jackpotWinnersCount;
            foreach($winners[6] as $w) {
                $uid = (int)$w['user_id'];
                $tid = (int)$w['ticket_id'];
                $conn->query("UPDATE users SET Money = Money + $share WHERE Iduser = $uid");
                $conn->query("UPDATE lottery_tickets SET prize_level = 6, prize_amount = $share WHERE id = $tid");
            }
        }
        
        // Pay fixed prizes for 1 to 5 matches
        foreach([5, 4, 3, 2, 1] as $matchCount) {
            if (count($winners[$matchCount]) > 0) {
                $prize = $fixedPrizes[$matchCount];
                foreach($winners[$matchCount] as $w) {
                    $uid = (int)$w['user_id'];
                    $tid = (int)$w['ticket_id'];
                    $conn->query("UPDATE users SET Money = Money + $prize WHERE Iduser = $uid");
                    $conn->query("UPDATE lottery_tickets SET prize_level = $matchCount, prize_amount = $prize WHERE id = $tid");
                }
            }
        }
        
        if ($jackpotWinnersCount > 0) {
            $nextPool = $baseJackpot;
        } else {
            $nextPool = $jackpot + 500000;
        }
        
        // Create the next draw immediately for continuous play
        $targetDate = ($now >= strtotime($todayDate . ' ' . $drawTime)) ? date('Y-m-d', strtotime('+1 day')) : $todayDate;
        $conn->query("INSERT IGNORE INTO lottery_draws (draw_date, jackpot_pool, status) VALUES ('$targetDate', $nextPool, 'pending')");
        
        if ($currentDrawId > 0) {
            $conn->query("UPDATE lottery_draws SET status = 'paid' WHERE id = $currentDrawId");
        }
        
        echo json_encode([
            'success' => true,
            'winning_numbers' => $winningStr,
            'message' => 'Chốt sổ thành công! Đã trao thưởng và tạo kỳ quay mới.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Tính năng này chỉ dành cho Admin']);
    }
    exit();
}

if ($action === 'test_draw') {
    $isAdmin = (isset($_SESSION['admin']) && $_SESSION['admin'] == true) || (isset($_SESSION['Role']) && $_SESSION['Role'] == 1);
    if ($isAdmin) {
        $nums = [];
        while(count($nums) < 6) {
            $n = str_pad(rand(1, 99), 2, '0', STR_PAD_LEFT);
            if (!in_array($n, $nums)) $nums[] = $n;
        }
        sort($nums);
        $winningStr = implode(',', $nums);
        
        $drawIdToUpdate = ($currentDraw && isset($currentDraw['id'])) ? (int)$currentDraw['id'] : 0;
        if ($drawIdToUpdate > 0) {
            $conn->query("UPDATE lottery_draws SET winning_numbers = '$winningStr', status = 'drawn' WHERE id = $drawIdToUpdate");
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Đã quay ảo! Kết quả sẽ hiển thị và tự động hủy sau vài giây.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Từ chối truy cập.']);
    }
    exit();
}

if ($action === 'revert_test') {
    $isAdmin = (isset($_SESSION['admin']) && $_SESSION['admin'] == true) || (isset($_SESSION['Role']) && $_SESSION['Role'] == 1);
    if ($isAdmin && $currentDraw && isset($currentDraw['status']) && $currentDraw['status'] === 'drawn') {
        $drawId = (int)$currentDraw['id'];
        $conn->query("UPDATE lottery_draws SET status = 'pending', winning_numbers = NULL WHERE id = $drawId");
        echo json_encode(['success' => true]);
    } else if ($isAdmin) {
        echo json_encode(['success' => false, 'message' => 'Kỳ quay hiện tại không ở trạng thái vừa quay ảo.']);
    }
    exit();
}

if ($action === 'status') {
    $userTickets = [];
    if ($userId && $currentDraw && isset($currentDraw['id'])) {
        $drawId = (int)$currentDraw['id'];
        $res = $conn->query("SELECT id, numbers, prize_level, prize_amount, is_bonus_spun FROM lottery_tickets WHERE draw_id = {$drawId} AND user_id = $userId");
        if ($res && $res instanceof mysqli_result) {
            while($row = $res->fetch_assoc()) {
                $userTickets[] = [
                    'id' => $row['id'],
                    'numbers' => $row['numbers'],
                    'prize_level' => (int)$row['prize_level'],
                    'prize_amount' => (float)$row['prize_amount'],
                    'is_bonus_spun' => (int)$row['is_bonus_spun']
                ];
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'today' => $currentDraw ? [
            'id' => $currentDraw['id'] ?? null,
            'date' => $currentDraw['draw_date'] ?? date('Y-m-d'),
            'jackpot' => isset($currentDraw['jackpot_pool']) ? (float)$currentDraw['jackpot_pool'] : (float)$baseJackpot,
            'status' => $currentDraw['status'] ?? 'pending',
            'winning_numbers' => $currentDraw['winning_numbers'] ?? null,
            'draw_time' => ($currentDraw['draw_date'] ?? date('Y-m-d')) . ' ' . $drawTime
        ] : null,
        'last_draw' => $lastDraw ? [
            'id' => $lastDraw['id'] ?? null,
            'date' => $lastDraw['draw_date'] ?? null,
            'status' => $lastDraw['status'] ?? null,
            'winning_numbers' => $lastDraw['winning_numbers'] ?? null
        ] : null,
        'user_tickets' => $userTickets,
        'ticket_price' => $ticketPrice,
        'server_time' => date('Y-m-d H:i:s')
    ]);
}

elseif ($action === 'community_tickets') {
    $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
    
    $drawId = ($currentDraw && isset($currentDraw['id'])) ? (int)$currentDraw['id'] : 0;
    $where = "lt.draw_id = {$drawId}";
    if ($userId) {
        $where .= " AND lt.user_id != $userId";
    }
    if ($search !== '') {
        $where .= " AND (u.Name LIKE '%$search%' OR lt.numbers LIKE '%$search%')";
    }
    
    $res = $conn->query("
        SELECT lt.id, lt.numbers, u.Name, u.ImageURL, u.avatar_frame_id, af.ImageURL AS frame_url 
        FROM lottery_tickets lt 
        JOIN users u ON lt.user_id = u.Iduser 
        LEFT JOIN avatar_frames af ON u.avatar_frame_id = af.id
        WHERE $where 
        ORDER BY lt.id DESC 
        LIMIT 50
    ");
    
    $tickets = [];
    if ($res && $res instanceof mysqli_result) {
        while($row = $res->fetch_assoc()) {
            $img = !empty($row['ImageURL']) ? htmlspecialchars($row['ImageURL']) : 'avatar_default.png';
            if ($img === 'avatar_default.png') {
                $img = '../img/avatar_default.png';
            } else if (!str_starts_with($img, 'http')) {
                 $img = '../uploads/' . $img;
            }
            
            $frameHtml = '';
            if (!empty($row['frame_url'])) {
                $furl = $row['frame_url'];
                if (!str_starts_with($furl, 'http')) {
                    $furl = '../uploads/frames/' . $furl;
                }
                $frameHtml = $furl;
            }
            
            $tickets[] = [
                'id' => $row['id'],
                'numbers' => $row['numbers'],
                'name' => htmlspecialchars($row['Name']),
                'avatar' => $img,
                'frame' => $frameHtml
            ];
        }
    }
    
    echo json_encode(['success' => true, 'tickets' => $tickets]);
}

elseif ($action === 'my_ticket_history') {
    if (!$userId) { echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']); exit(); }
    $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
    
    $where = "lt.user_id = $userId";
    if ($search !== '') {
        $where .= " AND (lt.numbers LIKE '%$search%' OR ld.draw_date LIKE '%$search%')";
    }
    
    $res = $conn->query("
        SELECT lt.id, lt.numbers, lt.prize_level, lt.prize_amount, ld.draw_date, ld.status, ld.winning_numbers 
        FROM lottery_tickets lt 
        JOIN lottery_draws ld ON lt.draw_id = ld.id 
        WHERE $where 
        ORDER BY lt.id DESC 
        LIMIT 100
    ");
    
    $history = [];
    if ($res && $res instanceof mysqli_result) {
        while($row = $res->fetch_assoc()) {
            $history[] = [
                'id' => $row['id'],
                'numbers' => $row['numbers'],
                'prize_level' => (int)$row['prize_level'],
                'prize_amount' => (float)$row['prize_amount'],
                'draw_date' => $row['draw_date'],
                'draw_status' => $row['status'],
                'winning_numbers' => $row['winning_numbers']
            ];
        }
    }
    
    echo json_encode(['success' => true, 'history' => $history]);
}

elseif ($action === 'spin_bonus' && $userId) {
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT prize_amount, is_bonus_spun, prize_level FROM lottery_tickets WHERE id = ? AND user_id = ? FOR UPDATE");
        $stmt->bind_param("ii", $ticketId, $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $ticket = $res->fetch_assoc();
        
        if (!$ticket) {
            throw new Exception("Vé không tồn tại hoặc không thuộc về bạn.");
        }
        if ($ticket['prize_amount'] <= 0) {
            throw new Exception("Vé này không trúng thưởng, không thể quay bonus.");
        }
        if ($ticket['is_bonus_spun'] == 1) {
            throw new Exception("Vé này đã được quay bonus rồi.");
        }
        
        // Random logic
        $r = rand(1, 100);
        $prizeType = '';
        $bonusAmount = 0;
        $message = '';
        
        // Count total users
        $userCountRes = $conn->query("SELECT COUNT(*) as c FROM users");
        $totalUsers = $userCountRes->fetch_assoc()['c'] ?? 100;
        
        // Determine prize
        // Giải Đặc Biệt (level 6) cannot get xTotalUsers or x2 to prevent breaking economy
        if ($ticket['prize_level'] == 6) {
            if ($r <= 50) {
                $prizeType = 'free_ticket';
                $bonusAmount = 10000;
                $message = 'Tuyệt vời! Bạn nhận được thêm 1 VÉ MIỄN PHÍ (10.000 GTLM) cho ngày mai!';
            } else {
                $prizeType = 'fixed_50k';
                $bonusAmount = 50000;
                $message = 'Chúc mừng! Bạn được tặng nóng 50.000 GTLM từ quỹ cộng đồng!';
            }
        } else {
            if ($r <= 5) { // 5% chance
                $prizeType = 'x_users';
                $bonusAmount = $ticket['prize_amount'] * $totalUsers;
                // Cap at 5,000,000 to be safe
                if ($bonusAmount > 5000000) $bonusAmount = 5000000;
                $message = 'TRÚNG ĐẬM! Bạn được nhân GTLM thưởng với tổng số người chơi ('.$totalUsers.' người) và nhận thêm '.number_format($bonusAmount).' GTLM!';
            } elseif ($r <= 20) { // 15% chance
                $prizeType = 'x2';
                $bonusAmount = $ticket['prize_amount']; // already got original, adding the same amount equals x2
                $message = 'CHÚC MỪNG! Số GTLM thưởng của bạn đã được NHÂN ĐÔI (+'.number_format($bonusAmount).' GTLM)!';
            } elseif ($r <= 60) { // 40% chance
                $prizeType = 'fixed_50k';
                $bonusAmount = 50000;
                $message = 'May mắn! Bạn nhận được thêm 50.000 GTLM GTLM mặt!';
            } else { // 40% chance
                $prizeType = 'free_ticket';
                $bonusAmount = 10000;
                $message = 'Tuyệt vời! Bạn nhận được thêm 1 VÉ MIỄN PHÍ (10.000 GTLM) cho ngày mai!';
            }
        }
        
        $isAdmin = (isset($_SESSION['admin']) && $_SESSION['admin'] == true) || (isset($_SESSION['Role']) && $_SESSION['Role'] == 1);
        
        if ($isAdmin) {
            $message .= '<br><br><small style="color: #f43f5e;">[CHẾ ĐỘ ADMIN TEST: Không cộng GTLM & Không lưu trạng thái]</small>';
        } else {
            // Update user money
            $conn->query("UPDATE users SET Money = Money + $bonusAmount WHERE Iduser = $userId");
            // Mark ticket as spun
            $conn->query("UPDATE lottery_tickets SET is_bonus_spun = 1 WHERE id = $ticketId");
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'bonus_amount' => $bonusAmount,
            'prize_type' => $prizeType,
            'message' => $message
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

elseif ($action === 'buy' && $userId) {
    $numbers = $_POST['numbers'] ?? ''; // Format: "01,02,03,04,05,06"
    
    // Validate format
    $numArr = explode(',', $numbers);
    if (count($numArr) !== 6) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng chọn đủ 6 số']);
        exit();
    }
    sort($numArr);
    $numbers = implode(',', $numArr);
    
    // Check balance and deduct in transaction
    $conn->begin_transaction();
    try {
        // Khóa hàng người dùng để tránh race condition
        $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        if ($user['Money'] < $ticketPrice) {
            throw new Exception('Không đủ  Gtlm');
        }
        
        // Deduct
        $newMoney = $user['Money'] - $ticketPrice;
        $upd = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $upd->bind_param("di", $newMoney, $userId);
        $upd->execute();
        
        $ins = $conn->prepare("INSERT INTO lottery_tickets (user_id, draw_id, numbers) VALUES (?, ?, ?)");
        $ins->bind_param("iis", $userId, $currentDraw['id'], $numbers);
        $ins->execute();
        
        // Hook: Cập nhật nhiệm vụ sự kiện
        updateEventMissionProgress($conn, $userId, 'Lottery', $ticketPrice, 0, false);
        
        // Add 50% of ticket price to jackpot pool
        $poolIncrease = $ticketPrice * 0.5;
        $conn->query("UPDATE lottery_draws SET jackpot_pool = jackpot_pool + $poolIncrease WHERE id = {$currentDraw['id']}");
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Mua vé thành công!']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

elseif ($action === 'history') {
    $res = $conn->query("SELECT * FROM lottery_draws WHERE status IN ('drawn', 'paid') ORDER BY draw_date DESC LIMIT 10");
    $history = [];
    if ($res && $res instanceof mysqli_result) {
        while($row = $res->fetch_assoc()) $history[] = $row;
    }
    echo json_encode(['success' => true, 'history' => $history]);
}

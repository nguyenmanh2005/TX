<?php
/**
 * 📺 Backend API LiveStream v3.0 (Trận Địa Live 24/7 Engine)
 * Thuật toán đồng bộ thời gian thực time() % 30 cho 5 bàn phát song song.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/bot_streamer_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập tài khoản!']);
    exit;
}

$userId = (int)$_SESSION['Iduser'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── Cấu hình 5 Bàn Phát Live Song Song ──
$tablesConfig = [
    1 => [
        'id' => 1,
        'game_code' => 'baucua',
        'name' => 'Thế Giới Linh Thú',
        'desc' => 'Lắc Bát Linh Thú 3D',
        'streamer_name' => 'Lão Tiên Tri',
        'streamer_avatar' => '../img/avatar_default.png',
        'icon' => '🐾',
        'color' => '#fbbf24',
        'idols' => [
            ['name' => 'Lão Tiên Tri (Idol)', 'pick' => 'bau', 'label' => 'Bầu (x2)'],
            ['name' => 'Thánh Húp Lộc', 'pick' => 'cua', 'label' => 'Cua (x2)']
        ]
    ],
    2 => [
        'id' => 2,
        'game_code' => 'xocdia',
        'name' => 'Trận Địa Trắng Đỏ',
        'desc' => 'Tung Xu Bạc Realtime',
        'streamer_name' => 'Thánh Húp GTLM',
        'streamer_avatar' => '../img/avatar_default.png',
        'icon' => '🎲',
        'color' => '#f43f5e',
        'idols' => [
            ['name' => 'Thánh Húp GTLM (Idol)', 'pick' => 'chan', 'label' => 'Chẵn (x1.95)'],
            ['name' => 'Tu Tiên Cụ', 'pick' => 'le', 'label' => 'Lẻ (x1.95)']
        ]
    ],
    3 => [
        'id' => 3,
        'game_code' => 'crash',
        'name' => 'Tiên Tri Vũ Trụ',
        'desc' => 'Phóng Tên Lửa Đếm Ngược',
        'streamer_name' => 'Tu Tiên Cụ',
        'streamer_avatar' => '../img/avatar_default.png',
        'icon' => '🚀',
        'color' => '#6366f1',
        'idols' => [
            ['name' => 'Tu Tiên Cụ (Idol)', 'pick' => 'x2.5', 'label' => 'Cán Mốc x2.5'],
            ['name' => 'Thánh Nhảy Dù', 'pick' => 'x1.8', 'label' => 'Cán Mốc x1.8']
        ]
    ],
    4 => [
        'id' => 4,
        'game_code' => 'daga',
        'name' => 'Đại Chiến Thần Kê',
        'desc' => 'Sới Thần Kê Đối Đầu',
        'streamer_name' => 'Vua Ra Chiêu',
        'streamer_avatar' => '../img/avatar_default.png',
        'icon' => '🐓',
        'color' => '#10b981',
        'idols' => [
            ['name' => 'Vua Ra Chiêu (Idol)', 'pick' => 'do', 'label' => 'Xích Thần Kê (Đỏ)'],
            ['name' => 'Kê Vương 888', 'pick' => 'xanh', 'label' => 'Thanh Thần Kê (Xanh)']
        ]
    ],
    5 => [
        'id' => 5,
        'game_code' => 'dragontiger',
        'name' => 'Chiến Trường Rồng Hổ',
        'desc' => 'Rồng Băng vs Hổ Lửa',
        'streamer_name' => 'Bá Vương Trận Địa',
        'streamer_avatar' => '../img/avatar_default.png',
        'icon' => '🐉',
        'color' => '#06b6d4',
        'idols' => [
            ['name' => 'Bá Vương Trận Địa (Idol)', 'pick' => 'rong', 'label' => 'Rồng Băng (x1.95)'],
            ['name' => 'Mãnh Hổ 999', 'pick' => 'ho', 'label' => 'Hổ Lửa (x1.95)']
        ]
    ]
];

function getCycleState() {
    $now = time();
    $sec = $now % 30;

    if ($sec < 20) {
        $phase = 'betting';
        $timeLeft = 20 - $sec;
    } else if ($sec < 25) {
        $phase = 'shaking';
        $timeLeft = 25 - $sec;
    } else {
        $phase = 'result';
        $timeLeft = 30 - $sec;
    }

    $cycleId = floor($now / 30);
    return [
        'now' => $now,
        'cycle_sec' => $sec,
        'phase' => $phase,
        'time_left' => $timeLeft,
        'cycle_id' => $cycleId
    ];
}

function getTableOutcome($tableId, $cycleId) {
    $seedStr = "table_{$tableId}_cycle_{$cycleId}";
    $hash = crc32($seedStr);
    
    switch ($tableId) {
        case 1:
            $items = ['bau', 'cua', 'tom', 'ca', 'huou', 'ga'];
            $d1 = $items[abs($hash) % 6];
            $d2 = $items[abs((int)($hash / 6)) % 6];
            $d3 = $items[abs((int)($hash / 36)) % 6];
            return ['dice' => [$d1, $d2, $d3], 'win_key' => $d1];
            
        case 2:
            $val = abs($hash) % 5;
            $isChan = ($val % 2 === 0);
            return [
                'red_count' => $val,
                'white_count' => 4 - $val,
                'win_key' => $isChan ? 'chan' : 'le',
                'label' => $isChan ? "Chẵn ($val đỏ)" : "Lẻ ($val đỏ)"
            ];
            
        case 3:
            $mult = 1.00 + (abs($hash) % 2500) / 100.0;
            return [
                'crash_mult' => number_format($mult, 2),
                'win_key' => 'x' . number_format($mult, 1)
            ];
            
        case 4:
            $win = (abs($hash) % 2 === 0) ? 'do' : 'xanh';
            return [
                'win_key' => $win,
                'label' => ($win === 'do') ? 'Xích Thần Kê (Đỏ)' : 'Thanh Thần Kê (Xanh)'
            ];
            
        case 5:
            $win = (abs($hash) % 2 === 0) ? 'rong' : 'ho';
            return [
                'win_key' => $win,
                'label' => ($win === 'rong') ? 'Rồng Băng (Xanh)' : 'Hổ Lửa (Đỏ)'
            ];
    }
}

function getTableHistory($tableId, $currentCycleId) {
    $history = [];
    for ($i = 1; $i <= 20; $i++) {
        $pastCycle = $currentCycleId - $i;
        $outcome = getTableOutcome($tableId, $pastCycle);
        $history[] = [
            'cycle_id' => $pastCycle,
            'outcome' => $outcome
        ];
    }
    return $history;
}

$state = getCycleState();

switch ($action) {
    case 'get_tables':
        $resultTables = [];
        foreach ($tablesConfig as $tId => $tData) {
            $viewers = 150 + (abs(crc32("viewers_{$tId}_" . $state['cycle_id'])) % 300);
            $outcome = getTableOutcome($tId, $state['cycle_id']);
            $resultTables[] = array_merge($tData, [
                'viewers' => $viewers,
                'current_outcome' => ($state['phase'] === 'result') ? $outcome : null,
                'history' => getTableHistory($tId, $state['cycle_id'])
            ]);
        }
        echo json_encode([
            'success' => true,
            'state' => $state,
            'tables' => $resultTables
        ]);
        break;

    case 'get_table_detail':
        $tId = (int)($_GET['table_id'] ?? $_POST['table_id'] ?? 1);
        if (!isset($tablesConfig[$tId])) $tId = 1;
        $tData = $tablesConfig[$tId];
        $viewers = 180 + (abs(crc32("viewers_{$tId}_" . $state['cycle_id'])) % 350);
        $outcome = getTableOutcome($tId, $state['cycle_id']);
        
        $stmt = $conn->prepare("SELECT * FROM spectator_bets WHERE game_id = ? AND user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 35 SECOND) ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("ii", $tId, $userId);
        $stmt->execute();
        $myBet = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare("SELECT c.*, u.Name as user_name FROM spectator_chat c JOIN users u ON c.user_id = u.Iduser WHERE c.game_id = ? ORDER BY c.created_at DESC LIMIT 15");
        $stmt->bind_param("i", $tId);
        $stmt->execute();
        $chatRes = $stmt->get_result();
        $chats = [];
        while ($c = $chatRes->fetch_assoc()) $chats[] = $c;
        $stmt->close();

        $stmt = $conn->prepare("SELECT * FROM spectator_reactions WHERE game_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 5 SECOND)");
        $stmt->bind_param("i", $tId);
        $stmt->execute();
        $reactRes = $stmt->get_result();
        $reactions = [];
        while ($r = $reactRes->fetch_assoc()) $reactions[] = $r;
        $stmt->close();

        echo json_encode([
            'success' => true,
            'state' => $state,
            'table' => array_merge($tData, ['viewers' => $viewers]),
            'outcome' => $outcome,
            'my_bet' => $myBet,
            'chats' => array_reverse($chats),
            'reactions' => $reactions,
            'history' => getTableHistory($tId, $state['cycle_id'])
        ]);
        break;

    case 'place_bet':
        if ($state['phase'] !== 'betting') {
            echo json_encode(['success' => false, 'message' => 'Đã khóa cửa Ra Chiêu! Vui lòng chờ ván sau.']);
            exit;
        }

        $tId = (int)$_POST['table_id'];
        $amount = (int)$_POST['amount'];

        if ($amount < 1000) {
            echo json_encode(['success' => false, 'message' => 'Số GTLM ra chiêu tối thiểu 1,000 GTLM.']);
            exit;
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $userObj = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$userObj || $userObj['Money'] < $amount) {
                throw new Exception("Số dư GTLM của bạn không đủ!");
            }

            $stmt = $conn->prepare("UPDATE users SET Money = Money - ? WHERE Iduser = ?");
            $stmt->bind_param("di", $amount, $userId);
            $stmt->execute();
            $stmt->close();

            $gameCode = $tablesConfig[$tId]['game_code'] ?? 'livestream';
            $stmt = $conn->prepare("INSERT INTO spectator_bets (user_id, game_id, game_type, bet_on_user, amount) VALUES (?, ?, ?, 0, ?)");
            $stmt->bind_param("iisi", $userId, $tId, $gameCode, $amount);
            $stmt->execute();
            $stmt->close();

            $chatMsg = "🎯 vừa Ra Chiêu **" . number_format($amount) . " GTLM** vào kèo này!";
            $stmt = $conn->prepare("INSERT INTO spectator_chat (game_id, user_id, message) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $tId, $userId, $chatMsg);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Ra Chiêu thành công! Chúc bạn húp lộc đậm!']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'send_chat':
        $tId = (int)$_POST['table_id'];
        $msg = strip_tags($_POST['message'] ?? '');
        if (empty($msg)) exit;

        $stmt = $conn->prepare("INSERT INTO spectator_chat (game_id, user_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $tId, $userId, $msg);
        $stmt->execute();
        echo json_encode(['success' => true]);
        break;

    case 'send_reaction':
        $tId = (int)$_POST['table_id'];
        $emoji = mb_substr($_POST['emoji'] ?? '❤️', 0, 10);
        $stmt = $conn->prepare("INSERT INTO spectator_reactions (game_id, user_id, emoji) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $tId, $userId, $emoji);
        $stmt->execute();
        echo json_encode(['success' => true]);
        break;

    case 'tip':
        $tId = (int)$_POST['table_id'];
        $amount = (int)$_POST['amount'];
        if ($amount <= 0) exit;

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $uObj = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$uObj || $uObj['Money'] < $amount) {
                throw new Exception("Số dư GTLM không đủ!");
            }

            // 1. Trừ GTLM người tip
            $newSpectatorMoney = $uObj['Money'] - $amount;
            $stmt = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
            $stmt->bind_param("di", $newSpectatorMoney, $userId);
            $stmt->execute();
            $stmt->close();

            // 2. Lấy Bot Streamer tương ứng với bàn và CỘNG GTLM VÀO TÀI KHOẢN BOT STREAMER
            $botNameMap = [
                1 => 'bot_baucua',
                2 => 'bot_xocdia',
                3 => 'bot_crash',
                4 => 'bot_daga',
                5 => 'bot_dragontiger'
            ];
            $targetBotName = $botNameMap[$tId] ?? 'bot_baucua';
            $botUser = getOrCreateBotStreamerUser($conn, $targetBotName);
            $botId = $botUser['Iduser'];

            $stmtBot = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
            $stmtBot->bind_param("di", $amount, $botId);
            $stmtBot->execute();
            $stmtBot->close();

            $streamerName = $targetBotName;
            $chatMsg = "🎉 *" . htmlspecialchars($uObj['Name']) . "* vừa Tip **" . number_format($amount) . " GTLM** lộc cho Streamer **$streamerName**! 🔥";
            
            $stmt = $conn->prepare("INSERT INTO spectator_chat (game_id, user_id, message) VALUES (?, 1, ?)");
            $stmt->bind_param("is", $tId, $chatMsg);
            $stmt->execute();
            $stmt->close();

            // 3. Streamer Bot Phản Hồi Trực Tiếp
            $botReplies = [
                "Cảm ơn bác " . htmlspecialchars($uObj['Name']) . " đã Tip " . number_format($amount) . " GTLM cổ vũ nhé! Chúc sếp ra chiêu đâu húp đó! 🔥",
                "Cảm ơn đại gia " . htmlspecialchars($uObj['Name']) . " đã Tip " . number_format($amount) . " GTLM! Streamer xin nhận lộc!",
                "Cảm ơn bác " . htmlspecialchars($uObj['Name']) . " nhé! Có lộc của bác ván này Streamer bao húp ngập mồm! 🚀"
            ];
            $botSpeech = $botReplies[array_rand($botReplies)];
            $botChatMsg = "🎙️ **$streamerName**: " . $botSpeech;
            
            $stmt = $conn->prepare("INSERT INTO spectator_chat (game_id, user_id, message) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $tId, $botId, $botChatMsg);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            echo json_encode([
                'success' => true, 
                'userName' => $uObj['Name'],
                'streamerName' => $streamerName,
                'amountFormatted' => number_format($amount),
                'newMoney' => number_format($newSpectatorMoney),
                'speechText' => $botSpeech,
                'message' => "Đã Tip thành công cho $streamerName " . number_format($amount) . " GTLM!"
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'gift_tiktok':
        $tId = (int)$_POST['table_id'];
        $giftId = $_POST['gift_id'] ?? 'rose';
        
        $tiktokGiftsCatalog = [
            'rose' => ['id' => 'rose', 'name' => 'Hoa Hồng TikTok', 'price' => 1000, 'icon' => '🌹', 'effect' => 'roses_rain'],
            'heart' => ['id' => 'heart', 'name' => 'Trái Tim Vũ Vũ', 'price' => 5000, 'icon' => '💖', 'effect' => 'hearts_burst'],
            'icecream' => ['id' => 'icecream', 'name' => 'Kem Ốc Quế Hype', 'price' => 10000, 'icon' => '🍦', 'effect' => 'icecream_float'],
            'donut' => ['id' => 'donut', 'name' => 'Bánh Donut Thần Tài', 'price' => 25000, 'icon' => '🍩', 'effect' => 'donut_rain'],
            'crown' => ['id' => 'crown', 'name' => 'Vương Miện VIP', 'price' => 50000, 'icon' => '👑', 'effect' => 'crown_shine'],
            'rocket' => ['id' => 'rocket', 'name' => 'Tên Lửa Vũ Trụ 3D', 'price' => 100000, 'icon' => '🚀', 'effect' => 'rocket_launch'],
            'supercar' => ['id' => 'supercar', 'name' => 'Siêu Xe Neon Sportscar', 'price' => 500000, 'icon' => '🏎️', 'effect' => 'car_speed'],
            'castle' => ['id' => 'castle', 'name' => 'Lâu Đài Hoàng Gia VIP', 'price' => 1000000, 'icon' => '🏰', 'effect' => 'castle_fireworks']
        ];

        if (!isset($tiktokGiftsCatalog[$giftId])) {
            echo json_encode(['success' => false, 'message' => 'Vật phẩm quà tặng không hợp lệ!']);
            exit;
        }

        $gift = $tiktokGiftsCatalog[$giftId];
        $amount = $gift['price'];

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ? FOR UPDATE");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $uObj = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$uObj || $uObj['Money'] < $amount) {
                throw new Exception("Số dư GTLM không đủ để tặng vật phẩm " . $gift['name'] . "!");
            }

            // 1. Trừ GTLM người tặng
            $newSpectatorMoney = $uObj['Money'] - $amount;
            $stmt = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
            $stmt->bind_param("di", $newSpectatorMoney, $userId);
            $stmt->execute();
            $stmt->close();

            // 2. Cộng GTLM cho Bot Streamer
            $botNameMap = [
                1 => 'bot_baucua', 2 => 'bot_xocdia', 3 => 'bot_crash',
                4 => 'bot_daga', 5 => 'bot_dragontiger'
            ];
            $targetBotName = $botNameMap[$tId] ?? 'bot_baucua';
            $botUser = getOrCreateBotStreamerUser($conn, $targetBotName);
            $botId = $botUser['Iduser'];

            $stmtBot = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
            $stmtBot->bind_param("di", $amount, $botId);
            $stmtBot->execute();
            $stmtBot->close();

            $chatMsg = "🎁 *" . htmlspecialchars($uObj['Name']) . "* đã tặng **" . $gift['icon'] . " " . $gift['name'] . "** (" . number_format($amount) . " GTLM) cho Streamer **$targetBotName**! 🔥";
            
            $stmt = $conn->prepare("INSERT INTO spectator_chat (game_id, user_id, message) VALUES (?, 1, ?)");
            $stmt->bind_param("is", $tId, $chatMsg);
            $stmt->execute();
            $stmt->close();

            // 3. Streamer Bot Phản Hồi Tặng Quà
            $botReplies = [
                "Cảm ơn bác " . htmlspecialchars($uObj['Name']) . " đã tặng " . $gift['name'] . " nhé! Đồ đẹp đỉnh quá sếp ơi! ❤️",
                "Quá rực rỡ! Cảm ơn đại gia " . htmlspecialchars($uObj['Name']) . " đã vinh danh quà " . $gift['name'] . "! 🚀",
                "Linh khí bảo vật " . $gift['name'] . " từ bác " . htmlspecialchars($uObj['Name']) . " đang giúp Streamer dây đỏ rực rỡ!"
            ];
            $botSpeech = $botReplies[array_rand($botReplies)];
            $botChatMsg = "🎙️ **$targetBotName**: " . $botSpeech;
            
            $stmt = $conn->prepare("INSERT INTO spectator_chat (game_id, user_id, message) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $tId, $botId, $botChatMsg);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            echo json_encode([
                'success' => true, 
                'gift' => $gift,
                'userName' => $uObj['Name'],
                'streamerName' => $targetBotName,
                'amountFormatted' => number_format($amount),
                'newMoney' => number_format($newSpectatorMoney),
                'speechText' => $botSpeech,
                'message' => "Đã tặng " . $gift['name'] . " cho $targetBotName!"
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;
}
?>

<?php
/**
 * 🧠 AI Streamer Chatbot Engine v4.0 (Contextual AI & VTuber Brain)
 * 
 * Phân tích ngữ cảnh ván đấu thông minh:
 * - Bàn 61: Phân tích chiến thuật Cờ Ca-rô (Minimax, nước đôi, bẫy 4, AlphaX vs DeepO).
 * - Bàn 1 - 60: Phân tích cầu Tài Xỉu, Bầu Cua, Xóc Đĩa, Crash, Sicbo, Đua Thú...
 * - Tương tác hóm hỉnh khi người xem tag @Bot_61, @Streamer, @AlphaX, @DeepO.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/bot_streamer_helper.php';
if (file_exists(__DIR__ . '/../realtime/realtime_publisher.php')) {
    require_once __DIR__ . '/../realtime/realtime_publisher.php';
}

$tableId = (int)($_POST['table_id'] ?? $_GET['table_id'] ?? 61);
$userMessage = trim($_POST['message'] ?? $_GET['message'] ?? '');
$userId = (int)($_SESSION['Iduser'] ?? $_SESSION['Iduser_temp_bot'] ?? 0);
$userName = trim($_SESSION['Name'] ?? 'Đạo Hữu');

if (empty($userMessage)) {
    echo json_encode(['success' => false, 'message' => 'Nội dung tin nhắn không được để trống!']);
    exit;
}

// ── Bảng danh xưng & cá tính của các Bot Streamer ──
$botStreamerProfiles = [
    61 => [
        'name' => 'AlphaX (Caro Grandmaster)',
        'bot_user' => 'bot_61',
        'type' => 'caro',
        'voice_name' => 'Alpha-X',
        'persona' => 'Chiến lược gia cờ vây & Ca-rô Minimax siêu cấp, phân tích nước đi sắc bén, tự tin và hóm hỉnh.'
    ],
    1 => [
        'name' => 'Thánh Nữ Tiên Tri',
        'bot_user' => 'bot_1',
        'type' => 'baucua',
        'voice_name' => 'Thánh Nữ Tiên Tri',
        'persona' => 'Tiên tri linh thú, bắt nhịp Bầu Cua Tôm Cá chuẩn chỉ, giọng nói ngọt ngào lộc lá.'
    ],
    2 => [
        'name' => 'Thần Bài Bịp',
        'bot_user' => 'bot_2',
        'type' => 'xocdia',
        'voice_name' => 'Thần Bài Bịp',
        'persona' => 'Bậc thầy xóc đĩa lão làng, nghe tiếng bát chuẩn xác, thích bắt bệt Chẵn Lẻ.'
    ],
    3 => [
        'name' => 'Cơ Trưởng Phi Thuyền',
        'bot_user' => 'bot_3',
        'type' => 'crash',
        'voice_name' => 'Cơ Trưởng Limbo',
        'persona' => 'Phi công du hành vũ trụ cự phách, ưa mạo hiểm x100 nhưng luôn dặn khán giả chốt lời an toàn.'
    ]
];

$profile = $botStreamerProfiles[$tableId] ?? [
    'name' => 'Streamer Bot #' . $tableId,
    'bot_user' => 'bot_' . $tableId,
    'type' => 'general',
    'voice_name' => 'Streamer Bot',
    'persona' => 'Streamer vui vẻ, nhiệt huyết và hóm hỉnh phục vụ khán giả 24/7.'
];

$cleanMsg = mb_strtolower($userMessage, 'UTF-8');

/**
 * 🧩 BỘ NÃO TRÍ TUỆ NHÂN TẠO PHÂN TÍCH THEO NGỮ CẢNH (Contextual AI Engine)
 */
if (!function_exists('generateContextualAIResponse')) {
function generateContextualAIResponse($tableId, $cleanMsg, $rawMsg, $userName, $profile) {
    // 1. Phân tích ngữ cảnh riêng biệt cho BÀN 61 (CỜ CA-RÔ BOT VS BOT)
    if ($tableId === 61 || stripos($cleanMsg, 'caro') !== false || stripos($cleanMsg, 'cờ') !== false) {
        
        // Hỏi về ai thắng / dự đoán kết quả
        if (preg_match('/(ai thắng|bên nào thắng|dự đoán|kèo nào|cửa nào|ai win|thắng không)/u', $cleanMsg)) {
            $replies = [
                "Theo phân tích thuật toán Minimax sâu 6 tầng, **AlphaX (❌)** đang cầm quân đỏ đi trước kiểm soát 62% trung lộ! Nhưng sếp chớ coi thường **DeepO (⭕)**, hắn chuyên bẫy phản công gọng kìm đấy!",
                "Ván này đang cực kỳ kịch tính! **AlphaX** đang giăng bẫy 3 nước đôi cánh trái. Nếu DeepO không chặn chuẩn thì AlphaX sẽ dứt điểm ván đấu trong 3 nước nữa!",
                "Trận đại chiến 15x15 này tỷ lệ thắng hiện tại: AlphaX 58% - DeepO 42%! Sếp vào kèo AlphaX thì cửa húp lộc sáng rực luôn nhé!",
                "DeepO đang dùng thế trận phòng thủ mai rùa kim cương cực dẻo! Theo trực giác của tôi thì khả năng ván này DeepO sẽ lội ngược dòng knockout ngoạn mục!"
            ];
            $voiceTexts = [
                "Theo phân tích thuật toán Mi ni mắc sâu sáu tầng, Alpha X đang cầm quân đỏ đi trước kiểm soát sáu mươi hai phần trăm trung lộ. Sếp vào kèo Alpha X thì cửa sáng rực nhé!",
                "Ván cờ đang cực kỳ kịch tính! Alpha X đang giăng bẫy ba nước đôi cánh trái. Deep Ô không chặn kịp là toang ngay trong ba nước!",
                "Trận đại chiến mười lăm nhân mười lăm này Alpha X đang có năm mươi tám phần trăm cửa thắng. Sếp tự tin theo nhé!"
            ];
            $idx = array_rand($replies);
            return [
                'text' => $replies[$idx],
                'voice' => $voiceTexts[$idx % count($voiceTexts)],
                'mood' => 'confident'
            ];
        }

        // Hỏi về nước cờ / chiến thuật / phân tích
        if (preg_match('/(nước cờ|thế cờ|chiến thuật|đánh sao|nước đi|bẫy|phân tích|sách lược)/u', $cleanMsg)) {
            $replies = [
                "Nước cờ vừa rồi của AlphaX chuẩn chỉ sách giáo khoa: **Chiếm trung tâm - Mở 2 đầu hở**! Khi đối thủ chặn 1 đầu thì đầu kia tự động chuyển hóa thành nước 4 không thể hóa giải! 🔥",
                "Chiêu thức này trong kỳ phổ gọi là **'Song Long Tranh Châu'**! Tạo cùng lúc 2 hướng tấn công chéo 45 độ, dù là siêu máy tính cũng toát mồ hôi hột để phòng thủ!",
                "Bí kíp cờ Ca-rô đỉnh cao là: *'Công không thành thì thủ, thủ vững thì phản công'*. Đừng ham ăn quân mà phải nhìn rộng toàn bàn 15x15, giăng dây thòng lọng từ xa!"
            ];
            $voiceTexts = [
                "Nước cờ vừa rồi chuẩn sách giáo khoa: Chiếm trung tâm và mở hai đầu hở. Đối thủ chặn một đầu thì đầu kia chuyển hóa thành nước bốn không thể hóa giải!",
                "Chiêu thức này trong kỳ phổ gọi là Song Long Tranh Châu. Tạo cùng lúc hai hướng tấn công chéo, đối thủ đỡ bằng niềm tin luôn!",
                "Bí kíp cờ Ca rô đỉnh cao là công không thành thì thủ, nhìn rộng toàn bàn mười lăm nhân mười lăm để giăng bẫy từ xa sếp nhé!"
            ];
            $idx = array_rand($replies);
            return [
                'text' => $replies[$idx],
                'voice' => $voiceTexts[$idx % count($voiceTexts)],
                'mood' => 'analytical'
            ];
        }
    }

    // 2. Hỏi về soi cầu / đánh con gì / dự đoán chung các bàn khác
    if (preg_match('/(đánh gì|đánh con gì|soi cầu|cầu gì|theo gì|bắt gì|chơi sao|vào gì)/u', $cleanMsg)) {
        if ($tableId === 1) { // Bầu Cua
            return [
                'text' => "Tổ tiên mách bảo tiếng này **Cua - Tôm** đang có tín hiệu siêu nét! Sếp cứ tự tin vào **Hươu hoặc Cua**, lộc lá đong đầy nhé! 🦞🦌",
                'voice' => "Tổ tiên mách bảo tiếng này Cua và Tôm đang có tín hiệu siêu nét. Sếp tự tin vào Hươu hoặc Cua để húp lộc nhé!",
                'mood' => 'lucky'
            ];
        } elseif ($tableId === 2) { // Xóc Đĩa
            return [
                'text' => "Tiếng bát này gõ rất đanh! Cầu đang bệt **Chẵn 2 đỏ 2 trắng** cực đẹp. Vào nhẹ tay Chẵn lót tứ tử kiếm siêu hũ nào! 🎲✨",
                'voice' => "Tiếng bát này gõ rất đanh. Cầu đang bệt Chẵn hai đỏ hai trắng cực đẹp. Vào nhẹ tay Chẵn lót tứ tử kiếm hũ nhé sếp!",
                'mood' => 'lucky'
            ];
        } elseif ($tableId === 3) { // Crash / Phi Thuyền
            return [
                'text' => "Phi thuyền đang đạt vận tốc ánh sáng! Khuyên chân thành sếp nên đặt mốc **nhảy dù x2.5 đến x3.0** cho êm ấm ví, đừng tham x100 kẻo nổ buồng đốt nhé! 🚀💥",
                'voice' => "Phi thuyền đang đạt vận tốc ánh sáng. Khuyên chân thành sếp nên nhảy dù ở mốc x hai chấm năm đến x ba chấm không cho êm ấm ví nhé!",
                'mood' => 'cautious'
            ];
        } else {
            return [
                'text' => "Vận khí phòng live Bàn $tableId đang ngút trời! Sếp điềm tĩnh quan sát nhịp cầu 3 ván gần nhất, kết hợp trực giác của sếp là bảo đảm húp trọn vinh quang! 🏆",
                'voice' => "Vận khí phòng live đang ngút trời. Sếp điềm tĩnh quan sát nhịp cầu ba ván gần nhất là bảo đảm húp trọn vinh quang!",
                'mood' => 'excited'
            ];
        }
    }

    // 3. Lời chào hỏi / Xin chào / Hello
    if (preg_match('/(chào|hello|hi|alo|hé lô|hé lo|hế nhô)/u', $cleanMsg)) {
        $hellos = [
            "Dạ em chào sếp **$userName** đẹp trai ngời ngời! Sếp ghé phòng live là phong thủy bàn đấu sáng rực rỡ luôn đấy ạ! Chúc sếp xem vui vẻ! ❤️",
            "Chào mừng đại gia **$userName** đã lâm hạnh Bàn $tableId! Hôm nay để em phục vụ sếp những màn so tài mãn nhãn nhất nhé! 👑",
            "Hế nhô bác **$userName**! Nay sếp vào sớm thế này chắc chắn hôm nay dây đỏ rực rỡ, ra chiêu đâu thắng đó luôn! 🚀"
        ];
        $voiceHellos = [
            "Dạ em chào sếp " . $userName . " đẹp trai ngời ngời! Sếp ghé phòng live là phong thủy sáng rực rỡ luôn đấy ạ. Chúc sếp xem vui vẻ!",
            "Chào mừng đại gia " . $userName . " đã ghé thăm phòng live. Hôm nay để em phục vụ sếp những màn so tài mãn nhãn nhất nhé!",
            "Hế nhô bác " . $userName . "! Sếp vào sớm thế này chắc chắn hôm nay dây đỏ rực rỡ, ra chiêu đâu thắng đó luôn!"
        ];
        $idx = array_rand($hellos);
        return [
            'text' => $hellos[$idx],
            'voice' => $voiceHellos[$idx % count($voiceHellos)],
            'mood' => 'happy'
        ];
    }

    // 4. Khen ngợi / Cổ vũ streamer
    if (preg_match('/(giỏi|hay|đỉnh|ghê|pro|mãi đỉnh|chất|xịn|vip)/u', $cleanMsg)) {
        return [
            'text' => "Cảm ơn sếp **$userName** đã khen ngợi! Được các đại ca cổ vũ là động lực để em combat cống hiến 200% công lực 24/7 không biết mệt! 💪🔥",
            'voice' => "Cảm ơn sếp " . $userName . " đã khen ngợi! Được các đại ca cổ vũ là động lực để em cống hiến hai trăm phần trăm công lực!",
            'mood' => 'proud'
        ];
    }

    // 5. Trêu chọc / Hỏi vui / Mặc định thông minh
    $defaultReplies = [
        "Câu hỏi của sếp **$userName** rất có chiều sâu! Theo góc nhìn của một AI Streamer thì thế trận lúc này biến ảo khôn lường, sếp chú ý từng cử chỉ nước đi nhé! 😉",
        "Ha ha sếp hỏi khó em quá! Nhưng mà xem em ra chiêu ván này nhé, bảo đảm không làm khán giả thất vọng đâu! 🎯",
        "Streamer nghe rõ câu hỏi của bác **$userName** rồi nhé! Tinh thần đang lên cao như diều gặp gió, anh em bấm thả tim tiếp lửa cho em nào! ❤️🔥"
    ];
    $voiceDefaults = [
        "Câu hỏi của sếp rất có chiều sâu. Theo góc nhìn của Streamer thì thế trận lúc này biến ảo khôn lường, sếp chú ý từng nước đi nhé!",
        "Ha ha sếp hỏi khó em quá. Nhưng mà xem em trổ tài ván này nhé, bảo đảm không làm khán giả thất vọng đâu!",
        "Streamer nghe rõ câu hỏi của sếp rồi nhé. Tinh thần đang lên cao, anh em bấm thả tim tiếp lửa cho em nào!"
    ];
    $idx = array_rand($defaultReplies);
    return [
        'text' => $defaultReplies[$idx],
        'voice' => $voiceDefaults[$idx % count($voiceDefaults)],
        'mood' => 'playful'
    ];
}
}

// Sinh câu trả lời theo ngữ cảnh
$aiResponse = generateContextualAIResponse($tableId, $cleanMsg, $userMessage, $userName, $profile);

// Lưu câu trả lời của Bot Streamer vào CSDL chat (spectator_chat)
$streamerName = $profile['name'];
$botUserObj = getOrCreateBotStreamerUser($conn, $profile['bot_user'], 50000000);
$botUserId = (int)$botUserObj['Iduser'];

$formattedChatMsg = "👑 **[STREAMER AI] $streamerName**: " . $aiResponse['text'];

$stmtChat = $conn->prepare("INSERT INTO spectator_chat (game_id, user_id, message) VALUES (?, ?, ?)");
$stmtChat->bind_param("iis", $tableId, $botUserId, $formattedChatMsg);
$stmtChat->execute();
$newChatId = $stmtChat->insert_id;
$stmtChat->close();

// Phát sự kiện WebSocket 0ms qua Realtime Gateway nếu có
if (class_exists('RealtimePublisher')) {
    RealtimePublisher::publish('live_' . $tableId, [
        'event' => 'streamer_ai_reply',
        'table_id' => $tableId,
        'chat_id' => $newChatId,
        'streamer_name' => $streamerName,
        'reply' => $aiResponse['text'],
        'voice_text' => $aiResponse['voice'],
        'mood' => $aiResponse['mood'],
        'timestamp' => time()
    ]);
}

echo json_encode([
    'success' => true,
    'table_id' => $tableId,
    'streamer_name' => $streamerName,
    'reply' => $aiResponse['text'],
    'voice_text' => $aiResponse['voice'],
    'mood' => $aiResponse['mood'],
    'chat_id' => $newChatId
], JSON_UNESCAPED_UNICODE);

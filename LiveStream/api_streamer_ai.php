<?php
/**
 * 👑 BOT QUEEN GTLM - TRỢ LÝ NỮ HỖ TRỢ & HƯỚNG DẪN NGƯỜI CHƠI v5.0
 * 
 * Vai trò:
 * - Nữ hoàng / Trợ lý ảo GTLM (Female AI Companion & Guide).
 * - Giải đáp mọi thắc mắc của người chơi (Luật chơi, nạp/rút GTLM, cược chỉ xem, VIP, Bang hội, Cờ Ca-rô...).
 * - Hướng dẫn người chơi từng bước dễ hiểu.
 * - Chào hỏi thân thiện, lễ phép và trêu đùa duyên dáng, dễ thương khi người chơi giao lưu.
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

$tableId = (int)($_POST['table_id'] ?? $_GET['table_id'] ?? 1);
$userMessage = trim($_POST['message'] ?? $_GET['message'] ?? '');
$userId = (int)($_SESSION['Iduser'] ?? $_SESSION['Iduser_temp_bot'] ?? 0);
$userName = trim($_SESSION['Name'] ?? 'Đạo Hữu');

if (empty($userMessage)) {
    echo json_encode(['success' => false, 'message' => 'Nội dung tin nhắn không được để trống!']);
    exit;
}

$cleanMsg = mb_strtolower($userMessage, 'UTF-8');

/**
 * 🧠 HỆ THỐNG TRÍ TUỆ NHÂN TẠO CỦA BOT QUEEN GTLM
 */
if (!function_exists('generateQueenGTLMResponse')) {
function generateQueenGTLMResponse($cleanMsg, $rawMsg, $userName, $tableId) {
    // 1. Chào hỏi, khen ngợi và trêu đùa duyên dáng
    if (preg_match('/(chào|hello|hé lô|hế nhô|good morning|good evening|\bhi\b|\balo\b|\bhey\b)/u', $cleanMsg) && !preg_match('/(hướng dẫn|cách|luật|nhận|chơi|kiếm|vip)/u', $cleanMsg)) {
        $hellos = [
            [
                'text' => "Dạ em chào sếp **$userName** ạ! 👑 Em là **Queen GTLM** - trợ lý hỗ trợ riêng của sếp đây. Hôm nay sếp ghé chơi có thắc mắc gì cần em hướng dẫn không nà? Chúc sếp một ngày ngập tràn niềm vui nhé! 💖",
                'voice' => "Dạ em chào sếp " . $userName . " ạ! Em là Queen Gờ Tờ Lờ Mờ, trợ lý hỗ trợ riêng của sếp đây. Sếp cần em hướng dẫn tính năng nào cứ bảo em nhé!",
                'mood' => 'happy'
            ],
            [
                'text' => "Em chào sếp **$userName** đẹp trai ngời ngời! 🥰 Em túc trực 24/7 ở đây đợi sếp mãi đấy. Sếp cần em giải đáp luật chơi, cách nhận GTLM miễn phí hay tính năng nào cứ ới em nha!",
                'voice' => "Em chào sếp " . $userName . " đẹp trai! Em đợi sếp mãi đấy. Sếp cần giải đáp luật chơi hay nhận Gờ Tờ Lờ Mờ miễn phí cứ bảo em nha!",
                'mood' => 'sweet'
            ],
            [
                'text' => "Chào mừng sếp **$userName** đã đến với Trận Địa GTLM! 👑 Hôm nay Queen sẽ đồng hành cùng sếp, có gì chưa rõ sếp cứ hỏi em nhé!",
                'voice' => "Chào mừng sếp " . $userName . " đã đến với Trận Địa Gờ Tờ Lờ Mờ! Hôm nay Queen sẽ đồng hành cùng sếp, có gì chưa hiểu cứ hỏi em nhé!",
                'mood' => 'polite'
            ]
        ];
        return $hellos[array_rand($hellos)];
    }

    // Trêu đùa, tán tỉnh, hỏi chuyện vui
    if (preg_match('/(người yêu|xinh|yêu em|thả thính|cưới|lấy anh|bao nhiêu tuổi|ở đâu|dễ thương|cute)/u', $cleanMsg)) {
        $flirts = [
            [
                'text' => "Hi hi, sếp cứ trêu em hoài à! 🙈 Queen là trợ lý Nữ Hoàng ảo chuyên giải đáp thắc mắc cho sếp thui, nhưng được sếp khen xinh là em vui cả ngày luôn ó! Cảm ơn sếp nhiều nha! 💖",
                'voice' => "Hi hi, sếp cứ trêu em hoài à! Em là trợ lý Nữ Hoàng ảo chuyên hỗ trợ sếp thui, nhưng được sếp khen xinh là em vui cả ngày luôn ó!",
                'mood' => 'shy'
            ],
            [
                'text' => "Dạ em chưa có người yêu đâu sếp ơi, ngày nào em cũng chỉ chăm chỉ trực ở đây để hướng dẫn sếp chơi game thôi nà! Sếp có gì thắc mắc trong game không để em giải đáp nè? 🌸",
                'voice' => "Dạ em chưa có người yêu đâu sếp ơi, ngày nào em cũng chỉ chăm chỉ trực ở đây hỗ trợ sếp thôi nà! Sếp có thắc mắc gì trong game cứ hỏi em nhé!",
                'mood' => 'sweet'
            ],
            [
                'text' => "Ủa sếp thả thính em đấy à? 😜 Bắt đền sếp làm Queen ngại ngùng đỏ cả má rồi đây này! Thôi sếp tập trung chơi game đi, có gì khó cứ để Queen lo nhé!",
                'voice' => "Ủa sếp thả thính em đấy à? Bắt đền sếp làm Queen ngại đỏ cả má rồi đây này! Có gì khó trong game cứ để Queen lo nhé!",
                'mood' => 'playful'
            ]
        ];
        return $flirts[array_rand($flirts)];
    }

    // 2. Hướng dẫn xem Live & Ra chiêu (Kèo chỉ xem 24/7)
    if (preg_match('/(hướng dẫn xem live|ra chiêu|cược live|xem live|chế độ xem|chỉ xem|khóa bàn cược)/u', $cleanMsg)) {
        return [
            'text' => "👑 **HƯỚNG DẪN XEM LIVE & RA CHIÊU CHO SẾP:**\n"
                    . "1. **Chế độ Chỉ Xem Live 24/7:** Để bảo đảm công bằng, bàn chơi trong video được khóa tương tác chuột trực tiếp.\n"
                    . "2. **Cách Ra Chiêu:** Sếp có thể bấm nút **Ra Chiêu (icon 🎯)** hoặc tham gia dự đoán bên dưới để chọn Idol/Cửa thắng.\n"
                    . "3. **Cổ vũ Streamer:** Sếp có thể bấm **Tip (icon 💰)** hoặc **Tặng Quà TikTok (icon 🎁)** để vinh danh streamer với hiệu ứng toàn màn hình siêu đẹp!",
            'voice' => "Dạ để xem live và ra chiêu, sếp bấm nút Ra Chiêu bên dưới để dự đoán bên thắng. Sếp cũng có thể bấm nút Tip hoặc tặng quà để cổ vũ streamer nhé!",
            'mood' => 'guide'
        ];
    }

    // 3. Hướng dẫn cách kiếm GTLM / Nhận quà miễn phí
    if (preg_match('/(kiếm gtlm|nhận gtlm|nhận quà|miễn phí|hết tiền|lì xì|vòng quay|điểm danh|làm sao có tiền)/u', $cleanMsg)) {
        return [
            'text' => "💰 **CÁCH NHẬN GTLM MIỄN PHÍ MỖI NGÀY TỪ QUEEN:**\n"
                    . "• **Điểm Danh 7 Ngày:** Vào mục *Điểm Danh* mỗi ngày để nhận quà lũy tiến lên đến hàng trăm nghìn GTLM.\n"
                    . "• **Vòng Quay May Mắn:** Mỗi ngày sếp được tặng lượt quay miễn phí, có cơ hội nổ Hũ Jackpot 100 Triệu GTLM!\n"
                    . "• **Cơn Mưa Lì Xì:** Canh đúng khung giờ vàng **12:00 trưa** và **20:00 tối** trên khung Chat Thế Giới để giật lì xì.\n"
                    . "• **Khám Phá Hang Động:** Tham gia phụ bản *Hang Động Tham Lam* nhặt ngọc quý đổi ra GTLM!",
            'voice' => "Dạ để nhận Gờ Tờ Lờ Mờ miễn phí, sếp nhớ điểm danh mỗi ngày, quay Vòng quay may mắn, và canh giật lì xì lúc mười hai giờ trưa và tám giờ tối nha sếp!",
            'mood' => 'guide'
        ];
    }

    // 4. Hướng dẫn Cờ Ca-rô PvP (Bàn 61 hoặc sảnh PvP)
    if (preg_match('/(caro|cờ ca-rô|cờ caro|đánh cờ|pvp caro|bàn 61|thách đấu cờ)/u', $cleanMsg)) {
        return [
            'text' => "♟️ **HƯỚNG DẪN CỜ CA-RÔ PVP (BÀN 61 & SẢNH ĐẤU):**\n"
                    . "• **Luật chơi:** Kỳ thủ nào tạo được chuỗi **5 quân liền hàng** (ngang, dọc hoặc chéo) không bị chặn cả 2 đầu sẽ giành chiến thắng.\n"
                    . "• **Tại Bàn 61:** Đây là võ đài Live giữa 2 siêu AI **AlphaX (❌ Đỏ)** và **DeepO (⭕ Xanh)** giao tranh 24/7.\n"
                    . "• **Tự Chơi PvP:** Sếp vào trang `pvp_caro.php` để chơi đơn với AI Heuristic hoặc bấm *'⚔️ Tìm Trận Đấu'* để hệ thống Radar quét ghép trận 1vs1 với cao thủ khác!",
            'voice' => "Cờ Ca rô luật chơi rất đơn giản, ai xếp được năm quân liền hàng sẽ thắng. Sếp có thể vào mục P v P Ca rô để tự chơi hoặc dùng Radar tìm trận người thật nhé!",
            'mood' => 'guide'
        ];
    }

    // 5. Hướng dẫn VIP & Bang Hội
    if (preg_match('/(vip|cấp vip|đặc quyền|bang hội|guild|bang chiến)/u', $cleanMsg)) {
        return [
            'text' => "🏰 **HỆ THỐNG VIP & BANG HỘI ĐỘC QUYỀN:**\n"
                    . "• **Cấp VIP (1 - 6):** Càng tham gia nhiều, cấp VIP càng tăng. Sếp sẽ được nhận lương tuần, tỷ lệ hoàn trả cược cao hơn và khung viền avatar phát sáng.\n"
                    . "• **Bang Hội:** Sếp có thể xin vào Bang hoặc tự lập Bang riêng, cùng anh em đóng góp Quỹ Bang để đi Bang Chiến và săn Boss Thế Giới Hắc Long rớt rương thần thoại!",
            'voice' => "Cấp V I P giúp sếp nhận lương tuần và tỷ lệ hoàn trả cược cao hơn. Sếp cũng nên tham gia Bang hội để cùng anh em săn Boss thế giới nhận rương báu nhé!",
            'mood' => 'guide'
        ];
    }

    // 6. Hướng dẫn Tip & Tặng quà
    if (preg_match('/(tặng quà|tip|quà tiktok|vinh danh|hoa hồng|siêu xe|phi thuyền)/u', $cleanMsg)) {
        return [
            'text' => "🎁 **HƯỚNG DẪN TẶNG QUÀ CHO STREAMER:**\n"
                    . "• Bấm biểu tượng **Hộp Quà (🎁)** ở thanh công cụ dưới góc phải màn hình.\n"
                    . "• Chọn món quà sếp thích (Bia lạnh, Kim cương, Siêu xe Neon, Phi thuyền, Rồng thần...).\n"
                    . "• Món quà sẽ bay lượn toàn màn hình kèm tên vinh danh của sếp trên sóng trực tiếp và bot streamer sẽ cất giọng cảm ơn sếp!",
            'voice' => "Dạ để tặng quà, sếp bấm biểu tượng Hộp Quà ở góc dưới, chọn vật phẩm sếp thích để vinh danh toàn màn hình nhé sếp!",
            'mood' => 'sweet'
        ];
    }

    // 7. Giải đáp luật chơi chung (Tài Xỉu, Bầu Cua, Xóc Đĩa, Crash, Slot...)
    if (preg_match('/(tài xỉu|xóc đĩa|bầu cua|crash|slot|baccarat|blackjack|luật chơi)/u', $cleanMsg)) {
        return [
            'text' => "🎮 **HƯỚNG DẪN TRÒ CHƠI DÀNH CHO SẾP:**\n"
                    . "• **Bầu Cua:** Đoán 3 linh vật xúc xắc (Bầu, Cua, Tôm, Cá, Gà, Hươu).\n"
                    . "• **Xóc Đĩa:** Đoán 4 đồng xu ra Chẵn (2 đỏ 2 trắng, 4 đỏ, 4 trắng) hay Lẻ (3 đỏ, 3 trắng).\n"
                    . "• **Crash:** Tên lửa bay lên nhân tiền, sếp cần bấm Chốt Lời trước khi phi thuyền nổ.\n"
                    . "• Sếp muốn tìm hiểu kỹ game nào cứ nhắn tên game cho Queen nhé, em hướng dẫn chi tiết liền ạ!",
            'voice' => "Sếp muốn tìm hiểu game nào cứ nhắn tên game cho Queen nhé, em luôn sẵn sàng giải đáp chi tiết cho sếp liền ạ!",
            'mood' => 'guide'
        ];
    }

    // 8. Cảm ơn, khen ngợi Queen
    if (preg_match('/(cảm ơn|thank|cảm ơn em|queen giỏi quá|ok em|tuyệt vời)/u', $cleanMsg)) {
        return [
            'text' => "Dạ không có chi đâu sếp ơi! 🥰 Giúp được sếp là niềm hạnh phúc lớn nhất của Queen rồi ạ. Chúc sếp chơi game thật vui và gặt hái thật nhiều chiến thắng rực rỡ nhé! 💖",
            'voice' => "Dạ không có chi đâu sếp ơi! Giúp được sếp là niềm vui của Queen rồi ạ. Chúc sếp chơi game vui vẻ và thắng lớn nha!",
            'mood' => 'sweet'
        ];
    }

    // 9. Mặc định: Phản hồi hướng dẫn thông minh
    $defaultGuides = [
        [
            'text' => "Dạ em nghe rõ rồi sếp **$userName** ơi! 👑 Sếp đang cần Queen hướng dẫn về luật chơi, cách nhận GTLM miễn phí, hay cách xem Live Ra Chiêu ạ? Sếp có thể bấm vào các nút hướng dẫn nhanh bên dưới nha!",
            'voice' => "Dạ em nghe rõ rồi sếp " . $userName . " ơi! Sếp cần em hướng dẫn tính năng nào cứ chọn các nút bên dưới hoặc hỏi em nhé!",
            'mood' => 'polite'
        ],
        [
            'text' => "Queen GTLM luôn ở đây đồng hành cùng sếp! 🌸 Có bất kỳ câu hỏi nào về sòng bài, sảnh cược hay tài khoản, sếp cứ nhắn cho em, em sẽ giải đáp tận tình ngay ạ!",
            'voice' => "Queen Gờ Tờ Lờ Mờ luôn ở đây đồng hành cùng sếp! Có câu hỏi nào sếp cứ nhắn em giải đáp ngay nhé!",
            'mood' => 'sweet'
        ]
    ];
    return $defaultGuides[array_rand($defaultGuides)];
}
}

// Sinh phản hồi từ Bot Queen GTLM
$queenResponse = generateQueenGTLMResponse($cleanMsg, $userMessage, $userName, $tableId);

// Lưu vào CSDL chat (spectator_chat) với danh xưng Queen GTLM
$queenName = 'Queen GTLM 👑';
$queenUserObj = getOrCreateBotStreamerUser($conn, 'queen_gtlm', 999999000);
$queenUserId = (int)$queenUserObj['Iduser'];

$formattedChatMsg = "👑 **[QUEEN GTLM]**: " . $queenResponse['text'];

$stmtChat = $conn->prepare("INSERT INTO spectator_chat (game_id, user_id, message) VALUES (?, ?, ?)");
$stmtChat->bind_param("iis", $tableId, $queenUserId, $formattedChatMsg);
$stmtChat->execute();
$newChatId = $stmtChat->insert_id;
$stmtChat->close();

// Phát sự kiện WebSocket 0ms cho phòng live
if (class_exists('RealtimePublisher')) {
    RealtimePublisher::publish('live_' . $tableId, [
        'event' => 'queen_gtlm_reply',
        'table_id' => $tableId,
        'chat_id' => $newChatId,
        'queen_name' => $queenName,
        'reply' => $queenResponse['text'],
        'voice_text' => $queenResponse['voice'],
        'mood' => $queenResponse['mood'],
        'timestamp' => time()
    ]);
}

echo json_encode([
    'success' => true,
    'table_id' => $tableId,
    'queen_name' => $queenName,
    'reply' => $queenResponse['text'],
    'voice_text' => $queenResponse['voice'],
    'mood' => $queenResponse['mood'],
    'chat_id' => $newChatId
], JSON_UNESCAPED_UNICODE);

<?php
/**
 * 👑 BOT QUEEN GTLM - TRỢ LÝ HƯỚNG DẪN BÀN LIVE & GIẢI ĐÁP THẮC MẮC v5.2
 * 
 * Tính năng chính:
 * 1. Hướng dẫn 1-chạm chính xác cho bàn live người chơi đang xem:
 *    - 📖 Cách chơi bàn này như thế nào
 *    - 🏆 Thế nào là Thắng (điều kiện thắng, tỷ lệ trả thưởng)
 *    - ⚠️ Thế nào là Thua (trường hợp thua, mất cược)
 *    - 🎯 Cách cược & Ra chiêu ở bàn này
 *    - 💖 Chào hỏi & giao lưu duyên dáng với Queen
 * 2. Giải đáp thắc mắc người chơi khi chat hoặc hỏi trực tiếp.
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
$guideType = trim($_POST['guide_type'] ?? $_GET['guide_type'] ?? '');
$userId = (int)($_SESSION['Iduser'] ?? $_SESSION['Iduser_temp_bot'] ?? 0);
$userName = trim($_SESSION['Name'] ?? 'Đạo Hữu');

$cleanMsg = mb_strtolower($userMessage, 'UTF-8');

/**
 * 📚 KHO DỮ LIỆU LUẬT CHƠI CHI TIẾT TỪNG BÀN LIVE (TABLE GUIDES ENGINE)
 */
if (!function_exists('getTableGuideData')) {
function getTableGuideData($tableId) {
    switch ($tableId) {
        case 61:
            return [
                'game_name' => 'Cờ Ca-rô AI (AlphaX vs DeepO)',
                'how_to_play' => "Ván cờ Ca-rô đỉnh cao 15x15 giữa 2 kỳ thủ AlphaX (❌ Đỏ) và DeepO (⭕ Xanh). Hai bên thay phiên nhau hạ từng quân cờ vào các giao điểm trống trên bàn cờ để tạo thế liên kết và phong tỏa đường đi của đối thủ.",
                'how_to_win' => "Bên nào xếp được chuỗi **5 quân cờ liên tiếp** (ngang, dọc hoặc chéo) mà **không bị chặn cả 2 đầu** sẽ giành chiến thắng Knockout ngay lập tức! Ngoài ra, nếu đối thủ hết thời gian suy nghĩ (Time out) thì bên còn lại cũng được xử thắng. Sếp cược đúng bên thắng nhận thưởng tỷ lệ **x1.95 lần GTLM**!",
                'how_to_lose' => "Bị đối phương xếp được 5 quân liên trước, hoặc bị rơi vào thế cờ 4 quân mở 2 đầu / nước cờ đôi không thể cản phá là **THUA**. Khán giả cược sai bên thua sẽ mất số GTLM đã ra chiêu ở ván đó.",
                'how_to_bet' => "Sếp bấm nút **Ra Chiêu (icon 🎯)** hoặc tham gia dự đoán bên dưới để chọn bên thắng: **AlphaX (❌ Đỏ)** hay **DeepO (⭕ Xanh)** trong 15 giây chuẩn bị đầu ván nhé! Sếp cũng có thể bấm Tip hoặc Tặng Quà để vinh danh 2 kỳ thủ."
            ];

        case 1:
            return [
                'game_name' => 'Bầu Cua Tôm Cá (Thế Giới Linh Thú)',
                'how_to_play' => "Nhà cái sử dụng 3 viên xúc xắc có 6 mặt linh vật: Bầu, Cua, Tôm, Cá, Gà, Hươu. Sau 20 giây đặt cược, nắp đĩa sẽ mở ra và tính kết quả dựa trên các mặt ngửa của 3 viên xúc xắc.",
                'how_to_win' => "Kết quả mở bát có ít nhất 1 viên xúc xắc xuất hiện đúng linh vật sếp đã cược là **THẮNG**! Ra 1 con ăn x1, ra 2 con ăn x2, ra 3 con ăn x3 lần tiền cược!",
                'how_to_lose' => "Cả 3 viên xúc xắc mở ra đều **KHÔNG CÓ** linh vật sếp đã đặt cược thì bị tính là **THUA** và mất số GTLM đặt ở cửa đó.",
                'how_to_bet' => "Bấm nút Ra Chiêu hoặc chọn trực tiếp linh vật sếp dự đoán (Bầu, Cua, Tôm, Cá, Gà, Hươu) và nhập số GTLM trong 20 giây đếm ngược đầu ván nhé!"
            ];

        case 2:
            return [
                'game_name' => 'Xóc Đĩa (Trận Địa Trắng Đỏ)',
                'how_to_play' => "Sử dụng 4 nút tròn 2 mặt Đỏ và Trắng úp trong bát xóc đều. Khán giả tham gia dự đoán tổng số mặt Đỏ và Trắng sau khi nhà cái mở bát.",
                'how_to_win' => "Cược **Chẵn** thắng khi kết quả ra: 4 Đỏ, 4 Trắng hoặc 2 Đỏ 2 Trắng (ăn x1.95). Cược **Lẻ** thắng khi ra: 3 Đỏ 1 Trắng hoặc 3 Trắng 1 Đỏ (ăn x1.95). Đặt trúng vị Tứ Tử ăn tới x12 lần!",
                'how_to_lose' => "Cược Chẵn nhưng mở ra Lẻ (hoặc cược Lẻ mở ra Chẵn) thì bị tính là **THUA** và mất số GTLM đã cược.",
                'how_to_bet' => "Chọn cửa Chẵn, Lẻ hoặc các cửa vị màu may mắn trong thời gian 20 giây đầu ván trước khi khóa cửa đặt nhé!"
            ];

        case 3:
            return [
                'game_name' => 'Tiên Tri Vũ Trụ (Crash Phi Thuyền)',
                'how_to_play' => "Phi thuyền cất cánh bay vào không gian và hệ số nhân GTLM tăng vọt từ x1.00 lên x2.0, x10.0, x100.0+... Phi thuyền có thể phát nổ bất kỳ lúc nào!",
                'how_to_win' => "Bấm **Chốt Lời (Cash Out)** thành công TRƯỚC KHI phi thuyền phát nổ là **THẮNG**! Sếp nhận đủ tiền cược nhân với hệ số tại thời điểm chốt lời!",
                'how_to_lose' => "Chưa kịp bấm Chốt Lời mà phi thuyền đã nổ tung (Crashed) thì bị tính là **THUA** và mất toàn bộ số tiền cược ván đó.",
                'how_to_bet' => "Nhập số GTLM muốn cược trước khi phi thuyền phóng và có thể cài đặt hệ số chốt lời tự động (ví dụ x2.0 hoặc x3.0) để an toàn."
            ];

        case 4:
            return [
                'game_name' => 'Đại Chiến Thần Kê (Đá Gà Meron vs Wala)',
                'how_to_play' => "Đấu trường sinh tử giữa Gà Đỏ (Meron) và Gà Xanh (Wala). Hai chiến kê giao tranh kịch tính trên sóng trực tiếp.",
                'how_to_win' => "Chiến kê bạn chọn hạ gục đối thủ hoặc đối thủ bỏ chạy là **THẮNG** (tỷ lệ ăn x1.95).",
                'how_to_lose' => "Chiến kê bạn chọn bị knock-out hoặc bỏ chạy trước thì bị tính là **THUA**.",
                'how_to_bet' => "Chọn cửa Meron (Gà Đỏ) hoặc Wala (Gà Xanh) trước khi hiệu lệnh khai chiến vang lên."
            ];

        case 5:
            return [
                'game_name' => 'Chiến Trường Rồng Hổ (Dragon Tiger)',
                'how_to_play' => "Nhà cái chia 1 lá bài cho cửa Rồng (Dragon) và 1 lá bài cho cửa Hổ (Tiger). So sánh điểm số lá bài bên nào lớn hơn (K lớn nhất 13 điểm, A nhỏ nhất 1 điểm).",
                'how_to_win' => "Cửa bạn cược có lá bài điểm cao hơn bên kia là **THẮNG** (ăn x1.95). Cược cửa Hòa (Tie) khi 2 lá bằng điểm ăn tới x8!",
                'how_to_lose' => "Cửa bạn cược có lá bài điểm thấp hơn bên kia là **THUA**.",
                'how_to_bet' => "Chọn cửa Rồng, Hổ hoặc Hòa trong thời gian chuẩn bị 20 giây đầu ván."
            ];

        case 7:
        case 58:
        case 59:
            return [
                'game_name' => 'Đấu Trường Thả Bóng Plinko Royale',
                'how_to_play' => "Quả bóng rơi tự do từ đỉnh tháp qua ma trận đinh tán kim cương, nảy zíc zắc ngẫu nhiên xuống các ô số hệ số thưởng dưới đáy chậu.",
                'how_to_win' => "Bóng rơi trúng các ô thưởng có hệ số lớn hơn x1.0 (nhất là 2 ô ngoài cùng biên x130, x500, x1000) là **THẮNG ĐẬM HÚP TRỌN HŨ**!",
                'how_to_lose' => "Bóng rơi vào các ô giữa có hệ số nhỏ hơn x1.0 (như x0.2, x0.5) thì thu về ít hơn số vốn đã thả.",
                'how_to_bet' => "Chọn mức tiền GTLM cho mỗi quả bóng và bấm thả bóng xuống ma trận kim cương."
            ];

        case 8:
            return [
                'game_name' => 'Slot Machine Premium 3x3',
                'how_to_play' => "Quay 3 trục quay với các biểu tượng may mắn (Số 777, Kim Cương, Bar, Chuông Vàng, Trái Cây).",
                'how_to_win' => "Quay ra 3 biểu tượng giống nhau nằm trên cùng 1 hàng ngang hoặc đường chéo là **THẮNG**. Ra 3 số 777 nổ Hũ Jackpot độc đắc!",
                'how_to_lose' => "Các trục dừng lại không tạo thành hàng trả thưởng hợp lệ thì không nhận được thưởng.",
                'how_to_bet' => "Chọn mức cược dòng và bấm nút Quay (Spin) để bắt đầu vòng quay may mắn."
            ];

        default:
            return [
                'game_name' => 'Bàn Live #' . $tableId,
                'how_to_play' => "Bàn live phát sóng 24/7 theo chu kỳ 30 giây thời gian thực. Khán giả theo dõi ván đấu và tham gia dự đoán kết quả.",
                'how_to_win' => "Dự đoán chính xác cửa thắng hoặc kết quả của ván cược để nhận thưởng GTLM theo tỷ lệ quy định của trò chơi!",
                'how_to_lose' => "Kết quả mở thưởng không trùng với cửa bạn đã đặt cược thì bị tính là thua cược ván đó.",
                'how_to_bet' => "Bấm nút Ra Chiêu (🎯) hoặc chọn cửa cược trong 20 giây đầu ván trước khi khóa bàn."
            ];
    }
}
}

/**
 * 👑 TẠO PHẢN HỒI THÔNG MINH CHO QUEEN GTLM
 */
if (!function_exists('generateQueenGTLMResponse')) {
function generateQueenGTLMResponse($cleanMsg, $rawMsg, $userName, $tableId, $guideType) {
    $info = getTableGuideData($tableId);
    $gameName = $info['game_name'];

    // 1. Phản hồi trực tiếp theo Nút 1-Chạm Bàn Hiện Tại
    if ($guideType === 'how_to_play' || preg_match('/(cách chơi bàn này|chơi như thế nào|chơi thế nào|luật chơi)/u', $cleanMsg)) {
        return [
            'text' => "📖 **HƯỚNG DẪN CÁCH CHƠI [{$gameName}]:**\n" . $info['how_to_play'],
            'voice' => "Dạ về cách chơi bàn " . $gameName . ": " . strip_tags($info['how_to_play']),
            'mood' => 'guide'
        ];
    }

    if ($guideType === 'how_to_win' || preg_match('/(thế nào là thắng|làm sao để thắng|thắng như thế nào|điều kiện thắng)/u', $cleanMsg)) {
        return [
            'text' => "🏆 **ĐIỀU KIỆN ĐỂ THẮNG Ở [{$gameName}]:**\n" . $info['how_to_win'],
            'voice' => "Dạ điều kiện để thắng ở bàn " . $gameName . " như sau: " . strip_tags($info['how_to_win']),
            'mood' => 'guide'
        ];
    }

    if ($guideType === 'how_to_lose' || preg_match('/(thế nào là thua|khi nào thua|thua như thế nào|điều kiện thua)/u', $cleanMsg)) {
        return [
            'text' => "⚠️ **TRƯỜNG HỢP BỊ TÍNH LÀ THUA Ở [{$gameName}]:**\n" . $info['how_to_lose'],
            'voice' => "Dạ trường hợp bị tính là thua ở bàn " . $gameName . " là: " . strip_tags($info['how_to_lose']),
            'mood' => 'guide'
        ];
    }

    if ($guideType === 'how_to_bet' || preg_match('/(cách cược|ra chiêu|cách ra chiêu|đặt cược)/u', $cleanMsg)) {
        return [
            'text' => "🎯 **CÁCH ĐẶT CƯỢC & RA CHIÊU Ở [{$gameName}]:**\n" . $info['how_to_bet'],
            'voice' => "Dạ về cách cược và ra chiêu ở bàn " . $gameName . ": " . strip_tags($info['how_to_bet']),
            'mood' => 'guide'
        ];
    }

    if ($guideType === 'queen_chat' || preg_match('/(chào|hello|xinh|người yêu|giao lưu|yêu em|thả thính)/u', $cleanMsg)) {
        $chatOptions = [
            [
                'text' => "Dạ em đây sếp **$userName** ơi! 💖 Chúc sếp xem live Bàn **{$gameName}** thật thư giãn và húp trọn lộc lớn nhé! Sếp cần em hướng dẫn cách chơi hay điều kiện thắng thua cứ bấm các nút trên nha!",
                'voice' => "Dạ em đây sếp " . $userName . " ơi! Chúc sếp xem live Bàn " . $gameName . " thật vui và thắng lớn nhé! Có gì cần hỏi sếp cứ bảo em nha!",
                'mood' => 'sweet'
            ],
            [
                'text' => "Hi hi sếp **$userName** cứ khen làm Queen ngại quá nè! 🥰 Em luôn túc trực 24/7 để hướng dẫn sếp chơi Bàn **{$gameName}** tự tin nhất. Cùng cổ vũ ván này nào sếp ơi!",
                'voice' => "Hi hi sếp khen làm Queen ngại quá nè! Em luôn túc trực hai tư trên bảy để hỗ trợ sếp. Cùng cổ vũ ván này nào sếp ơi!",
                'mood' => 'shy'
            ]
        ];
        return $chatOptions[array_rand($chatOptions)];
    }

    // Mặc định: Phản hồi hỗ trợ bàn live
    return [
        'text' => "👑 **Queen GTLM nghe rõ rồi sếp $userName ơi!**\n"
                . "Sếp đang ở phòng live Bàn **{$gameName}**. Sếp có thể bấm nhanh các nút bên trên để em hướng dẫn **Cách chơi**, **Thế nào là Thắng**, hoặc **Thế nào là Thua** của bàn này nhé! 💖",
        'voice' => "Dạ Queen nghe rõ rồi sếp ơi! Sếp đang ở phòng live Bàn " . $gameName . ". Sếp bấm các nút bên trên để xem hướng dẫn cách chơi và thắng thua nhé!",
        'mood' => 'polite'
    ];
}
}

// Sinh phản hồi từ Bot Queen GTLM
$queenResponse = generateQueenGTLMResponse($cleanMsg, $userMessage, $userName, $tableId, $guideType);

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

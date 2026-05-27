<?php
/**
 * 🔮 API NPC Oracle v2.0 - Lão Tiên Tri Thấu Thị
 * Phản hồi thông minh dựa trên bối cảnh thời gian thực của server.
 */
require_once 'db_connect.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$question = mb_strtolower($_POST['question'] ?? '', 'UTF-8');

// --- 🔮 ORACLE SENSE: Lấy dữ liệu thời gian thực từ Server ---
// 1. Tình hình Ma Thần
$boss = $conn->query("SELECT name, hp, max_hp FROM world_boss WHERE status = 'active' LIMIT 1")->fetch_assoc();

// 2. Vận khí (Big Win gần nhất)
$bigWin = $conn->query("SELECT target_name, value FROM arena_memory WHERE event_type = 'big_win' ORDER BY created_at DESC LIMIT 1")->fetch_assoc();

// 3. Bang chiến (Lãnh thổ mới nhất)
$territory = $conn->query("SELECT t.name, g.Name as GuildName FROM territories t JOIN guilds g ON t.occupying_guild_id = g.id ORDER BY t.last_reset DESC LIMIT 1")->fetch_assoc();

// 4. 🔮 Lời Tiên Tri Tuần (nếu có bảng)
$activeProphecyWeek = null;
$weekProphecies     = [];
$oracleBuff         = null;
try {
    $chkP = $conn->query("SHOW TABLES LIKE 'oracle_prophecy_weeks'");
    if ($chkP && $chkP->num_rows > 0) {
        $monday = date('Y-m-d', strtotime('monday this week'));
        $activeProphecyWeek = $conn->query("SELECT * FROM oracle_prophecy_weeks WHERE week_start='$monday' AND status='active' LIMIT 1")->fetch_assoc();
        if ($activeProphecyWeek) {
            $wid = (int)$activeProphecyWeek['id'];
            $weekProphecies = $conn->query("SELECT * FROM oracle_prophecies WHERE week_id=$wid ORDER BY prophecy_index")->fetch_all(MYSQLI_ASSOC);
        }
        $oracleBuff = $conn->query("SELECT * FROM community_buffs WHERE buff_type='oracle_blessing' AND is_active=1 AND expires_at > NOW() LIMIT 1")->fetch_assoc();
    }
} catch (\Throwable $e) { /* Bảng chưa tồn tại, bỏ qua */ }

$answer = "";

// --- 🧠 LOGIC XỬ LÝ CÂU TRẢ LỜI ---

// Case: Hỏi về Boss / Tình hình Ma Thần
if (stripos($question, 'boss') !== false || stripos($question, 'ma thần') !== false || stripos($question, 'con quái') !== false) {
    if ($boss) {
        $hpPercent = round(($boss['hp'] / $boss['max_hp']) * 100);
        $answer = "Tiểu tử! Ma Thần {$boss['name']} đang gào thét tại chiến trường, linh khí hắn chỉ còn $hpPercent%. Nếu không mau ra chiêu, đám dũng sĩ khác sẽ húp sạch phần thưởng đấy!";
    } else {
        $answer = "Ma Thần dạo này đang bế quan tỏa cảng. Nhưng lão cảm thấy linh khí đang tích tụ, sớm thôi cõi Trận Địa sẽ lại chấn động.";
    }
}
// Case: Hỏi về vận khí / Ai đang thắng / Đại gia
elseif (stripos($question, 'ai thắng') !== false || stripos($question, 'đại gia') !== false || stripos($question, 'vận khí') !== false || stripos($question, 'giàu') !== false) {
    if ($bigWin) {
        $val = json_decode($bigWin['value'], true);
        $amount = number_format($val['amount'] ?? 0);
        $answer = "Vận khí trong Trận Địa đang biến ảo khôn lường! Ta vừa thấy {$bigWin['target_name']} húp được $amount GTLM. Một con số khiến quỷ thần cũng phải khiếp sợ!";
    } else {
        $answer = "Lão chưa thấy ai có thiên mệnh đột phá hôm nay. Có lẽ thiên cơ đang chờ đợi ngươi ra chiêu chăng?";
    }
}
// Case: Hỏi về Lãnh thổ / Guild / Bang hội
elseif (stripos($question, 'lãnh thổ') !== false || stripos($question, 'bang') !== false || stripos($question, 'vùng đất') !== false) {
    if ($territory) {
        $answer = "Trận địa đang khói lửa ngút trời! Bang hội {$territory['GuildName']} đang thống trị vùng {$territory['name']}. Tiểu tử có muốn gia nhập một bang hội để cùng chia sẻ thiên hạ không?";
    } else {
        $answer = "Các vùng đất linh thiêng dạo này yên ả lạ kỳ. Dường như các bang hội đang ủ mưu cho một cuộc đại chiến chiếm đóng mới.";
    }
}
// Case: Hỏi về tình hình chung / Dạo này thế nào
elseif (stripos($question, 'dạo này') !== false || stripos($question, 'tình hình') !== false || stripos($question, 'có gì mới') !== false) {
    $parts = [];
    if ($boss) $parts[] = "Ma Thần đang bị vây hãm";
    if ($bigWin) $parts[] = "có đại gia vừa húp đậm";
    if ($territory) $parts[] = "bang hội đang tranh giành bờ cõi";
    
    if (!empty($parts)) {
        $answer = "Trận địa dạo này náo nhiệt lắm! " . implode(", ", $parts) . ". Tiểu tử hãy mau chóng ra chiêu để không bỏ lỡ vận khí.";
    } else {
        $answer = "Trận địa dạo này yên bình, rất thích hợp để tiểu tử tích lũy linh khí GTLM tại các ván giao lưu nhỏ.";
    }
}
// Case: Lore chung & Vocabulary chuẩn — tích hợp lời tiên tri
else {
    // Ưu tiên trả lời về Lời Tiên Tri nếu đang có tuần active
    if ($activeProphecyWeek && !empty($weekProphecies)) {
        $prophecyLines = array_map(fn($p) => '"' . $p['prophecy_text'] . '"', $weekProphecies);
        $answer = "Ta đã giáng 3 lời tiên tri thiêng liêng tuần này! Hãy vào trang <a href='oracle_prophecy.php'>🔮 Lời Tiên Tri</a> để chứng kiến. "
                . "Lời thứ nhất: " . ($prophecyLines[0] ?? '...');
    } elseif ($oracleBuff) {
        $mult = round((floatval($oracleBuff['multiplier']) - 1) * 100);
        $answer = "Phúc Lành Tiên Tri đang ban xuống toàn Trận Địa! Lão đã tiên tri đúng 3/3 — server đang được +{$mult}% GTLM trên mọi chiến thắng. Tiểu tử mau ra tay!";
    } else {
        $generic = [
            "Tiểu tử! Muốn xin quẻ hay muốn hỏi cách húp GTLM từ Ma Thần?",
            "Lão đang quan sát tinh tượng, dường như ván tới Xỉu sẽ mang lại linh khí cho ngươi.",
            "Đừng hỏi lão về kết quả, hãy hỏi về vận khí. Ta thấy vầng hào quang GTLM đang vây quanh nick của ngươi.",
            "Hôm nay hướng Đông đại cát, tiểu tử hãy thử vận may tại 'Trận Địa Trắng Đỏ' xem sao.",
            "Linh thú đang vẫy gọi, ta thấy hình bóng một con Rồng sắp hiện hình trong Linh Thú Trận."
        ];
        $answer = $generic[array_rand($generic)];
    }
}

echo json_encode(['success' => true, 'answer' => $answer]);

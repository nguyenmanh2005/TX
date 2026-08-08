<?php
/**
 * API Tháp Thần Bài 100 Tầng — Vận Mệnh Chi Lộ (V5)
 * Hỗ trợ hệ thống nhân vật, kỹ năng và logic hãm kim dừng.
 */

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db_connect.php';

$action   = $_GET['action'] ?? 'info';
$userId   = isset($_SESSION['Iduser']) ? (int)$_SESSION['Iduser'] : 1;
$username = isset($_SESSION['Name'])   ? $_SESSION['Name']   : 'Đạo Hữu Vượt Tháp';
$avatar   = isset($_SESSION['Avatar']) ? $_SESSION['Avatar'] : 'img/avatar_default.png';

// Lấy thạch thưởng GTLM gốc theo tầng
function getFloorReward($floor) {
    $base = $floor * 10000;
    if ($floor % 10 === 0) return $base * 5;  // Mốc Boss: x5
    if ($floor % 5 === 0)  return $base * 2;  // Tầng mốc: x2
    return $base;
}

// Lấy Cúp hoàng gia theo tầng
function getFloorTrophy($floor) {
    $trophies = [
        5  => ['code' => 'trophy_f5',   'name' => '🏆 Cúp Chinh Phục Tầng 5',    'icon' => '🏆', 'type' => 'trophy'],
        10 => ['code' => 'trophy_f10',  'name' => '🐉 Tượng Hắc Long Tầng 10',   'icon' => '🐉', 'type' => 'statue'],
        20 => ['code' => 'trophy_f20',  'name' => '⚡ Kiếm Sét Tầng 20',          'icon' => '⚡', 'type' => 'trophy'],
        30 => ['code' => 'trophy_f30',  'name' => '🌟 Ngôi Sao Chiến Thần Tầng 30','icon' => '🌟', 'type' => 'trophy'],
        50 => ['code' => 'trophy_f50',  'name' => '👑 Vương Miện Bất Tử Tầng 50', 'icon' => '👑', 'type' => 'statue'],
        75 => ['code' => 'trophy_f75',  'name' => '💎 Kim Cương Huyết Tầng 75',   'icon' => '💎', 'type' => 'statue'],
        100=> ['code' => 'trophy_f100', 'name' => '🔱 Thần Thánh Đỉnh Tháp 100',  'icon' => '🔱', 'type' => 'legendary'],
    ];
    if (isset($trophies[$floor])) return $trophies[$floor];
    if ($floor % 25 === 0) return ['code' => "trophy_f{$floor}", 'name' => "🎖️ Huy Chương Tầng {$floor}", 'icon' => '🎖️', 'type' => 'trophy'];
    return null;
}

// Lấy tiến trình người chơi
function getUserProgress($conn, $userId, $username, $avatar) {
    $stmt = $conn->prepare("SELECT * FROM tower_user_progress WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        $ins = $conn->prepare("INSERT INTO tower_user_progress (user_id, username, avatar, current_floor, highest_floor, companion_id, companion_name, selected_character, shield_count) VALUES (?, ?, ?, 1, 1, 1, 'Tháp Thần Bài', 'kiem_thanh', 0)");
        $ins->bind_param("iss", $userId, $username, $avatar);
        $ins->execute();
        $ins->close();
        return [
            'user_id' => $userId, 'username' => $username, 'avatar' => $avatar,
            'current_floor' => 1, 'highest_floor' => 1, 'total_wins' => 0, 'total_gtlm_won' => 0,
            'last_game_key' => '', 'selected_character' => 'kiem_thanh', 'shield_count' => 0
        ];
    }
    return $row;
}

// Khởi tạo cooldowns trong session nếu chưa có
if (!isset($_SESSION['tower_cooldowns'])) {
    $_SESSION['tower_cooldowns'] = [
        'kiem_thanh' => 0,
        'phap_su' => 0,
        'cuong_chien_si' => 0
    ];
}

// Lấy thời gian cooldown còn lại (giây)
function getCooldownLeft($char) {
    $expire = $_SESSION['tower_cooldowns'][$char] ?? 0;
    $left = $expire - time();
    return $left > 0 ? $left : 0;
}

// ===== ACTION: INFO =====
if ($action === 'info') {
    $prog = getUserProgress($conn, $userId, $username, $avatar);
    $floor = (int)$prog['current_floor'];
    $reward = getFloorReward($floor);
    $trophy = getFloorTrophy($floor);

    // Bảng xếp hạng top leo tháp
    $topRes = $conn->query("SELECT username, avatar, highest_floor, total_wins FROM tower_user_progress ORDER BY highest_floor DESC, total_wins DESC LIMIT 5");
    $leaderboard = [];
    while ($topRes && $row = $topRes->fetch_assoc()) $leaderboard[] = $row;

    echo json_encode([
        'success'      => true,
        'progress'     => $prog,
        'floor_reward' => $reward,
        'floor_trophy' => $trophy,
        'leaderboard'  => $leaderboard,
        'cooldowns'    => [
            'kiem_thanh' => getCooldownLeft('kiem_thanh'),
            'phap_su' => getCooldownLeft('phap_su'),
            'cuong_chien_si' => getCooldownLeft('cuong_chien_si')
        ],
        'active_buff'  => $_SESSION['tower_active_buff'] ?? null
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== ACTION: SELECT CHARACTER =====
if ($action === 'select_character') {
    $char = $_POST['character'] ?? 'kiem_thanh';
    if (!in_array($char, ['kiem_thanh', 'phap_su', 'cuong_chien_si'])) {
        echo json_encode(['success' => false, 'message' => 'Lớp nhân vật không hợp lệ!'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $prog = getUserProgress($conn, $userId, $username, $avatar);
    $floor = (int)$prog['current_floor'];

    // Cuồng Chiến Sĩ: Cấp sẵn 1 Khiên Hộ Mệnh nếu chưa có khiên
    $shieldUpdate = "";
    if ($char === 'cuong_chien_si') {
        $stmt = $conn->prepare("UPDATE tower_user_progress SET selected_character = ?, shield_count = CASE WHEN shield_count = 0 THEN 1 ELSE shield_count END WHERE user_id = ?");
    } else {
        $stmt = $conn->prepare("UPDATE tower_user_progress SET selected_character = ? WHERE user_id = ?");
    }
    
    $stmt->bind_param("si", $char, $userId);
    $stmt->execute();
    $stmt->close();

    // Hồi phục lại thông tin mới
    $progNew = getUserProgress($conn, $userId, $username, $avatar);

    echo json_encode([
        'success' => true,
        'message' => 'Đổi nhân vật thành công!',
        'progress' => $progNew
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== ACTION: USE_SKILL =====
if ($action === 'use_skill') {
    $prog = getUserProgress($conn, $userId, $username, $avatar);
    $char = $prog['selected_character'] ?? 'kiem_thanh';
    
    $skillMapping = [
        'kiem_thanh' => ['skill' => 'ngung_dong', 'cooldown' => 10, 'name' => 'Ngưng Đọng'],
        'phap_su' => ['skill' => 'chuyen_menh', 'cooldown' => 15, 'name' => 'Chuyển Mệnh'],
        'cuong_chien_si' => ['skill' => 'het_thau_troi', 'cooldown' => 20, 'name' => 'Hét Thấu Trời']
    ];

    $sInfo = $skillMapping[$char];
    $left = getCooldownLeft($char);
    if ($left > 0) {
        echo json_encode(['success' => false, 'message' => "Kỹ năng {$sInfo['name']} đang trong trạng thái hồi chiêu ({$left} giây)!"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Kích hoạt buff trong session
    $_SESSION['tower_active_buff'] = $sInfo['skill'];
    // Thiết lập thời gian hồi chiêu
    $_SESSION['tower_cooldowns'][$char] = time() + $sInfo['cooldown'];

    echo json_encode([
        'success' => true,
        'message' => "Đã kích hoạt Tuyệt Kỹ: {$sInfo['name']}!",
        'active_buff' => $sInfo['skill'],
        'cooldown_left' => $sInfo['cooldown']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== ACTION: CARD_RESULT (Xử lý kết quả dừng kim) =====
if ($action === 'card_result') {
    $cardResult = $_POST['card_result'] ?? 'death';
    $prog = getUserProgress($conn, $userId, $username, $avatar);
    $floor = (int)$prog['current_floor'];
    $baseReward = getFloorReward($floor);
    $trophy = getFloorTrophy($floor);
    $char = $prog['selected_character'] ?? 'kiem_thanh';
    $shieldCount = (int)$prog['shield_count'];

    $activeBuff = $_SESSION['tower_active_buff'] ?? null;
    // Reset buff sau khi ra chiêu dừng kim
    $_SESSION['tower_active_buff'] = null;

    $advance = false;
    $rewardGtlm = 0;
    $msg = '';
    
    // Sao lưu kết quả gốc trước khi biến đổi bởi kịch bản kỹ năng
    $finalResult = $cardResult;

    // 1. ÁP DỤNG SKILL / NỘI TẠI KHI DỪNG TRÚNG Ô TỬ THẦN (DEATH)
    if ($finalResult === 'death') {
        if ($char === 'phap_su' && $activeBuff === 'chuyen_menh') {
            $finalResult = 'retry';
            $msg = "🧙‍♂️ [Chuyển Mệnh] tự động kích hoạt! Ô Tử Thần đã biến thành Thử Lại (Retry)!";
        } elseif ($char === 'cuong_chien_si' && $shieldCount > 0) {
            $finalResult = 'retry';
            $shieldCount--;
            // Update shield count in database
            $upShield = $conn->prepare("UPDATE tower_user_progress SET shield_count = ? WHERE user_id = ?");
            $upShield->bind_param("ii", $shieldCount, $userId);
            $upShield->execute();
            $upShield->close();
            $msg = "🛡️ [Khiên Hộ Mệnh] vỡ tan để bảo vệ bạn! Ô Tử Thần biến thành Thử Lại (Retry)!";
        }
    }

    // 2. TÍNH TOÁN HỆ SỐ PHẦN THƯỞNG
    $multiplier = 0;
    switch ($finalResult) {
        case 'jackpot':
            $multiplier = 5;
            $advance = true;
            if (empty($msg)) $msg = "👑 THẦN BÀI! Vận khí đỉnh phong, húp trọn phúc lộc cực đại!";
            break;
        case 'win3':
            $multiplier = 3;
            $advance = true;
            if (empty($msg)) $msg = "🔥 ĐẠI THẮNG! Giao lưu thắng lớn, tiến thẳng lên tầng cao!";
            break;
        case 'win':
            $multiplier = 1;
            $advance = true;
            if (empty($msg)) $msg = "⚔️ CHIẾN THẮNG! Vượt tầng an toàn, thu về chiến lợi phẩm!";
            break;
        case 'half':
            $multiplier = 0.5;
            $advance = true;
            if (empty($msg)) $msg = "💫 NỬA THẮNG! Vẫn an toàn qua tầng với nửa lượng thạch thưởng!";
            break;
        case 'retry':
            $multiplier = 0;
            $advance = false;
            if (empty($msg)) $msg = "🛡️ THỬ LẠI! Bất phân thắng bại, không mất GTLM, được quay lại ngay!";
            break;
        case 'death':
        default:
            $multiplier = 0;
            $advance = false;
            if (empty($msg)) $msg = "💀 TỬ THẦN! Ra chiêu thất bại, bạn bay màu về cõi và kẹt lại tầng này!";
            break;
    }

    // 3. ÁP DỤNG SKILL CUỒNG CHIẾN SĨ (HÉT THẤU TRỜI CD 20S) NHÂN ĐÔI TIỀN THƯỞNG KHI THẮNG
    if ($char === 'cuong_chien_si' && $activeBuff === 'het_thau_troi' && $multiplier > 0) {
        $multiplier = $multiplier * 2;
        $msg .= " 📢 [Hét Thấu Trời] kích hoạt, nhân đôi toàn bộ thạch thưởng GTLM của tầng này!";
    }

    $rewardGtlm = intval($baseReward * $multiplier);

    $trophyAwarded = null;
    $conn->begin_transaction();
    try {
        if ($rewardGtlm > 0) {
            // Cộng GTLM húp được vào ví người chơi
            $upMoney = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
            $upMoney->bind_param("di", $rewardGtlm, $userId);
            $upMoney->execute();
            $upMoney->close();
        }

        if ($advance) {
            $newFloor   = $floor + 1;
            $newHighest = ($newFloor > (int)$prog['highest_floor']) ? $newFloor : (int)$prog['highest_floor'];
            
            // Cập nhật tầng và lưu kết quả
            $upProg = $conn->prepare("UPDATE tower_user_progress SET current_floor=?, highest_floor=?, total_wins=total_wins+1, total_gtlm_won=total_gtlm_won+?, last_game_key=? WHERE user_id=?");
            $upProg->bind_param("iidsi", $newFloor, $newHighest, $rewardGtlm, $finalResult, $userId);
            $upProg->execute();
            $upProg->close();

            // Nhận Trophy/Tượng nếu đạt mốc tầng đặc biệt
            if ($trophy) {
                $chk = $conn->prepare("SELECT id FROM lounge_items WHERE user_id=? AND item_code=? FOR UPDATE");
                $chk->bind_param("is", $userId, $trophy['code']);
                $chk->execute();
                $has = $chk->get_result()->fetch_assoc();
                $chk->close();
                if (!$has) {
                    $ins = $conn->prepare("INSERT INTO lounge_items (user_id, item_code, item_name, item_type, icon_url, grid_x, grid_y, is_placed, acquired_from, acquired_at) VALUES (?, ?, ?, ?, ?, 2, 2, 1, 'tower_card', NOW())");
                    $ins->bind_param("issss", $userId, $trophy['code'], $trophy['name'], $trophy['type'], $trophy['icon']);
                    $ins->execute();
                    $ins->close();
                    $trophyAwarded = $trophy['name'];
                }
            }

            // Gửi thông báo chấn động lên kênh chat thế giới
            if ($finalResult === 'jackpot' || $trophyAwarded) {
                $chatMsg = "🗼 [{$username}] vừa dừng kim trúng ô [{$finalResult}] tại Tầng #{$floor} Tháp Thần Bài! Húp ngập mặt " . number_format($rewardGtlm) . " GTLM" . ($trophyAwarded ? " và nhận báu vật [{$trophyAwarded}]" : "") . "!";
                $chatStmt = $conn->prepare("INSERT INTO chat_messages (user_id, username, message, avatar, created_at) VALUES (?, ?, ?, ?, NOW())");
                if ($chatStmt) {
                    $chatStmt->bind_param("isss", $userId, $username, $chatMsg, $avatar);
                    $chatStmt->execute();
                    $chatStmt->close();
                }
            }

            // Ghi lịch sử giao lưu (game_history)
            $gh = $conn->prepare("INSERT INTO game_history (user_id, game_type, bet_amount, win_amount, is_win, played_at) VALUES (?, 'tower_gods', 0, ?, 1, NOW())");
            $gh->bind_param("id", $userId, $rewardGtlm);
            $gh->execute();
            $gh->close();
        } elseif ($finalResult === 'death') {
            // Khi bị kẹt lại tầng, reset Khiên hộ mệnh về 1 khi rớt lại từ tầng 1 hoặc bắt đầu lại lượt
            // Tuy nhiên kịch bản nói "bị kẹt lại tầng và chơi lại"
            // Lưu lịch sử bay màu
            $gh = $conn->prepare("INSERT INTO game_history (user_id, game_type, bet_amount, win_amount, is_win, played_at) VALUES (?, 'tower_gods', 0, 0, 0, NOW())");
            $gh->bind_param("i", $userId);
            $gh->execute();
            $gh->close();
        }

        $conn->commit();

        $progNew = getUserProgress($conn, $userId, $username, $avatar);

        echo json_encode([
            'success'        => true,
            'card_result'    => $finalResult,
            'reward_gtlm'    => $rewardGtlm,
            'advanced'       => $advance,
            'trophy_awarded' => $trophyAwarded,
            'message'        => $msg,
            'progress'       => $progNew,
            'cooldowns'      => [
                'kiem_thanh' => getCooldownLeft('kiem_thanh'),
                'phap_su' => getCooldownLeft('phap_su'),
                'cuong_chien_si' => getCooldownLeft('cuong_chien_si')
            ]
        ], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Lỗi giao dịch: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ===== CÁC ACTION PHỤ KHÁC =====
if ($action === 'list_games') {
    echo json_encode(['success' => true, 'games' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action không hợp lệ'], JSON_UNESCAPED_UNICODE);
?>

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

$action = $_GET['action'] ?? ($_POST['action'] ?? 'info');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập!']);
    exit;
}

$userId   = (int)$_SESSION['Iduser'];
$username = $_SESSION['Name'] ?? 'Đạo Hữu Vượt Tháp';
$avatar   = $_SESSION['Avatar'] ?? 'img/avatar_default.png';

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

// (Cột team_chars đã được tạo)
// Lấy tiến trình người chơi
function getUserProgress($conn, $userId, $username, $avatar) {
    $stmt = $conn->prepare("SELECT * FROM tower_user_progress WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        $ins = $conn->prepare("INSERT INTO tower_user_progress (user_id, username, avatar, current_floor, highest_floor, companion_id, companion_name, selected_character, team_chars, shield_count) VALUES (?, ?, ?, 1, 1, 1, 'Tháp Thần Bài', 'kiem_thanh', '[\"kiem_thanh\"]', 0)");
        $ins->bind_param("iss", $userId, $username, $avatar);
        $ins->execute();
        $ins->close();
        return [
            'user_id' => $userId, 'username' => $username, 'avatar' => $avatar,
            'current_floor' => 1, 'highest_floor' => 1, 'total_wins' => 0, 'total_gtlm_won' => 0,
            'last_game_key' => '', 'selected_character' => 'kiem_thanh', 'team_chars' => '["kiem_thanh"]', 'shield_count' => 0
        ];
    }
    
    // Đảm bảo team_chars luôn hợp lệ
    if (empty($row['team_chars'])) {
        $row['team_chars'] = json_encode([$row['selected_character']]);
    }
    return $row;
}

function getCharCombatStats($char, $floor) {
    $baseHp = 100 + ($floor * 15);
    $baseAtk = 20 + ($floor * 3);
    $stats = ['hp' => $baseHp, 'atk' => $baseAtk, 'crit' => 0.1, 'lifesteal' => 0, 'evade' => 0];

    switch ($char) {
        case 'kiem_thanh': $stats['atk'] *= 1.5; $stats['crit'] = 0.3; break;
        case 'cung_thu': $stats['crit'] = 0.5; $stats['hp'] *= 0.8; break;
        case 'ninja': $stats['evade'] = 0.3; $stats['atk'] *= 1.2; break;
        case 'tien_tri': /* debuff is handled in monster init */ break;
        case 'ma_kiem_si': $stats['lifesteal'] = 0.1; break;
        case 'phap_su': $stats['atk'] *= 2; $stats['hp'] *= 0.5; break;
        case 'cuong_chien_si': $stats['hp'] *= 2; break;
        case 'hac_am': $stats['lifesteal'] = 0.5; break;
        case 'muc_su': $stats['hp'] *= 2; $stats['lifesteal'] = 0.2; break;
        case 'trieu_hoi': $stats['hp'] *= 1.3; $stats['atk'] *= 1.1; break;
        
        case 'dao_tac': /* passive gold handled at reward */ break;
        case 'than_tai': /* passive gold handled at reward */ break;
        case 'thuong_nhan': /* passive gold handled at reward */ break;
        case 'tho_san': /* passive gold handled at reward */ break;
        case 'nhac_si': $stats['hp'] *= 1.5; break;
        
        case 'gia_kim': /* passive gold handled in combat loop */ break;
        case 'cuong_tin': $stats['atk'] *= 2.5; $stats['hp'] *= 0.4; break;
        case 'xuyen_khong': $stats['hp'] *= 1.5; $stats['atk'] *= 1.2; break;
        case 'do_te': $stats['lifesteal'] = 0.4; $stats['hp'] *= 1.3; break;
        case 'vua_tro_choi': $stats['atk'] *= 2; $stats['hp'] *= 2; break;
    }
    
    $stats['hp'] = intval($stats['hp']);
    $stats['atk'] = intval($stats['atk']);
    return $stats;
}

function getMonsterForFloor($floor, $char = null) {
    $isBoss = ($floor % 10 === 0);
    $hp = ($floor * 20) + ($isBoss ? 200 : 50);
    $atk = ($floor * 4) + ($isBoss ? 20 : 8);
    
    if ($char === 'tien_tri') {
        $atk = intval($atk * 0.8);
    }
    
    $name = $isBoss ? "Boss Tầng $floor" : "Quái Vật Tầng $floor";
    $avatar = $isBoss ? '🐉' : '👾';
    if ($floor == 100) { 
        $name = "Thần Bài Tối Thượng"; 
        $avatar = '🃏'; 
        $hp *= 2; 
        $atk *= 1.5; 
    }
    
    return [
        'is_boss' => $isBoss,
        'hp' => intval($hp),
        'atk' => intval($atk),
        'name' => $name,
        'avatar' => $avatar
    ];
}

// Lấy số dư tài khoản
function getUserBalance($conn, $userId) {
    $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (float)$row['Money'] : 0;
}

// Khởi tạo cooldowns trong session nếu chưa có
$all_chars = ['kiem_thanh', 'cung_thu', 'ninja', 'tien_tri', 'ma_kiem_si', 'phap_su', 'cuong_chien_si', 'hac_am', 'muc_su', 'trieu_hoi', 'dao_tac', 'than_tai', 'thuong_nhan', 'tho_san', 'nhac_si', 'gia_kim', 'cuong_tin', 'xuyen_khong', 'do_te', 'vua_tro_choi'];

if (!isset($_SESSION['tower_cooldown_floors'])) {
    $cd = [];
    foreach($all_chars as $c) $cd[$c] = 0;
    $_SESSION['tower_cooldown_floors'] = $cd;
}

// Lấy thời gian cooldown còn lại (Tầng)
function getCooldownLeft($char) {
    return $_SESSION['tower_cooldown_floors'][$char] ?? 0;
}

// ===== ACTION: INFO =====
if ($action === 'info') {
    $prog = getUserProgress($conn, $userId, $username, $avatar);
    $floor = (int)$prog['current_floor'];
    $reward = getFloorReward($floor);
    $trophy = getFloorTrophy($floor);

    // Tự động seed/cập nhật Bot leo tháp nếu chưa có
    $checkBotProg = $conn->query("SELECT COUNT(*) as c FROM tower_user_progress WHERE user_id >= 9000 OR username LIKE 'Bot%' OR username IN ('Cụ Giáo', 'Đại Gia Whale', 'Thánh Nổ Plinko', 'Lão Triết Lý')");
    $botCount = ($checkBotProg) ? (int)$checkBotProg->fetch_assoc()['c'] : 0;
    if ($botCount < 4) {
        $sampleBots = [
            ['id' => 9001, 'name' => 'Cụ Giáo', 'avatar' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=cugia&style=circle', 'floor' => 88, 'wins' => 87, 'gtlm' => 12500000, 'char' => 'phap_su'],
            ['id' => 9002, 'name' => 'Đại Gia Whale', 'avatar' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=whale&style=circle', 'floor' => 75, 'wins' => 74, 'gtlm' => 8900000, 'char' => 'cuong_chien_si'],
            ['id' => 9003, 'name' => 'Thánh Nổ Plinko', 'avatar' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=plinko&style=circle', 'floor' => 62, 'wins' => 61, 'gtlm' => 5400000, 'char' => 'kiem_thanh'],
            ['id' => 9005, 'name' => 'Lão Triết Lý', 'avatar' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=trietly&style=circle', 'floor' => 54, 'wins' => 53, 'gtlm' => 3800000, 'char' => 'phap_su']
        ];
        foreach ($sampleBots as $b) {
            $stmtSeed = $conn->prepare("INSERT INTO tower_user_progress (user_id, username, avatar, current_floor, highest_floor, total_wins, total_gtlm_won, selected_character) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE highest_floor = VALUES(highest_floor)");
            if ($stmtSeed) {
                $stmtSeed->bind_param("issiiids", $b['id'], $b['name'], $b['avatar'], $b['floor'], $b['floor'], $b['wins'], $b['gtlm'], $b['char']);
                $stmtSeed->execute();
                $stmtSeed->close();
            }
        }
    }

    // Bảng xếp hạng top leo tháp
    $topRes = $conn->query("SELECT username, avatar, highest_floor, total_wins FROM tower_user_progress ORDER BY highest_floor DESC, total_wins DESC LIMIT 5");
    $leaderboard = [];
    while ($topRes && $row = $topRes->fetch_assoc()) $leaderboard[] = $row;

    $cds = [];
    foreach($all_chars as $c) $cds[$c] = getCooldownLeft($c);

    echo json_encode([
        'success'      => true,
        'progress'     => $prog,
        'user_balance' => getUserBalance($conn, $userId),
        'floor_reward' => $reward,
        'floor_trophy' => $trophy,
        'leaderboard'  => $leaderboard,
        'cooldowns'    => $cds,
        'active_buff'  => $_SESSION['tower_active_buff'] ?? null
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== ACTION: SELECT CHARACTER =====
if ($action === 'select_character') {
    $prog = getUserProgress($conn, $userId, $username, $avatar);
    $floor = (int)$prog['current_floor'];
    
    if ($floor >= 50) {
        echo json_encode(['success' => false, 'message' => 'Đội hình đã bị khóa vĩnh viễn từ tầng 50!'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $teamRaw = $_POST['team'] ?? '["kiem_thanh"]';
    $team = json_decode($teamRaw, true);
    
    if (!is_array($team) || count($team) === 0 || count($team) > 3) {
        echo json_encode(['success' => false, 'message' => 'Đội hình phải từ 1 đến 3 nhân vật!'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    foreach ($team as $c) {
        if (!in_array($c, $all_chars)) {
            echo json_encode(['success' => false, 'message' => 'Tồn tại nhân vật không hợp lệ!'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $mainChar = $team[0];
    $teamJson = json_encode($team);

    $stmt = $conn->prepare("UPDATE tower_user_progress SET selected_character = ?, team_chars = ? WHERE user_id = ?");
    $stmt->bind_param("ssi", $mainChar, $teamJson, $userId);
    $stmt->execute();
    $stmt->close();

    // Hồi phục lại thông tin mới
    $progNew = getUserProgress($conn, $userId, $username, $avatar);

    $cds = [];
    foreach($all_chars as $c) $cds[$c] = getCooldownLeft($c);

    echo json_encode([
        'success' => true,
        'message' => 'Đổi nhân vật thành công!',
        'progress' => $progNew,
        'user_balance' => getUserBalance($conn, $userId),
        'cooldowns' => $cds
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== ACTION: USE_SKILL =====
if ($action === 'use_skill') {
    $prog = getUserProgress($conn, $userId, $username, $avatar);
    $char = $prog['selected_character'] ?? 'kiem_thanh';
    
    $skillMapping = [
        'kiem_thanh' => ['skill' => 'nhat_kiem', 'cooldown' => 3, 'name' => 'Nhất Kiếm Đoạt Mệnh'],
        'cung_thu' => ['skill' => 'phong_tien', 'cooldown' => 3, 'name' => 'Phong Thần Tiễn'],
        'ninja' => ['skill' => 'phan_than', 'cooldown' => 4, 'name' => 'Phân Thân'],
        'tien_tri' => ['skill' => 'thau_thi', 'cooldown' => 4, 'name' => 'Thấu Thị'],
        'ma_kiem_si' => ['skill' => 'song_long', 'cooldown' => 3, 'name' => 'Song Long Kích'],
        
        'phap_su' => ['skill' => 'nghich_chuyen', 'cooldown' => 5, 'name' => 'Nghịch Chuyển Thời Không'],
        'cuong_chien_si' => ['skill' => 'thinh_no', 'cooldown' => 4, 'name' => 'Cơn Thịnh Nộ'],
        'hac_am' => ['skill' => 'bong_toi', 'cooldown' => 6, 'name' => 'Lãnh Vực Bóng Tối'],
        'muc_su' => ['skill' => 'thanh_ca', 'cooldown' => 5, 'name' => 'Thánh Ca'],
        'trieu_hoi' => ['skill' => 'hop_the', 'cooldown' => 5, 'name' => 'Triệu Hồi Rồng'],
        
        'dao_tac' => ['skill' => 'trao_phung', 'cooldown' => 4, 'name' => 'Trộm Long Tráo Phụng'],
        'than_tai' => ['skill' => 'hao_quang', 'cooldown' => 4, 'name' => 'Hào Quang Hoàng Kim'],
        'thuong_nhan' => ['skill' => 'hoi_lo', 'cooldown' => 4, 'name' => 'Hối Lộ'],
        'tho_san' => ['skill' => 'dong_dau', 'cooldown' => 4, 'name' => 'Đóng Dấu'],
        'nhac_si' => ['skill' => 'ru_ngu', 'cooldown' => 5, 'name' => 'Giai Điệu Ru Ngủ'],
        
        'gia_kim' => ['skill' => 'che_thuoc', 'cooldown' => 3, 'name' => 'Chế Thuốc Nổ'],
        'cuong_tin' => ['skill' => 'hy_sinh', 'cooldown' => 4, 'name' => 'Hy Sinh'],
        'xuyen_khong' => ['skill' => 'be_cong', 'cooldown' => 4, 'name' => 'Bẻ Cong Thời Gian'],
        'do_te' => ['skill' => 'chat_chem', 'cooldown' => 3, 'name' => 'Chặt Chém'],
        'vua_tro_choi' => ['skill' => 'lat_keo', 'cooldown' => 8, 'name' => 'Lật Kèo']
    ];

    $sInfo = $skillMapping[$char];
    $left = getCooldownLeft($char);
    if ($left > 0) {
        echo json_encode(['success' => false, 'message' => "Kỹ năng {$sInfo['name']} đang trong trạng thái hồi chiêu (còn {$left} Tầng)!"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Kích hoạt buff trong session
    $_SESSION['tower_active_buff'] = $sInfo['skill'];
    // Thiết lập thời gian hồi chiêu
    $_SESSION['tower_cooldown_floors'][$char] = $sInfo['cooldown'];

    echo json_encode([
        'success' => true,
        'message' => "Đã kích hoạt Tuyệt Kỹ: {$sInfo['name']}!",
        'active_buff' => $sInfo['skill'],
        'cooldown_left' => $sInfo['cooldown']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== ACTION: AUTO_BATTLE (Cơ chế Nhập vai Đánh tự động) =====
// ===== ACTION: AUTO_BATTLE (Cơ chế Nhập vai Đánh tự động 3v3) =====
if ($action === 'auto_battle') {
    $prog = getUserProgress($conn, $userId, $username, $avatar);
    $floor = (int)$prog['current_floor'];
    $baseReward = getFloorReward($floor);
    $trophy = getFloorTrophy($floor);
    
    // Đội hình phe Ta (Tối đa 3)
    $teamRaw = json_decode($prog['team_chars'] ?? '[]', true);
    if (!is_array($teamRaw) || count($teamRaw) === 0) {
        $teamRaw = [$prog['selected_character'] ?? 'kiem_thanh'];
    }
    $teamRaw = array_slice($teamRaw, 0, 3);
    
    // Lấy Leader (Thủ lĩnh) để kích hoạt tuyệt kỹ
    $leader = $teamRaw[0];
    $activeBuff = $_SESSION['tower_active_buff'] ?? null;
    $_SESSION['tower_active_buff'] = null;

    // Sinh Đội hình phe Địch
    $numMonsters = 1;
    if ($floor >= 11) $numMonsters = 2;
    if ($floor >= 31) $numMonsters = 3;
    
    $isBossFloor = ($floor % 10 === 0);
    if ($isBossFloor) $numMonsters = 3; 
    
    // Tăng độ khó Exponential
    $scaling = pow($floor, 1.2);
    
    $mTeam = [];
    for ($i = 0; $i < $numMonsters; $i++) {
        $isBoss = ($isBossFloor && $i === 0);
        $hp = intval(($scaling * 15) + ($isBoss ? 200 : 50));
        $atk = intval(($scaling * 3) + ($isBoss ? 20 : 8));
        
        $name = $isBoss ? "Boss Tầng $floor" : "Quái Vật " . ($i+1);
        $avatar = $isBoss ? '🐉' : '👾';
        
        if ($floor == 100 && $i == 0) {
            $name = "Thần Bài Tối Thượng"; $avatar = '🃏'; $hp *= 2; $atk *= 1.5;
        }
        
        $mTeam[] = [
            'id' => "m_$i", 'name' => $name, 'avatar' => $avatar,
            'hp' => intval($hp), 'max_hp' => intval($hp), 'atk' => intval($atk), 'is_boss' => $isBoss
        ];
    }
    
    // Tiên tri debuff (Passive)
    if (in_array('tien_tri', $teamRaw)) {
        foreach ($mTeam as &$m) $m['atk'] = intval($m['atk'] * 0.8);
    }
    
    // Player Team Generation
    $pTeam = [];
    $allNames = [
        'kiem_thanh'=>'Kiếm Thánh', 'cung_thu'=>'Cung Thủ', 'ninja'=>'Ninja', 'tien_tri'=>'Tiên Tri', 'ma_kiem_si'=>'Ma Kiếm Sĩ',
        'phap_su'=>'Pháp Sư', 'cuong_chien_si'=>'Cuồng Sĩ', 'hac_am'=>'Hắc Ám', 'muc_su'=>'Mục Sư', 'trieu_hoi'=>'Triệu Hồi Sư',
        'dao_tac'=>'Đạo Tặc', 'than_tai'=>'Thần Tài', 'thuong_nhan'=>'Thương Nhân', 'tho_san'=>'Thợ Săn', 'nhac_si'=>'Nhạc Sĩ',
        'gia_kim'=>'Giả Kim', 'cuong_tin'=>'Cuồng Tín', 'xuyen_khong'=>'Xuyên Không', 'do_te'=>'Đồ Tể', 'vua_tro_choi'=>'Gambler'
    ];
    
    foreach ($teamRaw as $i => $charKey) {
        $stats = getCharCombatStats($charKey, $floor);
        $pTeam[] = [
            'id' => "p_$i", 'char' => $charKey, 'name' => $allNames[$charKey] ?? $charKey,
            'hp' => $stats['hp'], 'max_hp' => $stats['hp'], 'atk' => $stats['atk'],
            'crit' => $stats['crit'], 'lifesteal' => $stats['lifesteal'], 'evade' => $stats['evade']
        ];
    }
    
    // Random Thời Tiết
    $weatherTypes = ['none', 'sun', 'rain', 'blood', 'bless'];
    $weatherIdx = hexdec(substr(md5('weather_'.$floor), 0, 4)) % count($weatherTypes);
    $weather = $weatherTypes[$weatherIdx];
    
    $weatherNames = [
        'none' => 'Bình Thường',
        'sun' => '☀️ Nắng Gắt',
        'rain' => '🌧️ Mưa Độc',
        'blood' => '🌙 Huyết Nguyệt',
        'bless' => '🛡️ Phước Lành'
    ];
    
    // Áp dụng buff Thời Tiết ban đầu
    if ($weather === 'sun') {
        foreach ($pTeam as &$p) $p['atk'] = intval($p['atk'] * 1.2);
        foreach ($mTeam as &$m) $m['atk'] = intval($m['atk'] * 1.2);
    } elseif ($weather === 'bless') {
        foreach ($pTeam as &$p) { $p['max_hp'] = intval($p['max_hp'] * 1.3); $p['hp'] = $p['max_hp']; }
    }
    
    $combatLog = [];
    $isWin = false;
    $skipCombat = false;
    
    if ($weather !== 'none') {
        $combatLog[] = ["speaker" => "system", "msg" => "Thời tiết: " . $weatherNames[$weather]];
    }
    
    // Active Buffs (Chỉ dùng buff của Leader)
    if ($activeBuff === 'thanh_ca') {
        foreach ($pTeam as &$p) { $p['hp'] *= 2; $p['max_hp'] *= 2; }
        $combatLog[] = ["speaker" => "player", "msg" => "✨ Thánh Ca vang lên, toàn đội nhân đôi lượng máu!"];
    } elseif ($activeBuff === 'thinh_no') {
        foreach ($pTeam as &$p) { $p['hp'] = max(1, intval($p['hp'] * 0.7)); $p['atk'] *= 2; }
        $combatLog[] = ["speaker" => "player", "msg" => "💢 Hy sinh máu để tăng x2 ATK toàn đội!"];
    } elseif ($activeBuff === 'hop_the') {
        foreach ($mTeam as &$m) {
            $dmg = intval($m['hp'] * 0.5);
            $m['hp'] -= $dmg;
        }
        $combatLog[] = ["speaker" => "player", "msg" => "🐉 Thần Long giáng lâm, rút đi 50% HP toàn bộ quái vật!"];
    } elseif ($activeBuff === 'dong_dau') {
        foreach ($mTeam as &$m) $m['atk'] *= 2;
        $combatLog[] = ["speaker" => "system", "msg" => "⚠️ Đóng Dấu! Thưởng x10 nhưng Quái vật mạnh x2!"];
    } elseif ($activeBuff === 'chat_chem') {
        $hasBoss = false;
        foreach ($mTeam as $m) { if ($m['is_boss']) $hasBoss = true; }
        if (!$hasBoss) {
            foreach ($mTeam as &$m) $m['hp'] = 0;
            $combatLog[] = ["speaker" => "player", "msg" => "🪓 Đồ Tể vung rìu! Toàn bộ quái thường bị chết ngay tại chỗ!"];
            $isWin = true; $skipCombat = true;
        } else {
            $combatLog[] = ["speaker" => "player", "msg" => "🪓 Đồ Tể vung rìu nhưng Boss quá cứng, đòn chém thất bại!"];
        }
    } elseif ($activeBuff === 'hy_sinh') {
        $combatLog[] = ["speaker" => "player", "msg" => "🩸 Hy sinh! Đội trưởng tự sát quyên sinh kéo theo mọi quái vật xuống mồ!"];
        foreach ($pTeam as &$p) $p['hp'] = 0;
        foreach ($mTeam as &$m) $m['hp'] = 0;
        $isWin = true; $skipCombat = true;
    } elseif ($activeBuff === 'be_cong') {
        $combatLog[] = ["speaker" => "player", "msg" => "⏳ Bỏ qua quái vật nhảy thẳng lên tầng trên!"];
        foreach ($mTeam as &$m) $m['hp'] = 0;
        $isWin = true; $skipCombat = true;
    } elseif ($activeBuff === 'hoi_lo') {
        $combatLog[] = ["speaker" => "player", "msg" => "💰 Quái vật nhận tiền hối lộ và bỏ đi!"];
        foreach ($mTeam as &$m) $m['hp'] = 0;
        $isWin = true; $skipCombat = true;
    } elseif ($activeBuff === 'lat_keo') {
        if (mt_rand(1, 100) <= 50) {
            $combatLog[] = ["speaker" => "player", "msg" => "🎲 Vua Trò Chơi đổ xúc xắc! Vận may mỉm cười, toàn bộ quái đột tử!"];
            foreach ($mTeam as &$m) $m['hp'] = 0;
            $isWin = true;
        } else {
            $combatLog[] = ["speaker" => "player", "msg" => "🎲 Vua Trò Chơi đổ xúc xắc! Xui xẻo ập đến, toàn đội bị đột tử!"];
            foreach ($pTeam as &$p) $p['hp'] = 0;
            $isWin = false;
        }
        $skipCombat = true;
    }
    
    // Combat Helper Functions
    $getAliveIdx = function(&$team) {
        foreach ($team as $idx => $t) {
            if ($t['hp'] > 0) return $idx;
        }
        return -1;
    };
    
    // Hàm lưu state cho UI
    $logState = function(&$combatLog, $pTeam, $mTeam) {
        $stateDump = ['turn_end' => true, 'pState' => [], 'mState' => []];
        foreach($pTeam as $p) $stateDump['pState'][] = ['id' => $p['id'], 'hp' => max(0, $p['hp']), 'max' => $p['max_hp']];
        foreach($mTeam as $m) $stateDump['mState'][] = ['id' => $m['id'], 'hp' => max(0, $m['hp']), 'max' => $m['max_hp']];
        $combatLog[] = $stateDump;
    };

    $logState($combatLog, $pTeam, $mTeam); // State đầu tiên
    
    // Combat Loop 3v3
    $turn = 1;
    while ($turn <= 50 && !$skipCombat) {
        // Mưa Độc (Mất 5% HP mỗi lượt)
        if ($weather === 'rain') {
            foreach($pTeam as &$p) { if ($p['hp'] > 0) $p['hp'] = max(1, intval($p['hp'] * 0.95)); }
            foreach($mTeam as &$m) { if ($m['hp'] > 0) $m['hp'] = max(1, intval($m['hp'] * 0.95)); }
            $combatLog[] = ["speaker" => "system", "msg" => "🌧️ Mưa Độc ăn mòn 5% sinh lực của tất cả!"];
            $logState($combatLog, $pTeam, $mTeam);
        }
        
        $mIdx = $getAliveIdx($mTeam);
        if ($mIdx === -1) { $isWin = true; break; }
        
        // Player Turn (Lượt của tất cả người chơi còn sống)
        foreach ($pTeam as &$p) {
            if ($p['hp'] <= 0) continue;
            
            $targetIdx = $getAliveIdx($mTeam);
            if ($targetIdx === -1) { $isWin = true; break 2; }
            
            // Gambler nội tại đột tử
            if ($p['char'] === 'vua_tro_choi' && mt_rand(1, 100) <= 20) {
                $p['hp'] = 0;
                $combatLog[] = ["speaker" => "system", "msg" => "💀 Nội tại Gambler: {$p['name']} bị ĐỘT TỬ!"];
                $logState($combatLog, $pTeam, $mTeam);
                continue;
            }
            
            $dmg = $p['atk'];
            $isCrit = (mt_rand(1, 100) <= ($p['crit'] * 100));
            
            // Buff Đội Trưởng
            if ($p['char'] === $leader) {
                if ($activeBuff === 'nhat_kiem' && $turn === 1) $dmg *= 3;
                if ($activeBuff === 'phong_tien' && $turn === 1) $isCrit = true;
            }
            
            if ($isCrit) $dmg = intval($dmg * 1.5);
            
            // Tiên Tri Thấu Thị (Buff Đội Trưởng)
            if ($p['char'] === $leader && $activeBuff === 'thau_thi' && $mTeam[$targetIdx]['hp'] < ($mTeam[$targetIdx]['max_hp'] * 0.5)) {
                $dmg = $mTeam[$targetIdx]['hp'];
                $combatLog[] = ["speaker" => "player", "msg" => "👁️ Tiên Tri nhìn thấu điểm yếu! Trực tiếp kết liễu {$mTeam[$targetIdx]['name']}!"];
                $mTeam[$targetIdx]['hp'] = 0;
                $logState($combatLog, $pTeam, $mTeam);
                continue;
            }

            $mTeam[$targetIdx]['hp'] -= $dmg;
            
            $lifesteal = intval($dmg * $p['lifesteal']);
            if ($lifesteal > 0) {
                $p['hp'] += $lifesteal;
                if ($p['hp'] > $p['max_hp']) $p['hp'] = $p['max_hp'];
            }
            
            $msg = "{$p['name']} chém {$mTeam[$targetIdx]['name']} gây <b>{$dmg}</b> ST";
            if ($isCrit) $msg .= " 💥(Chí mạng)";
            if ($lifesteal > 0) $msg .= " 🩸(Hút {$lifesteal} HP)";
            $combatLog[] = ["speaker" => "player", "msg" => $msg];
            
            // Ma Kiếm Sĩ Song Long Kích
            if ($p['char'] === $leader && $activeBuff === 'song_long' && $mTeam[$targetIdx]['hp'] > 0) {
                $mTeam[$targetIdx]['hp'] -= $dmg;
                $combatLog[] = ["speaker" => "player", "msg" => "⚔️ Song Long Kích! Đánh bồi thêm <b>{$dmg}</b> ST!"];
            }
            
            // Nghịch chuyển Thời Không
            if ($p['char'] === $leader && $activeBuff === 'nghich_chuyen' && $p['hp'] < ($p['max_hp'] * 0.2)) {
                $p['hp'] = $p['max_hp'];
                $combatLog[] = ["speaker" => "player", "msg" => "⏳ Nghịch Chuyển Thời Không! Khôi phục 100% thể lực!"];
                $activeBuff = null; 
            }
            
            // Chế thuốc nổ
            if ($p['char'] === $leader && $activeBuff === 'che_thuoc' && $turn === 3 && $mTeam[$targetIdx]['hp'] > 0) {
                $mTeam[$targetIdx]['hp'] = 0;
                $combatLog[] = ["speaker" => "player", "msg" => "💣 Thuốc nổ phát nổ! Quái vật bị nát bấy!"];
            }
            $logState($combatLog, $pTeam, $mTeam);
        }
        
        if ($getAliveIdx($mTeam) === -1) { $isWin = true; break; }
        
        // Monster Turn
        foreach ($mTeam as &$m) {
            if ($m['hp'] <= 0) continue;
            $targetIdx = $getAliveIdx($pTeam);
            if ($targetIdx === -1) { $isWin = false; break 2; }
            
            $target = &$pTeam[$targetIdx];
            $mDmg = $m['atk'];
            $isEvaded = (mt_rand(1, 100) <= ($target['evade'] * 100));
            
            if ($target['char'] === $leader && $activeBuff === 'phan_than' && $turn <= 2) $isEvaded = true;
            if ($target['char'] === $leader && $activeBuff === 'bong_toi' && $turn <= 3) $isEvaded = true;
            if ($target['char'] === $leader && $activeBuff === 'ru_ngu' && $turn <= 2) $isEvaded = true;
            
            if ($isEvaded) {
                $msg = "💨 {$target['name']} đã né tránh đòn đánh của {$m['name']}!";
                $combatLog[] = ["speaker" => "system", "msg" => $msg];
            } else {
                $target['hp'] -= $mDmg;
                $msg = "{$m['name']} vả {$target['name']} gây <b>{$mDmg}</b> ST!";
                // Huyết nguyệt hồi máu
                if ($weather === 'blood') {
                    $heal = intval($mDmg * 0.1);
                    $m['hp'] = min($m['max_hp'], $m['hp'] + $heal);
                    $msg .= " 🩸(Quái hồi {$heal} HP)";
                }
                $combatLog[] = ["speaker" => "monster", "msg" => $msg];
            }
            $logState($combatLog, $pTeam, $mTeam);
        }
        
        if ($getAliveIdx($pTeam) === -1) { $isWin = false; break; }
        $turn++;
    }

    $rewardGtlm = 0;
    $trophyAwarded = null;
    $msg = "";
    
    $conn->begin_transaction();
    try {
        if ($isWin) {
            $rewardGtlm = $baseReward;
            
            // Nội tại
            if (in_array('dao_tac', $teamRaw)) $rewardGtlm = intval($rewardGtlm * 1.15);
            if (in_array('than_tai', $teamRaw) && $floor % 5 === 0) $rewardGtlm *= 4;
            if (in_array('tho_san', $teamRaw) && $isBossFloor) $rewardGtlm *= 2;
            if (in_array('thuong_nhan', $teamRaw)) $rewardGtlm += intval($prog['total_gtlm_won'] * 0.05);
            if (in_array('gia_kim', $teamRaw)) {
                $dmgDealt = 0;
                foreach($mTeam as $m) $dmgDealt += ($m['max_hp'] - max(0, $m['hp']));
                $rewardGtlm += intval($dmgDealt * 10);
            }
            
            // Tuyệt kỹ Leader
            if ($activeBuff === 'trao_phung') {
                $rewardGtlm *= 2;
                $combatLog[] = ["speaker" => "system", "msg" => "💰 Tráo Phụng nhân đôi tiền thưởng!"];
            }
            if ($activeBuff === 'hao_quang') {
                $rewardGtlm = ($baseReward * 5);
                $combatLog[] = ["speaker" => "system", "msg" => "🌟 Hào Quang Hoàng Kim! Nhận tiền tương đương tầng Boss!"];
            }
            if ($activeBuff === 'dong_dau') {
                $rewardGtlm *= 10;
            }
            if ($activeBuff === 'be_cong') {
                $rewardGtlm = 0;
            }
            
            $deduct = 0;
            if ($activeBuff === 'hoi_lo') {
                $deduct = intval($prog['total_gtlm_won'] * 0.20);
                $rewardGtlm = -$deduct; 
            }
            
            $msg = "🎉 Đội của bạn đã hạ gục toàn bộ quái vật và nhận " . number_format($rewardGtlm) . " GTLM!";
            if ($rewardGtlm < 0) $msg = "💸 Bạn đã tốn " . number_format(abs($rewardGtlm)) . " GTLM để hối lộ!";
            
            $chkMoney = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
            $chkMoney->bind_param("i", $userId);
            $chkMoney->execute();
            $chkMoney->get_result()->fetch_assoc();
            $chkMoney->close();

            $upMoney = $conn->prepare("UPDATE users SET Money = GREATEST(0, Money + ?) WHERE Iduser = ?");
            $upMoney->bind_param("di", $rewardGtlm, $userId);
            $upMoney->execute();
            $upMoney->close();
            
            $newFloor = $floor + 1;
            
            foreach ($_SESSION['tower_cooldown_floors'] as $k => $v) {
                if ($v > 0) $_SESSION['tower_cooldown_floors'][$k] = $v - 1;
            }
            
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
            
            $up = $conn->prepare("UPDATE tower_user_progress SET current_floor=?, highest_floor=GREATEST(highest_floor, ?), total_wins=total_wins+1, total_gtlm_won=GREATEST(0, total_gtlm_won+?) WHERE user_id=?");
            $up->bind_param("iidi", $newFloor, $newFloor, $rewardGtlm, $userId);
            $up->execute();
            $up->close();
            
            if ($isBossFloor && $rewardGtlm > 0) {
                $chatMsg = "🗼 Đội của [" . $username . "] vừa dọn dẹp Tầng " . $floor . " tại Tháp Thần Bài! Nhận " . number_format($rewardGtlm) . " GTLM" . ($trophyAwarded ? " và báu vật [" . $trophyAwarded . "]" : "") . "!";
                $chatStmt = $conn->prepare("INSERT INTO chat_messages (user_id, username, message, avatar, created_at) VALUES (?, ?, ?, ?, NOW())");
                if ($chatStmt) {
                    $chatStmt->bind_param("isss", $userId, $username, $chatMsg, $avatar);
                    $chatStmt->execute();
                    $chatStmt->close();
                }
            }
            
            $gh = $conn->prepare("INSERT INTO game_history (user_id, game_name, bet_amount, win_amount, is_win, played_at) VALUES (?, 'tower_gods', 0, ?, 1, NOW())");
            $gh->bind_param("id", $userId, $rewardGtlm);
            $gh->execute();
            $gh->close();
            
        } else {
            $msg = "💀 Toàn bộ đội hình của bạn đã bị tiêu diệt!";
            $newFloor = 1;
            
            if (in_array('xuyen_khong', $teamRaw)) {
                $newFloor = max(1, $floor - 1);
                $combatLog[] = ["speaker" => "system", "msg" => "⏳ Nội tại Xuyên Không: Bạn chỉ bị lùi 1 tầng!"];
                $msg .= " (Nhờ Xuyên Không, đội hình chỉ bị lùi 1 tầng!)";
            }
            
            $up = $conn->prepare("UPDATE tower_user_progress SET current_floor=? WHERE user_id=?");
            $up->bind_param("ii", $newFloor, $userId);
            $up->execute();
            $up->close();
            
            $gh = $conn->prepare("INSERT INTO game_history (user_id, game_name, bet_amount, win_amount, is_win, played_at) VALUES (?, 'tower_gods', 0, 0, 0, NOW())");
            $gh->bind_param("i", $userId);
            $gh->execute();
            $gh->close();
        }

        $conn->commit();

        $progNew = getUserProgress($conn, $userId, $username, $avatar);
        $cds = [];
        foreach($all_chars as $c) {
            $cds[$c] = getCooldownLeft($c);
        }

        echo json_encode([
            'success'        => true,
            'combat_log'     => $combatLog,
            'is_win'         => $isWin,
            'reward_gtlm'    => $rewardGtlm,
            'trophy_awarded' => $trophyAwarded,
            'message'        => $msg,
            'progress'       => $progNew,
            'user_balance'   => getUserBalance($conn, $userId),
            'cooldowns'      => $cds,
            'mTeam'          => $mTeam,
            'pTeam'          => $pTeam
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

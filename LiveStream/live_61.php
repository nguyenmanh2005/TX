<?php
/**
 * 🗼 LiveStream Bàn 61 - Tháp Thần Bài (Tower of Gods 100 Tầng)
 * Tích hợp đầy đủ cơ chế 3v3 RPG Turn-based Auto-battler, 20 Danh Tướng, Tuyệt Kỹ Lãnh Tụ,
 * Ghi log cược game_history chuẩn mực, hiệu ứng đồ họa huyền ảo theo đúng game gốc tower_of_gods.php.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../game_history_helper.php';
require_once __DIR__ . '/bot_streamer_helper.php';

// Khởi tạo/Lấy Bot Streamer User
$botUser   = getOrCreateBotStreamerUser($conn, 'bot_61', 50000000);
$botUserId = (int)$botUser['Iduser'];
$userId    = $botUserId;
$username  = 'bot_61';
$avatar    = 'https://api.dicebear.com/7.x/avataaars/svg?seed=bot61';

$_SESSION['Iduser'] = $botUserId;
$_SESSION['Name']   = $username;
$_SESSION['Avatar'] = $avatar;

// Đảm bảo bot luôn dồi dào GTLM để leo tháp mượt mà
$chk = $conn->query("SELECT Money, Name FROM users WHERE Iduser = $userId");
$uRow = $chk ? $chk->fetch_assoc() : null;
$money = $uRow ? (float)$uRow['Money'] : 0;
if ($money < 2000000) {
    $conn->query("UPDATE users SET Money = Money + 50000000 WHERE Iduser = $userId");
    $money += 50000000;
}
$userName = $uRow['Name'] ?? 'bot_61';

// Theme huyền bí
$particleColor = $particleColor ?? '#a855f7';
$shapeColors   = $shapeColors   ?? ['#a855f7', '#fbbf24', '#3b82f6', '#ef4444'];
$bgGradient    = $bgGradient    ?? ['#030611', '#0d0821', '#0a0020'];
$bgGradientCSS = 'linear-gradient(135deg,' . $bgGradient[0] . ' 0%,' . $bgGradient[1] . ' 50%,' . ($bgGradient[2] ?? $bgGradient[1]) . ' 100%)';

// ==========================================
// CÁC HÀM XỬ LÝ RPG GAMEPLAY & PROGRESS
// ==========================================
function getFloorReward($floor) {
    $base = $floor * 10000;
    if ($floor % 10 === 0) return $base * 5;  // Mốc Boss: x5
    if ($floor % 5 === 0)  return $base * 2;  // Tầng mốc: x2
    return $base;
}

function getFloorTrophy($floor) {
    $trophies = [
        5   => ['code' => 'trophy_f5',   'name' => '🏆 Cúp Chinh Phục Tầng 5',     'icon' => '🏆', 'type' => 'trophy'],
        10  => ['code' => 'trophy_f10',  'name' => '🐉 Tượng Hắc Long Tầng 10',    'icon' => '🐉', 'type' => 'statue'],
        20  => ['code' => 'trophy_f20',  'name' => '⚡ Kiếm Sét Tầng 20',           'icon' => '⚡', 'type' => 'trophy'],
        30  => ['code' => 'trophy_f30',  'name' => '🌟 Ngôi Sao Chiến Thần Tầng 30', 'icon' => '🌟', 'type' => 'trophy'],
        50  => ['code' => 'trophy_f50',  'name' => '👑 Vương Miện Bất Tử Tầng 50',  'icon' => '👑', 'type' => 'statue'],
        75  => ['code' => 'trophy_f75',  'name' => '💎 Kim Cương Huyết Tầng 75',    'icon' => '💎', 'type' => 'statue'],
        100 => ['code' => 'trophy_f100', 'name' => '🔱 Thần Thánh Đỉnh Tháp 100',   'icon' => '🔱', 'type' => 'legendary'],
    ];
    if (isset($trophies[$floor])) return $trophies[$floor];
    if ($floor % 25 === 0) return ['code' => "trophy_f{$floor}", 'name' => "🎖️ Huy Chương Tầng {$floor}", 'icon' => '🎖️', 'type' => 'trophy'];
    return null;
}

function getBotProgress($conn, $userId, $username, $avatar) {
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
        case 'kiem_thanh':    $stats['atk'] *= 1.5; $stats['crit'] = 0.3; break;
        case 'cung_thu':      $stats['crit'] = 0.5; $stats['hp'] *= 0.8; break;
        case 'ninja':         $stats['evade'] = 0.3; $stats['atk'] *= 1.2; break;
        case 'tien_tri':      /* debuff quái vật */ break;
        case 'ma_kiem_si':    $stats['lifesteal'] = 0.1; break;
        case 'phap_su':       $stats['atk'] *= 2; $stats['hp'] *= 0.5; break;
        case 'cuong_chien_si':$stats['hp'] *= 2; break;
        case 'hac_am':        $stats['lifesteal'] = 0.5; break;
        case 'muc_su':        $stats['hp'] *= 2; $stats['lifesteal'] = 0.2; break;
        case 'trieu_hoi':     $stats['hp'] *= 1.3; $stats['atk'] *= 1.1; break;
        case 'dao_tac':       /* passive gold */ break;
        case 'than_tai':      /* passive gold */ break;
        case 'thuong_nhan':   /* passive gold */ break;
        case 'tho_san':       /* passive gold */ break;
        case 'nhac_si':       $stats['hp'] *= 1.5; break;
        case 'gia_kim':       /* passive gold */ break;
        case 'cuong_tin':     $stats['atk'] *= 2.5; $stats['hp'] *= 0.4; break;
        case 'xuyen_khong':   $stats['hp'] *= 1.5; $stats['atk'] *= 1.2; break;
        case 'do_te':         $stats['lifesteal'] = 0.4; $stats['hp'] *= 1.3; break;
        case 'vua_tro_choi':  $stats['atk'] *= 2; $stats['hp'] *= 2; break;
    }

    $stats['hp'] = (int)$stats['hp'];
    $stats['atk'] = (int)$stats['atk'];
    return $stats;
}

$all_chars = [
    'kiem_thanh', 'cung_thu', 'ninja', 'tien_tri', 'ma_kiem_si',
    'phap_su', 'cuong_chien_si', 'hac_am', 'muc_su', 'trieu_hoi',
    'dao_tac', 'than_tai', 'thuong_nhan', 'tho_san', 'nhac_si',
    'gia_kim', 'cuong_tin', 'xuyen_khong', 'do_te', 'vua_tro_choi'
];

if (!isset($_SESSION['tower_cooldown_floors'])) {
    $cd = [];
    foreach ($all_chars as $c) $cd[$c] = 0;
    $_SESSION['tower_cooldown_floors'] = $cd;
}

function getCooldownLeft($char) {
    return $_SESSION['tower_cooldown_floors'][$char] ?? 0;
}

// ==========================================
// AJAX API ENDPOINTS CHO BOT & GIAO DIỆN
// ==========================================
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'];

    // 1. INFO
    if ($action === 'info') {
        $prog = getBotProgress($conn, $userId, $username, $avatar);
        $floor = (int)$prog['current_floor'];
        $reward = getFloorReward($floor);
        $trophy = getFloorTrophy($floor);

        $cds = [];
        foreach ($all_chars as $c) $cds[$c] = getCooldownLeft($c);

        $topRes = $conn->query("SELECT username, avatar, highest_floor, total_wins FROM tower_user_progress ORDER BY highest_floor DESC, total_wins DESC LIMIT 5");
        $leaderboard = [];
        while ($topRes && $r = $topRes->fetch_assoc()) $leaderboard[] = $r;

        $moneyRes = $conn->query("SELECT Money FROM users WHERE Iduser = $userId");
        $curBal = $moneyRes ? (float)$moneyRes->fetch_assoc()['Money'] : 0;

        echo json_encode([
            'success'      => true,
            'progress'     => $prog,
            'user_balance' => $curBal,
            'floor_reward' => $reward,
            'floor_trophy' => $trophy,
            'leaderboard'  => $leaderboard,
            'cooldowns'    => $cds,
            'active_buff'  => $_SESSION['tower_active_buff'] ?? null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. SELECT CHARACTER
    if ($action === 'select_character') {
        $prog = getBotProgress($conn, $userId, $username, $avatar);
        $teamRaw = $_POST['team'] ?? '["kiem_thanh"]';
        $team = json_decode($teamRaw, true);
        if (!is_array($team) || count($team) === 0 || count($team) > 3) {
            $team = ['kiem_thanh'];
        }
        $mainChar = $team[0];
        $teamJson = json_encode($team);

        $stmt = $conn->prepare("UPDATE tower_user_progress SET selected_character = ?, team_chars = ? WHERE user_id = ?");
        $stmt->bind_param("ssi", $mainChar, $teamJson, $userId);
        $stmt->execute();
        $stmt->close();

        $progNew = getBotProgress($conn, $userId, $username, $avatar);
        $cds = [];
        foreach ($all_chars as $c) $cds[$c] = getCooldownLeft($c);

        echo json_encode([
            'success'   => true,
            'message'   => 'Đổi chiến binh thành công!',
            'progress'  => $progNew,
            'cooldowns' => $cds
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 3. USE SKILL
    if ($action === 'use_skill') {
        $prog = getBotProgress($conn, $userId, $username, $avatar);
        $char = $prog['selected_character'] ?? 'kiem_thanh';

        $skillMapping = [
            'kiem_thanh'     => ['skill' => 'nhat_kiem',     'cooldown' => 3, 'name' => 'Nhất Kiếm Đoạt Mệnh'],
            'cung_thu'       => ['skill' => 'phong_tien',    'cooldown' => 3, 'name' => 'Phong Thần Tiễn'],
            'ninja'          => ['skill' => 'phan_than',     'cooldown' => 4, 'name' => 'Phân Thân'],
            'tien_tri'       => ['skill' => 'thau_thi',      'cooldown' => 4, 'name' => 'Thấu Thị'],
            'ma_kiem_si'     => ['skill' => 'song_long',     'cooldown' => 3, 'name' => 'Song Long Kích'],
            'phap_su'        => ['skill' => 'nghich_chuyen', 'cooldown' => 5, 'name' => 'Nghịch Chuyển Thời Không'],
            'cuong_chien_si' => ['skill' => 'thinh_no',      'cooldown' => 4, 'name' => 'Cơn Thịnh Nộ'],
            'hac_am'         => ['skill' => 'bong_toi',      'cooldown' => 6, 'name' => 'Lãnh Vực Bóng Tối'],
            'muc_su'         => ['skill' => 'thanh_ca',      'cooldown' => 5, 'name' => 'Thánh Ca'],
            'trieu_hoi'      => ['skill' => 'hop_the',       'cooldown' => 5, 'name' => 'Triệu Hồi Rồng'],
            'dao_tac'        => ['skill' => 'trao_phung',    'cooldown' => 4, 'name' => 'Trộm Long Tráo Phụng'],
            'than_tai'       => ['skill' => 'hao_quang',     'cooldown' => 4, 'name' => 'Hào Quang Hoàng Kim'],
            'thuong_nhan'    => ['skill' => 'hoi_lo',        'cooldown' => 4, 'name' => 'Hối Lộ'],
            'tho_san'        => ['skill' => 'dong_dau',      'cooldown' => 4, 'name' => 'Đóng Dấu'],
            'nhac_si'        => ['skill' => 'ru_ngu',        'cooldown' => 5, 'name' => 'Giai Điệu Ru Ngủ'],
            'gia_kim'        => ['skill' => 'che_thuoc',     'cooldown' => 3, 'name' => 'Chế Thuốc Nổ'],
            'cuong_tin'      => ['skill' => 'hy_sinh',       'cooldown' => 4, 'name' => 'Hy Sinh'],
            'xuyen_khong'    => ['skill' => 'be_cong',       'cooldown' => 4, 'name' => 'Bẻ Cong Thời Gian'],
            'do_te'          => ['skill' => 'chat_chem',     'cooldown' => 3, 'name' => 'Chặt Chém'],
            'vua_tro_choi'   => ['skill' => 'lat_keo',       'cooldown' => 8, 'name' => 'Lật Kèo']
        ];

        $sInfo = $skillMapping[$char] ?? $skillMapping['kiem_thanh'];
        $left = getCooldownLeft($char);
        if ($left > 0) {
            echo json_encode(['success' => false, 'message' => "Kỹ năng {$sInfo['name']} đang hồi chiêu (còn {$left} Tầng)!"], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $_SESSION['tower_active_buff'] = $sInfo['skill'];
        $_SESSION['tower_cooldown_floors'][$char] = $sInfo['cooldown'];

        echo json_encode([
            'success'       => true,
            'message'       => "Đã kích hoạt Tuyệt Kỹ: {$sInfo['name']}!",
            'active_buff'   => $sInfo['skill'],
            'cooldown_left' => $sInfo['cooldown']
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 4. AUTO_BATTLE (3v3 RPG SIMULATION)
    if ($action === 'auto_battle') {
        $prog = getBotProgress($conn, $userId, $username, $avatar);
        $floor = (int)$prog['current_floor'];
        $baseReward = getFloorReward($floor);
        $trophy = getFloorTrophy($floor);

        $teamRaw = json_decode($prog['team_chars'] ?? '[]', true);
        if (!is_array($teamRaw) || count($teamRaw) === 0) {
            $teamRaw = [$prog['selected_character'] ?? 'kiem_thanh'];
        }
        $teamRaw = array_slice($teamRaw, 0, 3);
        $leader = $teamRaw[0];

        $activeBuff = $_SESSION['tower_active_buff'] ?? null;
        $_SESSION['tower_active_buff'] = null;

        $numMonsters = 1;
        if ($floor >= 11) $numMonsters = 2;
        if ($floor >= 31) $numMonsters = 3;
        $isBossFloor = ($floor % 10 === 0);
        if ($isBossFloor) $numMonsters = 3;

        $scaling = pow($floor, 1.2);
        $mTeam = [];
        for ($i = 0; $i < $numMonsters; $i++) {
            $isBoss = ($isBossFloor && $i === 0);
            $hp = (int)(($scaling * 15) + ($isBoss ? 200 : 50));
            $atk = (int)(($scaling * 3) + ($isBoss ? 20 : 8));
            $name = $isBoss ? "Boss Tầng $floor" : "Quái Vật " . ($i + 1);
            $mAvatar = $isBoss ? '🐉' : '👾';
            if ($floor == 100 && $i == 0) {
                $name = "Thần Bài Tối Thượng"; $mAvatar = '🃏'; $hp *= 2; $atk *= 1.5;
            }
            $mTeam[] = [
                'id' => "m_$i", 'name' => $name, 'avatar' => $mAvatar,
                'hp' => (int)$hp, 'max_hp' => (int)$hp, 'atk' => (int)$atk, 'is_boss' => $isBoss
            ];
        }

        if (in_array('tien_tri', $teamRaw)) {
            foreach ($mTeam as &$m) $m['atk'] = (int)($m['atk'] * 0.8);
        }

        $allNames = [
            'kiem_thanh'=>'Kiếm Thánh', 'cung_thu'=>'Cung Thủ', 'ninja'=>'Ninja', 'tien_tri'=>'Tiên Tri', 'ma_kiem_si'=>'Ma Kiếm Sĩ',
            'phap_su'=>'Pháp Sư', 'cuong_chien_si'=>'Cuồng Sĩ', 'hac_am'=>'Hắc Ám', 'muc_su'=>'Mục Sư', 'trieu_hoi'=>'Triệu Hồi Sư',
            'dao_tac'=>'Đạo Tặc', 'than_tai'=>'Thần Tài', 'thuong_nhan'=>'Thương Nhân', 'tho_san'=>'Thợ Săn', 'nhac_si'=>'Nhạc Sĩ',
            'gia_kim'=>'Giả Kim', 'cuong_tin'=>'Cuồng Tín', 'xuyen_khong'=>'Xuyên Không', 'do_te'=>'Đồ Tể', 'vua_tro_choi'=>'Gambler'
        ];

        $pTeam = [];
        foreach ($teamRaw as $i => $charKey) {
            $stats = getCharCombatStats($charKey, $floor);
            $pTeam[] = [
                'id' => "p_$i", 'char' => $charKey, 'name' => $allNames[$charKey] ?? $charKey,
                'hp' => $stats['hp'], 'max_hp' => $stats['hp'], 'atk' => $stats['atk'],
                'crit' => $stats['crit'], 'lifesteal' => $stats['lifesteal'], 'evade' => $stats['evade']
            ];
        }

        // Leader Active buffs
        if ($activeBuff === 'thinh_no') {
            foreach ($pTeam as &$p) {
                if ($p['char'] === $leader) {
                    $p['hp'] = max(1, (int)($p['hp'] * 0.7));
                    $p['atk'] *= 2;
                }
            }
        }
        if ($activeBuff === 'thanh_ca') {
            foreach ($pTeam as &$p) {
                if ($p['char'] === $leader) {
                    $p['max_hp'] *= 2;
                    $p['hp'] = $p['max_hp'];
                }
            }
        }
        if ($activeBuff === 'hop_the') {
            foreach ($mTeam as &$m) {
                $m['hp'] = max(1, (int)($m['hp'] * 0.5));
            }
        }

        $combatLog = [];
        $combatLog[] = ["speaker" => "system", "msg" => "⚔️ Cuộc chiến bắt đầu tại <b>Tầng {$floor}</b>!"];
        if ($activeBuff) {
            $combatLog[] = ["speaker" => "system", "msg" => "🔥 Tuyệt Kỹ Lãnh Tụ đã sẵn sàng phát huy uy lực!"];
        }

        $logState = function(&$log, &$pT, &$mT) {
            $pState = array_map(fn($p) => ['id' => $p['id'], 'hp' => max(0, $p['hp']), 'max' => $p['max_hp']], $pT);
            $mState = array_map(fn($m) => ['id' => $m['id'], 'hp' => max(0, $m['hp']), 'max' => $m['max_hp']], $mT);
            $log[] = ['turn_end' => true, 'pState' => $pState, 'mState' => $mState];
        };

        $logState($combatLog, $pTeam, $mTeam);

        $getAliveIdx = function($team) {
            foreach ($team as $idx => $t) {
                if ($t['hp'] > 0) return $idx;
            }
            return -1;
        };

        $isWin = false;
        $turn = 1;
        $maxTurns = 20;

        while ($turn <= $maxTurns) {
            // Player Turn
            foreach ($pTeam as &$p) {
                if ($p['hp'] <= 0) continue;
                $targetIdx = $getAliveIdx($mTeam);
                if ($targetIdx === -1) { $isWin = true; break 2; }

                if ($p['char'] === 'vua_tro_choi' && mt_rand(1, 100) <= 20) {
                    $p['hp'] = 0;
                    $combatLog[] = ["speaker" => "system", "msg" => "💀 Nội tại Gambler: {$p['name']} bị ĐỘT TỬ!"];
                    $logState($combatLog, $pTeam, $mTeam);
                    continue;
                }

                $dmg = $p['atk'];
                $isCrit = (mt_rand(1, 100) <= ($p['crit'] * 100));
                if ($p['char'] === $leader) {
                    if ($activeBuff === 'nhat_kiem' && $turn === 1) $dmg *= 3;
                    if ($activeBuff === 'phong_tien' && $turn === 1) $isCrit = true;
                }
                if ($isCrit) $dmg = (int)($dmg * 1.5);

                if ($p['char'] === $leader && $activeBuff === 'thau_thi' && $mTeam[$targetIdx]['hp'] < ($mTeam[$targetIdx]['max_hp'] * 0.5)) {
                    $combatLog[] = ["speaker" => "player", "msg" => "👁️ Tiên Tri nhìn thấu điểm yếu! Trực tiếp kết liễu {$mTeam[$targetIdx]['name']}!"];
                    $mTeam[$targetIdx]['hp'] = 0;
                    $logState($combatLog, $pTeam, $mTeam);
                    continue;
                }

                $mTeam[$targetIdx]['hp'] -= $dmg;
                $lifesteal = (int)($dmg * $p['lifesteal']);
                if ($lifesteal > 0) {
                    $p['hp'] = min($p['max_hp'], $p['hp'] + $lifesteal);
                }

                $msg = "{$p['name']} ra chiêu chém {$mTeam[$targetIdx]['name']} gây <b>{$dmg}</b> ST";
                if ($isCrit) $msg .= " 💥(Chí mạng)";
                if ($lifesteal > 0) $msg .= " 🩸(Hút {$lifesteal} HP)";
                $combatLog[] = ["speaker" => "player", "msg" => $msg];

                if ($p['char'] === $leader && $activeBuff === 'song_long' && $mTeam[$targetIdx]['hp'] > 0) {
                    $mTeam[$targetIdx]['hp'] -= $dmg;
                    $combatLog[] = ["speaker" => "player", "msg" => "⚔️ Song Long Kích! Đánh bồi thêm <b>{$dmg}</b> ST!"];
                }

                if ($p['char'] === $leader && $activeBuff === 'nghich_chuyen' && $p['hp'] < ($p['max_hp'] * 0.2)) {
                    $p['hp'] = $p['max_hp'];
                    $combatLog[] = ["speaker" => "player", "msg" => "⏳ Nghịch Chuyển Thời Không! Khôi phục 100% thể lực!"];
                    $activeBuff = null;
                }

                if ($p['char'] === $leader && $activeBuff === 'che_thuoc' && $turn === 3 && $mTeam[$targetIdx]['hp'] > 0) {
                    $mTeam[$targetIdx]['hp'] = 0;
                    $combatLog[] = ["speaker" => "player", "msg" => "💣 Thuốc nổ phát nổ! Quái vật bị tiêu diệt!"];
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
                    $combatLog[] = ["speaker" => "system", "msg" => "💨 {$target['name']} đã né tránh đòn đánh của {$m['name']}!"];
                } else {
                    $target['hp'] -= $mDmg;
                    $combatLog[] = ["speaker" => "monster", "msg" => "{$m['name']} tấn công {$target['name']} gây <b>{$mDmg}</b> ST!"];
                }
                $logState($combatLog, $pTeam, $mTeam);
            }

            if ($getAliveIdx($pTeam) === -1) { $isWin = false; break; }
            $turn++;
        }

        // KẾT QUẢ & CẬP NHẬT DATABASE
        $rewardGtlm = 0;
        $trophyAwarded = null;

        $conn->begin_transaction();
        try {
            if ($isWin) {
                $rewardGtlm = $baseReward;
                if (in_array('dao_tac', $teamRaw)) $rewardGtlm = (int)($rewardGtlm * 1.15);
                if (in_array('than_tai', $teamRaw) && $floor % 5 === 0) $rewardGtlm *= 4;
                if (in_array('tho_san', $teamRaw) && $isBossFloor) $rewardGtlm *= 2;
                if (in_array('thuong_nhan', $teamRaw)) $rewardGtlm += (int)($prog['total_gtlm_won'] * 0.05);

                if ($activeBuff === 'trao_phung') $rewardGtlm *= 2;
                if ($activeBuff === 'hao_quang')  $rewardGtlm = ($baseReward * 5);
                if ($activeBuff === 'dong_dau')   $rewardGtlm *= 10;
                if ($activeBuff === 'be_cong')    $rewardGtlm = 0;

                $msg = "🎉 Đội hình đã hạ gục toàn bộ quái vật và húp " . number_format($rewardGtlm, 0, ',', '.') . " GTLM!";

                // Cập nhật số dư người dùng
                $conn->query("UPDATE users SET Money = Money + $rewardGtlm WHERE Iduser = $userId");
                $newFloor = $floor + 1;

                // Giảm cooldown
                foreach ($_SESSION['tower_cooldown_floors'] as $k => $v) {
                    if ($v > 0) $_SESSION['tower_cooldown_floors'][$k] = $v - 1;
                }

                // Cúp vinh danh
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

                // Cập nhật tower_user_progress
                $up = $conn->prepare("UPDATE tower_user_progress SET current_floor=?, highest_floor=GREATEST(highest_floor, ?), total_wins=total_wins+1, total_gtlm_won=GREATEST(0, total_gtlm_won+?) WHERE user_id=?");
                $up->bind_param("iidi", $newFloor, $newFloor, $rewardGtlm, $userId);
                $up->execute();
                $up->close();

                // Ghi nhận game_history (Rule 5.2)
                $gh = $conn->prepare("INSERT INTO game_history (user_id, game_name, bet_amount, win_amount, is_win, played_at) VALUES (?, 'tower_gods', 0, ?, 1, NOW())");
                $gh->bind_param("id", $userId, $rewardGtlm);
                $gh->execute();
                $gh->close();

            } else {
                $msg = "💀 Toàn bộ đội hình của bạn đã bay màu ở Tầng {$floor}!";
                $newFloor = 1;
                if (in_array('xuyen_khong', $teamRaw)) {
                    $newFloor = max(1, $floor - 1);
                    $msg .= " (Nhờ Xuyên Không, đội hình chỉ lùi 1 tầng!)";
                }

                $up = $conn->prepare("UPDATE tower_user_progress SET current_floor=? WHERE user_id=?");
                $up->bind_param("ii", $newFloor, $userId);
                $up->execute();
                $up->close();

                // Ghi nhận game_history (Rule 5.2)
                $gh = $conn->prepare("INSERT INTO game_history (user_id, game_name, bet_amount, win_amount, is_win, played_at) VALUES (?, 'tower_gods', 0, 0, 0, NOW())");
                $gh->bind_param("i", $userId);
                $gh->execute();
                $gh->close();
            }

            $conn->commit();

            $progNew = getBotProgress($conn, $userId, $username, $avatar);
            $cds = [];
            foreach ($all_chars as $c) $cds[$c] = getCooldownLeft($c);

            $moneyRes = $conn->query("SELECT Money FROM users WHERE Iduser = $userId");
            $curBal = $moneyRes ? (float)$moneyRes->fetch_assoc()['Money'] : 0;

            echo json_encode([
                'success'        => true,
                'combat_log'     => $combatLog,
                'is_win'         => $isWin,
                'reward_gtlm'    => $rewardGtlm,
                'trophy_awarded' => $trophyAwarded,
                'message'        => $msg,
                'progress'       => $progNew,
                'user_balance'   => $curBal,
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
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Tháp Thần Bài — Bàn Live 61</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@700;900&family=Outfit:wght@400;600;800;900&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/game-ui-enhancements.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            height: 100%;
            overflow: hidden;
            font-family: 'Outfit', sans-serif;
            background: <?=$bgGradientCSS?>;
            background-attachment: fixed;
            color: #e2e8f0;
            cursor: url('../img/chuot.png'), auto;
        }

        #threejs-background { position: fixed; inset: 0; z-index: 0; pointer-events: none; }

        /* Stars */
        .star { position: fixed; border-radius: 50%; background: #fff; animation: twinkle var(--d, 3s) ease-in-out infinite; opacity: 0; pointer-events: none; z-index: 0; }
        @keyframes twinkle { 0%, 100% { opacity: 0; transform: scale(1); } 50% { opacity: var(--o, .6); transform: scale(1.4); } }

        /* Top Header Bar */
        .header-bar {
            width: 100%;
            height: 52px;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(3, 6, 17, 0.85);
            backdrop-filter: blur(15px);
            border-bottom: 2px solid #a855f7;
            position: relative;
            z-index: 50;
        }
        .logo-tower {
            font-family: 'Cinzel Decorative', serif;
            font-size: 15px;
            font-weight: 900;
            color: #fbbf24;
            letter-spacing: 2px;
            text-shadow: 0 0 14px rgba(251, 191, 36, .5);
            display: flex; align-items: center; gap: 8px;
        }
        .user-money {
            background: rgba(0, 0, 0, .5);
            padding: 5px 18px;
            border-radius: 30px;
            border: 1px solid #a855f7;
            font-weight: 800;
            color: #c084fc;
            font-size: 14px;
        }

        /* 3-Column Layout */
        #app {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: 270px 1fr 300px;
            height: calc(100vh - 52px);
            overflow: hidden;
        }

        /* Panels */
        .panel {
            background: rgba(8, 12, 30, 0.85);
            border: 1px solid rgba(168, 85, 247, 0.2);
            backdrop-filter: blur(20px);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .panel-head {
            padding: 14px 12px;
            border-bottom: 1px solid rgba(168, 85, 247, 0.2);
            text-align: center;
            flex-shrink: 0;
        }
        .panel-title {
            font-family: 'Cinzel Decorative', serif;
            font-size: 12px;
            letter-spacing: 2px;
            font-weight: 900;
            background: linear-gradient(to right, #fbbf24, #fff, #fbbf24);
            background-size: 200%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: shimmerText 3s linear infinite;
        }
        @keyframes shimmerText { 0% { background-position: 0%; } 100% { background-position: 200%; } }

        /* Left Panel */
        .floor-track {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .floor-track::-webkit-scrollbar { width: 3px; }
        .floor-track::-webkit-scrollbar-thumb { background: rgba(168, 85, 247, 0.3); border-radius: 2px; }
        .fp-row {
            display: flex; align-items: center; gap: 8px;
            padding: 7px 10px; border-radius: 8px;
            font-size: 11.5px; font-weight: 700;
            border: 1px solid transparent; transition: all 0.2s;
        }
        .fp-row.done { color: #34d399; background: rgba(52, 211, 153, 0.08); }
        .fp-row.current {
            color: #fbbf24;
            background: rgba(251, 191, 36, 0.15);
            border-color: rgba(251, 191, 36, 0.4);
            box-shadow: 0 0 14px rgba(251, 191, 36, 0.18);
        }
        .fp-row.upcoming { color: #475569; }
        .fp-row.boss-f { color: #fca5a5 !important; background: rgba(239, 68, 68, 0.08); border-color: rgba(239, 68, 68, 0.3); }
        .fp-row.boss-f.current { color: #f87171 !important; background: rgba(239, 68, 68, 0.25); border-color: rgba(239, 68, 68, 0.6); }
        .fp-icon { font-size: 13px; width: 18px; text-align: center; flex-shrink: 0; }
        .fp-name { flex: 1; }
        .fp-tag { font-size: 8.5px; padding: 2px 5px; border-radius: 4px; background: rgba(239, 68, 68, 0.3); color: #f87171; font-weight: 900; }

        .stats-panel {
            padding: 12px;
            border-top: 1px solid rgba(168, 85, 247, 0.2);
            background: rgba(4, 8, 20, 0.6);
            flex-shrink: 0;
        }
        .stat-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid rgba(255, 255, 255, 0.04); font-size: 12px; }
        .stat-row:last-child { border: none; }
        .stat-label { color: #64748b; font-weight: 600; text-transform: uppercase; font-size: 10px; letter-spacing: 1px; }
        .stat-value { font-weight: 800; color: #f1f5f9; }

        /* Center Arena */
        #center {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            padding: 16px;
            position: relative;
            overflow: hidden;
        }
        .arena-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
            margin-bottom: 8px;
            z-index: 5;
        }
        .floor-pill {
            font-family: 'Cinzel Decorative', serif;
            font-size: clamp(24px, 4vw, 36px);
            font-weight: 900;
            background: linear-gradient(180deg, #fff 0%, #fbbf24 60%, #d97706 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 0 16px rgba(251, 191, 36, 0.45));
            letter-spacing: 2px;
        }
        .boss-badge {
            display: inline-block;
            padding: 3px 12px;
            background: rgba(239, 68, 68, 0.25);
            border: 1px solid rgba(239, 68, 68, 0.6);
            border-radius: 20px;
            color: #f87171;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 2px;
            margin-top: 2px;
            animation: pulseGlow 1s infinite alternate;
        }

        /* Combat Container */
        .combat-container {
            width: 100%;
            max-width: 680px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            z-index: 5;
        }
        .combat-stage {
            display: flex;
            justify-content: space-between;
            align-items: stretch;
            background: rgba(0, 0, 0, 0.55);
            border-radius: 16px;
            padding: 14px;
            border: 1px solid rgba(168, 85, 247, 0.25);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
            min-height: 140px;
        }
        .team-area {
            display: flex;
            gap: 8px;
            width: 44%;
            justify-content: center;
            transition: transform 0.15s ease-out;
        }
        .combat-entity {
            text-align: center;
            padding: 8px 6px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            transition: all 0.3s;
            flex: 1;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .entity-avatar {
            font-size: 30px;
            transition: transform 0.1s;
        }
        .entity-name {
            font-weight: 800;
            color: #cbd5e1;
            margin: 4px 0 2px 0;
            font-size: 10px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .hp-bar-track {
            background: #1e293b;
            border-radius: 4px;
            height: 6px;
            overflow: hidden;
            border: 1px solid #334155;
            margin: 0 2px;
        }
        .hp-bar-fill {
            background: #22c55e;
            height: 100%;
            width: 100%;
            transition: width 0.3s ease-out, background-color 0.3s;
        }
        .hp-text {
            font-size: 9px;
            margin-top: 2px;
            color: #94a3b8;
            font-weight: 700;
        }

        /* Combat Log */
        .combat-log {
            background: #090e1f;
            border-radius: 12px;
            padding: 12px;
            height: 170px;
            overflow-y: auto;
            border: 1px solid rgba(168, 85, 247, 0.2);
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: #94a3b8;
            display: flex;
            flex-direction: column;
            gap: 6px;
            scroll-behavior: smooth;
        }
        .combat-log::-webkit-scrollbar { width: 3px; }
        .combat-log::-webkit-scrollbar-thumb { background: rgba(168, 85, 247, 0.3); border-radius: 2px; }

        /* Controls */
        .control-zone {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 5;
            margin-top: 4px;
        }
        .btn-battle-main {
            padding: 14px 48px;
            border-radius: 16px;
            font-weight: 900;
            font-size: 18px;
            font-family: 'Outfit', sans-serif;
            border: 2px solid #fbbf24;
            background: linear-gradient(135deg, #a855f7 0%, #7c3aed 100%);
            color: #fff;
            cursor: pointer;
            transition: all 0.25s;
            box-shadow: 0 8px 30px rgba(168, 85, 247, 0.5);
            position: relative;
            overflow: hidden;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .btn-battle-main::before {
            content: '';
            position: absolute;
            top: -50%; left: -75%;
            width: 50%; height: 200%;
            background: rgba(255, 255, 255, 0.3);
            transform: skewX(-20deg);
            animation: btnHighlight 2.5s ease-in-out infinite;
        }
        @keyframes btnHighlight { 0%, 100% { left: -75%; opacity: 0; } 40% { opacity: 1; } 60% { left: 125%; opacity: 0; } }
        .btn-battle-main:hover:not(:disabled) { transform: translateY(-3px) scale(1.02); filter: brightness(1.15); box-shadow: 0 12px 35px rgba(168, 85, 247, 0.7); }
        .btn-battle-main:disabled { opacity: 0.45; cursor: not-allowed !important; transform: none !important; box-shadow: none !important; }

        /* Result Status Badge */
        #result-status-badge {
            position: fixed;
            top: 22%; left: 50%;
            transform: translate(-50%, -50%) scale(0.8);
            display: none;
            align-items: center;
            gap: 12px;
            padding: 10px 24px;
            border-radius: 50px;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
            z-index: 9999;
            pointer-events: none;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            opacity: 0;
            backdrop-filter: blur(10px);
        }
        #result-status-badge.show { opacity: 1; transform: translate(-50%, -50%) scale(1); }
        #result-status-badge.badge-win { background: linear-gradient(135deg, rgba(16, 185, 129, 0.95), rgba(5, 150, 105, 0.95)); border: 2px solid #34d399; box-shadow: 0 0 35px rgba(16, 185, 129, 0.7); color: #fff; }
        #result-status-badge.badge-jackpot { background: linear-gradient(135deg, rgba(168, 85, 247, 0.98), rgba(124, 58, 237, 0.98)); border: 2px solid #c084fc; color: #fff; box-shadow: 0 0 60px rgba(168, 85, 247, 1); animation: pulseGlow .7s infinite alternate; }
        #result-status-badge.badge-lose { background: linear-gradient(135deg, rgba(239, 68, 68, 0.9), rgba(185, 28, 28, 0.9)); border: 2px solid #f87171; box-shadow: 0 0 30px rgba(239, 68, 68, 0.6); color: #fff; }
        @keyframes pulseGlow { from { transform: translate(-50%, -50%) scale(1); } to { transform: translate(-50%, -50%) scale(1.06); filter: brightness(1.2); } }

        /* Overlay Result Stage */
        .overlay-result-stage {
            display: none;
            position: absolute;
            inset: 0;
            background: rgba(3, 6, 17, 0.94);
            backdrop-filter: blur(10px);
            z-index: 20;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 16px;
            animation: fadeStage .35s ease-out;
        }
        @keyframes fadeStage { from { opacity: 0; } to { opacity: 1; } }
        .overlay-result-stage.active { display: flex; }
        .ov-icon-anim { font-size: 64px; animation: bounceIcon .6s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        @keyframes bounceIcon { from { transform: scale(0) rotate(-30deg); opacity: 0; } to { transform: scale(1) rotate(0); opacity: 1; } }
        .ov-title-text { font-family: 'Cinzel Decorative', serif; font-size: 24px; font-weight: 900; text-align: center; }
        .ov-subtitle-text { font-size: 13.5px; color: #94a3b8; text-align: center; max-width: 360px; line-height: 1.5; padding: 0 12px; }
        .ov-reward-capsule { display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; border-radius: 99px; font-size: 16px; font-weight: 900; background: rgba(251, 191, 36, 0.15); border: 1px solid rgba(251, 191, 36, 0.4); color: #fbbf24; }

        .post-action-btn { padding: 12px 32px; border-radius: 12px; font-weight: 800; font-size: 15px; font-family: 'Outfit', sans-serif; border: 1px solid; cursor: pointer; transition: all 0.2s; }
        .btn-advance-floor { background: linear-gradient(135deg, #22c55e, #15803d); border-color: #4ade80; color: #fff; box-shadow: 0 6px 20px rgba(34, 197, 94, 0.4); }
        .btn-advance-floor:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(34, 197, 94, 0.6); }
        .btn-retry-floor { background: rgba(239, 68, 68, 0.18); border-color: rgba(239, 68, 68, 0.5); color: #f87171; }
        .btn-retry-floor:hover { background: rgba(239, 68, 68, 0.3); transform: translateY(-2px); }

        /* Right Panel */
        .rp-body { padding: 14px; flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 14px; }
        .rp-body::-webkit-scrollbar { width: 3px; }
        .rp-body::-webkit-scrollbar-thumb { background: rgba(168, 85, 247, 0.3); border-radius: 2px; }

        /* Character Card */
        .char-card {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.85), rgba(30, 41, 59, 0.85));
            border: 1px solid rgba(251, 191, 36, 0.25);
            border-radius: 14px;
            padding: 12px;
            position: relative;
        }
        .char-info-row { display: flex; align-items: center; gap: 12px; }
        .char-avatar { width: 50px; height: 50px; border-radius: 50%; border: 2px solid #fbbf24; box-shadow: 0 0 12px rgba(251, 191, 36, 0.3); object-fit: cover; background: #0d1527; }
        .char-meta { flex: 1; }
        .char-class-tag { font-size: 10px; color: #fbbf24; font-weight: 900; text-transform: uppercase; letter-spacing: 1px; }
        .char-name-text { font-size: 16px; font-weight: 900; color: #f1f5f9; margin-top: 1px; }
        .char-desc-block { margin-top: 10px; border-top: 1px dashed rgba(255, 255, 255, 0.08); padding-top: 8px; font-size: 11.5px; color: #94a3b8; line-height: 1.4; }
        .char-passive-badge { display: inline-block; background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.3); color: #60a5fa; font-size: 9.5px; font-weight: 800; padding: 2px 6px; border-radius: 5px; margin-bottom: 4px; }

        /* Skill Button */
        .skill-control-box { background: rgba(10, 15, 35, 0.6); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 12px; padding: 10px; text-align: center; }
        .btn-skill-cast {
            width: 100%; padding: 11px; border-radius: 10px; font-family: 'Outfit', sans-serif; font-weight: 900; font-size: 13px;
            border: 1px solid; cursor: pointer; transition: all 0.2s; text-transform: uppercase; letter-spacing: 1px;
        }
        .btn-skill-cast.ready { background: linear-gradient(135deg, #a855f7, #6b21a8); border-color: #d8b4fe; color: #fff; box-shadow: 0 4px 15px rgba(168, 85, 247, 0.35); }
        .btn-skill-cast.ready:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(168, 85, 247, 0.5); }
        .btn-skill-cast.active-buff { background: linear-gradient(135deg, #22c55e, #15803d); border-color: #4ade80; color: #fff; box-shadow: 0 0 15px rgba(34, 197, 94, 0.4); animation: buffPulse 1.5s ease-in-out infinite alternate; }
        @keyframes buffPulse { from { filter: brightness(1); } to { filter: brightness(1.2); } }
        .btn-skill-cast.cooldown { background: rgba(15, 23, 42, 0.6); border-color: rgba(255, 255, 255, 0.08); color: #475569; cursor: default !important; }

        /* 20 Character Selectors */
        .char-selector-bar { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; margin-top: 8px; }
        .char-select-btn {
            background: rgba(15, 23, 42, 0.7); border: 1px solid rgba(255, 255, 255, 0.06); border-radius: 10px;
            padding: 8px 3px; font-size: 10.5px; font-weight: 800; color: #94a3b8; font-family: 'Outfit', sans-serif;
            cursor: pointer; transition: all 0.25s; text-align: center; position: relative; width: 100%;
        }
        .char-select-btn.active {
            background: linear-gradient(135deg, rgba(251, 191, 36, 0.2), rgba(217, 119, 6, 0.2));
            border-color: rgba(251, 191, 36, 0.6); color: #fbbf24;
            box-shadow: 0 0 15px rgba(251, 191, 36, 0.2); transform: translateY(-2px);
        }
        .char-select-btn:hover:not(.active) { background: rgba(30, 41, 59, 0.9); color: #fff; border-color: rgba(255, 255, 255, 0.2); }
        .char-select-icon { display: block; font-size: 20px; margin-bottom: 4px; }
        .char-select-name { display: block; font-size: 10px; letter-spacing: 0.3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .home-link { display: none !important; }
    </style>
</head>
<body>
    <canvas id="threejs-background"></canvas>
    <div id="result-status-badge"><span class="badge-icon">⚔️</span><span class="badge-text">CHIẾN THẮNG</span></div>

    <!-- Header -->
    <header class="header-bar">
        <div class="logo-tower"><span>🗼</span> THÁP THẦN BÀI — VẬN MỆNH CHI LỘ</div>
        <div class="user-money">💰 <span id="balance-val"><?=number_format($money, 0, ',', '.')?></span> GTLM</div>
        <div style="font-size: 12px; color: #94a3b8">STREAMER: <b style="color: #c084fc"><?=htmlspecialchars($userName)?></b></div>
    </header>

    <div id="app">
        <!-- ================= CỘT TRÁI: LỘ TRÌNH & THỐNG KÊ ================= -->
        <div class="panel" id="left-panel">
            <div class="panel-head">
                <div class="panel-title">🗼 LỘ TRÌNH THÁP</div>
                <div style="font-size: 10px; color: #64748b; margin-top: 3px; font-weight: 700">100 TẦNG VẬN MỆNH CHI LỘ</div>
            </div>
            <div class="floor-track" id="floorTrack">
                <div style="color: #475569; font-size: 12px; text-align: center; padding: 20px;">Đang nạp lộ trình...</div>
            </div>
            <div class="stats-panel">
                <div class="stat-row"><span class="stat-label">Số Dư Bot</span><span class="stat-value" style="color: #60a5fa" id="sBalance"><?=number_format($money, 0, ',', '.')?></span></div>
                <div class="stat-row"><span class="stat-label">Kỷ Lục Cao Nhất</span><span class="stat-value" style="color: #fbbf24">Tầng <span id="sBest">1</span></span></div>
                <div class="stat-row"><span class="stat-label">Tổng Trận Thắng</span><span class="stat-value" style="color: #34d399"><span id="sWins">0</span> trận</span></div>
                <div class="stat-row"><span class="stat-label">Tổng Phúc Lộc</span><span class="stat-value" style="color: #c084fc" id="sGtlm">0 GTLM</span></div>
            </div>
        </div>

        <!-- ================= CỘT GIỮA: ĐẤU TRƯỜNG ARENA 3V3 ================= -->
        <div id="center">
            <!-- Header Tầng -->
            <div class="arena-header">
                <div class="floor-pill">🔥 TẦNG <span id="floorNum">1</span></div>
                <div id="bossTag" style="display:none;"><span class="boss-badge">⚡ BOSS TẦNG HOÀNG KIM</span></div>
            </div>

            <!-- Combat Arena 3v3 -->
            <div class="combat-container">
                <div class="combat-stage">
                    <!-- Phe Ta (3 slots) -->
                    <div id="playerTeamArea" class="team-area"></div>

                    <div style="display: flex; align-items: center; justify-content: center; font-size: 26px; font-weight: 900; color: #ef4444; font-style: italic; width: 12%;">VS</div>

                    <!-- Phe Địch (1-3 slots) -->
                    <div id="monsterTeamArea" class="team-area"></div>
                </div>

                <!-- Combat Log -->
                <div class="combat-log" id="combatLog">
                    <div style="text-align: center; color: #64748b; font-style: italic; padding-top: 10px;">Đội hình đang sẵn sàng chiến đấu...</div>
                </div>
            </div>

            <!-- Nút Hành Động -->
            <div class="control-zone">
                <button class="btn-battle-main" id="btnStart" onclick="Game.startBattle()">⚔️ TIẾN VÀO CHIẾN ĐẤU</button>
            </div>

            <!-- Overlay Kết Quả Sau Trận -->
            <div class="overlay-result-stage" id="ovResult">
                <div class="ov-icon-anim" id="ovIcon">🎉</div>
                <div class="ov-title-text" id="ovTitle">-</div>
                <div class="ov-subtitle-text" id="ovSub">-</div>
                <div class="ov-reward-capsule" id="ovReward" style="display:none">💰 <span id="ovAmt">-</span></div>
                <div style="display: flex; gap: 12px; justify-content: center; margin-top: 8px;" id="ovActions"></div>
            </div>
        </div>

        <!-- ================= CỘT PHẢI: CHIẾN BINH & KỸ NĂNG ================= -->
        <div class="panel" id="right-panel">
            <div class="panel-head">
                <div class="panel-title">🎴 CHIẾN BINH & TUYỆT KỸ</div>
            </div>
            <div class="rp-body">
                <!-- Thẻ Nhân Vật -->
                <div class="char-card" id="charCard">
                    <div class="char-info-row">
                        <img class="char-avatar" id="charAvatar" src="https://api.dicebear.com/7.x/avataaars/svg?seed=kiemthanh" alt="Avatar">
                        <div class="char-meta">
                            <div class="char-class-tag" id="charClassTag">KIỂM SOÁT</div>
                            <div class="char-name-text" id="charName">Kiếm Thánh</div>
                        </div>
                    </div>
                    <div class="char-desc-block">
                        <div class="char-passive-badge">Nội Tại Bị Động</div>
                        <p id="charPassiveDesc">Tăng mạnh ATK (+50%), tỷ lệ bạo kích 30%.</p>
                    </div>
                </div>

                <!-- Nút Tuyệt Kỹ -->
                <div class="skill-control-box">
                    <button class="btn-skill-cast ready" id="btnSkill" onclick="Game.castSkill()">KÍCH HOẠT TUYỆT KỸ</button>
                    <div id="skillDescText" style="font-size: 11px; color: #64748b; margin-top: 6px; line-height: 1.4">Đòn chém đầu x3 Sát thương. (CD: 3 tầng)</div>
                </div>

                <!-- Bộ 20 Nhân Vật Selector -->
                <div>
                    <div style="font-size: 11px; color: #94a3b8; letter-spacing: 1.5px; text-transform: uppercase; font-weight: 900; margin-bottom: 6px; text-align: center;">
                        🔄 LỰA CHỌN CHIẾN BINH
                    </div>
                    <div class="char-selector-bar" id="charSelectorBar"></div>
                </div>

                <!-- Bảng Vàng -->
                <div style="border-top: 1px solid rgba(168, 85, 247, 0.2); padding-top: 10px;">
                    <div style="font-size: 10px; color: #64748b; letter-spacing: 1px; text-transform: uppercase; font-weight: 900; margin-bottom: 6px;">🏆 BẢNG VÀNG LEO THÁP</div>
                    <div id="lbBox" style="display: flex; flex-direction: column; gap: 4px;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- STAR BACKGROUND SCRIPT -->
    <script>
        for (let i = 0; i < 40; i++) {
            const star = document.createElement('div');
            star.className = 'star';
            const sz = Math.random() * 2 + 0.8;
            star.style.cssText = `width:${sz}px;height:${sz}px;top:${Math.random()*100}%;left:${Math.random()*100}%;--d:${2+Math.random()*4}s;--o:${0.2+Math.random()*0.6};animation-delay:${Math.random()*4}s`;
            document.body.appendChild(star);
        }
    </script>

    <script>
        window.themeConfig = {
            particleCount: <?=$particleCount ?? 1000?>,
            particleSize: <?=$particleSize ?? 0.05?>,
            particleColor: '<?=$particleColor ?? "#a855f7"?>',
            particleOpacity: <?=$particleOpacity ?? 0.7?>,
            shapeCount: <?=$shapeCount ?? 12?>,
            shapeColors: <?=json_encode($shapeColors ?? ["#a855f7","#fbbf24","#3b82f6","#ef4444"])?>,
            shapeOpacity: <?=$shapeOpacity ?? 0.35?>,
            bgGradient: <?=json_encode($bgGradient ?? ["#030611","#0d0821","#0a0020"])?>
        };
    </script>
    <script src="../threejs-background.js"></script>
    <script src="../assets/js/game-effects.js"></script>
    <script src="../assets/js/game-effects-auto.js"></script>

    <!-- GAME ENGINE SCRIPT -->
    <script>
        const CHARACTER_DB = {
            kiem_thanh:     { tag: 'Kiểm Soát', name: 'Kiếm Thánh', icon: '🥷', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=kiemthanh', passive: 'Tăng mạnh ATK (+50%), tỷ lệ bạo kích 30%.', activeDesc: 'Nhất Kiếm Đoạt Mệnh: Đòn chém đầu x3 Sát thương. (CD: 3 tầng)' },
            cung_thu:       { tag: 'Kiểm Soát', name: 'Cung Thủ', icon: '🏹', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=cungthu', passive: 'Tỷ lệ bạo kích 50%, nhưng HP rất thấp (-20%).', activeDesc: 'Phong Thần Tiễn: Tỷ lệ bạo kích lượt đầu là 100%. (CD: 3 tầng)' },
            ninja:          { tag: 'Kiểm Soát', name: 'Ninja', icon: '🗡️', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=ninja', passive: '30% né tránh sát thương. ATK khá.', activeDesc: 'Phân Thân: Miễn nhiễm sát thương trong 2 hiệp đầu. (CD: 4 tầng)' },
            tien_tri:       { tag: 'Kiểm Soát', name: 'Tiên Tri', icon: '🔮', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=tientri', passive: 'Giảm 20% sức mạnh tấn công của quái vật.', activeDesc: 'Thấu Thị: Trực tiếp kết liễu nếu HP quái < 50%. (CD: 4 tầng)' },
            ma_kiem_si:     { tag: 'Kiểm Soát', name: 'Ma Kiếm Sĩ', icon: '⚔️', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=makiemsi', passive: 'Hút máu cơ bản 10%.', activeDesc: 'Song Long Kích: Chém liên tiếp 2 lần trong hiệp này. (CD: 3 tầng)' },
            phap_su:        { tag: 'Sinh Tồn', name: 'Pháp Sư', icon: '🧙‍♂️', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=phapsu', passive: 'Sát thương phép lớn (ATK x2), máu giấy.', activeDesc: 'Nghịch Chuyển: Tự hồi 100% HP khi máu dưới 20%. (CD: 5 tầng)' },
            cuong_chien_si: { tag: 'Sinh Tồn', name: 'Cuồng Sĩ', icon: '🛡️', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=cuongsj', passive: 'Trâu bò (HP x2).', activeDesc: 'Thịnh Nộ: Hy sinh 30% HP tăng x2 ATK toàn trận. (CD: 4 tầng)' },
            hac_am:         { tag: 'Sinh Tồn', name: 'Hắc Ám', icon: '🧛‍♂️', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=hacam', passive: 'Hút máu cực mạnh (50% ST gây ra).', activeDesc: 'Bóng Tối: Vô hiệu hóa đòn đánh của quái 3 hiệp. (CD: 6 tầng)' },
            muc_su:         { tag: 'Sinh Tồn', name: 'Mục Sư', icon: '⛪', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=mucsu', passive: 'HP dồi dào, hút máu 20%.', activeDesc: 'Thánh Ca: Hồi toàn bộ máu và nhân đôi HP tối đa. (CD: 5 tầng)' },
            trieu_hoi:      { tag: 'Sinh Tồn', name: 'Triệu Hồi Sư', icon: '🐉', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=trieuhoi', passive: 'Chỉ số đồng đều, mạnh về HP.', activeDesc: 'Triệu Hồi Rồng: Rút 50% máu tối đa của quái vật. (CD: 5 tầng)' },
            dao_tac:        { tag: 'Kiếm GTLM', name: 'Đạo Tặc', icon: '🦹', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=daotac', passive: '+15% GTLM thưởng cơ bản.', activeDesc: 'Tráo Phụng: x2 GTLM thưởng tầng này nếu thắng. (CD: 4 tầng)' },
            than_tai:       { tag: 'Kiếm GTLM', name: 'Thần Tài', icon: '🤑', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=thantai', passive: 'Nhận x4 GTLM ở các tầng chia hết cho 5.', activeDesc: 'Hào Quang: Thưởng GTLM tương đương tầng Boss. (CD: 4 tầng)' },
            thuong_nhan:    { tag: 'Kiếm GTLM', name: 'Thương Nhân', icon: '💰', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=thuongnhan', passive: 'Mỗi tầng tự sinh 5% lãi dựa trên tổng thưởng.', activeDesc: 'Hối Lộ: Bỏ qua đánh quái, tốn 20% tổng GTLM. (CD: 4 tầng)' },
            tho_san:        { tag: 'Kiếm GTLM', name: 'Thợ Săn', icon: '🤠', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=thosan', passive: 'GTLM thưởng từ đánh Boss luôn x2.', activeDesc: 'Đóng Dấu: X10 thưởng nếu thắng, quái x2 ATK. (CD: 4 tầng)' },
            nhac_si:        { tag: 'Kiếm GTLM', name: 'Nhạc Sĩ', icon: '🪕', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=nhacsi', passive: 'HP dày.', activeDesc: 'Ru Ngủ: Quái vật bỏ qua 2 hiệp đánh đầu tiên. (CD: 5 tầng)' },
            gia_kim:        { tag: 'Đột Biến', name: 'Giả Kim', icon: '🧪', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=giakim', passive: 'Chuyển hóa 10% sát thương thành GTLM.', activeDesc: 'Thuốc Nổ: Đặt bom nổ chết quái ở hiệp 3. (CD: 3 tầng)' },
            cuong_tin:      { tag: 'Đột Biến', name: 'Cuồng Tín', icon: '🩸', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=cuongtin', passive: 'HP cực thấp, ATK x2.5.', activeDesc: 'Hy Sinh: Tự sát tiêu diệt quái, bảo toàn thưởng. (CD: 4 tầng)' },
            xuyen_khong:    { tag: 'Đột Biến', name: 'Xuyên Không', icon: '⏳', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=xuyenkhong', passive: 'Nếu chết chỉ bị lùi 1 tầng thay vì về tầng 1.', activeDesc: 'Bẻ Cong: Bỏ qua tầng này nhảy lên tầng kế. (CD: 4 tầng)' },
            do_te:          { tag: 'Đột Biến', name: 'Đồ Tể', icon: '🪓', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=dote', passive: 'Hút máu 40%. Trâu bò.', activeDesc: 'Chặt Chém: Tiêu diệt quái thường ngay tức thì. (CD: 3 tầng)' },
            vua_tro_choi:   { tag: 'Đột Biến', name: 'Gambler', icon: '🎲', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=gambler', passive: 'HP và ATK x2, 20% đột tử mỗi hiệp.', activeDesc: 'Lật Kèo: Trò chơi tử thần 50/50. (CD: 8 tầng)' }
        };

        // Render 20 Character Buttons
        const charBar = document.getElementById('charSelectorBar');
        Object.keys(CHARACTER_DB).forEach(k => {
            const c = CHARACTER_DB[k];
            const btn = document.createElement('button');
            btn.className = 'char-select-btn';
            btn.id = `btnChar_${k}`;
            btn.onclick = () => Game.switchCharacter(k);
            btn.innerHTML = `<span class="char-select-icon">${c.icon}</span><span class="char-select-name">${c.name}</span>`;
            charBar.appendChild(btn);
        });

        function showRS(type, text, icon) {
            const b = document.getElementById('result-status-badge');
            if (!b) return;
            b.className = '';
            b.classList.add('badge-' + type);
            b.querySelector('.badge-icon').textContent = icon;
            b.querySelector('.badge-text').textContent = text;
            b.style.display = 'flex';
            void b.offsetWidth;
            b.classList.add('show');

            if (type === 'jackpot' || type === 'win') {
                if (typeof GameEffects !== 'undefined' && GameEffects.win) GameEffects.win();
                if (typeof confetti === 'function') confetti({ particleCount: type === 'jackpot' ? 200 : 90, spread: 75, origin: { y: .5 }, colors: ['#a855f7', '#fbbf24', '#3b82f6'] });
            } else if (type === 'lose') {
                if (typeof GameEffects !== 'undefined' && GameEffects.lose) GameEffects.lose();
            }
            setTimeout(() => {
                b.classList.remove('show');
                setTimeout(() => { b.style.display = 'none'; }, 400);
            }, 3200);
        }

        const Game = (() => {
            let floor = 1;
            let activeBuff = null;
            let selectedChar = 'kiem_thanh';
            let teamChars = ['kiem_thanh'];
            let isBattling = false;

            function renderFloorTrack(currentF) {
                const track = document.getElementById('floorTrack');
                track.innerHTML = '';
                for (let f = 100; f >= 1; f--) {
                    const isBoss = (f % 10 === 0);
                    const isCurrent = (f === currentF);
                    const isDone = (f < currentF);
                    let cls = 'fp-row ' + (isCurrent ? 'current' : (isDone ? 'done' : 'upcoming'));
                    if (isBoss) cls += ' boss-f';

                    const row = document.createElement('div');
                    row.className = cls;
                    row.id = `floorRow_${f}`;
                    row.innerHTML = `
                        <span class="fp-icon">${isDone ? '✓' : (isBoss ? '🐉' : '⚔️')}</span>
                        <span class="fp-name">Tầng ${f}</span>
                        ${isBoss ? '<span class="fp-tag">BOSS</span>' : ''}
                    `;
                    track.appendChild(row);
                }
                const curEl = document.getElementById(`floorRow_${currentF}`);
                if (curEl) curEl.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }

            function updateUIStats(prog, cooldowns, bal) {
                if (!prog) return;
                floor = parseInt(prog.current_floor) || 1;
                $('#floorNum').text(floor);
                $('#sBest').text(prog.highest_floor);
                $('#sWins').text(prog.total_wins);
                $('#sGtlm').text(new Intl.NumberFormat().format(prog.total_gtlm_won) + ' GTLM');
                if (bal !== undefined) {
                    $('#balance-val').text(new Intl.NumberFormat().format(bal));
                    $('#sBalance').text(new Intl.NumberFormat().format(bal));
                }

                if (floor % 10 === 0) $('#bossTag').show(); else $('#bossTag').hide();
                renderFloorTrack(floor);

                if (prog.selected_character) {
                    selectedChar = prog.selected_character;
                    updateCharCardUI(selectedChar);
                }

                try {
                    teamChars = JSON.parse(prog.team_chars || '["kiem_thanh"]');
                } catch(e) { teamChars = [selectedChar]; }

                // Cập nhật nút active
                $('.char-select-btn').removeClass('active');
                teamChars.forEach(c => $(`#btnChar_${c}`).addClass('active'));

                // Cập nhật skill CD
                const cdLeft = cooldowns ? (cooldowns[selectedChar] || 0) : 0;
                updateSkillBtnUI(cdLeft);
            }

            function updateCharCardUI(cKey) {
                const c = CHARACTER_DB[cKey] || CHARACTER_DB.kiem_thanh;
                $('#charAvatar').attr('src', c.avatar);
                $('#charClassTag').text(c.tag.toUpperCase());
                $('#charName').text(c.name);
                $('#charPassiveDesc').text(c.passive);
                $('#skillDescText').text(c.activeDesc);
            }

            function updateSkillBtnUI(cdLeft) {
                const btn = document.getElementById('btnSkill');
                if (activeBuff) {
                    btn.className = 'btn-skill-cast active-buff';
                    btn.textContent = 'TUYỆT KỸ ĐANG KÍCH HOẠT!';
                    btn.disabled = true;
                    return;
                }
                if (cdLeft > 0) {
                    btn.className = 'btn-skill-cast cooldown';
                    btn.disabled = true;
                    btn.textContent = `HỒI CHIÊU (${cdLeft} TẦNG)`;
                } else {
                    btn.className = 'btn-skill-cast ready';
                    btn.disabled = false;
                    btn.textContent = 'KÍCH HOẠT TUYỆT KỸ';
                }
            }

            function createEntityHTML(entity, isPlayer) {
                const hpPct = Math.max(0, (entity.hp / entity.max_hp) * 100);
                const color = isPlayer ? '#3b82f6' : '#ef4444';
                const hpColor = hpPct < 30 ? '#ef4444' : '#22c55e';
                const avatar = (isPlayer && CHARACTER_DB[entity.char]) ? CHARACTER_DB[entity.char].icon : entity.avatar;
                return `
                <div id="${entity.id}" class="combat-entity ${isPlayer ? 'player' : 'monster'}">
                    <div id="${entity.id}_avatar" class="entity-avatar" style="filter: drop-shadow(0 0 10px ${color});">${avatar}</div>
                    <div id="${entity.id}_name" class="entity-name">${entity.name}</div>
                    <div class="hp-bar-track">
                        <div id="${entity.id}_hpBar" class="hp-bar-fill" style="background:${hpColor}; width:${hpPct}%;"></div>
                    </div>
                    <div id="${entity.id}_hpText" class="hp-text">${entity.hp}/${entity.max_hp}</div>
                </div>`;
            }

            async function startBattle() {
                if (isBattling) return;
                isBattling = true;

                $('#ovResult').removeClass('active');
                $('#btnStart').prop('disabled', true).text('⚔️ ĐANG GIAO TRANH...');
                const logBox = document.getElementById('combatLog');
                logBox.innerHTML = '';

                try {
                    const res = await fetch('?action=auto_battle', { method: 'POST' });
                    const data = await res.json();

                    if (!data.success) {
                        logBox.innerHTML = `<div style="color:#ef4444;text-align:center;">❌ ${data.message}</div>`;
                        readyPhase();
                        return;
                    }

                    await playCombatAnimation(data);
                } catch(e) {
                    console.error('[Tower 61 Battle Error]', e);
                    readyPhase();
                }
            }

            async function playCombatAnimation(data) {
                const logBox = document.getElementById('combatLog');
                const pArea = document.getElementById('playerTeamArea');
                const mArea = document.getElementById('monsterTeamArea');

                if (data.pTeam && data.mTeam) {
                    pArea.innerHTML = data.pTeam.map(p => createEntityHTML(p, true)).join('');
                    mArea.innerHTML = data.mTeam.map(m => createEntityHTML(m, false)).join('');
                }

                for (const log of data.combat_log) {
                    if (log.turn_end) {
                        for (const p of log.pState) {
                            const elBar = document.getElementById(p.id + "_hpBar");
                            const elText = document.getElementById(p.id + "_hpText");
                            const elBox = document.getElementById(p.id);
                            if (elBar) {
                                const pct = Math.max(0, (p.hp / p.max) * 100);
                                elBar.style.width = pct + '%';
                                elText.textContent = `${p.hp}/${p.max}`;
                                elBar.style.background = pct < 30 ? '#ef4444' : '#22c55e';
                                if (p.hp <= 0 && elBox) elBox.style.opacity = '0.35';
                            }
                        }
                        for (const m of log.mState) {
                            const elBar = document.getElementById(m.id + "_hpBar");
                            const elText = document.getElementById(m.id + "_hpText");
                            const elBox = document.getElementById(m.id);
                            if (elBar) {
                                const pct = Math.max(0, (m.hp / m.max) * 100);
                                elBar.style.width = pct + '%';
                                elText.textContent = `${m.hp}/${m.max}`;
                                elBar.style.background = pct < 30 ? '#ef4444' : '#22c55e';
                                if (m.hp <= 0 && elBox) elBox.style.opacity = '0.35';
                            }
                        }
                        await new Promise(r => setTimeout(r, 450));
                        continue;
                    }

                    const d = document.createElement('div');
                    d.style.padding = '3px 6px';
                    d.style.borderRadius = '4px';
                    d.style.background = log.speaker === 'system' ? 'rgba(255, 255, 255, 0.05)' :
                                        (log.speaker === 'player' ? 'rgba(59, 130, 246, 0.12)' : 'rgba(239, 68, 68, 0.12)');
                    d.style.color = log.speaker === 'system' ? '#fbbf24' :
                                   (log.speaker === 'player' ? '#93c5fd' : '#fca5a5');
                    d.innerHTML = log.msg;
                    logBox.appendChild(d);
                    logBox.scrollTop = logBox.scrollHeight;

                    // Hiệu ứng lướt đánh
                    if (log.speaker === 'player') {
                        pArea.style.transform = 'translateX(10px)';
                        setTimeout(() => pArea.style.transform = 'none', 120);
                    } else if (log.speaker === 'monster') {
                        mArea.style.transform = 'translateX(-10px)';
                        setTimeout(() => mArea.style.transform = 'none', 120);
                    }
                    await new Promise(r => setTimeout(r, 160));
                }

                await new Promise(r => setTimeout(r, 800));

                if (data.progress) {
                    updateUIStats(data.progress, data.cooldowns, data.user_balance);
                }

                activeBuff = null;
                const ov = document.getElementById('ovResult');
                if (data.is_win) {
                    $('#ovIcon').text('⚔️');
                    $('#ovTitle').text('CHIẾN THẮNG!').css('color', '#22c55e');
                    showRS('win', 'CHIẾN THẮNG! LÊN TẦNG ' + (floor), '⚔️');
                } else {
                    $('#ovIcon').text('💀');
                    $('#ovTitle').text('TỬ THẦN!').css('color', '#ef4444');
                    showRS('lose', 'BAY MÀU! QUAY LẠI TẦNG 1', '💀');
                }

                $('#ovSub').html(data.message);
                if (data.reward_gtlm > 0) {
                    $('#ovReward').show();
                    $('#ovAmt').text(new Intl.NumberFormat().format(data.reward_gtlm) + ' GTLM');
                } else {
                    $('#ovReward').hide();
                }

                const actRow = document.getElementById('ovActions');
                if (data.is_win) {
                    actRow.innerHTML = `<button class="post-action-btn btn-advance-floor" onclick="Game.nextFloor()">🚀 Lên Tầng ${floor}!</button>`;
                } else {
                    actRow.innerHTML = `<button class="post-action-btn btn-retry-floor" onclick="Game.closeOverlay()">🔄 Quay Lại Tầng 1</button>`;
                }

                ov.classList.add('active');
            }

            function nextFloor() {
                $('#ovResult').removeClass('active');
                readyPhase();
            }

            function closeOverlay() {
                $('#ovResult').removeClass('active');
                readyPhase();
            }

            function readyPhase() {
                isBattling = false;
                $('#btnStart').prop('disabled', false).text('⚔️ TIẾN VÀO CHIẾN ĐẤU');
            }

            function castSkill() {
                fetch('?action=use_skill', { method: 'POST' })
                    .then(res => res.json())
                    .then(data => {
                        if (!data.success) return;
                        activeBuff = data.active_buff;
                        const btn = document.getElementById('btnSkill');
                        btn.className = 'btn-skill-cast active-buff';
                        btn.textContent = 'TUYỆT KỸ ĐANG KÍCH HOẠT!';
                        if (data.cooldown_left) updateSkillBtnUI(data.cooldown_left);
                    });
            }

            function switchCharacter(char) {
                if (isBattling) return;
                teamChars = [char];
                const fd = new FormData();
                fd.append('team', JSON.stringify(teamChars));

                fetch('?action=select_character', { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && data.progress) {
                            updateUIStats(data.progress, data.cooldowns);
                        }
                    });
            }

            function initInfo() {
                fetch('?action=info')
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            updateUIStats(data.progress, data.cooldowns, data.user_balance);

                            if (data.leaderboard && data.leaderboard.length) {
                                const lbBox = document.getElementById('lbBox');
                                lbBox.innerHTML = data.leaderboard.map((u, i) => `
                                    <div style="display:flex; justify-content:space-between; font-size:11.5px; padding:3px 0; border-bottom:1px solid rgba(255,255,255,0.04);">
                                        <span style="color:#cbd5e1;"><b>#${i+1}</b> ${u.username}</span>
                                        <span style="color:#fbbf24; font-weight:800;">T${u.highest_floor} (${u.total_wins}W)</span>
                                    </div>
                                `).join('');
                            }
                        }
                    });
            }

            return {
                init: initInfo,
                startBattle,
                nextFloor,
                closeOverlay,
                castSkill,
                switchCharacter,
                isBattling: () => isBattling
            };
        })();

        $(document).ready(() => {
            Game.init();
        });
    </script>

    <!-- Bot AI & Virtual Cursor -->
    <script src="../assets/js/bot_virtual_cursor.js"></script>
    <script src="bots/bot_61.js"></script>
</body>
</html>

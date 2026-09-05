<?php
/**
 * 📺 Phòng Xem Live Stream v3.0 — Chế Độ Chỉ Xem (Spectator Only)
 * Nhúng bàn game thật (games/*.php) ở Chế Độ Chỉ Xem Live, khóa toàn bộ thao tác đặt cược/xóc trong iframe.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['Iduser'])) {
    header("Location: ../login.php");
    exit();
}
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/bot_streamer_helper.php';

$userId = (int)$_SESSION['Iduser'];
$tableId = isset($_GET['id']) ? (int)$_GET['id'] : 1;
if ($tableId < 1 || $tableId > 58) $tableId = 1;

// Lấy thông tin Bot Streamer để nạp Theme của Streamer thay vì người xem
$botUser = getOrCreateBotStreamerUser($conn, 'bot_' . $tableId, 50000000);
$botUserId = $botUser['Iduser'];
$useBotTheme = $botUserId;

require_once __DIR__ . '/../load_theme.php';

$gameFilesMap = [
    1 => ['file' => 'live_1.php', 'real_game' => '../games/baucua.php', 'name' => 'Thế Giới Linh Thú', 'icon' => '🐾'],
    2 => ['file' => 'live_2.php', 'real_game' => '../games/xocdia.php', 'name' => 'Trận Địa Trắng Đỏ', 'icon' => '🎲'],
    3 => ['file' => 'live_3.php', 'real_game' => '../games/crash.php', 'name' => 'Tiên Tri Vũ Trụ', 'icon' => '🚀'],
    4 => ['file' => 'live_4.php', 'real_game' => '../games/daga.php', 'name' => 'Đại Chiến Thần Kê', 'icon' => '🐓'],
    5 => ['file' => 'live_5.php', 'real_game' => '../games/dragontiger.php', 'name' => 'Chiến Trường Rồng Hổ', 'icon' => '🐉'],
    6 => ['file' => '../games/cyber_racing.php?live=1', 'real_game' => '../games/cyber_racing.php', 'name' => 'Đua Thú Cyberpunk', 'icon' => '🏎️'],
    7 => ['file' => 'live_7.php', 'real_game' => '../games/plinko.php', 'name' => 'Plinko Royale', 'icon' => '🎱'],
    8 => ['file' => 'live_8.php', 'real_game' => '../games/slot.php', 'name' => 'Slot Machine Premium', 'icon' => '🎰'],
    9 => ['file' => 'live_9.php', 'real_game' => '../games/baccarat.php', 'name' => 'Baccarat', 'icon' => '🎪'],
    10 => ['file' => 'live_10.php', 'real_game' => '../games/banharc.php', 'name' => 'Bắn Cá Arcade', 'icon' => '🐟'],
    11 => ['file' => 'live_11.php', 'real_game' => '../games/battleroyale.php', 'name' => 'Battleroyale', 'icon' => '🎮'],
    12 => ['file' => 'live_12.php', 'real_game' => '../games/bingo.php', 'name' => 'Bingo', 'icon' => '🕹️'],
    13 => ['file' => 'live_13.php', 'real_game' => '../games/bj.php', 'name' => 'Bj', 'icon' => '🎮'],
    14 => ['file' => 'live_14.php', 'real_game' => '../games/bjo.php', 'name' => 'Bjo', 'icon' => '🎴'],
    15 => ['file' => 'live_15.php', 'real_game' => '../games/blackjack.php', 'name' => 'Blackjack', 'icon' => '🎯'],
    16 => ['file' => 'live_16.php', 'real_game' => '../games/blackjack_multi.php', 'name' => 'Blackjack Multi', 'icon' => '🎲'],
    17 => ['file' => 'live_17.php', 'real_game' => '../games/caribbean.php', 'name' => 'Caribbean', 'icon' => '🕹️'],
    18 => ['file' => 'live_18.php', 'real_game' => '../games/coinflip.php', 'name' => 'Coinflip', 'icon' => '🎴'],
    19 => ['file' => 'live_19.php', 'real_game' => '../games/community_lottery.php', 'name' => 'Community Lottery', 'icon' => '🎮'],
    20 => ['file' => 'live_20.php', 'real_game' => '../games/craps.php', 'name' => 'Craps', 'icon' => '🎯'],
    21 => ['file' => 'live_21.php', 'real_game' => '../games/dice.php', 'name' => 'Dice', 'icon' => '🎯'],
    22 => ['file' => 'live_22.php', 'real_game' => '../games/duangua.php', 'name' => 'Duangua', 'icon' => '🎲'],
    23 => ['file' => 'live_23.php', 'real_game' => '../games/fantan.php', 'name' => 'Fantan', 'icon' => '🎴'],
    24 => ['file' => 'live_24.php', 'real_game' => '../games/farm.php', 'name' => 'Farm', 'icon' => '🃏'],
    25 => ['file' => 'live_25.php', 'real_game' => '../games/gacha_cards.php', 'name' => 'Gacha Cards', 'icon' => '🎲'],
    26 => ['file' => 'live_26.php', 'real_game' => '../games/greedy_cave.php', 'name' => 'Greedy Cave', 'icon' => '🃏'],
    27 => ['file' => 'live_27.php', 'real_game' => '../games/hilo.php', 'name' => 'Hilo', 'icon' => '🎮'],
    28 => ['file' => 'live_28.php', 'real_game' => '../games/holdem.php', 'name' => 'Holdem', 'icon' => '🎪'],
    29 => ['file' => 'live_29.php', 'real_game' => '../games/hopmu.php', 'name' => 'Hopmu', 'icon' => '🎴'],
    30 => ['file' => 'live_30.php', 'real_game' => '../games/horserace.php', 'name' => 'Horserace', 'icon' => '🕹️'],
    31 => ['file' => 'live_31.php', 'real_game' => '../games/horserace_pvp.php', 'name' => 'Horserace Pvp', 'icon' => '🎮'],
    32 => ['file' => 'live_32.php', 'real_game' => '../games/jojo_battle.php', 'name' => 'Jojo Battle', 'icon' => '🎮'],
    33 => ['file' => 'live_33.php', 'real_game' => '../games/keno.php', 'name' => 'Keno', 'icon' => '🕹️'],
    34 => ['file' => 'live_34.php', 'real_game' => '../games/letitride.php', 'name' => 'Letitride', 'icon' => '🎴'],
    35 => ['file' => 'live_35.php', 'real_game' => '../games/limbo.php', 'name' => 'Limbo', 'icon' => '🎮'],
    36 => ['file' => 'live_36.php', 'real_game' => '../games/lottery.php', 'name' => 'Lottery', 'icon' => '🎴'],
    37 => ['file' => 'live_37.php', 'real_game' => '../games/mahjong.php', 'name' => 'Mahjong', 'icon' => '🎴'],
    38 => ['file' => 'live_38.php', 'real_game' => '../games/megaspin.php', 'name' => 'Megaspin', 'icon' => '🎮'],
    39 => ['file' => 'live_39.php', 'real_game' => '../games/mines.php', 'name' => 'Mines', 'icon' => '🎯'],
    40 => ['file' => 'live_40.php', 'real_game' => '../games/minesweeper.php', 'name' => 'Minesweeper', 'icon' => '🎪'],
    41 => ['file' => 'live_41.php', 'real_game' => '../games/number.php', 'name' => 'Number', 'icon' => '🎴'],
    42 => ['file' => 'live_42.php', 'real_game' => '../games/paigow.php', 'name' => 'Paigow', 'icon' => '🕹️'],
    43 => ['file' => 'live_43.php', 'real_game' => '../games/poker.php', 'name' => 'Poker', 'icon' => '🃏'],
    44 => ['file' => 'live_44.php', 'real_game' => '../games/pontoon.php', 'name' => 'Pontoon', 'icon' => '🕹️'],
    45 => ['file' => 'live_45.php', 'real_game' => '../games/reddog.php', 'name' => 'Reddog', 'icon' => '🎴'],
    46 => ['file' => 'live_46.php', 'real_game' => '../games/roulette.php', 'name' => 'Roulette', 'icon' => '🎲'],
    47 => ['file' => 'live_47.php', 'real_game' => '../games/rps.php', 'name' => 'Rps', 'icon' => '🎲'],
    48 => ['file' => 'live_48.php', 'real_game' => '../games/ruttham.php', 'name' => 'Ruttham', 'icon' => '🎮'],
    49 => ['file' => 'live_49.php', 'real_game' => '../games/samloc.php', 'name' => 'Sâm Lốc', 'icon' => '🃏'],
    50 => ['file' => 'live_50.php', 'real_game' => '../games/scratch.php', 'name' => 'Scratch', 'icon' => '🃏'],
    51 => ['file' => 'live_51.php', 'real_game' => '../games/sicbo.php', 'name' => 'Sicbo', 'icon' => '🎮'],
    52 => ['file' => 'live_52.php', 'real_game' => '../games/threecard.php', 'name' => 'Threecard', 'icon' => '🎯'],
    53 => ['file' => 'live_53.php', 'real_game' => '../games/tower.php', 'name' => 'Tower', 'icon' => '🕹️'],
    54 => ['file' => 'live_54.php', 'real_game' => '../games/tusac.php', 'name' => 'Tusac', 'icon' => '🎴'],
    55 => ['file' => 'live_55.php', 'real_game' => '../games/videopoker.php', 'name' => 'Videopoker', 'icon' => '🎯'],
    56 => ['file' => 'live_56.php', 'real_game' => '../games/vietlott.php', 'name' => 'Vietlott', 'icon' => '🎯'],
    57 => ['file' => 'live_57.php', 'real_game' => '../games/war.php', 'name' => 'War', 'icon' => '🎪'],
    58 => ['file' => 'live_58.php', 'real_game' => '../games/yahtzee.php', 'name' => 'Yahtzee', 'icon' => '🎯']
];

$botThemesMap = [
    1 => ['particleColor' => '#00ff88', 'shapeColors' => ['#00ff88', '#00b894', '#fdcb6e'], 'bgGradient' => ['#000000', '#001a11', '#002a1b']],
    2 => ['particleColor' => '#00f2fe', 'shapeColors' => ['#00f2fe', '#712cf9', '#ff4757'], 'bgGradient' => ['#000000', '#050015', '#0a0025']],
    3 => ['particleColor' => '#ff4757', 'shapeColors' => ['#ff4757', '#ff6b81', '#70a1ff'], 'bgGradient' => ['#000000', '#12001a', '#250033']],
    4 => ['particleColor' => '#ff7f50', 'shapeColors' => ['#ff4757', '#ff7f50', '#ffd700'], 'bgGradient' => ['#000000', '#1a0500', '#2d0a00']],
    5 => ['particleColor' => '#f1c40f', 'shapeColors' => ['#f1c40f', '#e67e22', '#3498db'], 'bgGradient' => ['#000000', '#1a1500', '#2d2400']],
    6 => ['particleColor' => '#12c2e9', 'shapeColors' => ['#12c2e9', '#c471ed', '#f64f59'], 'bgGradient' => ['#0f0c29', '#302b63', '#24243e']],
    7 => ['particleColor' => '#ffd700', 'shapeColors' => ['#ffd700', '#ff4757', '#12c2e9'], 'bgGradient' => ['#1a0b2e', '#2a1b3d', '#000000']],
    8 => ['particleColor' => '#ff00ff', 'shapeColors' => ['#ff00ff', '#00ffff', '#ffff00'], 'bgGradient' => ['#000000', '#110011', '#220022']],
    9 => ['particleColor' => '#ff7f50', 'shapeColors' => ['#ff4757', '#ff7f50', '#ffd700'], 'bgGradient' => ['#000000', '#1a0500', '#2d0a00']],
    10 => ['particleColor' => '#12c2e9', 'shapeColors' => ['#12c2e9', '#c471ed', '#f64f59'], 'bgGradient' => ['#0f0c29', '#302b63', '#24243e']],
    11 => ['particleColor' => '#ffd700', 'shapeColors' => ['#ffd700', '#ff4757', '#12c2e9'], 'bgGradient' => ['#1a0b2e', '#2a1b3d', '#000000']],
    12 => ['particleColor' => '#f1c40f', 'shapeColors' => ['#f1c40f', '#e67e22', '#3498db'], 'bgGradient' => ['#000000', '#1a1500', '#2d2400']],
    13 => ['particleColor' => '#00f2fe', 'shapeColors' => ['#00f2fe', '#712cf9', '#ff4757'], 'bgGradient' => ['#000000', '#050015', '#0a0025']],
    14 => ['particleColor' => '#f1c40f', 'shapeColors' => ['#f1c40f', '#e67e22', '#3498db'], 'bgGradient' => ['#000000', '#1a1500', '#2d2400']],
    15 => ['particleColor' => '#00ff88', 'shapeColors' => ['#00ff88', '#00b894', '#fdcb6e'], 'bgGradient' => ['#000000', '#001a11', '#002a1b']],
    16 => ['particleColor' => '#12c2e9', 'shapeColors' => ['#12c2e9', '#c471ed', '#f64f59'], 'bgGradient' => ['#0f0c29', '#302b63', '#24243e']],
    17 => ['particleColor' => '#ff4757', 'shapeColors' => ['#ff4757', '#ff6b81', '#70a1ff'], 'bgGradient' => ['#000000', '#12001a', '#250033']],
    18 => ['particleColor' => '#ff4757', 'shapeColors' => ['#ff4757', '#ff6b81', '#70a1ff'], 'bgGradient' => ['#000000', '#12001a', '#250033']],
    19 => ['particleColor' => '#00f2fe', 'shapeColors' => ['#00f2fe', '#712cf9', '#ff4757'], 'bgGradient' => ['#000000', '#050015', '#0a0025']],
    20 => ['particleColor' => '#ff00ff', 'shapeColors' => ['#ff00ff', '#00ffff', '#ffff00'], 'bgGradient' => ['#000000', '#110011', '#220022']],
    21 => ['particleColor' => '#ff4757', 'shapeColors' => ['#ff4757', '#ff6b81', '#70a1ff'], 'bgGradient' => ['#000000', '#12001a', '#250033']],
    22 => ['particleColor' => '#ff4757', 'shapeColors' => ['#ff4757', '#ff6b81', '#70a1ff'], 'bgGradient' => ['#000000', '#12001a', '#250033']],
    23 => ['particleColor' => '#12c2e9', 'shapeColors' => ['#12c2e9', '#c471ed', '#f64f59'], 'bgGradient' => ['#0f0c29', '#302b63', '#24243e']],
    24 => ['particleColor' => '#00f2fe', 'shapeColors' => ['#00f2fe', '#712cf9', '#ff4757'], 'bgGradient' => ['#000000', '#050015', '#0a0025']],
    25 => ['particleColor' => '#ff7f50', 'shapeColors' => ['#ff4757', '#ff7f50', '#ffd700'], 'bgGradient' => ['#000000', '#1a0500', '#2d0a00']],
    26 => ['particleColor' => '#ffd700', 'shapeColors' => ['#ffd700', '#ff4757', '#12c2e9'], 'bgGradient' => ['#1a0b2e', '#2a1b3d', '#000000']],
    27 => ['particleColor' => '#ff4757', 'shapeColors' => ['#ff4757', '#ff6b81', '#70a1ff'], 'bgGradient' => ['#000000', '#12001a', '#250033']],
    28 => ['particleColor' => '#f1c40f', 'shapeColors' => ['#f1c40f', '#e67e22', '#3498db'], 'bgGradient' => ['#000000', '#1a1500', '#2d2400']],
    29 => ['particleColor' => '#f1c40f', 'shapeColors' => ['#f1c40f', '#e67e22', '#3498db'], 'bgGradient' => ['#000000', '#1a1500', '#2d2400']],
    30 => ['particleColor' => '#12c2e9', 'shapeColors' => ['#12c2e9', '#c471ed', '#f64f59'], 'bgGradient' => ['#0f0c29', '#302b63', '#24243e']],
    31 => ['particleColor' => '#00ff88', 'shapeColors' => ['#00ff88', '#00b894', '#fdcb6e'], 'bgGradient' => ['#000000', '#001a11', '#002a1b']],
    32 => ['particleColor' => '#12c2e9', 'shapeColors' => ['#12c2e9', '#c471ed', '#f64f59'], 'bgGradient' => ['#0f0c29', '#302b63', '#24243e']],
    33 => ['particleColor' => '#12c2e9', 'shapeColors' => ['#12c2e9', '#c471ed', '#f64f59'], 'bgGradient' => ['#0f0c29', '#302b63', '#24243e']],
    34 => ['particleColor' => '#00ff88', 'shapeColors' => ['#00ff88', '#00b894', '#fdcb6e'], 'bgGradient' => ['#000000', '#001a11', '#002a1b']],
    35 => ['particleColor' => '#ff7f50', 'shapeColors' => ['#ff4757', '#ff7f50', '#ffd700'], 'bgGradient' => ['#000000', '#1a0500', '#2d0a00']],
    36 => ['particleColor' => '#ff4757', 'shapeColors' => ['#ff4757', '#ff6b81', '#70a1ff'], 'bgGradient' => ['#000000', '#12001a', '#250033']],
    37 => ['particleColor' => '#f1c40f', 'shapeColors' => ['#f1c40f', '#e67e22', '#3498db'], 'bgGradient' => ['#000000', '#1a1500', '#2d2400']],
    38 => ['particleColor' => '#ffd700', 'shapeColors' => ['#ffd700', '#ff4757', '#12c2e9'], 'bgGradient' => ['#1a0b2e', '#2a1b3d', '#000000']],
    39 => ['particleColor' => '#ffd700', 'shapeColors' => ['#ffd700', '#ff4757', '#12c2e9'], 'bgGradient' => ['#1a0b2e', '#2a1b3d', '#000000']],
    40 => ['particleColor' => '#00f2fe', 'shapeColors' => ['#00f2fe', '#712cf9', '#ff4757'], 'bgGradient' => ['#000000', '#050015', '#0a0025']],
    41 => ['particleColor' => '#ffd700', 'shapeColors' => ['#ffd700', '#ff4757', '#12c2e9'], 'bgGradient' => ['#1a0b2e', '#2a1b3d', '#000000']],
    42 => ['particleColor' => '#ff4757', 'shapeColors' => ['#ff4757', '#ff6b81', '#70a1ff'], 'bgGradient' => ['#000000', '#12001a', '#250033']],
    43 => ['particleColor' => '#ff00ff', 'shapeColors' => ['#ff00ff', '#00ffff', '#ffff00'], 'bgGradient' => ['#000000', '#110011', '#220022']],
    44 => ['particleColor' => '#ff00ff', 'shapeColors' => ['#ff00ff', '#00ffff', '#ffff00'], 'bgGradient' => ['#000000', '#110011', '#220022']],
    45 => ['particleColor' => '#ff7f50', 'shapeColors' => ['#ff4757', '#ff7f50', '#ffd700'], 'bgGradient' => ['#000000', '#1a0500', '#2d0a00']],
    46 => ['particleColor' => '#00ff88', 'shapeColors' => ['#00ff88', '#00b894', '#fdcb6e'], 'bgGradient' => ['#000000', '#001a11', '#002a1b']],
    47 => ['particleColor' => '#12c2e9', 'shapeColors' => ['#12c2e9', '#c471ed', '#f64f59'], 'bgGradient' => ['#0f0c29', '#302b63', '#24243e']],
    48 => ['particleColor' => '#ff7f50', 'shapeColors' => ['#ff4757', '#ff7f50', '#ffd700'], 'bgGradient' => ['#000000', '#1a0500', '#2d0a00']],
    49 => ['particleColor' => '#ff4757', 'shapeColors' => ['#ff4757', '#ff6b81', '#70a1ff'], 'bgGradient' => ['#000000', '#12001a', '#250033']],
    50 => ['particleColor' => '#00f2fe', 'shapeColors' => ['#00f2fe', '#712cf9', '#ff4757'], 'bgGradient' => ['#000000', '#050015', '#0a0025']],
    51 => ['particleColor' => '#ff4757', 'shapeColors' => ['#ff4757', '#ff6b81', '#70a1ff'], 'bgGradient' => ['#000000', '#12001a', '#250033']],
    52 => ['particleColor' => '#00f2fe', 'shapeColors' => ['#00f2fe', '#712cf9', '#ff4757'], 'bgGradient' => ['#000000', '#050015', '#0a0025']],
    53 => ['particleColor' => '#00ff88', 'shapeColors' => ['#00ff88', '#00b894', '#fdcb6e'], 'bgGradient' => ['#000000', '#001a11', '#002a1b']],
    54 => ['particleColor' => '#ffd700', 'shapeColors' => ['#ffd700', '#ff4757', '#12c2e9'], 'bgGradient' => ['#1a0b2e', '#2a1b3d', '#000000']],
    55 => ['particleColor' => '#ff00ff', 'shapeColors' => ['#ff00ff', '#00ffff', '#ffff00'], 'bgGradient' => ['#000000', '#110011', '#220022']],
    56 => ['particleColor' => '#ff00ff', 'shapeColors' => ['#ff00ff', '#00ffff', '#ffff00'], 'bgGradient' => ['#000000', '#110011', '#220022']],
    57 => ['particleColor' => '#ffd700', 'shapeColors' => ['#ffd700', '#ff4757', '#12c2e9'], 'bgGradient' => ['#1a0b2e', '#2a1b3d', '#000000']],
    58 => ['particleColor' => '#ff4757', 'shapeColors' => ['#ff4757', '#ff6b81', '#70a1ff'], 'bgGradient' => ['#000000', '#12001a', '#250033']]
];

$currentGame = $gameFilesMap[$tableId];
$currentBotTheme = $botThemesMap[$tableId] ?? null;

// Áp dụng Theme riêng biệt của Streamer/Bàn Live
if (!empty($currentBotTheme)) {
    if (!empty($currentBotTheme['particleColor'])) $particleColor = $currentBotTheme['particleColor'];
    if (!empty($currentBotTheme['shapeColors'])) $shapeColors = $currentBotTheme['shapeColors'];
    if (!empty($currentBotTheme['bgGradient'])) {
        $bgGradient = $currentBotTheme['bgGradient'];
        $bgGradientCSS = 'linear-gradient(135deg, ' . 
            htmlspecialchars($bgGradient[0]) . ' 0%, ' . 
            htmlspecialchars($bgGradient[1]) . ' 50%, ' . 
            htmlspecialchars($bgGradient[2] ?? $bgGradient[1]) . ' 100%)';
    }
}

// Lấy số dư người dùng (Spectator)
$spectatorId = (int)$_SESSION['Iduser'];
$stmtUser = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$stmtUser->bind_param("i", $spectatorId);
$stmtUser->execute();
$userRow = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

$userMoney = (float)($userRow['Money'] ?? 0);
$userName = $userRow['Name'] ?? 'Đạo Hữu';
$isAdmin = isset($_SESSION['Role']) && $_SESSION['Role'] == 1;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Chỉ Xem Live 24/7 - Trận Địa GTLM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- TTS using native browser API instead of responsivevoice to avoid API key warning -->
    <style>
        html, body, div, p, span, section, header, footer, aside, nav, table, tr, td, iframe, canvas {
            cursor: url('img/chuot.png'), default;
        }
        a, button, input, select, textarea, label, .btn, [role="button"], [onclick], .clickable, .btn-react, .btn-back, .table-selector, .tiktok-gift-card, .btn-quick-tip, .swal2-confirm, .swal2-cancel, .swal2-close,
        a *, button *, [onclick] *, .tiktok-gift-card *, .btn-quick-tip *, .btn-react *, .swal2-popup button, .swal2-popup [onclick], .swal2-popup div[onclick] {
            cursor: url('img/tay.png'), pointer !important;
        }
        #threejs-background { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; pointer-events: none; }
        :root {
            --bg-main: #0b0f19;
            --panel-bg: rgba(30, 41, 59, 0.85);
            --panel-border: rgba(255, 255, 255, 0.1);
            --primary: #6366f1;
            --purple: #a855f7;
            --gold: #fbbf24;
            --emerald: #10b981;
            --rose: #f43f5e;
            --cyan: #06b6d4;
            --text-main: #f8fafc;
            --text-sub: #94a3b8;
        }

        * { box-sizing: border-box; }

        body {
            background: var(--bg-main);
            color: var(--text-main);
            font-family: 'Outfit', 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            height: 100vh;
            overflow: hidden;
            <?= isset($bgGradientCSS) ? "background-image: $bgGradientCSS; background-attachment: fixed;" : "" ?>
        }

        /* Top Header Bar */
        .watch-header {
            height: 60px;
            background: rgba(11, 15, 25, 0.95);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--panel-border);
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 50;
        }

        .btn-back {
            color: var(--text-sub);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.85rem;
            display: flex; align-items: center; gap: 8px;
        }
        .btn-back:hover { color: #fff; }

        .btn-channel-switch {
            background: linear-gradient(135deg, rgba(99,102,241,0.2), rgba(168,85,247,0.2));
            border: 1px solid var(--primary);
            color: #fff;
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 800;
            font-size: 0.85rem;
            outline: none;
            cursor: pointer;
            display: flex; align-items: center; gap: 6px;
            box-shadow: 0 0 10px rgba(99,102,241,0.3);
            transition: all 0.2s;
        }
        .btn-channel-switch:hover {
            transform: scale(1.05); background: var(--primary);
        }

        .user-balance-chip {
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 12px;
            padding: 5px 12px;
            font-weight: 800;
            color: var(--emerald);
            font-size: 0.85rem;
            display: flex; align-items: center; gap: 6px;
        }

        /* Ẩn hoàn toàn thanh cuộn (scrollbar) trên mọi trình duyệt */
        html, body, .player-section, .video-viewport, iframe, .sidebar-chat, .chat-body {
            scrollbar-width: none !important;
            -ms-overflow-style: none !important;
        }
        ::-webkit-scrollbar {
            display: none !important;
            width: 0px !important;
            height: 0px !important;
        }

        /* Layout Grid */
        .watch-layout {
            display: grid;
            grid-template-columns: 1fr 340px;
            height: calc(100vh - 60px);
            overflow: hidden;
        }

        /* Player Section */
        .player-section {
            display: flex;
            flex-direction: column;
            background: #000;
            position: relative;
            overflow: hidden;
        }

        /* 🎮 Real Game Embedded Viewport (Spectator Only) */
        .video-viewport {
            flex: 1;
            position: relative;
            background: #000;
            min-height: 420px;
            overflow: hidden;
        }

        /* 🔒 Khóa tương tác cược trực tiếp trong iframe khi xem live */
        .game-iframe {
            width: 100%;
            height: 100%;
            border: none;
            background: #0b0f19;
            pointer-events: none; /* Khóa tương tác chuột trong iframe */
        }

        /* Live HUD Overlay Badges */
        .live-hud-badge {
            position: absolute;
            top: 15px; left: 15px;
            background: rgba(244, 63, 94, 0.9);
            backdrop-filter: blur(12px);
            color: #fff;
            padding: 4px 12px;
            border-radius: 8px;
            font-weight: 900;
            font-size: 0.8rem;
            display: flex; align-items: center; gap: 6px;
            z-index: 30;
            box-shadow: 0 4px 15px rgba(244, 63, 94, 0.4);
        }

        .viewers-hud-pill {
            position: absolute;
            top: 15px; right: 15px;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(12px);
            color: #fff;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 800;
            border: 1px solid rgba(255, 255, 255, 0.15);
            z-index: 30;
        }

        /* 👁️ Banner Thông Báo Chế Độ Chỉ Xem */
        .spectator-mode-banner {
            position: absolute;
            top: 55px; left: 15px; /* Moved below live badge */
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(99, 102, 241, 0.4);
            color: #fff;
            padding: 6px 12px 6px 14px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 800;
            z-index: 35;
            box-shadow: 0 4px 20px rgba(0,0,0,0.6);
            display: flex; align-items: center; gap: 10px;
        }
        .btn-close-banner {
            background: none; border: none; color: rgba(255,255,255,0.5); cursor: pointer;
            font-size: 0.9rem; padding: 0 4px;
        }
        .btn-close-banner:hover { color: #fff; }

        /* 🎁 Banner Thông Báo Tip GTLM Vinh Danh Streamer Bot */
        .tip-notification-banner {
            position: absolute;
            top: 105px; left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.95), rgba(6, 182, 212, 0.95));
            backdrop-filter: blur(12px);
            border: 2px solid #5eead4;
            color: #fff;
            padding: 8px 24px;
            border-radius: 30px;
            font-size: 0.88rem;
            font-weight: 900;
            z-index: 36;
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.6), 0 0 20px rgba(6, 182, 212, 0.5);
            display: flex; align-items: center; gap: 8px;
            text-align: center;
            white-space: nowrap;
            animation: tipBannerPulse 0.5s ease-out;
        }
        @keyframes tipBannerPulse {
            0% { transform: translate(-50%, -15px) scale(0.85); opacity: 0; }
            60% { transform: translate(-50%, 5px) scale(1.05); opacity: 1; }
            100% { transform: translate(-50%, 0) scale(1); opacity: 1; }
        }

        /* 🎁 TikTok Live Gift Store & FX Styles */
        .tiktok-gift-card {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            padding: 12px 6px;
            text-align: center;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .tiktok-gift-card:hover {
            background: rgba(236, 72, 153, 0.18);
            border-color: #ec4899;
            transform: translateY(-4px) scale(1.04);
            box-shadow: 0 10px 25px rgba(236, 72, 153, 0.4);
        }
        .tiktok-gift-card.vip-castle {
            background: linear-gradient(135deg, rgba(234, 179, 8, 0.18), rgba(236, 72, 153, 0.18));
            border-color: #f59e0b;
        }
        .gift-icon-wrap { font-size: 2.4rem; margin-bottom: 4px; }
        .gift-title { font-weight: 800; font-size: 0.85rem; color: #fff; margin-bottom: 3px; }
        .gift-price { font-size: 0.75rem; color: #f472b6; font-weight: 900; font-family: 'Orbitron', sans-serif; }

        .tiktok-fx-container {
            position: fixed;
            top: 0; left: 0;
            width: 100vw; height: 100vh;
            pointer-events: none;
            z-index: 9999999;
            overflow: hidden;
        }

        /* 🎁 Rain Down From Top To Bottom Gift FX Animations */
        @keyframes giftRainDown {
            0% { transform: translateY(-130px) rotate(0deg) scale(0.6); opacity: 0; }
            15% { opacity: 1; transform: translateY(12vh) rotate(15deg) scale(1.4); }
            85% { opacity: 1; transform: translateY(85vh) rotate(-15deg) scale(1.2); }
            100% { transform: translateY(115vh) rotate(30deg) scale(0.8); opacity: 0; }
        }

        @keyframes giftCrownDropBig {
            0% { transform: translate(-50%, -300px) scale(0.2); opacity: 0; }
            50% { transform: translate(-50%, 25vh) scale(1.5); opacity: 1; }
            75% { transform: translate(-50%, 22vh) scale(1.1); opacity: 1; }
            100% { transform: translate(-50%, 25vh) scale(1.3); opacity: 1; }
        }

        @keyframes giftRocketBlastDown {
            0% { transform: translate(-50%, -200px) scale(0.6); opacity: 1; }
            100% { transform: translate(-50%, 115vh) scale(1.6); opacity: 0; }
        }

        @keyframes giftCarSpeedFull {
            0% { transform: translate(-400px, 35vh); opacity: 0; }
            15% { opacity: 1; }
            85% { opacity: 1; }
            100% { transform: translate(calc(100vw + 400px), 35vh); opacity: 0; }
        }

        @keyframes giftCastleRiseCenter {
            0% { transform: translate(-50%, -300px) scale(0); opacity: 0; }
            60% { transform: translate(-50%, 20vh) scale(1.3); opacity: 1; }
            100% { transform: translate(-50%, 20vh) scale(1.1); opacity: 1; }
        }

        /* Floating Bot Feed Overlay on Video */
        .live-bot-feed-overlay {
            position: absolute;
            top: 20px; left: 20px; /* Moved to top so it doesn't overlap bottom dock on mobile */
            background: rgba(11, 15, 25, 0.85);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            padding: 8px 14px;
            font-size: 0.8rem;
            color: #fff;
            z-index: 35;
            max-width: 320px;
            pointer-events: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.5);
        }

        /* Floating Emoji Overlay */
        .emoji-overlay {
            position: absolute;
            bottom: 80px; right: 20px; /* Adjusted for bottom dock */
            width: 120px; height: 350px;
            pointer-events: none; z-index: 40;
        }

        .floating-emoji {
            position: absolute; bottom: 0; right: 0; font-size: 32px;
            animation: floatUp 2.8s ease-out forwards;
        }

        @keyframes floatUp {
            0% { transform: translateY(0) scale(0.6); opacity: 0; }
            15% { opacity: 1; transform: translateY(-40px) scale(1.4); }
            100% { transform: translateY(-350px) translateX(calc(Math.random() * -60px)) scale(0.9); opacity: 0; }
        }

        /* Action Controls Dock (Mobile-First Bottom Bar) */
        .action-dock {
            background: rgba(15, 23, 42, 0.98);
            backdrop-filter: blur(16px);
            border-top: 1px solid var(--panel-border);
            padding: 12px 20px;
            display: flex; align-items: center; justify-content: space-between;
            z-index: 50;
        }

        .dock-btn {
            width: 44px; height: 44px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; cursor: pointer; border: none; transition: 0.2s;
            color: #fff; background: rgba(255, 255, 255, 0.1);
        }
        .dock-btn:hover { transform: scale(1.15); }
        .btn-tip { background: rgba(251, 191, 36, 0.2); color: var(--gold); border: 1px solid var(--gold); }
        .btn-gift { background: rgba(236, 72, 153, 0.2); color: #f472b6; border: 1px solid #ec4899; box-shadow: 0 0 10px rgba(236,72,153,0.3); }
        .btn-play { background: linear-gradient(135deg, var(--primary), var(--purple)); color: #fff; }
        
        .dock-left, .dock-right { display: flex; gap: 12px; }

        /* Sidebar Chat */
        .sidebar-chat {
            background: var(--panel-bg);
            backdrop-filter: blur(12px);
            border-left: 1px solid var(--panel-border);
            display: flex; flex-direction: column; height: 100%;
            position: relative;
        }

        .chat-header {
            padding: 14px 18px; border-bottom: 1px solid rgba(255,255,255,0.05);
            font-weight: 900; font-size: 0.9rem;
            display: flex; align-items: center; justify-content: space-between;
            color: var(--purple);
            background: rgba(0,0,0,0.2);
        }

        .chat-body {
            flex: 1; overflow-y: auto; padding: 15px;
            display: flex; flex-direction: column; gap: 10px;
        }

        .chat-line { font-size: 0.85rem; line-height: 1.4; word-break: break-word; }
        .chat-user { font-weight: 800; color: var(--purple); margin-right: 4px; }

        .chat-input-area {
            padding: 12px 15px; background: rgba(0, 0, 0, 0.4);
            border-top: 1px solid var(--panel-border); display: flex; gap: 8px;
        }

        .chat-input-area input {
            flex: 1; background: rgba(255, 255, 255, 0.06);
            border: 1px solid var(--panel-border); color: #fff;
            padding: 8px 12px; border-radius: 10px; font-size: 0.85rem; outline: none;
        }

        .btn-send-msg {
            background: var(--primary); color: #fff; border: none;
            border-radius: 10px; padding: 0 16px; font-weight: 800; cursor: pointer;
        }

        /* RESPONSIVE MỚI */
        @media (max-width: 768px) {
            body { overflow: hidden; } /* Prevent native scrolling, keep app feel */
            
            /* Tách lưới: Nửa trên Video, Nửa dưới Chat nổi */
            .watch-layout { 
                grid-template-columns: 1fr; 
                height: calc(100vh - 60px); 
                position: relative;
            }

            .player-section {
                position: absolute; top: 0; left: 0; width: 100%; height: 100%;
                z-index: 10;
            }
            .video-viewport {
                height: 45vh; /* Nửa trên cho video */
                min-height: auto;
            }

            /* Khung chat đè lên nền dạng Glassmorphism ở nửa dưới */
            .sidebar-chat {
                position: absolute;
                bottom: 60px; /* Chừa chỗ cho action-dock */
                left: 0; width: 100%; height: calc(55vh - 60px);
                background: linear-gradient(to top, rgba(11,15,25,0.95) 40%, rgba(11,15,25,0) 100%);
                border: none;
                z-index: 20;
                justify-content: flex-end;
            }
            .chat-header { display: none; } /* Giấu tiêu đề chat trên mobile cho đỡ chật */
            
            .chat-body {
                flex: none; height: 80%;
                mask-image: linear-gradient(to bottom, transparent, black 15%);
                -webkit-mask-image: linear-gradient(to bottom, transparent, black 15%);
            }
            .chat-line { text-shadow: 0 1px 2px #000; }

            .chat-input-area {
                background: transparent; border-top: none;
                padding: 10px 15px;
            }
            .chat-input-area input {
                background: rgba(255,255,255,0.15); backdrop-filter: blur(10px);
                border: 1px solid rgba(255,255,255,0.2);
            }

            /* Đưa thanh action dock ghim chặt dưới đáy màn hình */
            .action-dock {
                position: absolute;
                bottom: 0; left: 0; width: 100%;
                height: 60px;
                background: rgba(11, 15, 25, 0.98);
                padding: 0 15px;
                z-index: 50;
            }
            
            .dock-btn { width: 38px; height: 38px; font-size: 1.1rem; }
            .spectator-mode-banner { top: 40px; font-size: 0.7rem; padding: 4px 8px; }
            .live-hud-badge { font-size: 0.7rem; }
            .viewers-hud-pill { font-size: 0.7rem; }
        }
    </style>
</head>
<body>

    <!-- 🎬 Full Screen Top-to-Bottom Falling Gift FX Container (Z-Index 9999999) -->
    <div id="tiktokGiftFXContainer" class="tiktok-fx-container"></div>

    <!-- Top Header Navigation Bar -->
    <header class="watch-header">
        <a href="spectator.php" class="btn-back">
            <i class="fa fa-arrow-left"></i> Rời Phòng Live
        </a>

        <div style="display: flex; align-items: center; gap: 10px;">
            <button class="btn-channel-switch" onclick="openChannelSwitcher()">
                <i class="fa fa-tv"></i> ĐỔI KÊNH
            </button>
        </div>

        <div class="user-balance-chip">
            <i class="fa fa-wallet"></i>
            <span id="userMoneyDisplay"><?= number_format($userMoney) ?></span> GTLM
        </div>
    </header>

    <!-- Theater Grid Layout -->
    <div class="watch-layout">

        <!-- Player Section -->
        <div class="player-section">

            <!-- 🎮 Real Embedded Game Viewport (Spectator Mode Only) -->
            <div class="video-viewport" id="videoViewport">
                <!-- Live HUD Badge -->
                <div class="live-hud-badge">
                    <span style="width:8px; height:8px; background:#fff; border-radius:50%; display:inline-block;"></span> TRỰC TIẾP 24/7
                </div>

                <!-- Viewers Badge -->
                <div class="viewers-hud-pill" id="viewersPill">
                    <i class="fa fa-user"></i> <span id="viewersCount">320</span> Người xem
                </div>

                <!-- 👁️ Banner Thông Báo Chế Độ Chỉ Xem -->
                <div class="spectator-mode-banner" id="spectatorModeBanner">
                    <i class="fa fa-eye" style="color:var(--cyan)"></i> CHẾ ĐỘ CHỈ XEM LIVE 24/7 (ĐÃ KHÓA BÀN CƯỢC)
                    <button class="btn-close-banner" onclick="hideSpectatorBanner()"><i class="fa fa-times"></i></button>
                </div>
                <script>
                    if(localStorage.getItem('hideSpectatorBanner') === 'true') {
                        document.getElementById('spectatorModeBanner').style.display = 'none';
                    }
                    function hideSpectatorBanner() {
                        $('#spectatorModeBanner').fadeOut();
                        localStorage.setItem('hideSpectatorBanner', 'true');
                    }
                </script>

                <!-- 🎁 Banner Thông Báo Tip GTLM Vinh Danh Streamer Bot -->
                <div id="tipNotificationBanner" class="tip-notification-banner" style="display: none;">
                    <i class="fa fa-gift" style="color: #fef08a; font-size: 1.1rem;"></i>
                    <span id="tipBannerText">🎉 <b>Tuấn Mạnh</b> vừa Tip vinh danh Streamer <b>Lão Tiên Tri</b> +50,000 GTLM! ❤️</span>
                </div>

                <!-- Real Game Iframe (Bản LiveStream Riêng 24/7) -->
                <?php
                $isRoot = (basename(dirname($_SERVER['SCRIPT_FILENAME'])) !== 'LiveStream');
                $iframePrefix = $isRoot ? 'LiveStream/' : '';
                ?>
                <iframe src="<?= htmlspecialchars($iframePrefix . $currentGame['file']) ?>" class="game-iframe" id="gameFrame" title="Real Game Live"></iframe>

                <!-- Live Bot Auto-Play Feed Overlay -->
                <div class="live-bot-feed-overlay" id="botFeedOverlay">
                    ⚡ <span style="color:var(--cyan); font-weight:800;" id="feedBotName">Thánh Húp Lộc</span> vừa Ra Chiêu <b style="color:var(--gold);" id="feedBotBet">20,000 GTLM</b>
                </div>

                <!-- Floating Emoji Overlay -->
                <div class="emoji-overlay" id="emojiOverlay"></div>
            </div>

            <!-- Action Controls Dock (Bottom Bar) -->
            <div class="action-dock">
                <div class="dock-left">
                    <button class="dock-btn" onclick="sendReaction('❤️')">❤️</button>
                    <button class="dock-btn" onclick="sendReaction('🔥')">🔥</button>
                    <button class="dock-btn" onclick="sendReaction('🤣')">🤣</button>
                </div>

                <div class="dock-right">
                    <button class="dock-btn btn-tip" onclick="openTipModal()" title="Tip Cổ Vũ">
                        <i class="fa fa-coins"></i>
                    </button>
                    <button class="dock-btn btn-gift" onclick="openGiftStoreModal()" title="Tặng Quà">
                        <i class="fa fa-gift"></i>
                    </button>
                    <button class="dock-btn btn-play" onclick="confirmPlayNow('<?= htmlspecialchars($currentGame['real_game']) ?>', '<?= htmlspecialchars($currentGame['name']) ?>')" title="Vào Tự Chơi">
                        <i class="fa fa-gamepad"></i>
                    </button>
                </div>
            </div>

        </div>

        <!-- Right Chat Sidebar -->
        <div class="sidebar-chat">
            <div class="chat-header">
                <span><i class="fa fa-comments"></i> CHAT TRỰC TIẾP LIVE</span>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <?php if ($isAdmin): ?>
                    <button onclick="clearLiveChat()" style="background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #ef4444; border-radius: 4px; padding: 2px 6px; font-size: 0.7rem; font-weight: 700; cursor: pointer;"><i class="fa fa-trash"></i> Xóa Chat</button>
                    <?php endif; ?>
                    <span style="font-size: 0.75rem; color: var(--emerald);"><i class="fa fa-circle" style="font-size: 0.5rem;"></i> LIVE 24/7</span>
                </div>
            </div>

            <div class="chat-body" id="chatBody">
                <div class="chat-line"><span class="chat-user">Vệ Binh Trận Địa:</span> Chúc đạo hữu xem live vui vẻ! Đã bật Chế Độ Chỉ Xem Live 24/7 (Đã khóa bàn cược).</div>
            </div>

            <div class="chat-input-area">
                <input type="text" id="chatInput" placeholder="Nhập tin nhắn chat..." onkeypress="if(event.key==='Enter') sendChatMsg()">
                <button class="btn-send-msg" onclick="sendChatMsg()">GỬI</button>
            </div>
        </div>

    </div>

    <script>
        let currentTableId = <?= $tableId ?>;
        let lastChatId = 0;
        let processedReactions = new Set();
        const botNames = ['Thánh Húp Lộc', 'Tu Tiên Cụ', 'Mãnh Hổ 999', 'Lão Tiên Tri', 'Bá Vương Trận Địa', 'Kê Vương 888'];

        const gameFilesMap = <?= json_encode($gameFilesMap) ?>;

        function switchTable(newId) {
            window.location.href = 'watch.php?id=' + newId;
        }

        function openChannelSwitcher() {
            let html = '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; max-height: 50vh; overflow-y: auto; padding-right: 5px;">';
            for (let id in gameFilesMap) {
                const game = gameFilesMap[id];
                const isActive = (id == currentTableId) ? 'border: 2px solid #6366f1; background: rgba(99,102,241,0.2);' : 'border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.05);';
                html += `
                    <div onclick="switchTable(${id})" style="cursor: pointer; padding: 12px; border-radius: 12px; transition: 0.2s; ${isActive}" class="channel-card">
                        <div style="font-size: 2rem; margin-bottom: 5px;">${game.icon}</div>
                        <div style="font-size: 0.8rem; font-weight: 800; color: #fff;">${game.name}</div>
                    </div>
                `;
            }
            html += '</div><style>.channel-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.5); }</style>';
            
            Swal.fire({
                title: '📺 CHUYỂN KÊNH LIVE',
                html: html,
                showConfirmButton: false,
                showCancelButton: true,
                cancelButtonText: 'Đóng',
                background: '#0f172a',
                color: '#f8fafc',
                width: '600px'
            });
        }

        function loadDetails() {
            $.get('api_spectator.php?action=get_table_detail&table_id=' + currentTableId, function(res) {
                if (res.success) {

                    if (res.table && res.table.viewers) {
                        $('#viewersCount').text(res.table.viewers);
                    }
                    if (res.user_money !== undefined) {
                        $('#userMoneyDisplay').text(res.user_money);
                    }

                    // Feed cược bot nổi
                    if (Math.random() < 0.5) {
                        const rName = botNames[Math.floor(Math.random() * botNames.length)];
                        const rMoney = Math.floor(Math.random() * 8 + 1) * 10000;
                        $('#feedBotName').text(rName);
                        $('#feedBotBet').text(new Intl.NumberFormat().format(rMoney) + ' GTLM');
                    }

                    // Render Chat
                    if (res.chats) {
                        res.chats.forEach(chat => {
                            if (chat.id > lastChatId) {
                                let userPrefix = `<span class="chat-user">${chat.user_name}:</span> `;
                                if (chat.message.startsWith('🎙️') || chat.message.startsWith('🎉') || chat.message.startsWith('🎁')) {
                                    userPrefix = ''; // Ẩn tên thô từ CSDL đi vì trong message đã format sẵn tên đẹp
                                }
                                let formattedMsg = chat.message.replace(/\*\*(.*?)\*\*/g, '<b>$1</b>').replace(/\*(.*?)\*/g, '<b>$1</b>');

                                $('#chatBody').append(`
                                    <div class="chat-line">
                                        ${userPrefix}<span>${formattedMsg}</span>
                                    </div>
                                `);
                                lastChatId = chat.id;
                                scrollToBottom();
                            }
                        });
                    }

                    // Render Emojis
                    if (res.reactions) {
                        res.reactions.forEach(r => {
                            if (!processedReactions.has(r.id)) {
                                spawnEmoji(r.emoji);
                                processedReactions.add(r.id);
                            }
                        });
                        if (processedReactions.size > 80) processedReactions.clear();
                    }
                }
            });
        }

        function scrollToBottom() {
            const el = document.getElementById('chatBody');
            el.scrollTop = el.scrollHeight;
        }

        function spawnEmoji(emoji) {
            const id = 'emoji-' + Math.random().toString(36).substr(2, 9);
            const left = Math.random() * 80;
            $('#emojiOverlay').append(`<div class="floating-emoji" id="${id}" style="left: ${left}px;">${emoji}</div>`);
            setTimeout(() => { $('#' + id).remove(); }, 2800);
        }

        function sendReaction(emoji) {
            $.post('api_spectator.php', { action: 'send_reaction', table_id: currentTableId, emoji: emoji });
            spawnEmoji(emoji);
        }

        function sendChatMsg() {
            const msg = $('#chatInput').val().trim();
            if (!msg) return;
            $('#chatInput').val('');
            $.post('api_spectator.php', { action: 'send_chat', table_id: currentTableId, message: msg }, function() {
                loadDetails();
            });
        }

        function clearLiveChat() {
            Swal.fire({
                title: 'Xóa Chat?',
                text: "Bạn có chắc muốn xóa toàn bộ tin nhắn chat trong phòng này?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonText: 'Hủy',
                confirmButtonText: 'Xóa ngay',
                background: '#0f172a',
                color: '#f8fafc'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('api_spectator.php', { action: 'clear_chat', table_id: currentTableId }, function(res) {
                        if (res.success) {
                            $('#chatBody').empty();
                            Swal.fire({ title: 'Thành công', text: 'Đã dọn dẹp khung chat.', icon: 'success', background: '#0f172a', color: '#f8fafc' });
                        } else {
                            Swal.fire({ title: 'Lỗi', text: res.message, icon: 'error', background: '#0f172a', color: '#f8fafc' });
                        }
                    }, 'json');
                }
            });
        }

        function speakStreamerVoice(text) {
            if (!text) return;
            // Thay thế GTLM thành "Gờ Tờ Lờ Mờ" để đọc tiếng Việt chuẩn hơn
            let cleanText = text.replace(/[*#_`~]/g, '').replace(/GTLM/g, 'Gờ Tờ Lờ Mờ').trim();
            if (!cleanText) return;

            // Sử dụng Google Translate TTS để đảm bảo đọc tiếng Việt chuẩn xác
            fallbackSpeech(cleanText);
        }

        function fallbackSpeech(cleanText) {
            if ('speechSynthesis' in window) {
                try {
                    window.speechSynthesis.cancel();
                    const utterance = new SpeechSynthesisUtterance(cleanText);
                    utterance.lang = 'vi-VN';
                    utterance.rate = 1.1;
                    
                    // Cố gắng tìm giọng tiếng Việt cục bộ trên máy
                    let voices = window.speechSynthesis.getVoices();
                    let viVoice = voices.find(v => v.lang.toLowerCase().includes('vi') || v.name.toLowerCase().includes('viet'));
                    
                    if (viVoice) {
                        utterance.voice = viVoice;
                    }

                    window.speechSynthesis.speak(utterance);
                } catch(e) {
                    console.log("Speech error:", e);
                }
            }
        }

        function setTipAmount(amt) {
            $('#customTipInput').val(amt);
            $('.btn-quick-tip').css('border-color', 'rgba(255,255,255,0.15)');
            $(event.target).css('border-color', '#10b981');
        }

        function showTipNotification(userName, botName, amountFormatted, speechText) {
            const text = `🎉 <b style="color:#fef08a;">${userName}</b> vừa Tip vinh danh Streamer <b style="color:#67e8f9;">${botName}</b> +<b style="color:#fef08a;">${amountFormatted}</b> GTLM! ❤️`;
            $('#tipBannerText').html(text);
            const banner = $('#tipNotificationBanner');
            banner.stop(true, true).css('display', 'flex').hide().fadeIn(300);

            if (speechText) {
                speakStreamerVoice(speechText);
            }

            if (window.GameEffects && window.GameEffects.showWin) {
                window.GameEffects.showWin(parseInt(amountFormatted.replace(/\./g, '')) || 50000);
            }

            setTimeout(() => {
                banner.fadeOut(800);
            }, 5500);
        }

        function openTipModal() {
            Swal.fire({
                title: '💰 TIP GTLM CỔ VŨ',
                html: `
                    <div style="margin-bottom: 12px; font-size: 0.9rem; color: #94a3b8;">Chọn nhanh số GTLM Tip vinh danh Streamer:</div>
                    <div class="quick-tip-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(70px, 1fr)); gap: 8px; margin-bottom: 15px;">
                        <button type="button" class="btn-quick-tip" onclick="setTipAmount(10000)" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.15); color: #fff; padding: 8px; border-radius: 8px; font-weight: 800; cursor: pointer; font-size: 0.85rem;">10K</button>
                        <button type="button" class="btn-quick-tip" onclick="setTipAmount(50000)" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.15); color: #fff; padding: 8px; border-radius: 8px; font-weight: 800; cursor: pointer; font-size: 0.85rem;">50K</button>
                        <button type="button" class="btn-quick-tip" onclick="setTipAmount(100000)" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.15); color: #fff; padding: 8px; border-radius: 8px; font-weight: 800; cursor: pointer; font-size: 0.85rem;">100K</button>
                        <button type="button" class="btn-quick-tip" onclick="setTipAmount(500000)" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.15); color: #fff; padding: 8px; border-radius: 8px; font-weight: 800; cursor: pointer; font-size: 0.85rem;">500K</button>
                        <button type="button" class="btn-quick-tip" onclick="setTipAmount(1000000)" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.15); color: #fff; padding: 8px; border-radius: 8px; font-weight: 800; cursor: pointer; font-size: 0.85rem;">1M</button>
                        <button type="button" class="btn-quick-tip" onclick="setTipAmount(5000000)" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.15); color: #fff; padding: 8px; border-radius: 8px; font-weight: 800; cursor: pointer; font-size: 0.85rem;">5M</button>
                        <button type="button" class="btn-quick-tip" onclick="setTipAmount(10000000)" style="background: linear-gradient(135deg, #fbbf24, #f59e0b); border: none; color: #000; padding: 8px; border-radius: 8px; font-weight: 900; cursor: pointer; font-size: 0.85rem; grid-column: span 2;">10M (VIP)</button>
                    </div>
                    <input type="number" id="customTipInput" class="swal2-input" value="10000" min="1000" step="1000" placeholder="Hoặc nhập số GTLM..." style="width: 100%; margin: 0; box-sizing: border-box; background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.2); color: #fff; text-align: center; font-weight: 800;">
                    <input type="text" id="customTipMessage" class="swal2-input" placeholder="Nhập lời nhắn (tùy chọn)..." maxlength="100" style="width: 100%; margin: 10px 0 0 0; box-sizing: border-box; background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.2); color: #fff; text-align: center;">
                `,
                showCancelButton: true,
                confirmButtonText: 'GỬI TIP! ❤️',
                confirmButtonColor: '#10b981',
                cancelButtonText: 'Hủy',
                background: '#0f172a',
                color: '#f8fafc',
                preConfirm: () => {
                    const val = parseInt($('#customTipInput').val());
                    const msg = $('#customTipMessage').val() ? $('#customTipMessage').val().trim() : '';
                    if (!val || val < 1000) {
                        Swal.showValidationMessage('Vui lòng nhập số GTLM tối thiểu từ 1,000 GTLM!');
                        return false;
                    }
                    if (val > 1000000000) {
                        Swal.showValidationMessage('Giới hạn Tip tối đa mỗi lần là 1 Tỷ GTLM!');
                        return false;
                    }
                    return { amount: val, message: msg };
                }
            }).then((res) => {
                if (res.isConfirmed && res.value) {
                    $.post('api_spectator.php', { action: 'tip', table_id: currentTableId, amount: res.value.amount, message: res.value.message }, function(data) {
                        if (data.success) {
                            if (data.newMoney) $('#userMoneyDisplay').text(data.newMoney);
                            showTipNotification(data.userName, data.streamerName, data.amountFormatted, data.speechText);
                            loadDetails();
                        } else {
                            Swal.fire({
                                title: 'LỖI TIP',
                                text: data.message,
                                icon: 'error',
                                background: '#0f172a',
                                color: '#f8fafc'
                            });
                        }
                    });
                }
            });
        }

        function openGiftStoreModal() {
            Swal.fire({
                title: '🎁 CỬA HÀNG VẬT PHẨM VINH DANH STREAMER',
                html: `
                    <div style="font-size:0.85rem; color:#94a3b8; margin-bottom:12px;">Chọn quà vinh danh gửi trực tiếp cho Streamer Bot:</div>
                    <div class="tiktok-gift-store-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 10px; max-height: 50vh; overflow-y: auto; padding-right: 4px;">
                        <div class="tiktok-gift-card" onclick="sendTikTokGift('beer')">
                            <div class="gift-icon-wrap">🍺</div>
                            <div class="gift-title">Bia Lạnh</div>
                            <div class="gift-price">5,000 GTLM</div>
                        </div>
                        <div class="tiktok-gift-card" onclick="sendTikTokGift('moneygun')">
                            <div class="gift-icon-wrap">🔫</div>
                            <div class="gift-title">Súng Bắn GTLM</div>
                            <div class="gift-price">20,000 GTLM</div>
                        </div>
                        <div class="tiktok-gift-card" onclick="sendTikTokGift('gem')">
                            <div class="gift-icon-wrap">💎</div>
                            <div class="gift-title">Kim Cương</div>
                            <div class="gift-price">50,000 GTLM</div>
                        </div>
                        <div class="tiktok-gift-card" onclick="sendTikTokGift('sports_car')">
                            <div class="gift-icon-wrap">🏎️</div>
                            <div class="gift-title">Siêu Xe Cyber</div>
                            <div class="gift-price">200,000 GTLM</div>
                        </div>
                        <div class="tiktok-gift-card" onclick="sendTikTokGift('spaceship')">
                            <div class="gift-icon-wrap">🛸</div>
                            <div class="gift-title">Phi Thuyền</div>
                            <div class="gift-price">500,000 GTLM</div>
                        </div>
                        <div class="tiktok-gift-card" onclick="sendTikTokGift('dragon')">
                            <div class="gift-icon-wrap">🐉</div>
                            <div class="gift-title">Rồng Thần</div>
                            <div class="gift-price">1,000,000 GTLM</div>
                        </div>
                        <div class="tiktok-gift-card" onclick="sendTikTokGift('crown')">
                            <div class="gift-icon-wrap">👑</div>
                            <div class="gift-title">Vương Miện H.Đế</div>
                            <div class="gift-price">5,000,000 GTLM</div>
                        </div>
                        <div class="tiktok-gift-card vip-castle" onclick="sendTikTokGift('planet')">
                            <div class="gift-icon-wrap">💥</div>
                            <div class="gift-title">Vụ Nổ Big Bang</div>
                            <div class="gift-price">10,000,000 GTLM</div>
                        </div>
                    </div>
                `,
                showConfirmButton: false,
                showCancelButton: true,
                cancelButtonText: 'Đóng cửa hàng',
                background: '#0f172a',
                color: '#f8fafc',
                width: '600px'
            });
        }

        function sendTikTokGift(giftId) {
            Swal.fire({
                title: '🎁 CHỌN COMBO QUÀ TẶNG',
                html: `
                    <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                        <button class="combo-btn" onclick="confirmSendGift('${giftId}', 1)">x1</button>
                        <button class="combo-btn" onclick="confirmSendGift('${giftId}', 10)">x10</button>
                        <button class="combo-btn" onclick="confirmSendGift('${giftId}', 50)">x50</button>
                        <button class="combo-btn" onclick="confirmSendGift('${giftId}', 100)">x100</button>
                    </div>
                    <style>
                        .combo-btn { background: rgba(236,72,153,0.2); border: 1px solid #ec4899; color: #f472b6; padding: 10px 20px; border-radius: 8px; font-weight: 900; cursor: pointer; transition: 0.2s; min-width: 60px;}
                        .combo-btn:hover { background: #ec4899; color: #fff; }
                    </style>
                `,
                showConfirmButton: false,
                showCancelButton: true,
                cancelButtonText: 'Hủy',
                background: '#0f172a',
                color: '#f8fafc'
            });
        }

        function confirmSendGift(giftId, combo) {
            Swal.close();
            $.post('api_spectator.php', { action: 'gift_tiktok', table_id: currentTableId, gift_id: giftId, combo: combo }, function(data) {
                if (data.success) {
                    if (data.newMoney) $('#userMoneyDisplay').text(data.newMoney);
                    
                    if (window.GameEffects && window.GameEffects.showTikTokGift) {
                        window.GameEffects.showTikTokGift(data.gift.icon);
                    }
                    if (data.speechText) speakStreamerVoice(data.speechText);
                    
                    triggerGiftFX(data.gift); // Trigger custom FX

                    const banner = $('#tipNotificationBanner');
                    $('#tipBannerText').html(`🎁 <b style="color:#fef08a;">${data.userName}</b> vừa tặng <b>Combo x${combo} ${data.gift.name}</b> cho Streamer <b>${data.streamerName}</b>! ❤️`);
                    banner.stop(true, true).css('display', 'flex').hide().fadeIn(300);
                    setTimeout(() => banner.fadeOut(800), 5500);

                    loadDetails();
                } else {
                    Swal.fire({
                        text: data.message,
                        icon: 'error',
                        background: '#0f172a',
                        color: '#f8fafc'
                    });
                }
            });
        }

        function showGiftBanner(userName, giftIcon, giftName, botName, amountFormatted, speechText) {
            const text = `🎁 <b style="color:#fef08a;">${userName}</b> vừa Tặng <span style="font-size:1.2rem;">${giftIcon}</span> <b style="color:#f472b6;">${giftName}</b> cho Streamer <b style="color:#67e8f9;">${botName}</b> (+${amountFormatted} GTLM)! ❤️`;
            $('#tipBannerText').html(text);
            const banner = $('#tipNotificationBanner');
            banner.stop(true, true).css('display', 'flex').hide().fadeIn(300);

            if (speechText) {
                speakStreamerVoice(speechText);
            }

            setTimeout(() => {
                banner.fadeOut(800);
            }, 6000);
        }

        function triggerGiftFX(gift) {
            const container = $('#tiktokGiftFXContainer');
            if (container.length === 0) return;
            container.empty();

            const giftId = gift.id;
            const icon = gift.icon;

            if (giftId === 'beer' || giftId === 'gem') {
                const el = $(`
                    <div style="position:fixed; top:40%; left:50%; transform:translate(-50%, -50%); font-size:160px; filter:drop-shadow(0 0 50px #3b82f6) drop-shadow(0 0 80px #60a5fa); text-align:center; pointer-events:none; z-index:9999999; animation: giftPopScale 1s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;">
                        ${icon}
                        <div style="font-size:2rem; font-weight:900; color:#60a5fa; text-shadow:0 0 20px #000, 0 0 30px #60a5fa; letter-spacing:2px; font-family:'Outfit', sans-serif;">${gift.name.toUpperCase()}</div>
                    </div>
                `).appendTo(container);

                if (window.gsap) {
                    gsap.fromTo(el, { scale: 0.5, rotation: -20, opacity: 0 }, { scale: 1, rotation: 0, opacity: 1, duration: 1.2, ease: "elastic.out(1, 0.5)" });
                }

                setTimeout(() => el.fadeOut(800, () => el.remove()), 3000);

            } else if (giftId === 'moneygun') {
                for (let i = 0; i < 50; i++) {
                    const leftPos = Math.random() * 100;
                    const animDelay = Math.random() * 1.5;
                    const animDur = Math.random() * 1.5 + 2.0;
                    const size = Math.random() * 30 + 40;

                    const el = $(`
                        <div style="position:fixed; top:-120px; left:${leftPos}vw; font-size:${size}px; z-index:9999999; pointer-events:none; filter:drop-shadow(0 0 10px #22c55e); animation: giftRainDown ${animDur}s linear ${animDelay}s forwards;">
                            💵
                        </div>
                    `).appendTo(container);

                    setTimeout(() => el.remove(), (animDur + animDelay + 0.5) * 1000);
                }
            } else if (giftId === 'sports_car') {
                const car = $(`
                    <div style="position:fixed; top:40vh; left:-400px; font-size:180px; filter:drop-shadow(0 0 60px #14b8a6) drop-shadow(0 0 100px #2dd4bf); text-align:center; pointer-events:none; z-index:9999999; animation: giftCarSpeedFull 2s cubic-bezier(0.4, 0, 0.2, 1) forwards;">
                        🏎️💨
                        <div style="font-size:1.8rem; font-weight:900; color:#2dd4bf; text-shadow:0 0 20px #000, 0 0 30px #2dd4bf; letter-spacing:3px; font-family:'Outfit', sans-serif;">SIÊU XE NEON</div>
                    </div>
                `).appendTo(container);

                if (window.GameEffects && window.GameEffects.showWin) window.GameEffects.showWin(200000);
                setTimeout(() => car.remove(), 2500);

            } else if (giftId === 'spaceship') {
                const ship = $(`
                    <div style="position:fixed; top:10vh; left:50%; transform:translateX(-50%); font-size:200px; filter:drop-shadow(0 0 80px #a855f7) drop-shadow(0 0 130px #c084fc); text-align:center; pointer-events:none; z-index:9999999; animation: giftUFOHover 4s ease-in-out forwards;">
                        🛸
                        <div style="width: 200px; height: 100vh; background: linear-gradient(to bottom, rgba(168,85,247,0.8), transparent); margin: 0 auto; filter: blur(10px); clip-path: polygon(40% 0, 60% 0, 100% 100%, 0% 100%);"></div>
                        <div style="font-size:2.2rem; font-weight:900; color:#c084fc; text-shadow:0 0 25px #000, 0 0 40px #c084fc; letter-spacing:4px; font-family:'Outfit', sans-serif; margin-top: -80vh;">PHI THUYỀN VŨ TRỤ</div>
                    </div>
                `).appendTo(container);

                if (window.gsap) {
                    gsap.fromTo(ship, { y: -300, opacity: 0 }, { y: 0, opacity: 1, duration: 1.5, ease: "power3.out" });
                }

                if (window.GameEffects && window.GameEffects.showWin) window.GameEffects.showWin(500000);
                setTimeout(() => {
                    ship.fadeOut(1000, () => ship.remove());
                }, 4000);

            } else if (giftId === 'dragon') {
                const dragon = $(`
                    <div style="position:fixed; bottom:-300px; left:-200px; font-size:250px; filter:drop-shadow(0 0 90px #f97316) drop-shadow(0 0 150px #fdba74); text-align:center; pointer-events:none; z-index:9999999; animation: giftDragonFly 5s cubic-bezier(0.4, 0, 0.2, 1) forwards;">
                        🐉🔥
                        <div style="font-size:2.5rem; font-weight:900; color:#fb923c; text-shadow:0 0 30px #000, 0 0 50px #fb923c; letter-spacing:5px; font-family:'Outfit', sans-serif;">RỒNG THẦN THỨC TỈNH</div>
                    </div>
                `).appendTo(container);

                if (window.GameEffects && window.GameEffects.showWin) window.GameEffects.showWin(1000000);
                setTimeout(() => dragon.remove(), 5500);

            } else if (giftId === 'crown') {
                const crown = $(`
                    <div style="position:fixed; top:20vh; left:50%; transform:translateX(-50%); font-size:220px; filter:drop-shadow(0 0 100px #fbbf24) drop-shadow(0 0 150px #fcd34d); text-align:center; pointer-events:none; z-index:9999999; animation: giftCrownDropBig 2s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;">
                        👑
                        <div style="font-size:2.8rem; font-weight:900; color:#fbbf24; text-shadow:0 0 30px #000, 0 0 60px #fbbf24; letter-spacing:4px; font-family:'Outfit', sans-serif;">VƯƠNG MIỆN HOÀNG ĐẾ</div>
                    </div>
                `).appendTo(container);

                if (window.gsap) {
                    gsap.fromTo(crown, { scale: 0.1, y: -500, opacity: 0 }, { scale: 1.5, y: 0, opacity: 1, duration: 1.5, ease: "bounce.out" });
                }

                if (window.GameEffects && window.GameEffects.showWin) window.GameEffects.showWin(5000000);
                setTimeout(() => crown.fadeOut(1500, () => crown.remove()), 5000);

            } else if (giftId === 'planet') {
                // Vụ nổ Big Bang
                const explosion = $(`
                    <div style="position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:10vw; height:10vw; background:radial-gradient(circle, #fff 10%, #ef4444 40%, transparent 70%); filter:drop-shadow(0 0 150px #ef4444); border-radius:50%; pointer-events:none; z-index:9999999; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                        <span style="font-size:200px;">💥</span>
                        <div style="font-size:3.5rem; font-weight:900; color:#fff; text-shadow:0 0 40px #000, 0 0 80px #ef4444; letter-spacing:8px; font-family:'Outfit', sans-serif; white-space:nowrap; margin-top:20px;">VỤ NỔ BIG BANG</div>
                    </div>
                `).appendTo(container);

                if (window.gsap) {
                    gsap.fromTo(explosion, 
                        { scale: 0.1, opacity: 1 }, 
                        { scale: 20, opacity: 0, duration: 3.5, ease: "power2.out" }
                    );
                    
                    // Flash screen
                    const flash = $('<div style="position:fixed;top:0;left:0;width:100%;height:100%;background:#fff;z-index:9999998;pointer-events:none;"></div>').appendTo('body');
                    gsap.to(flash, { opacity: 0, duration: 2, ease: "power3.out", onComplete: () => flash.remove() });
                }

                if (window.GameEffects && window.GameEffects.showWin) window.GameEffects.showWin(10000000);
                setTimeout(() => explosion.remove(), 4000);
            }
        }

        (function () {
            window.themeConfig = {
                particleCount: <?= (int)$particleCount ?>, 
                particleSize: <?= (float)$particleSize ?>, 
                particleColor: "<?= htmlspecialchars($particleColor) ?>", 
                particleOpacity: <?= (float)$particleOpacity ?>,
                shapeCount: <?= (int)$shapeCount ?>, 
                shapeColors: <?= json_encode($shapeColors) ?>, 
                shapeOpacity: <?= (float)$shapeOpacity ?>,
                bgGradient: <?= json_encode($bgGradient) ?>
            };
            
            // Dùng đường dẫn tuyệt đối để tự động mapping folder gốc
            const origin = window.location.origin;
            const pathParts = window.location.pathname.split('/');
            const appRoot = '/' + (pathParts[1] && !pathParts[1].includes('.') ? pathParts[1] + '/' : '');
            const base = origin + appRoot;

            ['threejs-background.js', 'assets/js/game-effects.js'].forEach(src => {
                const s = document.createElement('script');
                s.src = base + src; s.async = false;
                document.head.appendChild(s);
            });
        })();

        $(document).ready(() => {
            loadDetails();
            setInterval(loadDetails, 2000);
        });
    </script>
    <canvas id="threejs-background"></canvas>
</body>
</html>

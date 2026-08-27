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
    1 => ['id' => 1, 'game_code' => 'baucua', 'name' => 'Thế Giới Linh Thú', 'desc' => 'Tứ Linh Hội Tụ', 'streamer_name' => 'Thánh Nữ Tiên Tri', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🐾', 'color' => '#00ff88', 'idols' => [['name' => 'Thánh Nữ Tiên Tri', 'pick' => 'huou', 'label' => 'Hươu Tài Lộc'], ['name' => 'Chuyên Gia Lọc', 'pick' => 'bau', 'label' => 'Bầu Phước Lành']]],
    2 => ['id' => 2, 'game_code' => 'xocdia', 'name' => 'Trận Địa Trắng Đỏ', 'desc' => 'Quyết Chiến Định Mệnh', 'streamer_name' => 'Thần Bài Bịp', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎲', 'color' => '#ff4757', 'idols' => [['name' => 'Thần Bài Bịp', 'pick' => 'chan', 'label' => 'Chẵn'], ['name' => 'Bà Cô Xóc', 'pick' => 'le', 'label' => 'Lẻ']]],
    3 => ['id' => 3, 'game_code' => 'crash', 'name' => 'Tiên Tri Vũ Trụ', 'desc' => 'Chuyến Tàu Sinh Tử', 'streamer_name' => 'Cơ Trưởng Đẹp Trai', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🚀', 'color' => '#12c2e9', 'idols' => [['name' => 'Cơ Trưởng', 'pick' => 'x2', 'label' => 'Mục tiêu x2.0'], ['name' => 'Đại Gia', 'pick' => 'x10', 'label' => 'Mục tiêu x10.0']]],
    4 => ['id' => 4, 'game_code' => 'daga', 'name' => 'Đại Chiến Thần Kê', 'desc' => 'Đấu Trường Sinh Tử', 'streamer_name' => 'Chủ Sới Gà', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🐓', 'color' => '#e67e22', 'idols' => [['name' => 'Sư Kê', 'pick' => 'meron', 'label' => 'Gà Đỏ (Meron)'], ['name' => 'Thánh Dự', 'pick' => 'wala', 'label' => 'Gà Xanh (Wala)']]],
    5 => ['id' => 5, 'game_code' => 'dragontiger', 'name' => 'Chiến Trường Rồng Hổ', 'desc' => 'Long Hổ Tranh Bá', 'streamer_name' => 'Chân Mệnh Thiên Tử', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🐉', 'color' => '#f1c40f', 'idols' => [['name' => 'Long Vương', 'pick' => 'dragon', 'label' => 'Cửa Rồng'], ['name' => 'Hổ Tướng', 'pick' => 'tiger', 'label' => 'Cửa Hổ']]],
    6 => ['id' => 6, 'game_code' => 'cyber_racing', 'name' => 'Đua Thú Cyberpunk', 'desc' => 'Trường Đua Neon 2077', 'streamer_name' => 'Thánh Cược Đua Thú', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🏎️', 'color' => '#00ff88', 'idols' => [['name' => 'Thánh Cược Đua Thú', 'pick' => 'soi', 'label' => 'Sói Cyber (x3)'], ['name' => 'Chuyên Gia Tốc Độ', 'pick' => 'tho', 'label' => 'Thỏ Neon (x2)']]],
    7 => ['id' => 7, 'game_code' => 'plinko', 'name' => 'Plinko Royale', 'desc' => 'Đấu Trường Thả Bóng', 'streamer_name' => 'Đại Gia Thả Bóng', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎱', 'color' => '#12c2e9', 'idols' => [['name' => 'Đại Gia Thả Bóng', 'pick' => '1000', 'label' => 'Siêu Hũ x1000'], ['name' => 'Thần Chơi', 'pick' => '130', 'label' => 'x130']]],
    8 => ['id' => 8, 'game_code' => 'slot', 'name' => 'Slot Machine Premium', 'desc' => 'Máy Vận May 3x3', 'streamer_name' => 'Thánh Nổ Hũ', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎰', 'color' => '#ffd700', 'idols' => [['name' => 'Thánh Nổ Hũ', 'pick' => 'jackpot', 'label' => 'Jackpot'], ['name' => 'Chuyên Gia Lốc', 'pick' => 'bigwin', 'label' => 'Big Win']]],
    9 => ['id' => 9, 'game_code' => 'baccarat', 'name' => 'Baccarat', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Baccarat', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎯', 'color' => '#ff4757', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    10 => ['id' => 10, 'game_code' => 'banharc', 'name' => 'Banharc', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Banharc', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🃏', 'color' => '#ff00ff', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    11 => ['id' => 11, 'game_code' => 'battleroyale', 'name' => 'Battleroyale', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Battleroyale', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🃏', 'color' => '#ff7f50', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    12 => ['id' => 12, 'game_code' => 'bingo', 'name' => 'Bingo', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Bingo', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🕹️', 'color' => '#00ff88', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    13 => ['id' => 13, 'game_code' => 'bj', 'name' => 'Bj', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Bj', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🃏', 'color' => '#00f2fe', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    14 => ['id' => 14, 'game_code' => 'bjo', 'name' => 'Bjo', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Bjo', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎮', 'color' => '#12c2e9', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    15 => ['id' => 15, 'game_code' => 'blackjack', 'name' => 'Blackjack', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Blackjack', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎴', 'color' => '#f1c40f', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    16 => ['id' => 16, 'game_code' => 'blackjack_multi', 'name' => 'Blackjack Multi', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Blackjack Multi', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎮', 'color' => '#ff7f50', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    17 => ['id' => 17, 'game_code' => 'caribbean', 'name' => 'Caribbean', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Caribbean', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎪', 'color' => '#ff4757', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    18 => ['id' => 18, 'game_code' => 'coinflip', 'name' => 'Coinflip', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Coinflip', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🕹️', 'color' => '#00f2fe', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    19 => ['id' => 19, 'game_code' => 'community_lottery', 'name' => 'Community Lottery', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Community Lottery', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎲', 'color' => '#00ff88', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    20 => ['id' => 20, 'game_code' => 'craps', 'name' => 'Craps', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Craps', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎯', 'color' => '#00f2fe', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    21 => ['id' => 21, 'game_code' => 'dice', 'name' => 'Dice', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Dice', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎴', 'color' => '#12c2e9', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    22 => ['id' => 22, 'game_code' => 'duangua', 'name' => 'Duangua', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Duangua', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎮', 'color' => '#f1c40f', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    23 => ['id' => 23, 'game_code' => 'fantan', 'name' => 'Fantan', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Fantan', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎮', 'color' => '#00ff88', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    24 => ['id' => 24, 'game_code' => 'farm', 'name' => 'Farm', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Farm', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🃏', 'color' => '#ffd700', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    25 => ['id' => 25, 'game_code' => 'gacha_cards', 'name' => 'Gacha Cards', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Gacha Cards', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎮', 'color' => '#ff7f50', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    26 => ['id' => 26, 'game_code' => 'greedy_cave', 'name' => 'Greedy Cave', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Greedy Cave', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎯', 'color' => '#ff00ff', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    27 => ['id' => 27, 'game_code' => 'hilo', 'name' => 'Hilo', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Hilo', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎮', 'color' => '#00f2fe', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    28 => ['id' => 28, 'game_code' => 'holdem', 'name' => 'Holdem', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Holdem', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎲', 'color' => '#ff4757', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    29 => ['id' => 29, 'game_code' => 'hopmu', 'name' => 'Hopmu', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Hopmu', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎲', 'color' => '#ffd700', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    30 => ['id' => 30, 'game_code' => 'horserace', 'name' => 'Horserace', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Horserace', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🕹️', 'color' => '#f1c40f', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    31 => ['id' => 31, 'game_code' => 'horserace_pvp', 'name' => 'Horserace Pvp', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Horserace Pvp', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎯', 'color' => '#00f2fe', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    32 => ['id' => 32, 'game_code' => 'jojo_battle', 'name' => 'Jojo Battle', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Jojo Battle', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎯', 'color' => '#ffd700', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    33 => ['id' => 33, 'game_code' => 'keno', 'name' => 'Keno', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Keno', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎲', 'color' => '#00f2fe', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    34 => ['id' => 34, 'game_code' => 'letitride', 'name' => 'Letitride', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Letitride', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🕹️', 'color' => '#12c2e9', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    35 => ['id' => 35, 'game_code' => 'limbo', 'name' => 'Limbo', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Limbo', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎯', 'color' => '#f1c40f', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    36 => ['id' => 36, 'game_code' => 'lottery', 'name' => 'Lottery', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Lottery', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🃏', 'color' => '#ff4757', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    37 => ['id' => 37, 'game_code' => 'mahjong', 'name' => 'Mahjong', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Mahjong', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎴', 'color' => '#ff4757', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    38 => ['id' => 38, 'game_code' => 'megaspin', 'name' => 'Megaspin', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Megaspin', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎲', 'color' => '#ff4757', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    39 => ['id' => 39, 'game_code' => 'mines', 'name' => 'Mines', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Mines', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🃏', 'color' => '#00f2fe', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    40 => ['id' => 40, 'game_code' => 'minesweeper', 'name' => 'Minesweeper', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Minesweeper', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎮', 'color' => '#ff7f50', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    41 => ['id' => 41, 'game_code' => 'number', 'name' => 'Number', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Number', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎮', 'color' => '#ff7f50', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    42 => ['id' => 42, 'game_code' => 'paigow', 'name' => 'Paigow', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Paigow', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎴', 'color' => '#ffd700', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    43 => ['id' => 43, 'game_code' => 'poker', 'name' => 'Poker', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Poker', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎪', 'color' => '#00f2fe', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    44 => ['id' => 44, 'game_code' => 'pontoon', 'name' => 'Pontoon', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Pontoon', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🕹️', 'color' => '#00f2fe', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    45 => ['id' => 45, 'game_code' => 'reddog', 'name' => 'Reddog', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Reddog', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎯', 'color' => '#00f2fe', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    46 => ['id' => 46, 'game_code' => 'roulette', 'name' => 'Roulette', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Roulette', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎪', 'color' => '#ff00ff', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    47 => ['id' => 47, 'game_code' => 'rps', 'name' => 'Rps', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Rps', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎴', 'color' => '#ffd700', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    48 => ['id' => 48, 'game_code' => 'ruttham', 'name' => 'Ruttham', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Ruttham', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎲', 'color' => '#ff4757', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    49 => ['id' => 49, 'game_code' => 'samloc', 'name' => 'Samloc', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Samloc', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🃏', 'color' => '#12c2e9', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    50 => ['id' => 50, 'game_code' => 'scratch', 'name' => 'Scratch', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Scratch', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎪', 'color' => '#ff7f50', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    51 => ['id' => 51, 'game_code' => 'sicbo', 'name' => 'Sicbo', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Sicbo', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎪', 'color' => '#ff4757', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    52 => ['id' => 52, 'game_code' => 'threecard', 'name' => 'Threecard', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Threecard', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🕹️', 'color' => '#00ff88', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    53 => ['id' => 53, 'game_code' => 'tower', 'name' => 'Tower', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Tower', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎯', 'color' => '#12c2e9', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    54 => ['id' => 54, 'game_code' => 'tusac', 'name' => 'Tusac', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Tusac', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎪', 'color' => '#ff00ff', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    55 => ['id' => 55, 'game_code' => 'videopoker', 'name' => 'Videopoker', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Videopoker', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎮', 'color' => '#ff00ff', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    56 => ['id' => 56, 'game_code' => 'vietlott', 'name' => 'Vietlott', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Vietlott', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🃏', 'color' => '#ff7f50', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    57 => ['id' => 57, 'game_code' => 'war', 'name' => 'War', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ War', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎲', 'color' => '#00ff88', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]],
    58 => ['id' => 58, 'game_code' => 'yahtzee', 'name' => 'Yahtzee', 'desc' => 'Đỉnh Cao Giải Trí', 'streamer_name' => 'Cao Thủ Yahtzee', 'streamer_avatar' => '../img/avatar_default.png', 'icon' => '🎮', 'color' => '#ff4757', 'idols' => [['name' => 'Đại Gia', 'pick' => '1', 'label' => 'Mục tiêu 1'], ['name' => 'Thần Bài', 'pick' => '2', 'label' => 'Mục tiêu 2']]]
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

        $stmtU = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
        $stmtU->bind_param("i", $userId);
        $stmtU->execute();
        $userMoneyRow = $stmtU->get_result()->fetch_assoc();
        $stmtU->close();
        $userMoneyVal = (float)($userMoneyRow['Money'] ?? 0);

        echo json_encode([
            'success' => true,
            'state' => $state,
            'table' => array_merge($tData, ['viewers' => $viewers]),
            'outcome' => $outcome,
            'my_bet' => $myBet,
            'user_money' => number_format($userMoneyVal),
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
        if ($amount > 1000000000) {
            echo json_encode(['success' => false, 'message' => 'Giới hạn mỗi lần Tip tối đa là 1 Tỷ GTLM!']);
            exit;
        }

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
            $targetBotName = 'bot_' . $tId;
            $botUser = getOrCreateBotStreamerUser($conn, $targetBotName, 50000000);
            $botId = $botUser['Iduser'];

            $stmtBot = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
            $stmtBot->bind_param("di", $amount, $botId);
            $stmtBot->execute();
            $stmtBot->close();

            $customMsg = trim(strip_tags($_POST['message'] ?? ''));

            $streamerName = $tablesConfig[$tId]['streamer_name'] ?? 'Idol';
            
            if ($customMsg !== '') {
                $chatMsg = "🎉 *" . htmlspecialchars($uObj['Name']) . "* vừa Tip **" . number_format($amount) . " GTLM** cho Streamer **$streamerName** với lời nhắn: \"$customMsg\" 🔥";
            } else {
                $chatMsg = "🎉 *" . htmlspecialchars($uObj['Name']) . "* vừa Tip **" . number_format($amount) . " GTLM** lộc cho Streamer **$streamerName**! 🔥";
            }
            
            $stmt = $conn->prepare("INSERT INTO spectator_chat (game_id, user_id, message) VALUES (?, 1, ?)");
            $stmt->bind_param("is", $tId, $chatMsg);
            $stmt->execute();
            $stmt->close();

            // 3. Streamer Bot Phản Hồi Trực Tiếp
            $readableAmount = "";
            if ($amount >= 1000000000) $readableAmount = round($amount / 1000000000, 1) . " tỷ";
            elseif ($amount >= 1000000) $readableAmount = round($amount / 1000000, 1) . " triệu";
            elseif ($amount >= 1000) $readableAmount = round($amount / 1000, 1) . " nghìn";
            else $readableAmount = $amount;

            if ($customMsg !== '') {
                $botSpeech = htmlspecialchars($uObj['Name']) . " gửi tặng " . $readableAmount . " Gờ Tờ Lờ Mờ kèm lời nhắn: " . $customMsg;
            } else {
                $botReplies = [
                    "Cảm ơn bác " . htmlspecialchars($uObj['Name']) . " đã Tip " . $readableAmount . " GTLM cổ vũ nhé! Chúc sếp ra chiêu đâu húp đó! 🔥",
                    "Cảm ơn đại gia " . htmlspecialchars($uObj['Name']) . " đã Tip " . $readableAmount . " GTLM! Streamer xin nhận lộc!",
                    "Cảm ơn bác " . htmlspecialchars($uObj['Name']) . " nhé! Có lộc " . $readableAmount . " của bác ván này Streamer bao húp ngập mồm! 🚀"
                ];
                $botSpeech = $botReplies[array_rand($botReplies)];
            }
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
        $combo = max(1, (int)($_POST['combo'] ?? 1));
        
        $tiktokGiftsCatalog = [
            'beer' => ['id' => 'beer', 'name' => 'Bia Lạnh', 'price' => 5000, 'icon' => '🍺', 'effect' => 'beer_toast'],
            'moneygun' => ['id' => 'moneygun', 'name' => 'Súng Bắn Tiền', 'price' => 20000, 'icon' => '🔫', 'effect' => 'money_rain'],
            'gem' => ['id' => 'gem', 'name' => 'Kim Cương', 'price' => 50000, 'icon' => '💎', 'effect' => 'gem_spin'],
            'sports_car' => ['id' => 'sports_car', 'name' => 'Siêu Xe Cyber', 'price' => 200000, 'icon' => '🏎️', 'effect' => 'car_neon'],
            'spaceship' => ['id' => 'spaceship', 'name' => 'Phi Thuyền', 'price' => 500000, 'icon' => '🛸', 'effect' => 'spaceship_laser'],
            'dragon' => ['id' => 'dragon', 'name' => 'Rồng Thần', 'price' => 1000000, 'icon' => '🐉', 'effect' => 'dragon_fly'],
            'crown' => ['id' => 'crown', 'name' => 'Vương Miện Hoàng Đế', 'price' => 5000000, 'icon' => '👑', 'effect' => 'crown_emperor'],
            'planet' => ['id' => 'planet', 'name' => 'Vụ Nổ Big Bang', 'price' => 10000000, 'icon' => '💥', 'effect' => 'big_bang']
        ];

        if (!isset($tiktokGiftsCatalog[$giftId])) {
            echo json_encode(['success' => false, 'message' => 'Vật phẩm quà tặng không hợp lệ!']);
            exit;
        }

        $gift = $tiktokGiftsCatalog[$giftId];
        $amount = $gift['price'] * $combo;

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
            $targetBotName = 'bot_' . $tId;
            $botUser = getOrCreateBotStreamerUser($conn, $targetBotName, 50000000);
            $botId = $botUser['Iduser'];

            $stmtBot = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
            $stmtBot->bind_param("di", $amount, $botId);
            $stmtBot->execute();
            $stmtBot->close();

            $streamerName = $tablesConfig[$tId]['streamer_name'] ?? 'Idol';
            $chatMsg = "🎁 *" . htmlspecialchars($uObj['Name']) . "* đã tặng **Combo x$combo " . $gift['icon'] . " " . $gift['name'] . "** (" . number_format($amount) . " GTLM) cho Streamer **$streamerName**! 🔥";
            
            $stmt = $conn->prepare("INSERT INTO spectator_chat (game_id, user_id, message) VALUES (?, 1, ?)");
            $stmt->bind_param("is", $tId, $chatMsg);
            $stmt->execute();
            $stmt->close();

            // 3. Streamer Bot Phản Hồi Tặng Quà
            $botReplies = [
                "Cảm ơn bác " . htmlspecialchars($uObj['Name']) . " đã tặng Combo x$combo " . $gift['name'] . " nhé! Đồ đẹp đỉnh quá sếp ơi! ❤️",
                "Quá rực rỡ! Cảm ơn đại gia " . htmlspecialchars($uObj['Name']) . " đã vinh danh Combo x$combo " . $gift['name'] . "! 🚀",
                "Linh khí bảo vật Combo x$combo " . $gift['name'] . " từ bác " . htmlspecialchars($uObj['Name']) . " đang giúp Streamer dây đỏ rực rỡ!"
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
                'gift' => $gift,
                'userName' => $uObj['Name'],
                'streamerName' => $streamerName,
                'amountFormatted' => number_format($amount),
                'newMoney' => number_format($newSpectatorMoney),
                'speechText' => $botSpeech,
                'message' => "Đã tặng " . $gift['name'] . " cho $streamerName!"
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'send_chat':
        $tId = (int)$_POST['table_id'];
        $msg = trim($_POST['message'] ?? '');
        if ($msg !== '') {
            $stmt = $conn->prepare("INSERT INTO spectator_chat (game_id, user_id, message) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $tId, $userId, $msg);
            $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => true]);
        }
        break;

    case 'send_reaction':
        $tId = (int)$_POST['table_id'];
        $emoji = trim($_POST['emoji'] ?? '');
        if ($emoji !== '') {
            $stmt = $conn->prepare("INSERT INTO spectator_reactions (game_id, user_id, emoji) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $tId, $userId, $emoji);
            $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => true]);
        }
        break;

    case 'clear_chat':
        if (!isset($_SESSION['Role']) || $_SESSION['Role'] != 1) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền Admin!']);
            exit;
        }
        $tId = (int)$_POST['table_id'];
        $stmt = $conn->prepare("DELETE FROM spectator_chat WHERE game_id = ?");
        $stmt->bind_param("i", $tId);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true, 'message' => 'Đã xóa toàn bộ tin nhắn tại phòng này!']);
        break;
}
?>

<?php
/**
 * Script tự động cập nhật CSS cho tất cả các trang
 * Chạy script này một lần để cập nhật tất cả các trang
 * 
 * Usage: php update_all_pages_css.php
 */

$gamePages = [
    'slot.php', 'bj.php', 'dice.php', 'rps.php', 'coinflip.php',
    'roulette.php', 'xocdia.php', 'bot.php', 'vq.php', 'vietlott.php',
    'cs.php', 'hopmu.php', 'ruttham.php', 'duangua.php', 'number.php',
    'poker.php', 'bingo.php', 'minesweeper.php', 'ac.php'
];

$otherPages = [
    'statistics.php', 'quests.php', 'notifications.php', 'leaderboard.php',
    'profile.php', 'achievements.php', 'shop.php', 'marketplace.php',
    'daily_challenges.php', 'weekly_challenges.php', 'guilds.php',
    'tournament.php', 'trivia.php', 'gift.php', 'friends.php',
    'inventory.php', 'lucky_wheel.php', 'daily_login.php', 'vip_system.php',
    'reward_points.php', 'social_feed.php', 'events.php', 'pvp_challenge.php',
    'chat.php', 'private_message.php', 'select_title.php', 'khungavatar.php',
    'khungchat.php', 'addimg.php', 'editProfile.php', 'about.php'
];

$adminPages = [
    'admin_dashboard.php', 'admin_manage_users.php', 'admin_manage_items.php',
    'admin_manage_frames.php', 'admin_add_items.php', 'admin_add_frames.php'
];

function updatePageCSS($filePath, $isGame = false, $isAdmin = false) {
    if (!file_exists($filePath)) {
        echo "⚠️  File không tồn tại: $filePath\n";
        return false;
    }
    
    $content = file_get_contents($filePath);
    
    // Kiểm tra xem đã có components.css chưa
    if (strpos($content, 'components.css') !== false) {
        echo "✓ Đã cập nhật: $filePath\n";
        return true;
    }
    
    // Pattern để tìm thẻ link stylesheet cuối cùng
    $patterns = [
        // Pattern 1: Tìm sau main.css và animations.css
        '/(<link\s+rel=["\']stylesheet["\']\s+href=["\']assets\/css\/animations\.css["\']\s*>\s*\n)/i',
        // Pattern 2: Tìm sau main.css (nếu không có animations.css)
        '/(<link\s+rel=["\']stylesheet["\']\s+href=["\']assets\/css\/main\.css["\']\s*>\s*\n)/i',
    ];
    
    $newCSS = '';
    if ($isGame) {
        $newCSS = "    <link rel=\"stylesheet\" href=\"assets/css/main.css\">\n";
        $newCSS .= "    <link rel=\"stylesheet\" href=\"assets/css/components.css\">\n";
        $newCSS .= "    <link rel=\"stylesheet\" href=\"assets/css/responsive.css\">\n";
        $newCSS .= "    <link rel=\"stylesheet\" href=\"assets/css/loading.css\">\n";
        $newCSS .= "    <link rel=\"stylesheet\" href=\"assets/css/animations.css\">\n";
        $newCSS .= "    <link rel=\"stylesheet\" href=\"assets/css/game-effects.css\">\n";
    } elseif ($isAdmin) {
        $newCSS = "    <link rel=\"stylesheet\" href=\"assets/css/master.css\">\n";
    } else {
        $newCSS = "    <link rel=\"stylesheet\" href=\"assets/css/main.css\">\n";
        $newCSS .= "    <link rel=\"stylesheet\" href=\"assets/css/components.css\">\n";
        $newCSS .= "    <link rel=\"stylesheet\" href=\"assets/css/responsive.css\">\n";
        $newCSS .= "    <link rel=\"stylesheet\" href=\"assets/css/loading.css\">\n";
        $newCSS .= "    <link rel=\"stylesheet\" href=\"assets/css/animations.css\">\n";
    }
    
    // Thử thay thế pattern 1 trước (có animations.css)
    if (preg_match('/<link\s+rel=["\']stylesheet["\']\s+href=["\']assets\/css\/animations\.css["\']/i', $content)) {
        // Tìm và thay thế phần CSS
        $pattern = '/(<link\s+rel=["\']stylesheet["\']\s+href=["\']assets\/css\/main\.css["\']\s*>\s*\n)(\s*<link\s+rel=["\']stylesheet["\']\s+href=["\']assets\/css\/animations\.css["\']\s*>\s*\n)/i';
        if (preg_match($pattern, $content)) {
            if ($isGame) {
                $replacement = "    <link rel=\"stylesheet\" href=\"assets/css/main.css\">\n";
                $replacement .= "    <link rel=\"stylesheet\" href=\"assets/css/components.css\">\n";
                $replacement .= "    <link rel=\"stylesheet\" href=\"assets/css/responsive.css\">\n";
                $replacement .= "    <link rel=\"stylesheet\" href=\"assets/css/loading.css\">\n";
                $replacement .= "    <link rel=\"stylesheet\" href=\"assets/css/animations.css\">\n";
                $replacement .= "    <link rel=\"stylesheet\" href=\"assets/css/game-effects.css\">\n";
            } else {
                $replacement = "    <link rel=\"stylesheet\" href=\"assets/css/main.css\">\n";
                $replacement .= "    <link rel=\"stylesheet\" href=\"assets/css/components.css\">\n";
                $replacement .= "    <link rel=\"stylesheet\" href=\"assets/css/responsive.css\">\n";
                $replacement .= "    <link rel=\"stylesheet\" href=\"assets/css/loading.css\">\n";
                $replacement .= "    <link rel=\"stylesheet\" href=\"assets/css/animations.css\">\n";
            }
            $content = preg_replace($pattern, $replacement, $content);
        }
    } elseif (preg_match('/<link\s+rel=["\']stylesheet["\']\s+href=["\']assets\/css\/main\.css["\']/i', $content)) {
        // Chỉ có main.css
        $pattern = '/(<link\s+rel=["\']stylesheet["\']\s+href=["\']assets\/css\/main\.css["\']\s*>\s*\n)/i';
        if ($isGame) {
            $replacement = "    <link rel=\"stylesheet\" href=\"assets/css/main.css\">\n";
            $replacement .= "    <link rel=\"stylesheet\" href=\"assets/css/components.css\">\n";
            $replacement .= "    <link rel=\"stylesheet\" href=\"assets/css/responsive.css\">\n";
            $replacement .= "    <link rel=\"stylesheet\" href=\"assets/css/loading.css\">\n";
            $replacement .= "    <link rel=\"stylesheet\" href=\"assets/css/animations.css\">\n";
            $replacement .= "    <link rel=\"stylesheet\" href=\"assets/css/game-effects.css\">\n";
        } else {
            $replacement = "    <link rel=\"stylesheet\" href=\"assets/css/main.css\">\n";
            $replacement .= "    <link rel=\"stylesheet\" href=\"assets/css/components.css\">\n";
            $replacement .= "    <link rel=\"stylesheet\" href=\"assets/css/responsive.css\">\n";
            $replacement .= "    <link rel=\"stylesheet\" href=\"assets/css/loading.css\">\n";
            $replacement .= "    <link rel=\"stylesheet\" href=\"assets/css/animations.css\">\n";
        }
        $content = preg_replace($pattern, $replacement, $content);
    } else {
        echo "⚠️  Không tìm thấy main.css trong: $filePath\n";
        return false;
    }
    
    // Lưu file
    if (file_put_contents($filePath, $content)) {
        echo "✓ Đã cập nhật: $filePath\n";
        return true;
    } else {
        echo "✗ Lỗi khi lưu: $filePath\n";
        return false;
    }
}

echo "🚀 Bắt đầu cập nhật CSS cho tất cả các trang...\n\n";

$updated = 0;
$failed = 0;

// Cập nhật game pages
echo "📱 Cập nhật Game Pages:\n";
foreach ($gamePages as $page) {
    if (updatePageCSS($page, true)) {
        $updated++;
    } else {
        $failed++;
    }
}

// Cập nhật other pages
echo "\n📄 Cập nhật Other Pages:\n";
foreach ($otherPages as $page) {
    if (updatePageCSS($page, false)) {
        $updated++;
    } else {
        $failed++;
    }
}

// Cập nhật admin pages
echo "\n👑 Cập nhật Admin Pages:\n";
foreach ($adminPages as $page) {
    if (updatePageCSS($page, false, true)) {
        $updated++;
    } else {
        $failed++;
    }
}

echo "\n✅ Hoàn thành!\n";
echo "✓ Đã cập nhật: $updated trang\n";
if ($failed > 0) {
    echo "✗ Thất bại: $failed trang\n";
}
?>


<?php
/**
 * Script tự động cập nhật CSS và JS cho tất cả các trang game
 * Chạy script này để thêm game-ui-enhancements.css và game-enhancements.js vào tất cả game pages
 * 
 * Usage: php update_all_games.php
 */

$gamePages = [
    'slot.php', 'bj.php', 'dice.php', 'rps.php', 'coinflip.php',
    'roulette.php', 'xocdia.php', 'bot.php', 'vq.php', 'vietlott.php',
    'cs.php', 'hopmu.php', 'ruttham.php', 'duangua.php', 'number.php',
    'poker.php', 'bingo.php', 'minesweeper.php', 'ac.php'
];

function updateGamePage($filePath) {
    if (!file_exists($filePath)) {
        echo "⚠️  File không tồn tại: $filePath\n";
        return false;
    }
    
    $content = file_get_contents($filePath);
    $originalContent = $content;
    
    // Kiểm tra xem đã có game-ui-enhancements.css chưa
    if (strpos($content, 'game-ui-enhancements.css') !== false) {
        echo "✓ Đã có CSS: $filePath\n";
    } else {
        // Tìm vị trí để thêm CSS (sau game-effects.css hoặc animations.css)
        $cssPattern = '/(<link\s+rel=["\']stylesheet["\']\s+href=["\']assets\/css\/game-effects\.css["\']\s*>\s*\n)/i';
        if (preg_match($cssPattern, $content)) {
            // Thêm sau game-effects.css
            $replacement = "$1    <link rel=\"stylesheet\" href=\"assets/css/game-ui-enhancements.css\">\n";
            $content = preg_replace($cssPattern, $replacement, $content);
        } else {
            // Tìm sau animations.css
            $cssPattern2 = '/(<link\s+rel=["\']stylesheet["\']\s+href=["\']assets\/css\/animations\.css["\']\s*>\s*\n)/i';
            if (preg_match($cssPattern2, $content)) {
                $replacement = "$1    <link rel=\"stylesheet\" href=\"assets/css/game-ui-enhancements.css\">\n";
                $content = preg_replace($cssPattern2, $replacement, $content);
            } else {
                // Tìm sau main.css
                $cssPattern3 = '/(<link\s+rel=["\']stylesheet["\']\s+href=["\']assets\/css\/main\.css["\']\s*>\s*\n)/i';
                if (preg_match($cssPattern3, $content)) {
                    $replacement = "$1    <link rel=\"stylesheet\" href=\"assets/css/game-ui-enhancements.css\">\n";
                    $content = preg_replace($cssPattern3, $replacement, $content);
                }
            }
        }
    }
    
    // Kiểm tra xem đã có game-enhancements.js chưa
    if (strpos($content, 'game-enhancements.js') !== false) {
        echo "✓ Đã có JS: $filePath\n";
    } else {
        // Tìm vị trí để thêm JS (sau game-effects.js hoặc game-effects-auto.js hoặc sweetalert2)
        $jsPatterns = [
            // Sau game-effects-auto.js
            '/(<script\s+src=["\']assets\/js\/game-effects-auto\.js["\']\s*><\/script>\s*\n)/i',
            // Sau game-effects.js
            '/(<script\s+src=["\']assets\/js\/game-effects\.js["\']\s*><\/script>\s*\n)/i',
            // Sau sweetalert2
            '/(<script\s+src=["\']https:\/\/cdn\.jsdelivr\.net\/npm\/sweetalert2@11["\']\s*><\/script>\s*\n)/i',
            // Sau jquery
            '/(<script\s+src=["\']https:\/\/code\.jquery\.com\/jquery-3\.6\.0\.min\.js["\']\s*><\/script>\s*\n)/i',
        ];
        
        $jsAdded = false;
        foreach ($jsPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $replacement = "$1    <script src=\"assets/js/game-enhancements.js\"></script>\n";
                $content = preg_replace($pattern, $replacement, $content);
                $jsAdded = true;
                break;
            }
        }
        
        // Nếu không tìm thấy, thêm trước thẻ </body>
        if (!$jsAdded) {
            $bodyPattern = '/(<\/body>)/i';
            if (preg_match($bodyPattern, $content)) {
                $replacement = "    <script src=\"assets/js/game-enhancements.js\"></script>\n$1";
                $content = preg_replace($bodyPattern, $replacement, $content);
            }
        }
    }
    
    // Chỉ lưu nếu có thay đổi
    if ($content !== $originalContent) {
        if (file_put_contents($filePath, $content)) {
            echo "✅ Đã cập nhật: $filePath\n";
            return true;
        } else {
            echo "✗ Lỗi khi lưu: $filePath\n";
            return false;
        }
    } else {
        echo "ℹ️  Không có thay đổi: $filePath\n";
        return true;
    }
}

echo "🚀 Bắt đầu cập nhật CSS và JS cho tất cả các trang game...\n\n";

$updated = 0;
$failed = 0;
$skipped = 0;

foreach ($gamePages as $page) {
    $result = updateGamePage($page);
    if ($result === true) {
        $updated++;
    } elseif ($result === false) {
        $failed++;
    } else {
        $skipped++;
    }
}

echo "\n✅ Hoàn thành!\n";
echo "✓ Đã cập nhật: $updated trang\n";
if ($skipped > 0) {
    echo "ℹ️  Đã có sẵn: $skipped trang\n";
}
if ($failed > 0) {
    echo "✗ Thất bại: $failed trang\n";
}
echo "\n💡 Lưu ý: Hãy kiểm tra lại các trang game để đảm bảo CSS và JS đã được load đúng.\n";
?>


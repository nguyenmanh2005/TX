<?php
/**
 * Script sửa các duplicate CSS trong các trang game
 */

$gamePages = [
    'slot.php', 'bj.php', 'dice.php', 'rps.php', 'coinflip.php',
    'roulette.php', 'xocdia.php', 'bot.php', 'vq.php', 'vietlott.php',
    'cs.php', 'hopmu.php', 'ruttham.php', 'duangua.php', 'number.php',
    'poker.php', 'bingo.php', 'minesweeper.php', 'ac.php', 'baucua.php'
];

function fixDuplicates($filePath) {
    if (!file_exists($filePath)) {
        return false;
    }
    
    $content = file_get_contents($filePath);
    $originalContent = $content;
    
    // Remove duplicate game-ui-enhancements.css
    $pattern = '/(<link\s+rel=["\']stylesheet["\']\s+href=["\']assets\/css\/game-ui-enhancements\.css["\']\s*>\s*\n)+/i';
    $content = preg_replace($pattern, "    <link rel=\"stylesheet\" href=\"assets/css/game-ui-enhancements.css\">\n", $content);
    
    // Remove duplicate game-effects.css
    $pattern2 = '/(<link\s+rel=["\']stylesheet["\']\s+href=["\']assets\/css\/game-effects\.css["\']\s*>\s*\n)+/i';
    $content = preg_replace($pattern2, "    <link rel=\"stylesheet\" href=\"assets/css/game-effects.css\">\n", $content);
    
    // Remove duplicate game-enhancements.js
    $pattern3 = '/(<script\s+src=["\']assets\/js\/game-enhancements\.js["\']\s*><\/script>\s*\n)+/i';
    $content = preg_replace($pattern3, "    <script src=\"assets/js/game-enhancements.js\"></script>\n", $content);
    
    if ($content !== $originalContent) {
        file_put_contents($filePath, $content);
        return true;
    }
    
    return false;
}

echo "🔧 Đang sửa các duplicate CSS/JS...\n\n";

$fixed = 0;
foreach ($gamePages as $page) {
    if (fixDuplicates($page)) {
        echo "✅ Đã sửa: $page\n";
        $fixed++;
    }
}

echo "\n✅ Hoàn thành! Đã sửa $fixed trang.\n";
?>


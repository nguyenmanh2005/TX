<?php
require 'db_connect.php';
/**
 * Script tự động áp dụng hiệu ứng game vào tất cả các file game
 */

$gameFiles = [
    'ac.php', 'slot.php', 'dice.php', 'roulette.php', 'coinflip.php',
    'rps.php', 'xocdia.php', 'bot.php', 'vq.php', 'vietlott.php',
    'cs.php', 'number.php', 'duangua.php', 'hopmu.php', 'ruttham.php',
    'poker.php', 'bingo.php', 'minesweeper.php', 'baucua.php', 'bj.php'
];

$cssLink = '    <link rel="stylesheet" href="assets/css/game-effects.css">';
$jsLinks = [
    '    <script src="assets/js/game-effects.js"></script>',
    '    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>',
    '    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>'
];

$stats = ['updated' => 0, 'skipped' => 0, 'errors' => 0];

foreach ($gameFiles as $file) {
    if (!file_exists($file)) {
        echo "⚠ File không tồn tại: $file\n";
        $stats['skipped']++;
        continue;
    }
    
    $content = file_get_contents($file);
    $originalContent = $content;
    $changed = false;
    
    // 1. Thêm CSS link sau animations.css
    if (strpos($content, 'game-effects.css') === false && strpos($content, 'animations.css') !== false) {
        $content = str_replace(
            '<link rel="stylesheet" href="assets/css/animations.css">',
            "<link rel=\"stylesheet\" href=\"assets/css/animations.css\">\n$cssLink",
            $content
        );
        $changed = true;
    }
    
    // 2. Thêm JS files trước </body> hoặc sau three.js
    $hasGameEffectsJS = strpos($content, 'game-effects.js') !== false;
    
    if (!$hasGameEffectsJS) {
        // Tìm vị trí để thêm JS
        if (strpos($content, '</body>') !== false) {
            // Thêm trước </body>
            $jsBlock = "\n" . implode("\n", $jsLinks) . "\n";
            $content = str_replace('</body>', "$jsBlock</body>", $content);
            $changed = true;
        } elseif (strpos($content, 'three.js') !== false) {
            // Thêm sau three.js script tag
            $jsBlock = "\n" . implode("\n", $jsLinks) . "\n";
            $content = preg_replace(
                '/(<script[^>]*three\.js[^>]*><\/script>)/i',
                "$1$jsBlock",
                $content,
                1
            );
            $changed = true;
        }
    }
    
    if ($changed) {
        if (file_put_contents($file, $content)) {
            echo "✓ Updated: $file\n";
            $stats['updated']++;
        } else {
            echo "✗ Error writing: $file\n";
            $stats['errors']++;
        }
    } else {
        echo "○ No changes needed: $file\n";
        $stats['skipped']++;
    }
}

echo "\n=== Summary ===\n";
echo "Updated: {$stats['updated']} files\n";
echo "Skipped: {$stats['skipped']} files\n";
echo "Errors: {$stats['errors']} files\n";
echo "\nDone! Các file game đã được nâng cấp với hiệu ứng mới.\n";
?>










<?php
require 'db_connect.php';
/**
 * Script sửa tất cả hiệu ứng cho các trang trò chơi
 */

$gameFiles = [
    'ac.php', 'slot.php', 'dice.php', 'roulette.php', 'coinflip.php',
    'rps.php', 'xocdia.php', 'bot.php', 'vq.php', 'vietlott.php',
    'cs.php', 'number.php', 'duangua.php', 'hopmu.php', 'ruttham.php',
    'poker.php', 'bingo.php', 'minesweeper.php', 'baucua.php', 'bj.php',
    'caothe.php', 'luckywheel.php'
];

$cssLink = '    <link rel="stylesheet" href="assets/css/game-effects.css">';
$jsLinks = [
    '    <script src="assets/js/game-effects.js"></script>',
    '    <script src="assets/js/game-effects-auto.js"></script>'
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
    
    // 1. Đảm bảo có game-effects.css
    if (strpos($content, 'game-effects.css') === false) {
        // Tìm vị trí sau animations.css hoặc main.css
        if (preg_match('/(<link[^>]*animations\.css[^>]*>)/i', $content, $matches)) {
            $content = str_replace($matches[0], $matches[0] . "\n" . $cssLink, $content);
            $changed = true;
        } elseif (preg_match('/(<link[^>]*main\.css[^>]*>)/i', $content, $matches)) {
            $content = str_replace($matches[0], $matches[0] . "\n" . $cssLink, $content);
            $changed = true;
        }
    }
    
    // 2. Đảm bảo có game-effects.js và game-effects-auto.js
    $hasGameEffectsJS = strpos($content, 'game-effects.js') !== false;
    $hasGameEffectsAutoJS = strpos($content, 'game-effects-auto.js') !== false;
    
    if (!$hasGameEffectsJS || !$hasGameEffectsAutoJS) {
        // Tìm vị trí trước </body>
        if (strpos($content, '</body>') !== false) {
            $jsBlock = "\n";
            if (!$hasGameEffectsJS) {
                $jsBlock .= $jsLinks[0] . "\n";
            }
            if (!$hasGameEffectsAutoJS) {
                $jsBlock .= $jsLinks[1] . "\n";
            }
            $content = str_replace('</body>', "$jsBlock</body>", $content);
            $changed = true;
        }
    }
    
    // 3. Thêm auto-init script nếu chưa có
    if (strpos($content, 'GameEffectsAuto') === false && strpos($content, 'game-effects-auto.js') !== false) {
        $initScript = "\n<script>\n    // Auto initialize game effects\n    if (typeof GameEffectsAuto !== 'undefined') {\n        GameEffectsAuto.init();\n    }\n</script>\n";
        $content = str_replace('</body>', "$initScript</body>", $content);
        $changed = true;
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
echo "\nDone! Tất cả hiệu ứng đã được sửa và cập nhật.\n";
?>










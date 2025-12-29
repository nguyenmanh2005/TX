<?php
/**
 * Script để apply theme cho tất cả các trang
 * Chạy script này một lần để update tất cả các file
 */

$filesToUpdate = [
    // Game files
    'slot.php',
    'baucua.php',
    'luckywheel.php',
    'roulette.php',
    'rps.php',
    'coinflip.php',
    'number.php',
    'poker.php',
    'minesweeper.php',
    'bingo.php',
    'xocdia.php',
    'bot.php',
    'bj.php',
    'duangua.php',
    'vq.php',
    'cs.php',
    'hopmu.php',
    'ruttham.php',
    // Other pages
    'chat.php',
    'in4.php',
    'select_title.php',
    'achievements.php',
    'khungchat.php',
    'khungavatar.php',
    'addimg.php',
    'caothe.php',
    'shop.php',
    'admin_add_frames.php',
    'admin_add_items.php',
    'admin_manage_frames.php',
    'admin_manage_items.php',
    'admin_manage_users.php',
];

$patterns = [
    // Pattern 1: background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%);
    '/background:\s*linear-gradient\(135deg,\s*#[0-9a-fA-F]+\s*0%,\s*#[0-9a-fA-F]+\s*(?:50%,\s*)?#[0-9a-fA-F]+\s*100%\);/i',
    // Pattern 2: background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    '/background:\s*linear-gradient\(135deg,\s*#[0-9a-fA-F]+\s*0%,\s*#[0-9a-fA-F]+\s*100%\);/i',
];

$replacement = 'background: <?= $bgGradientCSS ?>; background-attachment: fixed;';

foreach ($filesToUpdate as $file) {
    if (!file_exists($file)) {
        echo "File không tồn tại: $file\n";
        continue;
    }
    
    $content = file_get_contents($file);
    $originalContent = $content;
    
    // Thêm require_once load_theme.php nếu chưa có
    if (strpos($content, "require_once 'load_theme.php'") === false && 
        strpos($content, "require 'db_connect.php'") !== false) {
        $content = str_replace(
            "require 'db_connect.php';",
            "require 'db_connect.php';\n\n// Load theme\nrequire_once 'load_theme.php';",
            $content
        );
    }
    
    // Thay thế background gradients
    foreach ($patterns as $pattern) {
        $content = preg_replace($pattern, $replacement, $content);
    }
    
    if ($content !== $originalContent) {
        file_put_contents($file, $content);
        echo "✓ Updated: $file\n";
    } else {
        echo "○ No changes: $file\n";
    }
}

echo "\nDone!\n";
?>


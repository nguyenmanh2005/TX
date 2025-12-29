<?php
/**
 * Batch Update Theme for All Pages
 * Run this script to update all PHP files to use the theme system
 */

// Danh sách các file cần update
$files = [
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

$updated = 0;
$skipped = 0;
$errors = [];

foreach ($files as $file) {
    if (!file_exists($file)) {
        $errors[] = "File không tồn tại: $file";
        continue;
    }
    
    $content = file_get_contents($file);
    $original = $content;
    
    // 1. Thêm load_theme.php nếu chưa có
    if (strpos($content, "require_once 'load_theme.php'") === false) {
        // Tìm vị trí sau require 'db_connect.php'
        if (preg_match("/require\s+['\"]db_connect\.php['\"];/i", $content)) {
            // Thêm sau db_connect
            $content = preg_replace(
                "/(require\s+['\"]db_connect\.php['\"];)/i",
                "$1\n\n// Load theme\nrequire_once 'load_theme.php';",
                $content,
                1
            );
        } elseif (preg_match("/require\s+['\"]db_connect\.php['\"];\s*\n\s*\/\/\s*Kiểm tra/i", $content)) {
            // Nếu có comment sau db_connect
            $content = preg_replace(
                "/(require\s+['\"]db_connect\.php['\"];)\s*\n\s*(\/\/.*Kiểm tra)/i",
                "$1\n\n// Load theme\nrequire_once 'load_theme.php';\n\n$2",
                $content,
                1
            );
        }
    }
    
    // 2. Thay thế background gradients
    $patterns = [
        '/background:\s*linear-gradient\s*\(\s*135deg\s*,\s*#[0-9a-fA-F]+\s+0%\s*,\s*#[0-9a-fA-F]+\s+50%\s*,\s*#[0-9a-fA-F]+\s+100%\s*\)\s*;/i',
        '/background:\s*linear-gradient\s*\(\s*135deg\s*,\s*#[0-9a-fA-F]+\s+0%\s*,\s*#[0-9a-fA-F]+\s+100%\s*\)\s*;/i',
    ];
    
    foreach ($patterns as $pattern) {
        $content = preg_replace(
            $pattern,
            "background: <?= \$bgGradientCSS ?>; background-attachment: fixed;",
            $content
        );
    }
    
    // 3. Lưu file nếu có thay đổi
    if ($content !== $original) {
        file_put_contents($file, $content);
        echo "✓ Updated: $file\n";
        $updated++;
    } else {
        echo "○ No changes: $file\n";
        $skipped++;
    }
}

echo "\n=== Summary ===\n";
echo "Updated: $updated files\n";
echo "Skipped: $skipped files\n";
if (!empty($errors)) {
    echo "Errors:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}
?>


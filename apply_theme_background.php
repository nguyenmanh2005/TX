<?php
/**
 * Script tự động apply theme background cho tất cả các trang (trừ login/auth)
 * Chạy script này để đảm bảo tất cả các trang đều sử dụng $bgGradientCSS
 */

// Danh sách file cần kiểm tra (trừ login.php, auth.php)
$filesToCheck = [
    // Game files
    'bj.php', 'baucua.php', 'slot.php', 'roulette.php', 'coinflip.php',
    'dice.php', 'rps.php', 'xocdia.php', 'bot.php', 'vq.php', 'vietlott.php',
    'cs.php', 'number.php', 'duangua.php', 'hopmu.php', 'ruttham.php',
    'ac.php', 'poker.php', 'bingo.php', 'minesweeper.php',
    // Other pages
    'index.php', 'chat.php', 'in4.php', 'select_title.php', 'achievements.php',
    'khungchat.php', 'khungavatar.php', 'addimg.php', 'caothe.php', 'shop.php',
    'about.php', 'diemdanh.php', 'gift.php', 'quests.php', 'statistics.php',
    'inventory.php', 'lucky_wheel.php',
    // Admin pages
    'admin_add_frames.php', 'admin_manage_frames.php', 'admin_add_items.php',
    'admin_manage_items.php', 'admin_manage_users.php'
];

$patterns = [
    // Pattern 1: background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%);
    '/background:\s*linear-gradient\(135deg,\s*#[0-9a-fA-F]+\s*0%,\s*#[0-9a-fA-F]+\s*(?:50%,\s*)?#[0-9a-fA-F]+\s*100%\);/i',
    // Pattern 2: background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    '/background:\s*linear-gradient\(135deg,\s*#[0-9a-fA-F]+\s*0%,\s*#[0-9a-fA-F]+\s*100%\);/i',
    // Pattern 3: background-color: #...
    '/background-color:\s*#[0-9a-fA-F]+;/i',
];

$replacement = 'background: <?= $bgGradientCSS ?>; background-attachment: fixed;';

$stats = ['updated' => 0, 'skipped' => 0, 'errors' => 0];

foreach ($filesToCheck as $file) {
    if (!file_exists($file)) {
        echo "⚠ File không tồn tại: $file\n";
        $stats['skipped']++;
        continue;
    }
    
    $content = file_get_contents($file);
    $originalContent = $content;
    $changed = false;
    
    // Bỏ qua nếu là login hoặc auth
    if (strpos($file, 'login.php') !== false || strpos($file, 'auth.php') !== false) {
        echo "○ Skipped (login/auth): $file\n";
        $stats['skipped']++;
        continue;
    }
    
    // 1. Thêm require_once load_theme.php nếu chưa có (sau require db_connect.php)
    if (strpos($content, "require_once 'load_theme.php'") === false && 
        strpos($content, 'require') !== false) {
        
        // Tìm vị trí sau require db_connect.php
        if (preg_match("/(require\s+['\"]db_connect\.php['\"];)/i", $content, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][1] + strlen($matches[0][0]);
            $before = substr($content, 0, $pos);
            $after = substr($content, $pos);
            
            // Kiểm tra nếu chưa có session check hoặc đã có session check
            $insertText = "\n\n// Load theme\nrequire_once 'load_theme.php';";
            
            // Chèn sau db_connect.php, nhưng trước các điều kiện kiểm tra session
            $content = $before . $insertText . $after;
            $changed = true;
        }
    }
    
    // 2. Đảm bảo $bgGradientCSS có giá trị mặc định nếu chưa có
    if (strpos($content, '$bgGradientCSS') !== false && 
        strpos($content, "if (!isset(\$bgGradientCSS)") === false) {
        // Thêm check để đảm bảo $bgGradientCSS luôn có giá trị
        $checkCode = "\n// Đảm bảo \$bgGradientCSS có giá trị\nif (!isset(\$bgGradientCSS) || empty(\$bgGradientCSS)) {\n    \$bgGradientCSS = 'linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%)';\n}";
        if (preg_match("/(require_once\s+['\"]load_theme\.php['\"];)/i", $content, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][1] + strlen($matches[0][0]);
            $before = substr($content, 0, $pos);
            $after = substr($content, $pos);
            $content = $before . $checkCode . $after;
            $changed = true;
        }
    }
    
    // 3. Thay thế background gradients trong CSS
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $replacement, $content);
            $changed = true;
        }
    }
    
    // 4. Thay thế background trong style inline của body
    $bodyPatterns = [
        '/<body[^>]*style\s*=\s*["\'][^"\']*background[^"\']*["\']/i',
        '/style\s*=\s*["\']background-color:\s*#[0-9a-fA-F]+["\']/i',
    ];
    
    foreach ($bodyPatterns as $pattern) {
        if (preg_match($pattern, $content)) {
            // Thay thế background-color trong inline style
            $content = preg_replace('/(<body[^>]*style\s*=\s*["\'])([^"\']*background[^"\']*)(["\'])/i', 
                '$1$2 background: <?= $bgGradientCSS ?>; background-attachment: fixed;$3', $content);
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
echo "\nDone!\n";
?>


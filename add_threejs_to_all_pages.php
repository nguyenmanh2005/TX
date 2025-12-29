<?php
require 'db_connect.php';
/**
 * Script để thêm Three.js background vào tất cả các trang
 * Chạy script này để tự động thêm Three.js background vào các file PHP
 */

$filesToUpdate = [
    // Game files
    'bj.php', 'baucua.php', 'slot.php', 'roulette.php', 'coinflip.php',
    'dice.php', 'rps.php', 'xocdia.php', 'bot.php', 'vq.php', 'vietlott.php',
    'cs.php', 'number.php', 'duangua.php', 'hopmu.php', 'ruttham.php',
    'ac.php', 'poker.php', 'bingo.php', 'minesweeper.php',
    // Other pages
    'chat.php', 'in4.php', 'select_title.php', 'achievements.php',
    'khungchat.php', 'khungavatar.php', 'addimg.php', 'caothe.php', 'shop.php',
    'about.php', 'diemdanh.php', 'gift.php', 'quests.php', 'statistics.php',
    'inventory.php', 'lucky_wheel.php',
    // Admin pages
    'admin_add_frames.php', 'admin_manage_frames.php', 'admin_add_items.php',
    'admin_manage_items.php', 'admin_manage_users.php'
];

$threeJSLibrary = '<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>';
$canvasElement = '<canvas id="threejs-background"></canvas>';
$threeJSCSS = '
        /* Three.js canvas background */
        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }
';

$threeJSInitScript = '
    // Initialize Three.js Background
    (function() {
        // Pass theme config từ PHP sang JavaScript
        window.themeConfig = {
            particleCount: <?= isset($particleCount) ? $particleCount : 800 ?>,
            particleSize: <?= isset($particleSize) ? $particleSize : 0.05 ?>,
            particleColor: \'<?= isset($particleColor) ? htmlspecialchars($particleColor, ENT_QUOTES) : "#ffffff" ?>\',
            particleOpacity: <?= isset($particleOpacity) ? $particleOpacity : 0.6 ?>,
            shapeCount: <?= isset($shapeCount) ? $shapeCount : 10 ?>,
            shapeColors: <?= isset($shapeColors) ? json_encode($shapeColors) : json_encode([\'#667eea\', \'#764ba2\', \'#4facfe\', \'#00f2fe\']) ?>,
            shapeOpacity: <?= isset($shapeOpacity) ? $shapeOpacity : 0.3 ?>,
            bgGradient: <?= isset($bgGradient) ? json_encode($bgGradient) : json_encode([\'#667eea\', \'#764ba2\', \'#4facfe\']) ?>
        };
        
        // Load Three.js background script
        const script = document.createElement(\'script\');
        script.src = \'threejs-background.js\';
        script.onload = function() {
            console.log(\'Three.js background loaded\');
        };
        document.head.appendChild(script);
    })();
';

$stats = ['updated' => 0, 'skipped' => 0, 'errors' => 0];

foreach ($filesToUpdate as $file) {
    if (!file_exists($file)) {
        echo "⚠ File không tồn tại: $file\n";
        $stats['skipped']++;
        continue;
    }
    
    // Bỏ qua login và auth
    if (strpos($file, 'login.php') !== false || strpos($file, 'auth.php') !== false) {
        echo "○ Skipped (login/auth): $file\n";
        $stats['skipped']++;
        continue;
    }
    
    $content = file_get_contents($file);
    $originalContent = $content;
    $changed = false;
    
    // 1. Thêm Three.js library vào head (sau <head>)
    if (strpos($content, 'three.js') === false && strpos($content, '<head>') !== false) {
        $content = str_replace('<head>', "<head>\n    $threeJSLibrary", $content);
        $changed = true;
    }
    
    // 2. Thêm canvas element vào body (sau <body>)
    if (strpos($content, 'id="threejs-background"') === false && strpos($content, '<body>') !== false) {
        // Tìm <body> và thêm canvas ngay sau đó
        $content = preg_replace('/<body[^>]*>/', "$0\n    $canvasElement", $content, 1);
        $changed = true;
    }
    
    // 3. Thêm CSS cho canvas vào style tag (hoặc tạo style tag mới)
    if (strpos($content, '#threejs-background') === false) {
        // Tìm </style> và thêm CSS trước đó
        if (strpos($content, '</style>') !== false) {
            $content = str_replace('</style>', "$threeJSCSS\n    </style>", $content);
            $changed = true;
        } elseif (strpos($content, '</head>') !== false) {
            // Nếu không có style tag, thêm vào trước </head>
            $content = str_replace('</head>', "<style>$threeJSCSS\n    </style>\n</head>", $content);
            $changed = true;
        }
    }
    
    // 4. Thêm init script trước </body>
    if (strpos($content, 'window.themeConfig') === false && strpos($content, '</body>') !== false) {
        // Tìm script tag cuối cùng hoặc thêm trước </body>
        if (strpos($content, '</script>') !== false) {
            // Thêm vào sau script tag cuối cùng
            $lastScriptPos = strrpos($content, '</script>');
            $content = substr_replace($content, "</script>\n$threeJSInitScript\n", $lastScriptPos, strlen('</script>'));
            $changed = true;
        } else {
            // Thêm script tag mới trước </body>
            $content = str_replace('</body>', "<script>$threeJSInitScript\n</script>\n</body>", $content);
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
echo "\nDone! Don't forget to run this script manually for each file if needed.\n";
?>


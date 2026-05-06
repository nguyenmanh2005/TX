<?php
require 'db_connect.php';
/**
 * Script để tự động thêm Three.js background vào tất cả các trang
 * Chạy: php apply_threejs_background.php
 */

$filesToUpdate = [
    // Game files
    'slot.php', 'roulette.php', 'coinflip.php', 'dice.php', 'rps.php',
    'xocdia.php', 'bot.php', 'vq.php', 'vietlott.php', 'cs.php', 'number.php',
    'duangua.php', 'hopmu.php', 'ruttham.php', 'poker.php', 'bingo.php', 'minesweeper.php',
    // Other pages
    'chat.php', 'in4.php', 'select_title.php', 'achievements.php',
    'khungchat.php', 'khungavatar.php', 'addimg.php', 'caothe.php', 'shop.php',
    'about.php', 'diemdanh.php', 'gift.php', 'quests.php', 'statistics.php',
    'inventory.php', 'lucky_wheel.php',
    // Admin pages
    'admin_add_frames.php', 'admin_manage_frames.php', 'admin_add_items.php',
    'admin_manage_items.php', 'admin_manage_users.php'
];

$threeJSLib = '<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>';
$canvasHTML = '    <canvas id="threejs-background"></canvas>';
$threeJSCSS = '        /* Three.js canvas background */\n        #threejs-background {\n            position: fixed;\n            top: 0;\n            left: 0;\n            width: 100%;\n            height: 100%;\n            z-index: -1;\n            pointer-events: none;\n        }\n';
$threeJSInitScript = '    // Initialize Three.js Background\n    (function() {\n        // Pass theme config từ PHP sang JavaScript\n        window.themeConfig = {\n            particleCount: <?= $particleCount ?>,\n            particleSize: <?= $particleSize ?>,\n            particleColor: \'<?= $particleColor ?>\',\n            particleOpacity: <?= $particleOpacity ?>,\n            shapeCount: <?= $shapeCount ?>,\n            shapeColors: <?= json_encode($shapeColors) ?>,\n            shapeOpacity: <?= $shapeOpacity ?>,\n            bgGradient: <?= json_encode($bgGradient) ?>\n        };\n        \n        // Load Three.js background script\n        const script = document.createElement(\'script\');\n        script.src = \'threejs-background.js\';\n        script.onload = function() {\n            console.log(\'Three.js background loaded\');\n        };\n        document.head.appendChild(script);\n    })();';

$stats = ['updated' => 0, 'skipped' => 0, 'errors' => 0, 'already_have' => 0];

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
    
    // Kiểm tra đã có Three.js background chưa
    if (strpos($content, 'id="threejs-background"') !== false && 
        strpos($content, 'threejs-background.js') !== false) {
        echo "✓ Already has Three.js: $file\n";
        $stats['already_have']++;
        continue;
    }
    
    // 1. Thêm Three.js library vào head (sau <head> hoặc trước </head>)
    if (strpos($content, 'three.js') === false && strpos($content, 'threejs-background.js') === false) {
        // Tìm vị trí trong head để chèn
        if (preg_match('/(<head[^>]*>)/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][1] + strlen($matches[0][0]);
            $before = substr($content, 0, $pos);
            $after = substr($content, $pos);
            $content = $before . "\n    " . $threeJSLib . $after;
            $changed = true;
        } elseif (strpos($content, '</head>') !== false) {
            $content = str_replace('</head>', "    $threeJSLib\n</head>", $content);
            $changed = true;
        }
    }
    
    // 2. Thêm canvas vào body (sau <body>)
    if (strpos($content, 'id="threejs-background"') === false) {
        if (preg_match('/(<body[^>]*>)/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][1] + strlen($matches[0][0]);
            $before = substr($content, 0, $pos);
            $after = substr($content, $pos);
            $content = $before . "\n" . $canvasHTML . "\n" . $after;
            $changed = true;
        }
    }
    
    // 3. Thêm CSS cho canvas
    if (strpos($content, '#threejs-background') === false) {
        // Tìm </style> để thêm CSS trước đó
        if (strpos($content, '</style>') !== false) {
            $content = str_replace('</style>', $threeJSCSS . "\n    </style>", $content);
            $changed = true;
        } elseif (strpos($content, '</head>') !== false) {
            // Nếu không có style tag, thêm vào trước </head>
            $content = str_replace('</head>', "<style>\n" . $threeJSCSS . "\n    </style>\n</head>", $content);
            $changed = true;
        }
    }
    
    // 4. Thêm init script trước </body>
    if (strpos($content, 'window.themeConfig') === false && strpos($content, 'threejs-background.js') === false) {
        if (strpos($content, '</body>') !== false) {
            // Tìm script tag cuối cùng
            if (preg_match('/<\/script>\s*<\/body>/i', $content)) {
                // Thêm vào sau script tag cuối cùng
                $content = preg_replace('/(<\/script>)\s*(<\/body>)/i', "$1\n\n<script>\n" . $threeJSInitScript . "\n</script>\n$2", $content, 1);
                $changed = true;
            } else {
                // Thêm script tag mới trước </body>
                $content = str_replace('</body>', "<script>\n" . $threeJSInitScript . "\n</script>\n</body>", $content);
                $changed = true;
            }
        }
    }
    
    // 5. Đảm bảo body có position: relative
    if (strpos($content, 'body {') !== false && strpos($content, 'position: relative') === false) {
        $content = preg_replace('/(body\s*\{[^}]*)(padding[^;]*;)/i', '$1position: relative;\n        $2', $content);
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
echo "Already have: {$stats['already_have']} files\n";
echo "Skipped: {$stats['skipped']} files\n";
echo "Errors: {$stats['errors']} files\n";
echo "\nDone!\n";
?>


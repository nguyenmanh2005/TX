<?php
require 'db_connect.php';
/**
 * Script tự động thêm Three.js background vào tất cả các trang game
 * Chạy: php add_threejs_to_games.php
 */

$gameFiles = [
    'roulette.php', 'coinflip.php', 'dice.php', 'rps.php', 
    'number.php', 'bot.php', 'vq.php', 'cs.php', 
    'duangua.php', 'hopmu.php', 'ruttham.php', 'ac.php',
    'poker.php', 'bingo.php', 'minesweeper.php'
];

$threeJSLib = '<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>';
$canvasHTML = '    <canvas id="threejs-background"></canvas>';
$threeJSCSS = '        /* Three.js canvas background */\n        #threejs-background {\n            position: fixed;\n            top: 0;\n            left: 0;\n            width: 100%;\n            height: 100%;\n            z-index: -1;\n            pointer-events: none;\n        }\n        \n        .game-box, .game-container, .container {\n            position: relative;\n            z-index: 1;\n        }';
$threeJSInitScript = '    // Initialize Three.js Background\n    (function() {\n        window.themeConfig = {\n            particleCount: <?= $particleCount ?>,\n            particleSize: <?= $particleSize ?>,\n            particleColor: \'<?= $particleColor ?>\',\n            particleOpacity: <?= $particleOpacity ?>,\n            shapeCount: <?= $shapeCount ?>,\n            shapeColors: <?= json_encode($shapeColors) ?>,\n            shapeOpacity: <?= $shapeOpacity ?>,\n            bgGradient: <?= json_encode($bgGradient) ?>\n        };\n        const script = document.createElement(\'script\');\n        script.src = \'threejs-background.js\';\n        script.onload = function() { console.log(\'Three.js background loaded\'); };\n        document.head.appendChild(script);\n    })();';

$stats = ['updated' => 0, 'skipped' => 0, 'errors' => 0, 'already_have' => 0];

foreach ($gameFiles as $file) {
    if (!file_exists($file)) {
        echo "⚠ File không tồn tại: $file\n";
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
    
    // 1. Thêm Three.js library vào head
    if (strpos($content, 'three.js') === false && strpos($content, 'threejs-background.js') === false) {
        // Tìm <head> hoặc </head>
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
    
    // 2. Thêm canvas vào body
    if (strpos($content, 'id="threejs-background"') === false) {
        if (preg_match('/(<body[^>]*>)/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][1] + strlen($matches[0][0]);
            $before = substr($content, 0, $pos);
            $after = substr($content, $pos);
            // Kiểm tra nếu có canvas pháo hoa, thêm trước nó
            if (strpos($after, '<canvas id="phaohoa"') !== false) {
                $after = str_replace('<canvas id="phaohoa"', $canvasHTML . "\n" . '<canvas id="phaohoa"', $after);
            } else {
                $content = $before . "\n" . $canvasHTML . "\n" . $after;
            }
            $changed = true;
        }
    }
    
    // 3. Thêm CSS cho canvas
    if (strpos($content, '#threejs-background') === false) {
        if (strpos($content, '</style>') !== false) {
            $content = str_replace('</style>', $threeJSCSS . "\n    </style>", $content);
            $changed = true;
        } elseif (strpos($content, '</head>') !== false) {
            $content = str_replace('</head>', "<style>\n" . $threeJSCSS . "\n    </style>\n</head>", $content);
            $changed = true;
        }
    }
    
    // 4. Đảm bảo body có position: relative
    if (strpos($content, 'body {') !== false && strpos($content, 'position: relative') === false && strpos($content, 'body {') < strpos($content, 'position: relative')) {
        $content = preg_replace('/(body\s*\{[^}]*)(min-height[^;]*;)/i', '$1position: relative;\n        $2', $content);
        $changed = true;
    }
    
    // 5. Thêm init script trước </body>
    if (strpos($content, 'window.themeConfig') === false && strpos($content, 'threejs-background.js') === false) {
        if (strpos($content, '</body>') !== false) {
            // Tìm script tag cuối cùng
            if (preg_match('/(<\/script>)\s*(<\/body>)/s', $content, $matches, PREG_OFFSET_CAPTURE)) {
                $pos = $matches[0][1];
                $before = substr($content, 0, $pos);
                $after = substr($content, $pos);
                $content = $before . "</script>\n\n<script>\n" . $threeJSInitScript . "\n</script>\n" . $after;
                $changed = true;
            } else {
                // Thêm script tag mới trước </body>
                $content = str_replace('</body>', "<script>\n" . $threeJSInitScript . "\n</script>\n</body>", $content);
                $changed = true;
            }
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
echo "Already have: {$stats['already_have']} files\n";
echo "Skipped: {$stats['skipped']} files\n";
echo "Errors: {$stats['errors']} files\n";
echo "\nDone! Please check the files manually to ensure everything is correct.\n";
?>


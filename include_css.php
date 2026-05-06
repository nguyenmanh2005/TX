<?php
/**
 * CSS Include Helper
 * Sử dụng file này để include tất cả CSS files một cách dễ dàng
 * 
 * Usage:
 * require_once 'include_css.php';
 * echo getCSSIncludes();
 * 
 * Hoặc với options:
 * echo getCSSIncludes(['responsive' => false, 'loading' => false]);
 */

function getCSSIncludes($options = [])
{
    $defaults = [
        'main' => true,
        'components' => true,
        'responsive' => true,
        'loading' => true,
        'animations' => true,
        'special_effects' => false,
        'game_effects' => false, // Chỉ cần cho game pages
        'use_master' => true, // Mặc định sử dụng master.css để tối ưu
    ];

    $options = array_merge($defaults, $options);
    
    // Tự động điều chỉnh đường dẫn nếu đang ở trong thư mục games/
    $currentDir = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
    $prefix = (strpos($currentDir, '/games') !== false) ? '../' : '';

    $cssFiles = [];

    if ($options['use_master']) {
        // Sử dụng master.css (tất cả trong một)
        $cssFiles[] = 'assets/css/master.css';
    } else {
        // Include từng file riêng
        if ($options['main']) {
            $cssFiles[] = 'assets/css/main.css';
        }
        if ($options['components']) {
            $cssFiles[] = 'assets/css/components.css';
        }
        if ($options['responsive']) {
            $cssFiles[] = 'assets/css/responsive.css';
        }
        if ($options['loading']) {
            $cssFiles[] = 'assets/css/loading.css';
        }
        if ($options['animations']) {
            $cssFiles[] = 'assets/css/animations.css';
        }
        if ($options['game_effects']) {
            $cssFiles[] = 'assets/css/game-effects.css';
        }
        if ($options['special_effects']) {
            $cssFiles[] = 'assets/css/special_effects.css';
        }
    }

    $html = '';
    foreach ($cssFiles as $file) {
        $html .= '<link rel="stylesheet" href="' . $prefix . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '?v=1.0.1">' . "\n    ";
    }

    return trim($html);
}

/**
 * Get CSS includes for game pages
 */
function getGameCSSIncludes()
{
    return getCSSIncludes([
        'game_effects' => true,
    ]);
}

/**
 * Get CSS includes for admin pages
 */
function getAdminCSSIncludes()
{
    return getCSSIncludes([
        'use_master' => true, // Admin pages dùng master.css
    ]);
}

/**
 * Get minimal CSS includes (chỉ main.css)
 */
function getMinimalCSSIncludes()
{
    return getCSSIncludes([
        'components' => false,
        'responsive' => false,
        'loading' => false,
        'animations' => false,
    ]);
}
?>
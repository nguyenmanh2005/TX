<?php
/**
 * Script đơn giản hơn để tạo ảnh khung - sử dụng HTML/CSS và convert sang ảnh
 * Hoặc tạo ảnh đơn giản với GD Library
 */

require 'db_connect.php';

// Tạo thư mục
$framesDir = 'uploads/frames';
if (!file_exists($framesDir)) {
    mkdir($framesDir, 0777, true);
}

// Kiểm tra GD Library
if (!extension_loaded('gd')) {
    die('GD Library không được cài đặt!');
}

/**
 * Tạo khung chat đơn giản
 */
function createSimpleChatFrame($filename, $color, $width = 800, $height = 200) {
    global $framesDir;
    
    $img = imagecreatetruecolor($width, $height);
    imagesavealpha($img, true);
    
    // Màu nền trong suốt
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);
    
    // Parse màu hex
    $color = str_replace('#', '', $color);
    $r = hexdec(substr($color, 0, 2));
    $g = hexdec(substr($color, 2, 2));
    $b = hexdec(substr($color, 4, 2));
    
    $frameColor = imagecolorallocate($img, $r, $g, $b);
    
    // Vẽ khung với viền và gradient đơn giản
    $margin = 8;
    $radius = 15;
    
    // Vẽ hình chữ nhật chính
    imagefilledrectangle($img, $margin + $radius, $margin, $width - $margin - $radius, $height - $margin, $frameColor);
    imagefilledrectangle($img, $margin, $margin + $radius, $width - $margin, $height - $margin - $radius, $frameColor);
    
    // Vẽ góc bo tròn (mô phỏng bằng elipse)
    imagefilledellipse($img, $margin + $radius, $margin + $radius, $radius * 2, $radius * 2, $frameColor);
    imagefilledellipse($img, $width - $margin - $radius, $margin + $radius, $radius * 2, $radius * 2, $frameColor);
    imagefilledellipse($img, $margin + $radius, $height - $margin - $radius, $radius * 2, $radius * 2, $frameColor);
    imagefilledellipse($img, $width - $margin - $radius, $height - $margin - $radius, $radius * 2, $radius * 2, $frameColor);
    
    // Thêm viền sáng bên trong
    $lightR = min(255, $r + 60);
    $lightG = min(255, $g + 60);
    $lightB = min(255, $b + 60);
    $lightColor = imagecolorallocate($img, $lightR, $lightG, $lightB);
    
    $border = 3;
    imagerectangle($img, $margin + $border, $margin + $border, 
                   $width - $margin - $border, $height - $margin - $border, $lightColor);
    
    // Lưu file
    $filepath = $framesDir . '/' . $filename;
    imagepng($img, $filepath);
    imagedestroy($img);
    
    return $filepath;
}

/**
 * Tạo khung avatar đơn giản
 */
function createSimpleAvatarFrame($filename, $color, $size = 200) {
    global $framesDir;
    
    $img = imagecreatetruecolor($size, $size);
    imagesavealpha($img, true);
    
    // Màu nền trong suốt
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);
    
    // Parse màu hex
    $color = str_replace('#', '', $color);
    $r = hexdec(substr($color, 0, 2));
    $g = hexdec(substr($color, 2, 2));
    $b = hexdec(substr($color, 4, 2));
    
    $frameColor = imagecolorallocate($img, $r, $g, $b);
    
    $centerX = $size / 2;
    $centerY = $size / 2;
    $outerRadius = ($size / 2) - 2;
    $innerRadius = ($size / 2) - 12;
    
    // Vẽ vòng tròn ngoài (viền ngoài)
    imagefilledellipse($img, $centerX, $centerY, $outerRadius * 2, $outerRadius * 2, $frameColor);
    
    // Vẽ vòng tròn trong (tạo viền rỗng)
    $inner = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefilledellipse($img, $centerX, $centerY, $innerRadius * 2, $innerRadius * 2, $inner);
    
    // Thêm viền sáng bên trong
    $lightR = min(255, $r + 50);
    $lightG = min(255, $g + 50);
    $lightB = min(255, $b + 50);
    $lightColor = imagecolorallocate($img, $lightR, $lightG, $lightB);
    
    $borderRadius = $innerRadius + 2;
    imagefilledellipse($img, $centerX, $centerY, $borderRadius * 2, $borderRadius * 2, $lightColor);
    imagefilledellipse($img, $centerX, $centerY, $innerRadius * 2, $innerRadius * 2, $inner);
    
    // Thêm highlight ở góc trên trái
    $highlight = imagecolorallocatealpha($img, 255, 255, 255, 100);
    $highlightRadius = $outerRadius * 0.6;
    imagefilledellipse($img, $centerX - 15, $centerY - 15, $highlightRadius, $highlightRadius, $highlight);
    
    // Lưu file
    $filepath = $framesDir . '/' . $filename;
    imagepng($img, $filepath);
    imagedestroy($img);
    
    return $filepath;
}

// Định nghĩa các khung chat
$chatFrames = [
    ['chat_blue.png', '#3498db'],
    ['chat_red.png', '#e74c3c'],
    ['chat_gold.png', '#f39c12'],
    ['chat_purple.png', '#9b59b6'],
    ['chat_pink.png', '#e91e63'],
    ['chat_green.png', '#2ecc71'],
    ['chat_black.png', '#2c3e50'],
    ['chat_rainbow.png', '#ff6b6b'],
    ['chat_silver.png', '#95a5a6'],
    ['chat_diamond.png', '#1abc9c']
];

// Định nghĩa các khung avatar
$avatarFrames = [
    ['avatar_gold.png', '#f39c12'],
    ['avatar_silver.png', '#bdc3c7'],
    ['avatar_red.png', '#e74c3c'],
    ['avatar_blue.png', '#3498db'],
    ['avatar_purple.png', '#9b59b6'],
    ['avatar_pink.png', '#e91e63'],
    ['avatar_green.png', '#2ecc71'],
    ['avatar_black.png', '#2c3e50'],
    ['avatar_rainbow.png', '#ff6b6b'],
    ['avatar_diamond.png', '#1abc9c'],
    ['avatar_fire.png', '#ff4500'],
    ['avatar_ice.png', '#00bfff']
];

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Tạo Khung</title></head><body>";
echo "<h1>Đang tạo ảnh khung...</h1>";

// Tạo khung chat
echo "<h2>Khung Chat:</h2>";
foreach ($chatFrames as $frame) {
    try {
        $path = createSimpleChatFrame($frame[0], $frame[1]);
        echo "✓ {$frame[0]} - <a href='{$path}' target='_blank'>Xem</a><br>";
    } catch (Exception $e) {
        echo "✗ Lỗi tạo {$frame[0]}: " . $e->getMessage() . "<br>";
    }
}

// Tạo khung avatar
echo "<h2>Khung Avatar:</h2>";
foreach ($avatarFrames as $frame) {
    try {
        $path = createSimpleAvatarFrame($frame[0], $frame[1]);
        echo "✓ {$frame[0]} - <a href='{$path}' target='_blank'>Xem</a><br>";
    } catch (Exception $e) {
        echo "✗ Lỗi tạo {$frame[0]}: " . $e->getMessage() . "<br>";
    }
}

echo "<hr>";
echo "<h2>Hoàn thành!</h2>";
echo "<p>Các ảnh đã được tạo. Bây giờ bạn có thể:</p>";
echo "<ol>";
echo "<li>Chạy file <strong>add_sample_frames.sql</strong> trong phpMyAdmin để thêm các khung vào database</li>";
echo "<li>Hoặc sử dụng trang admin để thêm khung thủ công</li>";
echo "</ol>";
echo "</body></html>";
?>


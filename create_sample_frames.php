<?php
/**
 * Script tạo ảnh khung chat và avatar mẫu
 * Chạy file này một lần để tạo các ảnh mẫu
 */

require 'db_connect.php';

// Tạo thư mục nếu chưa có
$framesDir = 'uploads/frames';
if (!file_exists($framesDir)) {
    mkdir($framesDir, 0777, true);
}

/**
 * Tạo ảnh khung chat với màu sắc và kích thước
 */
function createChatFrame($filename, $color, $width = 800, $height = 200) {
    global $framesDir;
    
    $image = imagecreatetruecolor($width, $height);
    imagesavealpha($image, true);
    
    // Màu trong suốt
    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagefill($image, 0, 0, $transparent);
    
    // Chuyển đổi màu hex sang RGB
    $rgb = hex2rgb($color);
    $frameColor = imagecolorallocate($image, $rgb['r'], $rgb['g'], $rgb['b']);
    
    // Vẽ viền bo tròn
    $borderWidth = 8;
    $radius = 20;
    
    // Vẽ khung với góc bo tròn
    imagefilledroundedrectangle($image, $borderWidth, $borderWidth, 
                                $width - $borderWidth, $height - $borderWidth, 
                                $radius, $frameColor);
    
    // Thêm hiệu ứng gradient đơn giản
    for ($i = 0; $i < $height; $i++) {
        $alpha = (int)(127 * ($i / $height) * 0.3);
        $gradientColor = imagecolorallocatealpha($image, $rgb['r'], $rgb['g'], $rgb['b'], $alpha);
        imageline($image, 0, $i, $width, $i, $gradientColor);
    }
    
    // Lưu ảnh
    $filepath = $framesDir . '/' . $filename;
    imagepng($image, $filepath);
    imagedestroy($image);
    
    return $filepath;
}

/**
 * Tạo ảnh khung avatar với màu sắc
 */
function createAvatarFrame($filename, $color, $size = 200) {
    global $framesDir;
    
    $image = imagecreatetruecolor($size, $size);
    imagesavealpha($image, true);
    
    // Màu trong suốt
    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagefill($image, 0, 0, $transparent);
    
    // Chuyển đổi màu hex sang RGB
    $rgb = hex2rgb($color);
    $frameColor = imagecolorallocate($image, $rgb['r'], $rgb['g'], $rgb['b']);
    
    // Vẽ khung tròn với viền dày
    $borderWidth = 12;
    $centerX = $size / 2;
    $centerY = $size / 2;
    $outerRadius = ($size / 2) - 2;
    $innerRadius = ($size / 2) - $borderWidth;
    
    // Vẽ vòng tròn ngoài
    imagefilledellipse($image, $centerX, $centerY, $outerRadius * 2, $outerRadius * 2, $frameColor);
    
    // Vẽ vòng tròn trong (trong suốt) để tạo viền
    $innerColor = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagefilledellipse($image, $centerX, $centerY, $innerRadius * 2, $innerRadius * 2, $innerColor);
    
    // Thêm hiệu ứng ánh sáng
    $highlight = imagecolorallocatealpha($image, 255, 255, 255, 50);
    imagefilledellipse($image, $centerX - 10, $centerY - 10, $outerRadius, $outerRadius, $highlight);
    
    // Lưu ảnh
    $filepath = $framesDir . '/' . $filename;
    imagepng($image, $filepath);
    imagedestroy($image);
    
    return $filepath;
}

/**
 * Chuyển đổi hex color sang RGB
 */
function hex2rgb($hex) {
    $hex = str_replace('#', '', $hex);
    return [
        'r' => hexdec(substr($hex, 0, 2)),
        'g' => hexdec(substr($hex, 2, 2)),
        'b' => hexdec(substr($hex, 4, 2))
    ];
}

/**
 * Vẽ hình chữ nhật bo tròn
 */
function imagefilledroundedrectangle($image, $x1, $y1, $x2, $y2, $radius, $color) {
    imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
    imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
    imagefilledellipse($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
}

// Màu sắc cho các khung chat
$chatFrames = [
    ['chat_blue.png', '#3498db', 'Khung Xanh Dương'],
    ['chat_red.png', '#e74c3c', 'Khung Đỏ Rực'],
    ['chat_gold.png', '#f39c12', 'Khung Vàng Kim'],
    ['chat_purple.png', '#9b59b6', 'Khung Tím Huyền Bí'],
    ['chat_pink.png', '#e91e63', 'Khung Hồng Ngọt Ngào'],
    ['chat_green.png', '#2ecc71', 'Khung Xanh Lá'],
    ['chat_black.png', '#34495e', 'Khung Đen Bóng'],
    ['chat_rainbow.png', '#ff6b6b', 'Khung Cầu Vồng'], // Màu đỏ cam cho cầu vồng
    ['chat_silver.png', '#95a5a6', 'Khung Bạc'],
    ['chat_diamond.png', '#1abc9c', 'Khung Kim Cương']
];

// Màu sắc cho các khung avatar
$avatarFrames = [
    ['avatar_gold.png', '#f39c12', 'Khung Vàng'],
    ['avatar_silver.png', '#bdc3c7', 'Khung Bạc'],
    ['avatar_red.png', '#e74c3c', 'Khung Đỏ'],
    ['avatar_blue.png', '#3498db', 'Khung Xanh'],
    ['avatar_purple.png', '#9b59b6', 'Khung Tím'],
    ['avatar_pink.png', '#e91e63', 'Khung Hồng'],
    ['avatar_green.png', '#2ecc71', 'Khung Xanh Lá'],
    ['avatar_black.png', '#2c3e50', 'Khung Đen'],
    ['avatar_rainbow.png', '#ff6b6b', 'Khung Cầu Vồng'],
    ['avatar_diamond.png', '#1abc9c', 'Khung Kim Cương'],
    ['avatar_fire.png', '#ff4500', 'Khung Lửa'],
    ['avatar_ice.png', '#00bfff', 'Khung Băng']
];

echo "<h2>Đang tạo ảnh khung chat...</h2>";
foreach ($chatFrames as $frame) {
    $filepath = createChatFrame($frame[0], $frame[1]);
    echo "✓ Đã tạo: {$filepath}<br>";
}

echo "<h2>Đang tạo ảnh khung avatar...</h2>";
foreach ($avatarFrames as $frame) {
    $filepath = createAvatarFrame($frame[0], $frame[1]);
    echo "✓ Đã tạo: {$filepath}<br>";
}

echo "<h2>Hoàn thành! Các ảnh đã được tạo trong thư mục uploads/frames/</h2>";
echo "<p>Bây giờ bạn có thể chạy file add_sample_frames.sql để thêm các khung vào database.</p>";
?>


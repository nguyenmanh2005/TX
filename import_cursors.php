<?php
require 'db_connect.php';

$cursorDir = 'cursors/';
$directories = array_filter(glob($cursorDir . '*'), 'is_dir');

echo "Đang cập nhật bộ sưu tập con trỏ (Cursor + Pointer)...<br><br>";

foreach ($directories as $dir) {
    $folderName = basename($dir);
    if ($folderName === 'AI_CREATE') continue;

    // Tìm file ảnh chính (cursor) và ảnh bàn tay (pointer)
    $cursorImages = glob($dir . '/*--cursor--SweezyCursors.png');
    $pointerImages = glob($dir . '/*--pointer--SweezyCursors.png');
    
    if (!empty($cursorImages)) {
        $imagePath = $cursorImages[0];
        $pointerPath = !empty($pointerImages) ? $pointerImages[0] : null;
        
        $name = str_replace(['Animated', 'Meme', '(', ')'], '', $folderName);
        $name = trim($name);
        
        // Kiểm tra xem đã tồn tại chưa
        $check = $conn->prepare("SELECT id FROM cursors WHERE cursor_image = ?");
        $check->bind_param("s", $imagePath);
        $check->execute();
        $res = $check->get_result();
        
        if ($res->num_rows > 0) {
            // Cập nhật pointer_image nếu đã có bản ghi
            $stmt = $conn->prepare("UPDATE cursors SET pointer_image = ? WHERE cursor_image = ?");
            $stmt->bind_param("ss", $pointerPath, $imagePath);
            $stmt->execute();
            echo "🔄 Đã cập nhật Pointer cho: <b>$name</b><br>";
        } else {
            // Thêm mới nếu chưa có
            $desc = "Bộ con trỏ đồng bộ: $folderName";
            $price = rand(5, 50) * 1000;
            $isPremium = ($price > 30000) ? 1 : 0;
            
            $stmt = $conn->prepare("INSERT INTO cursors (name, description, price, cursor_image, pointer_image, is_premium) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdssi", $name, $desc, $price, $imagePath, $pointerPath, $isPremium);
            $stmt->execute();
            echo "✨ Đã thêm bộ mới: <b>$name</b><br>";
        }
    }
}

echo "<br><b>Hoàn tất cập nhật 2 trạng thái cho toàn bộ Shop!</b>";
?>

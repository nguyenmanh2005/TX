<?php
$file = 'bot_engine.php';
$lines = file($file);

for ($i = 0; $i < count($lines); $i++) {
    // Nếu dòng chỉ chứa */ và gây lỗi, hãy xóa nó
    if (trim($lines[$i]) === '*/' && ($i > 100 && $i < 300)) {
        $lines[$i] = "}\n"; // Giả sử nó là đóng hàm bị sai
    }
}

file_put_contents($file, implode("", $lines));
echo "Forcefully replaced orphan */ with }\n";
?>

<?php
$logPath = 'C:\Users\manht\.gemini\antigravity-ide\brain\a63179e2-3577-41b9-bebb-54b1582ff44f\.system_generated\logs\transcript.jsonl';
$lines = file($logPath);
echo "--- LONG USER INPUTS ---\n";
foreach ($lines as $line) {
    $data = json_decode($line, true);
    if (isset($data['type']) && $data['type'] === 'USER_INPUT') {
        $content = $data['content'];
        if (strlen($content) > 150 && strlen($content) < 3000) {
            // Loại bỏ các đoạn chứa quá nhiều mojibake nếu có thể
            if (strpos($content, 'Lich King') === false) {
                echo "\n[MSG LENGTH " . strlen($content) . "]\n";
                echo $content . "\n";
            }
        }
    }
}
?>

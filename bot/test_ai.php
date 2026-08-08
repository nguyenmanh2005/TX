<?php
require_once 'bot_chat_smart_ai.php';

// Khởi tạo một phiên bản mock của class
$mockDb = new class {
    public function query($q) { return null; }
    public function prepare($q) { return null; }
};
$ai = new BotChatSmartAI($mockDb);

// Dùng Reflection để gọi hàm private
$reflection = new ReflectionClass(BotChatSmartAI::class);
$method = $reflection->getMethod('generateDynamicResponse');
$method->setAccessible(true);

echo "--- THỬ NGHIỆM SINH CÂU TỰ ĐỘNG (DYNAMIC DIALOGUE ENGINE) ---\n\n";

$tests = [
    ['context' => 'Win_Context', 'author' => 'ManhT', 'mood' => 'happy', 'extra' => ['game_name' => 'Tài Xỉu']],
    ['context' => 'Win_Context', 'author' => 'ManhT', 'mood' => 'angry', 'extra' => []],
    ['context' => 'Lose_Context', 'author' => 'Gà Mờ', 'mood' => 'sad', 'extra' => []],
    ['context' => 'Advice_Context', 'author' => 'Newbie', 'mood' => 'neutral', 'extra' => ['game_name' => 'Plinko V2']],
    ['context' => 'Rivalry_Trigger', 'author' => 'KẻThù', 'mood' => 'angry', 'extra' => ['amount' => 1500000]],
];

foreach ($tests as $i => $t) {
    echo "Bài test #".($i+1)." - Context: {$t['context']} | Mood: {$t['mood']}\n";
    // Sinh thử 3 biến thể cho cùng 1 đầu vào
    for($j=0; $j<3; $j++) {
        $result = $method->invoke($ai, $t['context'], $t['author'], $t['mood'], $t['extra']);
        echo "   -> $result\n";
    }
    echo "---------------------------\n";
}
?>

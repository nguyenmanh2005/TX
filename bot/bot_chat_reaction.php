<?php
/**
 * Chat Reaction Bot Logic
 * Handles bots randomly reacting (liking, laughing, etc.) to messages in the global chat.
 */

function handleChatReactionBot($baseUrl, $cFile, $botMood, $botPersonality, $botName) {
    $actions = [];

    // 1. Tải 50 tin nhắn mới nhất
    $chatRes = executeBotAction($baseUrl . "/chat.php?action=load", null, $cFile);
    
    // API load chat trả về array trực tiếp hoặc chứa error
    if (!is_array($chatRes) || isset($chatRes['success']) && !$chatRes['success']) {
        return null; 
    }

    $messages = $chatRes;
    if (empty($messages)) return null;

    // Lọc ra các tin nhắn KHÔNG PHẢI của chính bot này
    $otherMessages = array_filter($messages, function($m) use ($botName) {
        return isset($m['username']) && $m['username'] !== $botName;
    });

    if (empty($otherMessages)) return null;

    // 2. Chọn ngẫu nhiên 1 tin nhắn để tương tác
    $targetMsg = $otherMessages[array_rand($otherMessages)];
    $targetId = $targetMsg['id'] ?? null;
    $targetAuthor = $targetMsg['username'] ?? 'Ai đó';

    if (!$targetId) return null;

    // 3. Chọn Emoji dựa theo Tâm trạng (Mood)
    $emojiMap = [
        'happy' => ['❤️', '👍', '😂', '🔥', '🥰'],
        'excited' => ['🔥', '🎉', '🤩', '🚀', '❤️'],
        'tilted' => ['😡', '👎', '🤬', '🙄'],
        'broke' => ['😭', '💔', '😢', '🥺'],
        'depressed' => ['😭', '👎', '😔', '💔']
    ];

    $moodList = $emojiMap[$botMood] ?? $emojiMap['happy'];
    
    // Nếu bot là 'announcer' thì thích thả Lửa hơn
    if ($botPersonality === 'announcer' && rand(1, 100) <= 50) {
        $moodList = ['🔥', '🎉', '📢', '🌟'];
    }

    $chosenEmoji = $moodList[array_rand($moodList)];

    // 4. Gọi API React
    $reactRes = executeBotAction($baseUrl . "/chat.php?action=react&msg_id=$targetId&emoji=" . urlencode($chosenEmoji), null, $cFile);

    if (isset($reactRes['status']) && $reactRes['status'] === 'added') {
        $actions[] = "Lướt chat và thả cảm xúc $chosenEmoji vào tin nhắn của <b>$targetAuthor</b>!";
        return ['status' => 'success', 'actions' => $actions];
    }

    return null;
}
?>

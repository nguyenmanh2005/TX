<?php
/**
 * Bot Chat Smart AI & Context-Aware NLP Engine
 * Tuân thủ Rule 5.3 (Bộ từ vựng thay thế) & Rule 5.4 (Hoạt động độc lập trong bot/)
 * Tích hợp Hướng E: Mood & Rivalry System.
 */

if (!defined('BOT_SMART_AI_LOADED')) {
    define('BOT_SMART_AI_LOADED', true);
}

class BotChatSmartAI {
    private $conn;
    private $botList = [];

    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
        $this->initBots();
    }

    /**
     * Khởi tạo danh sách các Bot danh tiếng để sẵn sàng phản hồi ngữ cảnh
     */
    private function initBots() {
        $this->botList = [
            [ 'id' => 9001, 'username' => 'Cụ Giáo', 'avatar' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=cugia&style=circle', 'personality' => 'cugia', 'role' => 2, 'title_id' => 1 ],
            [ 'id' => 9002, 'username' => 'Đại Gia Whale', 'avatar' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=whale&style=circle', 'personality' => 'rich_kid', 'role' => 3, 'title_id' => 2 ],
            [ 'id' => 9003, 'username' => 'Thánh Nổ Plinko', 'avatar' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=plinko&style=circle', 'personality' => 'danchoi', 'role' => 0, 'title_id' => 3 ],
            [ 'id' => 9004, 'username' => 'Bé Simp Lởm', 'avatar' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=simp&style=circle', 'personality' => 'simp', 'role' => 0, 'title_id' => 4 ],
            [ 'id' => 9005, 'username' => 'Lão Triết Lý', 'avatar' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=trietly&style=circle', 'personality' => 'trietly_nguoc', 'role' => 0, 'title_id' => 5 ]
        ];
    }

    private function applyVocabulary($text) {
        $vocabPath = __DIR__ . '/../vocabulary_helper.php';
        if (file_exists($vocabPath)) {
            require_once $vocabPath;
            if (class_exists('VocabularyHelper')) {
                $text = VocabularyHelper::mask($text);
            }
        }
        $replacements = [
            '/thắng\s+GTLM/ui' => 'húp GTLM',
            '/thua\s+GTLM/ui' => 'bay màu',
            '/cá\s+cược/ui' => 'giao lưu giải trí',
            '/đặt\s+cược/ui' => 'ra chiêu',
            '/sòng\s+bài/ui' => 'trận địa',
            '/casino/ui' => 'trận địa',
            '/ván\s+bài/ui' => 'ván giao lưu',
            '/hết\s+GTLM/ui' => 'nick trắng tay',
            '/xóc\s+đĩa/ui' => 'Trận Địa Trắng Đỏ',
            '/slot\s+machine/ui' => 'máy vận may'
        ];
        foreach ($replacements as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }
        return $text;
    }

    private function generateDynamicResponse($context, $author, $mood, $extraData = []) {
        $jsonPath = __DIR__ . '/chat/dynamic_dialogues.json';
        if (!file_exists($jsonPath)) return '';
        $data = json_decode(file_get_contents($jsonPath), true);
        if (!$data) return '';

        // Chọn Greeting
        $greetings = $data['greetings'][$mood] ?? $data['greetings']['neutral'] ?? [""];
        $greeting = $greetings[array_rand($greetings)];

        // Chọn Context Body
        $bodies = [];
        if (isset($data['contexts'][$context])) {
            $bodies = $data['contexts'][$context][$mood] ?? $data['contexts'][$context]['neutral'] ?? [];
        }
        if (empty($bodies)) return '';
        $body = $bodies[array_rand($bodies)];

        // Chọn Ending
        $ending = $data['endings'][array_rand($data['endings'])] ?? "";

        // Format
        $reply = "@{$author} {$greeting} {$body}{$ending}";
        
        // Thay biến
        $gameName = $extraData['game_name'] ?? $data['games'][array_rand($data['games'])];
        $amount = $extraData['amount'] ?? 0;
        
        $reply = str_replace('{author}', $author, $reply);
        $reply = str_replace('{game_name}', $gameName, $reply);
        $reply = str_replace('{amount}', number_format($amount), $reply);

        // Xóa dấu @$author nếu trong greeting đã có
        if (strpos($greeting, '{author}') !== false) {
             $reply = preg_replace('/^@' . preg_quote($author, '/') . '\s+/', '', $reply);
        }

        return $reply;
    }

    private function generateRealtimeResponse($text, $author) {
        $lower = mb_strtolower($text);
        
        // 1. Hỏi Hũ Jackpot Realtime
        if (preg_match('/(jackpot|hũ|nổ hũ|kho gtlm)/ui', $lower)) {
            $res = $this->conn->query("SELECT amount FROM jackpots ORDER BY amount DESC LIMIT 1");
            $jpAmount = ($res && $row = $res->fetch_assoc()) ? (float)$row['amount'] : 50000000;
            $templates = [
                "Hũ Jackpot hiện tại đang ở mức **" . number_format($jpAmount) . " GTLM** đó bác @{$author}! Ra chiêu ngay kẻo nổ mất! 🚀",
                "Kho Jackpot rực rỡ đang tích đến **" . number_format($jpAmount) . " GTLM** nhé @{$author}! Nhanh tay húp lộc nào! 🔥",
                "Bác @{$author} hỏi đúng lúc thế, hũ Jackpot đang chứa **" . number_format($jpAmount) . " GTLM** rồi đó!"
            ];
            return $templates[array_rand($templates)];
        }
        
        // 2. Hỏi Top Giàu Nhất Realtime
        if (preg_match('/(top 1|giàu nhất|bá chủ|đại gia|ai giàu)/ui', $lower)) {
            $res = $this->conn->query("SELECT Name, Money FROM users WHERE Email NOT REGEXP '^bot[0-9]+@' ORDER BY Money DESC LIMIT 1");
            if ($res && $topUser = $res->fetch_assoc()) {
                $templates = [
                    "Bá chủ Trận Địa hiện tại là đại gia **@{$topUser['Name']}** với **" . number_format($topUser['Money']) . " GTLM** nhé bác @{$author}! 😎",
                    "Top 1 server đang thuộc về đại gia **@{$topUser['Name']}** (nắm giữ " . number_format($topUser['Money']) . " GTLM) đó @{$author}! 🏆",
                    "Đại gia **@{$topUser['Name']}** đang làm trùm Trận Địa với " . number_format($topUser['Money']) . " GTLM nhé bác!"
                ];
                return $templates[array_rand($templates)];
            }
        }
        
        // 3. Hỏi Ván Húp Đậm Gần Nhất
        if (preg_match('/(ai thắng|ai húp|húp đậm|thắng lớn|vận khí)/ui', $lower)) {
            $res = $this->conn->query("SELECT u.Name, h.win_amount, h.game_name FROM game_history h JOIN users u ON h.user_id = u.Iduser WHERE h.is_win = 1 ORDER BY h.win_amount DESC LIMIT 1");
            if ($res && $bigWin = $res->fetch_assoc()) {
                $templates = [
                    "Gần đây nhất có cao thủ **@{$bigWin['Name']}** vừa húp đậm **" . number_format($bigWin['win_amount']) . " GTLM** tại " . $bigWin['game_name'] . " đó bác @{$author}! 🔥",
                    "Bái phục vận khí của **@{$bigWin['Name']}**, vừa bỏ túi " . number_format($bigWin['win_amount']) . " GTLM ở " . $bigWin['game_name'] . " kìa bác @{$author}! 💎"
                ];
                return $templates[array_rand($templates)];
            }
        }

        return null;
    }

    public function scanAndRespond() {
        if (!$this->conn) return ['success' => false, 'message' => 'No database connection'];

        $checkTable = $this->conn->query("SHOW TABLES LIKE 'bot_smart_chat_logs'");
        if (!$checkTable || $checkTable->num_rows === 0) return ['success' => false, 'message' => 'Table bot_smart_chat_logs not yet created.'];

        $sql = "SELECT * FROM chat_messages 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                  AND username NOT IN ('Cụ Giáo', 'Đại Gia Whale', 'Thánh Nổ Plinko', 'Bé Simp Lởm', 'Lão Triết Lý', 'Admin Tester Bot', 'Hệ Thống')
                  AND id NOT IN (SELECT target_message_id FROM bot_smart_chat_logs)
                ORDER BY id DESC LIMIT 15";

        $res = $this->conn->query($sql);
        if (!$res || $res->num_rows === 0) return ['success' => true, 'actions' => [], 'message' => 'No new context messages to process'];

        $actions = [];
        $botReplies = [];

        $rivalsFile = __DIR__ . '/sessions/rivalries.json';
        $allRivals = file_exists($rivalsFile) ? json_decode(file_get_contents($rivalsFile), true) : [];

        while ($row = $res->fetch_assoc()) {
            $msgId = (int)$row['id'];
            $author = $row['username'];
            $authorId = (int)$row['user_id'];
            $text = trim($row['message']);
            $lower = mb_strtolower($text);

            $selectedBot = null;
            $replyText = '';
            $detectedKeyword = '';
            $botMood = 'happy';
            $extraData = [];

            // --- THỬ GIẢI MÃ BẰNG REALTIME SYSTEM QUERY ENGINE ---
            $realtimeReply = $this->generateRealtimeResponse($text, $author);
            if ($realtimeReply) {
                $selectedBot = $this->botList[0]; // Cụ Giáo hoặc Bot Trợ Lý
                $replyText = $realtimeReply;
                $detectedKeyword = 'Realtime_System_Query';
            }

            // 0. Phân tích Kẻ Thù (Rivalry Interception - Ưu tiên tiếp theo)
            if (!$selectedBot && $authorId > 0) {
                foreach ($this->botList as $bot) {
                    if (isset($allRivals[$bot['id']][$authorId])) {
                        $enemy = $allRivals[$bot['id']][$authorId];
                        if ($enemy['amount_lost'] >= 50000 && rand(1, 100) <= 50) { // 50% cơ hội trigger Rivalry
                            $selectedBot = $bot;
                            $detectedKeyword = 'Rivalry_Trigger';
                            $extraData['amount'] = $enemy['amount_lost'];
                            break;
                        }
                    }
                }
            }

            // Nếu không phải Kẻ thù, chạy logic ngữ cảnh bình thường
            if (!$selectedBot) {
                // 1. Phân tích nhắc tên (@Mention)
                if (mb_strpos($lower, '@cụ giáo') !== false || mb_strpos($lower, 'cụ giáo') !== false) {
                    $selectedBot = $this->botList[0];
                    $detectedKeyword = 'Advice_Context';
                } elseif (mb_strpos($lower, '@whale') !== false || mb_strpos($lower, 'đại gia') !== false) {
                    $selectedBot = $this->botList[1];
                    $detectedKeyword = 'Advice_Context';
                } elseif (mb_strpos($lower, '@plinko') !== false || mb_strpos($lower, 'plinko') !== false) {
                    $selectedBot = $this->botList[2];
                    $detectedKeyword = 'Advice_Context';
                    $extraData['game_name'] = 'Plinko V2';
                }
                // 2. Phân tích ngữ cảnh Trúng Thưởng
                elseif (preg_match('/(nổ\s+hũ|húp|thắng\s+đậm|trúng|jackpot|được\s+lộc)/ui', $text)) {
                    $selectedBot = $this->botList[array_rand([0, 1, 2])];
                    $detectedKeyword = 'Win_Context';
                }
                // 3. Phân tích ngữ cảnh Thua
                elseif (preg_match('/(thua|cay|cháy|bay\s+màu|hết\s+gtlm|đen\s+quá|xa\s+bờ)/ui', $text)) {
                    $selectedBot = $this->botList[array_rand([0, 3, 4])];
                    $detectedKeyword = 'Lose_Context';
                }
                // 4. Phân tích ngữ cảnh hỏi game
                elseif (preg_match('/(chơi\s+gì|game\s+nào|kèo\s+nào|xin\s+kèo|tư\s+vấn)/ui', $text)) {
                    $selectedBot = $this->botList[array_rand($this->botList)];
                    $detectedKeyword = 'Advice_Context';
                }
            }

            // Ghi phản hồi vào CSDL
            if ($selectedBot && ($detectedKeyword !== '' || !empty($replyText))) {
                $botId = (int)$selectedBot['id'];
                $botUsername = $selectedBot['username'];
                $botAvatar = $selectedBot['avatar'];

                // --- ĐỌC MOOD & ÁP DỤNG CẢM XÚC VÀO CHAT ---
                $emailRes = $this->conn->query("SELECT Email FROM users WHERE Iduser = " . $botId);
                if ($emailRes && $emailRes->num_rows > 0) {
                    $bRow = $emailRes->fetch_assoc();
                    $stateFile = __DIR__ . '/sessions/' . md5($bRow['Email']) . '.state.json';
                    if (file_exists($stateFile)) {
                        $state = json_decode(file_get_contents($stateFile), true);
                        $botMood = $state['mood'] ?? 'happy';
                    }
                }

                // --- SINH CÂU BẰNG DYNAMIC DIALOGUE ENGINE ---
                $replyText = $this->generateDynamicResponse($detectedKeyword, $author, $botMood, $extraData);
                if (empty($replyText)) continue;

                // Gắn bộ lọc Mood formatting
                if ($botMood === 'tilted' || $botMood === 'angry') {
                    $replyText = mb_strtoupper($replyText, 'UTF-8');
                    $replyText = str_replace('!', '!!! 🤬', $replyText);
                    $replyText = "CÚT HẾT RA, ĐANG CAY! " . $replyText;
                } elseif ($botMood === 'excited') {
                    $replyText .= " 🤑 (Húp sướng quá, hưng phấn quá anh em ạ!)";
                } elseif ($botMood === 'depressed' || $botMood === 'sad') {
                    $replyText = "*(Buồn bã thở dài...)* " . mb_strtolower($replyText, 'UTF-8') . " 😞";
                }

                $replyText = $this->applyVocabulary($replyText);

                $stmtInsert = $this->conn->prepare("INSERT INTO chat_messages (user_id, username, message, avatar, created_at) VALUES (?, ?, ?, ?, NOW())");
                if ($stmtInsert) {
                    $stmtInsert->bind_param("isss", $botId, $botUsername, $replyText, $botAvatar);
                    $stmtInsert->execute();
                    $stmtInsert->close();

                    $stmtLog = $this->conn->prepare("INSERT INTO bot_smart_chat_logs (target_message_id, bot_user_id, bot_name, detected_keyword, bot_reply, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    if ($stmtLog) {
                        $stmtLog->bind_param("iisss", $msgId, $botId, $botUsername, $detectedKeyword, $replyText);
                        $stmtLog->execute();
                        $stmtLog->close();
                    }

                    $actions[] = "Bot <b>{$botUsername} (Mood: {$botMood})</b> phản hồi: \"{$replyText}\"";
                    $botReplies[] = ['target_author' => $author, 'bot_username' => $botUsername, 'reply' => $replyText, 'mood' => $botMood];
                }
            }
        }

        return [ 'success' => true, 'processed_count' => count($botReplies), 'actions' => $actions, 'replies' => $botReplies ];
    }
}
?>

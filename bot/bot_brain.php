<?php
/**
 * 🧠 Bot Brain v4.1 - Specialized Predictions
 */
class BotBrain {
    private $personalities = [
        'aggressive' => ['chat_style' => 'aggressive'],
        'shy' => ['chat_style' => 'shy'],
        'balanced' => ['chat_style' => 'balanced'],
        'trietly' => ['chat_style' => 'trietly'],
        'random' => ['chat_style' => 'random'],
        'simp' => ['chat_style' => 'simp'],
        'danchoi' => ['chat_style' => 'danchoi'],
        'trietly_nguoc' => ['chat_style' => 'trietly_nguoc'],
        'hambo' => ['chat_style' => 'hambo'],
        'cugia' => ['chat_style' => 'cugia'],
        'genalpha' => ['chat_style' => 'genalpha']
    ];

    public function getPersonality($userId) {
        $types = array_keys($this->personalities);
        return $types[$userId % count($types)];
    }

    public function getTimeKey() {
        $hour = (int)date('H');
        if ($hour >= 5 && $hour < 12) return 'time_morning';
        if ($hour >= 18 || $hour < 5) return 'time_night';
        return 'greet';
    }

    public function getDayKey() {
        $day = date('N'); // 1 (Monday) to 7 (Sunday)
        if ($day == 1) return 'monday';
        if ($day == 5) return 'friday';
        if ($day == 7) return 'sunday';
        return null;
    }

    /**
     * 🔮 Logic Dự đoán chuyên sâu theo từng loại game
     */
    public function generatePrediction($game) {
        $gameSpecific = [
            'Thiên Thần Ác Quỷ' => [
                "Địa trận này Ác Quỷ chắc chắn sẽ húp thế! 😈",
                "Thiên Thần đang được phù hộ, húp chắc rồi!",
                "Nhìn địa thế này, thả thính vào Thiên Thần là chuẩn bài."
            ],
            'Xì Dách Royale' => [
                "Tỉ thí này linh cảm sẽ được Ngũ Linh nè! 🃏",
                "Nhìn tay bài này là biết húp lớn rồi.",
                "Đừng dằn sớm, cứ kéo đi, vận may đang tới!"
            ],
            'Poker Texas' => [
                "All-in tỉ thí này đi, bài đẹp lắm! 🚀",
                "Đang có sảnh rồng trong tay, ai dám theo ra chiêu?",
                "Tỉ thí này chỉ cần ra chiêu nhẹ là tụi nó bay màu hết."
            ],
            'Baccarat Premium' => [
                "Player tỉ thí này chắc húp, cảm giác rõ lắm! 🎴",
                "Banker đang vào dây, cứ theo ra chiêu thôi.",
                "Trận địa này tui linh cảm về Hòa, thả thính nhẹ xem sao."
            ]
        ];

        $defaults = [
            "Tỉ thí này tui linh cảm húp chắc! 🍀",
            "Làm nhẹ tỉ thí này xem vận may đến đâu...",
            "Tui dự đoán kết quả sẽ cực kỳ bất ngờ! 🚀"
        ];

        $list = $gameSpecific[$game] ?? $defaults;
        return $list[array_rand($list)];
    }

    public function generateMessage($userId, $type, $data = [], $state = []) {
        $p = $this->getPersonality($userId);
        $style = $this->personalities[$p]['chat_style'];
        $dictionary = $this->loadChatFile($style);
        
        // 0. Bad Day Logic (Override regular messages)
        if (isset($state['is_bad_day']) && $state['is_bad_day'] && rand(1, 100) <= 40) {
            $badDayQuotes = [
                "Hôm nay là một ngày tồi tệ nhất đời nick luôn... 🥀",
                "Cảm giác như cả trận địa đang quay lưng lại với mình.",
                "Sao hôm nay đen quá vậy trời? Húp hụt hoài luôn.",
                "Chắc do nãy chưa xem ngày trước khi online trận địa rồi. 😔",
                "Trắng tay, bay màu, buồn quá không muốn ra chiêu nữa."
            ];
            return $badDayQuotes[array_rand($badDayQuotes)];
        }

        // 0.1 Low Population Logic
        if (isset($data['user_count']) && $data['user_count'] < 5 && rand(1, 100) <= 20) {
            return "Trận địa vắng vẻ quá, mình tỉ thí với bóng tối thôi! 🌌";
        }

        // 0.2 Catchphrase Logic (Randomly inject)
        if (rand(1, 100) <= 10 && isset($dictionary['catchphrase'])) {
            return $dictionary['catchphrase'][array_rand($dictionary['catchphrase'])];
        }

        // 0.3 Day of the week special
        $dayKey = $this->getDayKey();
        if ($dayKey && rand(1, 100) <= 15) {
            $dayMsgs = [
                'monday' => ["Thứ 2 là ngày đầu tuần, hứa húp thật nhiều nhưng toàn bay màu... 😩", "Lại là thứ 2, uể oải quá anh em ơi."],
                'friday' => ["Thứ 6 máu chảy về trận địa! Quẩy lên nào anh em! 🔥", "Cuối tuần tới nơi rồi, húp ngập mặt để đi quẩy thôi!"],
                'sunday' => ["Chủ nhật lười biếng quá, chỉ muốn nằm húp GTLM thôi. 😴", "Ngày nghỉ mà, cứ thong thả mà ra chiêu bác ạ."]
            ];
            return $dayMsgs[$dayKey][array_rand($dayMsgs[$dayKey])];
        }

        // 1. Keyword-based reactions
        if ($type === 'keyword' && isset($data['text'])) {
            $keywords = $dictionary['keywords'] ?? [];
            foreach ($keywords as $kw => $response) {
                if (stripos($data['text'], $kw) !== false) {
                    return $response;
                }
            }
            return null; // No keyword match
        }

        // 2. Memory-based mentions
        if (rand(1, 100) <= 30 && !empty($state['remembered_players']) && ($type === 'greet' || $type === 'trash_talk')) {
            $players = $state['remembered_players'];
            $name = $players[array_rand($players)];
            $msg = ($type === 'greet') ? "Lô {$name}, lại gặp bạn ở trận địa này rồi! 😊" : "Bác {$name} ra chiêu cẩn thận nha, đừng để bay màu sớm!";
            return $msg;
        }

        $list = $dictionary[$type] ?? ($this->loadChatFile('shy')[$type] ?? ["Đang tỉ thí tập trung..."]);
        if (empty($list)) $list = ["Đang tỉ thí..."];
        $msg = $list[array_rand($list)];

        // 3. Fanboy (Hambo) logic for Idol replacement
        if ($p === 'hambo' && isset($state['idol_name'])) {
            $msg = str_replace('{idol}', $state['idol_name'], $msg);
        }

        foreach ($data as $key => $val) {
            $msg = str_replace('{' . $key . '}', $val, $msg);
        }
        return $msg;
    }

    private function loadChatFile($style) {
        $path = __DIR__ . "/chat/{$style}.php";
        return file_exists($path) ? require $path : [];
    }
}


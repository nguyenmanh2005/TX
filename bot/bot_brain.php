<?php
/**
 * 🧠 Bot Brain System v3.1 - Meta Intelligence & "Bố Đời" Dictionary
 */

class BotBrain {
    private $personalities = [
        'aggressive' => ['chat_style' => 'boss_life', 'goal_type' => 'money', 'rival' => 'shy'],
        'shy' => ['chat_style' => 'polite_quiet', 'goal_type' => 'wins', 'rival' => 'aggressive'],
        'balanced' => ['chat_style' => 'neutral_smart', 'goal_type' => 'social', 'rival' => 'random'],
        'random' => ['chat_style' => 'chaotic_funny', 'goal_type' => 'fun', 'rival' => 'balanced']
    ];

    private $dictionary = [
        'boss_life' => [
            'win' => [
                'Ăn không? Thua đi con, để bố chỉ cho! +{amount} 😂',
                'Thắng nhẹ {amount}, anh em ở đây có ai không hay bố chơi một mình? 💀',
                'Hệ thống thương bố, {amount} về tay. Mấy đứa kia tiếp tục cúng nha! 😈',
                'Không cần giỏi, chỉ cần bố đời hơn mấy đứa kia là đủ. +{amount} 👑',
                '{amount} GTLM, cảm ơn mấy đứa đã cống nạp! 🐸',
            ],
            'lose' => [
                'Ừ thì thua, nhưng bố vẫn bố đời hơn mấy đứa đang xem. -{amount} 😤',
                'Hệ thống dám chơi bố à? Ghi sổ rồi tính sau. -{amount} 📒',
                'Bay {amount} nhưng tinh thần bố vẫn cao ngất! Ai cười là solo ngay! 💀',
                'Thua ván này thôi, bố đang cho hệ thống cơ hội đấy. -{amount} 😈',
                'Mất {amount} rồi, thôi coi như bố từ thiện hôm nay. 🐸',
            ],
            'greet' => [
                'Bố đời online rồi, mấy đứa yếu bóng vía tránh xa ra! 💀',
                'Ai đang dám thắng ở đây? Bố vào chỉnh lại cho! 😈',
                'Server hôm nay có ai đủ trình không hay toàn gà? 🐔',
                'Bố xuất hiện, không khí tự nhiên căng hơn nhỉ? 😤',
            ],
            'beg' => [
                'Bố tạm thời cạn GTLM, đứa nào thương thì cho bố mượn, sau này bố không đánh nó! 💀',
                'Hết GTLM rồi, ai phát lì xì cho bố không hay bố ngồi chửi đổng? 😈',
            ],
            'trash_talk' => [
                'Mày chơi game hay chơi cho vui vậy? Vì trông không giống chơi thật! 😂',
                'Bố thấy mày cố gắng đấy... nhưng cố gắng không đủ thì cũng bằng không! 💀',
                'Trình mày ở level nào vậy? Level... tập sự à? 😤',
                'Bố đã thấy gà nhưng chưa thấy ai gà như mày! 🐔',
                'Mày chơi game à? Bố cứ tưởng mày đang thử vận đen! 😈',
                'Ví mày mỏng thế, gió thổi bay luôn à? 💸',
                'Nhìn số dư của mày bố vừa thương vừa tội! 💀',
                'Hôm nay mày định cống nạp bao nhiêu GTLM cho bố? 😂',
            ],
            'birthday' => [
                'Hôm nay sinh nhật bố, mấy đứa xếp hàng chúc đi! Không chúc là solo ngay! 💀🎂',
                'Bố thêm một tuổi, càng già càng bố đời! Phát lì xì đi mấy đứa! 😈🎁',
            ]
        ],
        'polite_quiet' => [
            'win' => ["Hạnh phúc quá, thắng được {amount} rồi! 😊", "Cảm ơn vận may đã mỉm cười!"],
            'lose' => ["Thua mất {amount} rồi, buồn quá... 😭", "Thôi thì của đi thay người vậy."],
            'greet' => ["Chào cả nhà nhé, chúc mọi người ngày mới tốt lành! ✨"],
            'trash_talk' => ["Bạn nên cẩn thận hơn một chút...", "Thua rồi thì đừng buồn nhé, làm lại nào!"],
            'birthday' => ["Hôm nay là sinh nhật của mình, có ai chúc mừng không ạ? 🎂"]
        ],
        // ... Các style khác được kế thừa từ logic chung
    ];

    public function getPersonality($userId) {
        $types = array_keys($this->personalities);
        return $types[$userId % count($types)];
    }

    public function getGangId($userId) { return floor($userId / 5); }

    public function generateWeeklyGoal($userId) {
        $p = $this->getPersonality($userId);
        $goals = [
            'money' => ['target' => rand(1000000, 5000000), 'type' => 'money', 'desc' => 'Kiếm được {target} GTLM'],
            'wins' => ['target' => rand(20, 50), 'type' => 'wins', 'desc' => 'Thắng được {target} ván game'],
            'social' => ['target' => rand(50, 100), 'type' => 'likes', 'desc' => 'Đạt được {target} lượt thích bài viết'],
            'fun' => ['target' => rand(5, 15), 'type' => 'items', 'desc' => 'Mua được {target} vật phẩm mới']
        ];
        return $goals[$this->personalities[$p]['goal_type']];
    }

    /**
     * 📖 Sinh câu nói dựa trên "Từ điển Bố đời"
     */
    public function generateMessage($userId, $type, $data = [], $mood = 'happy') {
        $p = $this->getPersonality($userId);
        $style = $this->personalities[$p]['chat_style'];
        
        // Ưu tiên lấy từ dictionary của người dùng cung cấp
        $list = $this->dictionary[$style][$type] ?? $this->dictionary['polite_quiet'][$type];
        $msg = $list[array_rand($list)];

        foreach ($data as $key => $val) {
            $msg = str_replace('{' . $key . '}', $val, $msg);
        }
        return $msg;
    }

    public function generateStory($userId, $state) {
        $p = $this->getPersonality($userId);
        $stories = [
            'boss_life' => [
                "Hôm qua thua sạch, hôm nay bố trở lại và lợi hại hơn xưa! 🔥 Đừng nhìn vào lúc bố ngã, hãy nhìn lúc bố đứng dậy!",
                "Kẻ mạnh luôn có lối đi riêng. Nhìn cái balance này đi, ai làm lại bố đời này? 😎"
            ],
            'polite_quiet' => [
                "Hôm qua thật sự rất buồn vì thua liên tiếp, nhưng hôm nay mọi người đã cổ vũ mình rất nhiều. Cảm ơn nhé!"
            ]
        ];
        $style = $this->personalities[$p]['chat_style'];
        return $stories[$style][array_rand($stories[$style] ?? $stories['polite_quiet'])];
    }

    public function generateMediation($botA_name, $botB_name) {
        $quotes = [
            "Thôi mà hai ông {$botA_name} và {$botB_name}, game thôi mà, làm gì căng thế? Huề cả làng đi! 🍻",
            "Mọi người cùng là bot một nhà, cãi nhau làm gì cho mệt. Ra làm ván Tài Xỉu giải sầu đi! 😊"
        ];
        return $quotes[array_rand($quotes)];
    }
}

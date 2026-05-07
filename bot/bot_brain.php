<?php
/**
 * 🧠 Bot Brain v4.1 - Specialized Predictions
 */
class BotBrain {
    private $personalities = [
        'aggressive' => ['chat_style' => 'aggressive'],
        'shy' => ['chat_style' => 'shy'],
        'balanced' => ['chat_style' => 'balanced'],
        'random' => ['chat_style' => 'random']
    ];

    public function getPersonality($userId) {
        $types = array_keys($this->personalities);
        return $types[$userId % count($types)];
    }

    /**
     * 🔮 Logic Dự đoán chuyên sâu theo từng loại game
     */
    public function generatePrediction($game) {
        $gameSpecific = [
            'Thiên Thần Ác Quỷ' => [
                "Địa trận này Ác Quỷ chắc chắn sẽ thắng thế! 😈",
                "Thiên Thần đang được phù hộ, húp chắc rồi!",
                "Nhìn địa thế này, đặt vào Thiên Thần là chuẩn bài."
            ],
            'Xì Dách Royale' => [
                "Ván này linh cảm sẽ được Ngũ Linh nè! 🃏",
                "Nhìn tay bài này là biết húp lớn rồi.",
                "Đừng dằn sớm, cứ kéo đi, vận may đang tới!"
            ],
            'Poker Texas' => [
                "All-in ván này đi, bài đẹp lắm! 🚀",
                "Đang có sảnh rồng trong tay, ai dám theo?",
                "Ván này chỉ cần tố nhẹ là tụi nó sợ chạy hết."
            ],
            'Baccarat Premium' => [
                "Player ván này chắc thắng, cảm giác rõ lắm! 🎴",
                "Banker đang vào dây, cứ theo thôi.",
                "Trận này tui linh cảm về Hòa, đặt nhẹ xem sao."
            ]
        ];

        $defaults = [
            "Trận này tui linh cảm húp chắc! 🍀",
            "Làm nhẹ ván này xem vận may đến đâu...",
            "Tui dự đoán kết quả sẽ cực kỳ bất ngờ! 🚀"
        ];

        $list = $gameSpecific[$game] ?? $defaults;
        return $list[array_rand($list)];
    }

    public function generateMessage($userId, $type, $data = [], $mood = 'happy') {
        $p = $this->getPersonality($userId);
        $style = $this->personalities[$p]['chat_style'];
        $dictionary = $this->loadChatFile($style);
        
        $list = $dictionary[$type] ?? ($this->loadChatFile('shy')[$type] ?? ["Đang tập trung chơi..."]);
        $msg = $list[array_rand($list)];

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

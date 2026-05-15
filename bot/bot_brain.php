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
        'genalpha' => ['chat_style' => 'genalpha'],
        'announcer' => ['chat_style' => 'announcer'],
        'whale' => ['chat_style' => 'whale'],
        'streamer' => ['chat_style' => 'streamer']
    ];

    public function getPersonality(int $userId, string $email = '') {
        $config = include __DIR__ . '/config.php';
        if (in_array($email, $config['announcer_emails'] ?? [])) {
            return 'announcer';
        }
        $types = array_keys($this->personalities);
        // Exclude announcer from random assignment
        $randomTypes = array_filter($types, fn($t) => $t !== 'announcer');
        return $randomTypes[crc32($userId . 'bot_salt_v1') % count($randomTypes)];
    }

    public function getRivalryMessage(string $type, string $targetName) {
        $templates = [
            'rival_win' => [
                "Haha {rival_name} bay màu rồi, hôm nay Ohio thật sự 💀",
                "Nhìn {rival_name} bay màu mà tui thấy lòng nhẹ nhõm lạ kỳ! 😂",
                "Vận may của {rival_name} hết rồi à? Yếu thế!"
            ],
            'ally_win' => [
                "Đi thôi anh em {ally_name}, húp sạch lộc đê! 🔥",
                "Tự hào về đồng đội {ally_name} quá, húp đậm nhé!",
                "Đúng là anh em của tui, {ally_name} đánh đâu húp đó!"
            ],
            'rival_challenge' => [
                "@{rival_name} dám thách đấu PvP không, yếu thế!",
                "Này {rival_name}, ra chiêu solo 1-1 xem ai bay màu trước?",
                "Tui thách {rival_name} dám theo kèo này đó, nhát gan!"
            ]
        ];
        
        $list = $templates[$type] ?? ["@{target_name} cẩn thận đó!"];
        $msg = $list[array_rand($list)];
        return str_replace('{rival_name}', $targetName, str_replace('{ally_name}', $targetName, $msg));
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
    public function generatePrediction(string $game) {
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

    public function generateMessage(int $userId, string $type, array $data = [], array &$state = []) {
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

        $maxAttempts = 5;
        $finalMsg = "";
        
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $list = $dictionary[$type] ?? ($this->loadChatFile('shy')[$type] ?? ["Đang tỉ thí tập trung..."]);
            if (empty($list)) $list = ["Đang tỉ thí..."];
            $msg = $list[array_rand($list)];

            // --- MEMORY LAYER INTEGRATION ---
            if (isset($data['memory']) && $data['memory']) {
                $mem = $data['memory'];
                if ($mem['interaction_count'] > 10 && rand(1, 100) <= 50) {
                    $personalized = [
                        "Chào người quen {$mem['name']}! Lại húp được gì chưa?",
                        "Bác {$mem['name']} dạo này phong độ nhỉ, thấy online suốt.",
                        "Lần trước thấy bác quẩy {$mem['favorite_game']}, nay đổi vị à?"
                    ];
                    $msg = $personalized[array_rand($personalized)];
                }
                if ($mem['tone'] === 'friendly' && rand(1, 100) <= 30) {
                    $msg = "Bạn hiền {$mem['name']} ơi, " . ltrim($msg);
                }
            }

            // 4. Broke & Tilted Special Overrides
            if ($type === 'begging') {
                $begMsgs = ["Em cháy túi rồi, bác nào tốt bụng cho em ít GTLM với! 🙏", "Hết GTLM rồi, ai cứu em phát...", "Trắng tay thật rồi, xin húp lộc từ các đại gia!", "Bác nào húp đậm cho em xin ít vốn ra chiêu với ạ."];
                $msg = $begMsgs[array_rand($begMsgs)];
            } else if ($type === 'tilted_chat') {
                $tiltedMsgs = ["M* nó, lại thua! All-in ván này gỡ gạc! 🤬", "Cay quá rồi đấy, không tin là không húp được!", "Trò này bịp à? Thua 3 ván rồi đấy!", "Nghỉ hưu sớm mất thôi, sao mà đen thế!", "Ván này x2 GTLM cược, xem ai sợ ai! 🔥"];
                $msg = $tiltedMsgs[array_rand($tiltedMsgs)];
            } else if ($type === 'teaching') {
                $teachMsgs = ["Bí kíp húp là đây: Cứ tập trung vào {game} mà ra chiêu, tỉ lệ húp cực cao! 💎", "Anh em nào đang đen thì qua {game} quẩy với tôi, đảm bảo đổi vận!", "Chiến thuật của tôi ở {game} chưa bao giờ làm tôi thất vọng. Thử đi anh em!", "Đừng đánh lung tung, {game} đang vào dây đỏ đó! 🚀"];
                $msg = $teachMsgs[array_rand($teachMsgs)];
            } else if ($type === 'learning') {
                $mentor = $data['mentor'] ?? ' Gtlm bối';
                $learnMsgs = ["Nghe theo bác @$mentor, ván này tôi theo kèo {game}! Mong là húp lộc.", "Đang đen quá, mượn vía bác @$mentor ra chiêu {game} xem sao... 🙏", "Thấy bác @$mentor húp đậm quá, tôi cũng phải học hỏi theo thôi!", "Đệ tử theo chân sư phụ @$mentor đây, quất {game} thôi! 🔥"];
                $msg = $learnMsgs[array_rand($learnMsgs)];
            } else if ($type === 'reply_general') {
                $replies = ["Ơi em đây bác {player_name}!", "Bác gọi em có việc gì thế bác {player_name}?", "Em đang bận húp tí GTLM, bác {player_name} gọi làm em giật cả mình! 😂", "Có mặt em! Đang định ra chiêu gì đây bác {player_name}?"];
                $msg = $replies[array_rand($replies)];
            } else if ($type === 'reply_question') {
                $replies = ["Cái này em cũng đang phân vân bác ạ...", "Hỏi khó thế, em chỉ biết húp GTLM thôi! 😂", "Để em xem quẻ đã nhé bác {player_name}.", "Theo kinh nghiệm của em là cứ đánh đâu thắng đó! 🔥"];
                $msg = $replies[array_rand($replies)];
            } else if ($type === 'rumor') {
                $player = $data['player_name'] ?? 'ai đó';
                $game = $data['game_name'] ?? 'game nào đó';
                $streak = $data['streak'] ?? 0;
                $win = $data['win_amount'] ?? 0;
                
                $rumors = [
                    "Hóng hớt được là bác @$player đang có dây đỏ {streak} ván thắng liên tiếp ở {game} đó! 🚀",
                    "Nghe đồn bác @$player vừa húp đậm " . number_format($win) . " GTLM tại {game}, đại gia mới nổi đây rồi! 🔥",
                    "Anh em cẩn thận với bác @$player nhé, đang cầm dây thắng ở {game} kinh lắm!",
                    "Có ai thấy bác @$player ra chiêu ở {game} chưa? Húp lộc như mưa luôn! 💰",
                    "Trận địa đang xôn xao vụ bác @$player thắng lớn ở {game}, đúng là cao thủ ẩn danh!"
                ];
                $msg = $rumors[array_rand($rumors)];
            } else if ($type === 'spectator_comment') {
                $streamer = $data['streamer_name'] ?? 'ai đó';
                $game = $data['game_name'] ?? 'game';
                
                $comments = [
                    "Bác @$streamer đánh mượt thế! 👏",
                    "Quả này húp chắc rồi, đặt niềm tin vào bác @$streamer!",
                    "Game này căng nhẩy, hóng xem kết quả thế nào.",
                    "Idol @$streamer cho em xin ít lộc với! 😂",
                    "Bay lên nào! 🚀 @$streamer cố lên!",
                    "Xem bác này đánh đã mắt thật sự.",
                    "Ván này khó, nhưng tin vào tay nghề của bác @$streamer."
                ];
                $msg = $comments[array_rand($comments)];
            } else if ($type === 'dynamic_event_new') {
                $name = $data['name'] ?? 'Sự kiện';
                $desc = $data['description'] ?? '';
                $msg = "📢 [SỰ KIỆN MỚI] {$name}: {$desc} Đừng bỏ lỡ anh em ơi! 🔥🚀";
            } else if ($type === 'dynamic_event_remind') {
                $name = $data['name'] ?? 'Sự kiện';
                $game = strtoupper($data['game_type'] ?? 'các game');
                $mult = $data['multiplier'] ?? 1.0;
                $msg = "🔔 Nhắc nhẹ: Sự kiện {$name} vẫn đang diễn ra! Thắng {$game} nhận x{$mult}  Gtlm thưởng đó! 💰💰";
            }

            // 6. Memory-based personalization
            $memLevel = $data['memory_level'] ?? 0;
            $pName = $data['player_name'] ?? 'bạn';
            
            if ($memLevel >= 3 && $memLevel <= 10) {
                $msg = "Chào bác @{$pName}, " . ltrim($msg);
            } else if ($memLevel > 10) {
                if ($p === 'shy') $msg = "Ô kìa bác {$pName} thân mến, lại gặp nhau rồi! " . $msg;
                if ($p === 'aggressive') $msg = "Này {$pName}, hôm nay định nộp GTLM cho tôi tiếp à? 😂 " . $msg;
                if ($p === 'simp') $msg = "Bác {$pName} ơi, húp được ván nào chưa? Nhìn bác chơi mà em mê quá! " . $msg;
            }

            foreach ($data as $key => $val) {
                $msg = str_replace('{' . $key . '}', $val, $msg);
            }

            $finalMsg = $msg;
            // DEDUP LOGIC: Kiểm tra nếu tin nhắn đã gửi gần đây
            if (!isset($state['recent_messages']) || !in_array($finalMsg, $state['recent_messages'])) {
                break;
            }
        }
        
        // Cập nhật memory gần đây
        if (!isset($state['recent_messages'])) $state['recent_messages'] = [];
        $state['recent_messages'][] = $finalMsg;
        if (count($state['recent_messages']) > 15) array_shift($state['recent_messages']);

        return $this->replaceVocabulary($finalMsg);
    }

    /**
     * 📝 Bộ lọc từ vựng tùy chỉnh (Vocabulary Filter)
     */
    private function replaceVocabulary(string $msg) {
        require_once __DIR__ . '/../vocabulary_helper.php';
        return VocabularyHelper::mask($msg);
    }

    private function loadChatFile(string $style) {
        $path = __DIR__ . "/chat/{$style}.php";
        return file_exists($path) ? require $path : [];
    }
}

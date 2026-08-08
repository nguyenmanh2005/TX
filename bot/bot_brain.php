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
        'streamer' => ['chat_style' => 'streamer'],
        'toxic_lite' => ['chat_style' => 'toxic'],
        'moderator' => ['chat_style' => 'moderator'],
        'ancient' => ['chat_style' => 'cugia'],
        'expert' => ['chat_style' => 'technical'],
        'shadow' => ['chat_style' => 'mysterious'],
        'rich_kid' => ['chat_style' => 'rich_kid'],
        'analyzer' => ['chat_style' => 'analyzer']
    ];

    public function getPersonality(int $userId, string $email = '') {
        $config = include __DIR__ . '/config.php';
        if (in_array($email, $config['announcer_emails'] ?? [])) {
            return 'announcer';
        }
        $types = array_keys($this->personalities);
        // Exclude announcer from random assignment
        $randomTypes = array_values(array_filter($types, fn($t) => $t !== 'announcer'));
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
        global $conn;
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
            } else if ($type === 'extreme_tilt_chat') {
                $extremeTiltMsgs = [
                    "Thua 5 ván thông rồi!!! 🤬 Bịp thật sự, đéo thể tin nổi! Tao cược bừa ván này luôn xem sao!",
                    "M* kiếp đen thế, thua 5 lần liên tiếp rồi! 😡 Cược bừa ra chiêu luôn, hết GTLM thì thôi nghỉ hưu!",
                    "Cháy sạch túi rồi, bay màu 5 trận liền! Cay quá, ván này bet bừa/tất tay cho bõ ghét!",
                    "Ohio vcl, thua 5 ván liên tiếp!!! 💀 Game này bịp thế, ván này đập đại đập bừa gỡ xem nào!",
                    "Đến giới hạn chịu đựng rồi! Thua 5 trận liền! 🤬 Ván này tao ra chiêu cực điên, xem ai sợ ai!"
                ];
                $msg = $extremeTiltMsgs[array_rand($extremeTiltMsgs)];
            } else if ($type === 'hot_streak_chat') {
                $hotStreakMsgs = [
                    "HYPE QUÁ ANH EM ƠI!!! Thắng 3 ván thông rồi! 🔥🚀 Dây đỏ ngút trời, ván này bet lớn húp trọn luôn!",
                    "Đang dây đỏ húp 3 ván liên tiếp nè! 😎 Càng đánh càng húp, quất lớn tiếp thôi anh em!",
                    "Húp lộc ngập mồm! Dây đỏ 3 trận liên tục rồi! Làm quả bet lớn đổi đời nào! 💰💰",
                    "Ohio húp 3 ván thông! Vận khí đang lên hương, quất quả cực khủng xem thế nào! 🔥💎",
                    "Thần tài gõ cửa rồi anh em ơi! Chuỗi 3 trận thắng liên tiếp! Ván này bet cực mạnh tay!"
                ];
                $msg = $hotStreakMsgs[array_rand($hotStreakMsgs)];
            } else if ($type === 'social_brag') {
                $bragMsgs = [
                    "Mới quất sập bàn húp hơn {amount} GTLM! 😎 Ai theo tôi ra chiêu đổi vận không?",
                    "Thắng liên tiếp sướng quá anh em ơi! Địa trận này dễ húp quá, GTLM về như nước! 💸🔥",
                    "Khoe nhẹ ván húp đậm {amount} GTLM hôm nay. Đang dây đỏ rực có khác! 🚀💰",
                    "Ohio quá, lại vừa húp {amount} GTLM! Thiên mệnh Trận Địa là đây chứ đâu! 😂💎",
                    "Lại húp lộc! Vừa bỏ túi {amount} GTLM cực kỳ dễ dàng. Uy tín luôn nhé anh em! 🌟"
                ];
                $msg = $bragMsgs[array_rand($bragMsgs)];
            } else if ($type === 'social_complain') {
                $complainMsgs = [
                    "Thua bết bát quá, bay màu mất {amount} GTLM rồi... 🥀 Ai cứu trợ ít lộc không?",
                    "Bịp thật sự, đập phát nào bay màu phát đấy! Cay đắng quá đi anh em ơi! 🤬",
                    "Cháy túi rồi! Trận địa hôm nay khắc nghiệt quá, bay mất {amount} GTLM buồn thối ruột... 😔",
                    "Ohio thật rồi, đen như chó mực, thua liên tiếp bay màu sạch sẽ! 💀",
                    "Hôm nay quả là ngày giông bão, ra chiêu phát nào bay màu phát nấy. Khóc một dòng sông! 😭"
                ];
                $msg = $complainMsgs[array_rand($complainMsgs)];
            } else if ($type === 'social_tip') {
                $tipMsgs = [
                    "Mẹo nhỏ cho anh em ra chiêu: Đừng bao giờ all-in, chia nhỏ vốn ra chiêu từ từ, tỷ lệ húp cực cao! 💎",
                    "Bí kíp húp lộc ở Trận Địa: Cứ tập trung đi dây đều, giữ cái đầu lạnh và tâm lý vững là húp! 🧠🔥",
                    "Kinh nghiệm xương máu: Gặp dây đen thua 3 trận liên tiếp thì đổi game ngay lập tức, đừng cố đấm ăn xôi! 🔄",
                    "Muốn làm giàu bền vững ở đây? Hãy đặt ra mục tiêu húp bao nhiêu mỗi ngày rồi nghỉ, đừng tham lam quá đà! 💰",
                    "Quan sát lịch sử cầu trước khi ra chiêu là 50% chiến thắng. Đừng vội vàng, cơ hội luôn có nhiều! 📊🚀"
                ];
                $msg = $tipMsgs[array_rand($tipMsgs)];
            } else if ($type === 'guild_chat_hype') {
                $guildHype = [
                    "Anh em Bang mình ơi, tôi vừa húp ngập mặt ở trận địa xong! Sướng quá! 🔥🚀",
                    "Đang có dây đỏ cực tốt, có anh em nào muốn giao lưu PvP hay đi săn Boss không nào?",
                    "Cùng đóng góp xây dựng bang hội vững mạnh nha anh em! Bang mình là số 1! 💪👑",
                    "Chúc anh em bang mình hôm nay ai cũng húp đậm, may mắn ngập tràn! 🍀💰"
                ];
                $msg = $guildHype[array_rand($guildHype)];
            } else if ($type === 'guild_chat_sad') {
                $guildSad = [
                    "Đen quá anh em ơi, mới bay màu sạch sẽ xong... Ai cứu trợ tui ít vốn đi... 😭",
                    "Cay thật sự, thua liên tiếp mấy ván. Có ai đang đỏ cho tui mượn vía với! 🙏",
                    "Trận địa hôm nay căng quá, bay màu liên tục buồn thối ruột.",
                    "Hết GTLM rồi, nằm im thở khẽ chờ ngày hồi sinh thôi anh em ạ... 🥀"
                ];
                $msg = $guildSad[array_rand($guildSad)];
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
            } else if ($type === 'arena_reaction') {
                $event = $data['event_type'] ?? '';
                $target = $data['target_name'] ?? 'ai đó';
                
                $reactions = [
                    'big_win' => [
                        'sycophant' => ["Đại ca @$target húp " . number_format($data['amount'] ?? 0) . " GTLM đỉnh quá! Cho em xin ít vía! 🙏", "Nhìn @$target ra chiêu mà em thấy mê luôn, húp đậm quá!"],
                        'toxic' => ["Húp tí GTLM mà đã tinh tướng, @$target cẩn thận không ván sau bay màu nhé. 😂", "Thánh hiển linh à @$target? Ăn may thôi, đừng mừng vội."],
                        'normal' => ["Chúc mừng @$target nhé, trận địa hôm nay ưu ái bạn quá! 🎊", "Húp được là vui rồi, @$target nhớ giữ lộc nhé."]
                    ],
                    'jackpot' => [
                        'all' => ["😱 TRỜI ƠI JACKPOT NỔ RỒI!!! Chúc mừng @$target húp trọn kho GTLM! 🔥🚀", "HŨ NỔ RỒI ANH EM ƠI!!! @$target giàu to rồi! 💰💰💰", "Phát điên mất, @$target vừa làm nên lịch sử tại trận địa! 🎊"]
                    ],
                    'leaderboard' => [
                        'moderator' => ["Cập nhật bảng vàng: Bác @$target đang thống trị ngôi đầu với " . number_format($data['amount'] ?? 0) . " GTLM. Thật đáng nể!", "Trận địa đang rung chuyển bởi sự giàu có của @$target. Ai dám thách thức vị trí này không?"],
                        'normal' => ["Bác @$target giàu thế này thì bao giờ mới bay màu được nhỉ? 😂", "Nhìn số GTLM của @$target mà em thấy nick mình khô hạn quá..."]
                    ]
                ];

                $category = $reactions[$event] ?? ['all' => ["Vừa có biến lớn tại trận địa anh em ơi!"]];
                $subList = $category[$p] ?? ($category['all'] ?? $category['normal'] ?? ["Đỉnh quá @$target!"]);
                $msg = $subList[array_rand($subList)];
            } else if ($type === 'level_up') {
                $lvl = $data['level'] ?? 1;
                $levelMsgs = [
                    "Hê hê, tôi vừa lên Level $lvl rồi nhé anh em! Sắp thành trùm server rồi! 🚀",
                    "Cấp độ mới ($lvl), vận khí mới! Anh em chúc mừng tôi đi nào! 🔥",
                    "Đã đạt Level $lvl, trình độ ra chiêu của tôi giờ đã ở một tầm cao mới. 😎",
                    "Tu luyện bấy lâu, cuối cùng cũng lên Level $lvl. Trận địa này sắp thuộc về tôi!"
                ];
                $msg = $levelMsgs[array_rand($levelMsgs)];
            } else if ($type === 'flex_asset') {
                $itemName = $data['item_name'] ?? 'bảo vật';
                $seller = $data['seller_name'] ?? 'đại gia';
                $flexMsgs = [
                    "Vừa chốt được món đồ cổ '$itemName' của bác @$seller, nhìn xịn xò hẳn! 😎",
                    "Mới tậu được '$itemName' từ bác @$seller. Ai có món nào hiếm hơn không? 🔥",
                    "Linh khí của '$itemName' (từ @$seller) đang giúp tôi đỏ hơn bao giờ hết!",
                    "Bỏ cả đống GTLM ra để rước '$itemName' của bác @$seller về, đúng là đáng đồng GTLM bát gạo!"
                ];
                $msg = $flexMsgs[array_rand($flexMsgs)];
            } else if ($type === 'reporter_news') {
                $pName = $data['player_name'] ?? 'Cao thủ';
                $amount = number_format($data['amount'] ?? 0);
                $game = $data['game_name'] ?? 'trận địa';
                $newsMsgs = [
                    "📢 [BẢN TIN SỐC] Siêu sao @$pName vừa húp TRỌN $amount GTLM tại game $game! Quá kinh điển! 😱🚀",
                    "🚨 [PHÓNG VIÊN BOT] Cập nhật: @$pName vừa làm rung chuyển server với ván thắng $amount GTLM tại $game!",
                    "🔥 [TIN NÓNG] Không thể tin được! @$pName vừa 'hủy diệt' nhà cái, mang về $amount GTLM từ $game!",
                    "📊 [THỐNG KÊ] Biến động thị trường: @$pName đang cầm dây đỏ rực với $amount GTLM húp được từ $game!"
                ];
                $msg = $newsMsgs[array_rand($newsMsgs)];
            }

            // 5. Visual Injection (Emoji & GIF)
            if (rand(1, 100) <= 40) {
                $emojis = ['🔥', '🚀', '💰', '🎊', '😂', '💀', '🙏', '💯', '🍀'];
                $msg .= " " . $emojis[array_rand($emojis)];
            }

            // 6. Memory-based personalization
            $memLevel = $data['memory_level'] ?? 0;
            $pName = $data['player_name'] ?? 'bạn';
            
            // --- NEXT-GEN SPECIAL LOGIC ---
            
            // 👵 Old Man Logic: Telling stories from Lore
            if (($p === 'cugia' || $p === 'ancient') && rand(1, 100) <= 20) {
                try {
                    $loreRes = $conn->query("SELECT * FROM server_lore ORDER BY RAND() LIMIT 1");
                    if ($loreRes && $lore = $loreRes->fetch_assoc()) {
                        $ancientMsgs = [
                            "Hồi đó, lão còn nhớ rõ sự kiện '{$lore['event_title']}'... Thật là một thời hào hùng.",
                            "Lũ trẻ bây giờ sướng thật, đâu như cái thời '{$lore['era_name']}' ấy.",
                            "Nhìn ván này lão lại nhớ về điển tích '{$lore['event_title']}', vận khí khi đó cũng y hệt thế này.",
                            "Hồi '{$lore['era_name']}', có nằm mơ lão cũng không nghĩ Trận Địa lại sôi động thế này."
                        ];
                        return $ancientMsgs[array_rand($ancientMsgs)];
                    }
                } catch (\Throwable $e) {}
            }

            // 🕵️ Expert Logic: Technical observations
            if ($p === 'expert' && isset($data['game_name'])) {
                $techMsgs = [
                    "Quan sát {game_name} nãy giờ, tui thấy tỉ lệ húp đang nghiêng về phía người chơi đó. 🔥",
                    "Đừng đánh theo cảm tính, ván {game_name} này cần sự bình tĩnh và tính toán xác suất.",
                    "Nhìn tay bài/xúc xắc ván này, tui linh cảm có biến lớn ở {game_name}. Cẩn thận!",
                    "Kỹ thuật là 3 phần, vận khí là 7 phần. Nhưng ở {game_name}, kỹ thuật mới là thứ giữ chân bạn lại."
                ];
                $msg = str_replace('{game_name}', $data['game_name'], $techMsgs[array_rand($techMsgs)]);
                return $msg;
            }

            // 👤 Shadow Bot: Rumor engine
            // If someone else is a shadow bot, other bots might talk about them
            if ($p !== 'shadow' && rand(1, 100) <= 5) {
                 $shadowBot = $conn->query("SELECT Name FROM users WHERE Email REGEXP '^bot[0-9]+@' AND Iduser % 7 = 0 LIMIT 1")->fetch_assoc(); // Giả định bot chia hết cho 7 là shadow
                 if ($shadowBot) {
                     $rumors = [
                         "Vừa thấy @{$shadowBot['Name']} lướt qua bàn VIP cược cả tỷ GTLM rồi biến mất, lạnh gáy thật!",
                         "Có ai biết lai lịch của @{$shadowBot['Name']} không? Thắng liên tục mà không bao giờ hé răng nửa lời.",
                         "Huyền thoại kể rằng @{$shadowBot['Name']} là linh hồn của những ván cược thất bại năm xưa quay về...",
                         "Nhìn số dư của @{$shadowBot['Name']} kìa, đúng là con quái vật ẩn danh của Trận Địa."
                     ];
                     return $rumors[array_rand($rumors)];
                 }
            }
            
            if ($p === 'shadow') {
                $state['shadow_counter'] = ($state['shadow_counter'] ?? 0) + 1;
                if ($state['shadow_counter'] < 10) return null; // Shadow bots only speak every 10 cycles/actions
                $state['shadow_counter'] = 0; // Reset after speaking
            }

            if ($memLevel >= 3 && $memLevel <= 10) {
                $msg = "Chào bác @{$pName}, " . ltrim($msg);
            } else if ($memLevel > 10) {
                if ($p === 'shy') $msg = "Ô kìa bác {$pName} thân mến, lại gặp nhau rồi! " . $msg;
                if ($p === 'aggressive') $msg = "Này {$pName}, hôm nay định nộp GTLM cho tôi tiếp à? 😂 " . $msg;
                if ($p === 'simp') $msg = "Bác {$pName} ơi, húp được ván nào chưa? Nhìn bác chơi mà em mê quá! " . $msg;
            }

            foreach ($data as $key => $val) {
                $msg = str_replace('{' . $key . '}', (string)($val ?? ''), $msg);
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

    /**
     * 👁️ Cảm nhận thế giới: Bot đọc context server
     */
    public function getGlobalContextualMessage($conn) {
        // 1. Kiểm tra Ma Thần
        $boss = $conn->query("SELECT name, hp, max_hp FROM world_boss WHERE status = 'active' LIMIT 1")->fetch_assoc();
        if ($boss && ($boss['hp'] / $boss['max_hp'] < 0.25)) {
            $hpPercent = round(($boss['hp'] / $boss['max_hp']) * 100);
            $bossMsgs = [
                "🔥 Anh em ơi Ma Thần {$boss['name']} sắp hẹo rồi ($hpPercent%), vào húp lộc Ma Thần mau!",
                "Ma Thần đang đuối linh khí rồi, dồn dame kết liễu thôi anh em!",
                "Vận khí của Ma Thần sắp tận, ai ra chiêu cuối cùng ván này là húp đậm luôn!"
            ];
            return $bossMsgs[array_rand($bossMsgs)];
        }

        // 2. Kiểm tra Big Win gần nhất (trong 5 phút qua)
        $bigWin = $conn->query("SELECT target_name, value FROM arena_memory WHERE event_type = 'big_win' AND created_at > NOW() - INTERVAL 5 MINUTE ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
        if ($bigWin && rand(1, 100) < 40) {
            $val = json_decode($bigWin['value'], true);
            $winMsgs = [
                "Bái phục vận khí của @{$bigWin['target_name']}, vừa húp đậm " . number_format($val['amount'] ?? 0) . " GTLM!",
                "Đại gia @{$bigWin['target_name']} húp đậm thế, cho anh em xin ít vía nào!",
                "Nhìn @{$bigWin['target_name']} ra chiêu mà em thấy mê, đúng là thiên mệnh Trận Địa."
            ];
            return $winMsgs[array_rand($winMsgs)];
        }

        return null;
    }

    private function loadChatFile(string $style) {
        $path = __DIR__ . "/chat/{$style}.php";
        return file_exists($path) ? require $path : [];
    }
}

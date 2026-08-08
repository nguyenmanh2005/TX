<?php
/**
 * Bot Mining Tycoon Automation
 * Chức năng: Tự động thu hoạch, tự động nâng cấp mỏ, tự động cướp mỏ của người chơi khác.
 */

function handleMiningTycoonBot($conn, $baseUrl, $cFile, $botMoney) {
    // 1. Lấy thông tin Khu Mỏ
    $info = executeBotAction($baseUrl . "/api_mining.php?action=info", null, $cFile);
    if (!isset($info['success']) || !$info['success']) return;

    // 2. Tự động thu hoạch nếu có kha khá GTLM (Tránh spam API)
    if ($info['total_accumulated'] > 50000) {
        $claimRes = executeBotAction($baseUrl . "/api_mining.php?action=claim_all", null, $cFile);
        if (isset($claimRes['success']) && $claimRes['success']) {
            uiLog('⛏️', 'Bot thu hoạch Mỏ: <span class="highlight-money">+' . number_format($claimRes['claimed']) . '</span> GTLM');
            $botMoney = (float)str_replace(['.', ','], '', $claimRes['new_money']);
        }
    }

    // 3. Nâng cấp Kho chứa nếu GTLM nhiều hơn 100M (Tránh bị tràn)
    if ($info['storage_level'] == 1 && $botMoney > 150000000) {
        if (rand(1, 100) <= 20) { // 20% cơ hội mỗi cycle
            executeBotAction($baseUrl . "/api_mining.php?action=buy_storage", null, $cFile);
            uiLog('📦', 'Bot nâng cấp Kho Chứa lên 48 Giờ!');
        }
    }

    // 4. Mua & Nâng Cấp Khe Thợ Mỏ
    if ($botMoney > 500000) {
        foreach ($info['slots'] as $i => $slot) {
            if ($slot['empty']) {
                $cost = $info['config'][1]['cost'];
                if ($botMoney > ($cost * 2)) {
                    $upgRes = executeBotAction($baseUrl . "/api_mining.php?action=upgrade", ['slot' => $i, 'levels_to_add' => 1], $cFile);
                    if (isset($upgRes['success']) && $upgRes['success']) {
                        uiLog('👷', "Bot vừa mở Khe Thợ Mỏ số $i");
                        $botMoney -= $cost;
                    }
                }
            } else if ($slot['level'] < 10 && rand(1, 100) <= 10) {
                // Thỉnh thoảng bot sẽ tự nâng cấp nếu mỏ cấp thấp
                $nextLvlCost = $info['config'][$slot['level'] + 1]['cost'];
                if ($botMoney > ($nextLvlCost * 3)) {
                    executeBotAction($baseUrl . "/api_mining.php?action=upgrade", ['slot' => $i, 'levels_to_add' => 1], $cFile);
                    uiLog('⬆️', "Bot nâng cấp Thợ Mỏ số $i lên cấp " . ($slot['level'] + 1));
                    $botMoney -= $nextLvlCost;
                }
            }
        }
    }

    // 5. Tính năng Cướp Mỏ PVP (Raid)
    if (rand(1, 100) <= 30) { // 30% tỷ lệ check cướp mỏ mỗi cycle
        $raidList = executeBotAction($baseUrl . "/api_mining_pvp.php?action=vulnerable_list", null, $cFile);
        if (isset($raidList['success']) && $raidList['success'] && !empty($raidList['list'])) {
            // Random 1 mục tiêu
            $target = $raidList['list'][array_rand($raidList['list'])];
            
            $raidRes = executeBotAction($baseUrl . "/api_mining_pvp.php?action=raid", ['target_id' => $target['id']], $cFile);
            
            if (isset($raidRes['success']) && $raidRes['success']) {
                uiLog('🥷', 'Bot Đột nhập mỏ của <b>' . $target['name'] . '</b> thành công!');
                // Chat khịa (Tuân thủ văn phong Rule 5.3)
                if (rand(1, 100) <= 60) {
                    $messages = [
                        "Húp mỏ của " . $target['name'] . " ngon quá! Nick lười thế, để tôi thu hoạch giùm cho! 😂",
                        "Trời đất, mỏ nhà " . $target['name'] . " quên không thu hoạch à? Cảm ơn nhé, tôi húp trọn! 🤑",
                        "Đã Cướp xong mỏ của " . $target['name'] . ". Ai rảnh qua đó mà húp ké đi kìa anh em ơi! 🔥",
                        "Chủ nick " . $target['name'] . " đi ngủ à? GTLM rớt đầy đường thế này thì mình lụm nha! 💸"
                    ];
                    $msg = $messages[array_rand($messages)];
                    executeBotAction($baseUrl . "/chat.php", ['message' => $msg], $cFile);
                }
            } else {
                if (strpos($raidRes['message'] ?? '', 'Chó Canh Gác') !== false) {
                    uiLog('🐶', 'Bot bị chó cắn khi định đột nhập <b>' . $target['name'] . '</b>!');
                    // Chat than vãn
                    if (rand(1, 100) <= 60) {
                        $messages = [
                            "Đang rình mỏ " . $target['name'] . " mà bị chó cắn bay màu luôn, xui quá! 😭",
                            "Cay quá, tính qua nhà " . $target['name'] . " húp tí GTLM mà con chó dữ quá cắn rách quần! 🤬",
                            "Thằng " . $target['name'] . " nuôi chó khôn thế, tính húp trộm mà bị nó rượt chạy té khói! 🐕"
                        ];
                        $msg = $messages[array_rand($messages)];
                        executeBotAction($baseUrl . "/chat.php", ['message' => $msg], $cFile);
                    }
                }
            }
        }
    }
}

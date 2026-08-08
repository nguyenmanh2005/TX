<?php
/**
 * Farming Bot Logic
 * Handles automatic planting, harvesting, and seed purchasing for bots.
 */

function handleFarmBot($baseUrl, $cFile, $botMoney) {
    if ($botMoney < 2000) return null; // Cần tối thiểu một ít GTLM để làm nông

    $farmRes = executeBotAction($baseUrl . "/api_farm.php?action=get_farm", null, $cFile);
    
    if (!isset($farmRes['success']) || !$farmRes['success']) {
        return null; // Không lấy được thông tin farm
    }

    $plots = $farmRes['plots'] ?? [];
    $inventory = $farmRes['inventory'] ?? [];
    $now = strtotime($farmRes['now'] ?? date('Y-m-d H:i:s'));
    
    $actions = [];
    $hasReadyToHarvest = false;
    $emptyPlots = 0;

    // Phân tích các ô đất
    foreach ($plots as $plot) {
        if (!$plot['seed_code']) {
            $emptyPlots++;
        } else if (strtotime($plot['harvest_time']) <= $now) {
            $hasReadyToHarvest = true;
        }
    }

    // 1. Thu hoạch nếu có cây chín
    if ($hasReadyToHarvest) {
        $harvestRes = executeBotAction($baseUrl . "/api_farm.php", ['action' => 'harvest_all'], $cFile);
        if (isset($harvestRes['success']) && $harvestRes['success']) {
            $qty = $harvestRes['harvested'] ?? 0;
            $actions[] = "Đã thu hoạch thành công $qty lô nông sản!";
            $emptyPlots += $qty; // Thu hoạch xong sẽ có thêm đất trống
        }
    }

    // 2. Trồng cây và Mua giống nếu có đất trống
    if ($emptyPlots > 0) {
        // Tìm các hạt giống có sẵn trong kho
        $availableSeeds = [];
        $seedTypes = ['WHEAT', 'CORN', 'TOMATO', 'APPLE', 'WATERMELON', 'STRAWBERRY', 'GRAPE', 'PEACH', 'CHERRY', 'LEMON', 'BANANA', 'KIWI', 'MANGO', 'PINEAPPLE', 'COCONUT', 'MELON', 'ORANGE', 'AVOCADO', 'PEAR', 'POMEGRANATE'];
        
        foreach ($seedTypes as $code) {
            $col = 'seed_' . strtolower($code);
            if (isset($inventory[$col]) && $inventory[$col] > 0) {
                $availableSeeds[] = ['code' => $code, 'qty' => $inventory[$col]];
            }
        }

        // Nếu hết sạch hạt giống, tự động đi mua
        if (empty($availableSeeds)) {
            // Xác định loại hạt dựa trên ngân sách (dùng tối đa 10% ngân sách)
            $budget = $botMoney * 0.1;
            $chosenSeed = 'WHEAT'; // Mặc định rẻ nhất
            $prices = [
                'POMEGRANATE' => 2000000, 'PEAR' => 1200000, 'AVOCADO' => 800000, 
                'MELON' => 600000, 'ORANGE' => 450000, 'COCONUT' => 350000, 
                'PINEAPPLE' => 200000, 'PEACH' => 150000, 'MANGO' => 120000, 
                'KIWI' => 90000, 'GRAPE' => 70000, 'BANANA' => 45000, 
                'STRAWBERRY' => 30000, 'LEMON' => 20000, 'WATERMELON' => 15000, 
                'CHERRY' => 8000, 'APPLE' => 4000, 'TOMATO' => 1500, 
                'CORN' => 500, 'WHEAT' => 200
            ];

            // Chọn hạt đắt nhất trong khả năng
            foreach ($prices as $code => $price) {
                if ($budget >= ($price * $emptyPlots)) {
                    $chosenSeed = $code;
                    break;
                }
            }

            // Gọi API mua hạt giống
            $buyRes = executeBotAction($baseUrl . "/api_farm.php", [
                'action' => 'buy_item',
                'item' => $chosenSeed,
                'amount' => $emptyPlots
            ], $cFile);

            if (isset($buyRes['success']) && $buyRes['success']) {
                $actions[] = "Tự động mua $emptyPlots hạt giống $chosenSeed để canh tác.";
                $availableSeeds[] = ['code' => $chosenSeed, 'qty' => $emptyPlots];
            }
        }

        // Gieo trồng hạt giống đang có
        if (!empty($availableSeeds)) {
            $seedToPlant = $availableSeeds[array_rand($availableSeeds)]['code'];
            
            $plantRes = executeBotAction($baseUrl . "/api_farm.php", [
                'action' => 'plant_all',
                'seed_code' => $seedToPlant,
                'limit' => $emptyPlots
            ], $cFile);

            if (isset($plantRes['success']) && $plantRes['success']) {
                $plantedCount = $plantRes['planted'] ?? 0;
                $actions[] = "Đã gieo trồng $plantedCount cây $seedToPlant xuống đất!";
            }
        }
    }

    if (!empty($actions)) {
        return ['status' => 'success', 'actions' => $actions];
    }

    return null;
}
?>

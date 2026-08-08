<?php
/**
 * Pets Bot Logic
 * Handles automatic purchasing and equipping of pets.
 */

function handlePetBot($baseUrl, $cFile, $botMoney) {
    if ($botMoney < 10000) return null; // Cần ít nhất 10k GTLM để có thể suy nghĩ tới việc mua Pet

    $actions = [];

    // 1. Kiểm tra túi Pet của mình
    $myPetsRes = executeBotAction($baseUrl . "/api_pets.php", ['action' => 'get_my_pets'], $cFile);
    
    if (!isset($myPetsRes['success']) || !$myPetsRes['success']) {
        return null;
    }

    $myPets = $myPetsRes['my_pets'] ?? [];
    $activePet = $myPetsRes['active_pet'] ?? null;

    // Nếu đã có thú cưng đang dùng, bot không làm gì thêm
    if ($activePet !== null) {
        return null;
    }

    // Nếu có thú cưng trong kho nhưng chưa dùng -> Mang ra dùng
    if (!empty($myPets) && $activePet === null) {
        $petToEquip = $myPets[array_rand($myPets)];
        $equipRes = executeBotAction($baseUrl . "/api_pets.php", [
            'action' => 'equip',
            'pet_id' => $petToEquip['id']
        ], $cFile);

        if (isset($equipRes['success']) && $equipRes['success']) {
            $actions[] = "Vừa trang bị thú cưng <b>" . $petToEquip['name'] . "</b> để lấy Buff!";
            return ['status' => 'success', 'actions' => $actions];
        }
    }

    // 2. Nếu chưa có thú cưng nào, đi chợ mua Pet
    if (empty($myPets)) {
        $allPetsRes = executeBotAction($baseUrl . "/api_pets.php", ['action' => 'get_all'], $cFile);
        
        if (!isset($allPetsRes['success']) || !$allPetsRes['success']) {
            return null;
        }

        $allPets = $allPetsRes['pets'] ?? [];
        if (empty($allPets)) return null;

        // Lọc các con thú cưng mà bot có thể mua (dùng tối đa 15% tổng tài sản)
        $affordablePets = [];
        $budget = $botMoney * 0.15;

        foreach ($allPets as $pet) {
            if ($pet['price'] <= $budget) {
                $affordablePets[] = $pet;
            }
        }

        if (!empty($affordablePets)) {
            // Mua một con ngẫu nhiên trong tầm giá
            $petToBuy = $affordablePets[array_rand($affordablePets)];
            
            $buyRes = executeBotAction($baseUrl . "/api_pets.php", [
                'action' => 'buy',
                'pet_id' => $petToBuy['id']
            ], $cFile);

            if (isset($buyRes['success']) && $buyRes['success']) {
                $costStr = number_format($petToBuy['price']);
                $actions[] = "Tự động trích $costStr GTLM sắm bé <b>" . $petToBuy['name'] . "</b> về nhà!";
                
                // Mua xong thì mang ra dùng luôn
                executeBotAction($baseUrl . "/api_pets.php", [
                    'action' => 'equip',
                    'pet_id' => $petToBuy['id']
                ], $cFile);
            }
        }
    }

    if (!empty($actions)) {
        return ['status' => 'success', 'actions' => $actions];
    }

    return null;
}
?>

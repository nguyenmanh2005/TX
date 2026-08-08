<?php
/**
 * Bot Crafting Logic
 * Giả lập hành vi bot thử chế tạo đồ vật (Crafting)
 */

function handleCraftingBot($baseUrl, $cFile) {
    $actions = [];

    if (rand(1, 100) <= 5) { // 5% cơ hội thử chế tạo
        $recipeId = rand(1, 10); // Random thử các công thức từ 1 đến 10
        
        $craftRes = executeBotAction($baseUrl . "/api_crafting.php", [
            'action' => 'craft',
            'recipe_id' => $recipeId
        ], $cFile, true);
        
        if (isset($craftRes['status']) && $craftRes['status'] === 'success') {
            $itemName = $craftRes['item_name'] ?? "Vật phẩm bí ẩn";
            $actions[] = "Táy máy học đòi làm thợ rèn và vừa chế tạo thành công <b>$itemName</b>!";
        }
    }

    if (!empty($actions)) {
        return ['status' => 'success', 'actions' => $actions];
    }
    
    return null;
}
?>

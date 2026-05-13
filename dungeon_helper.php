<?php
if (!defined('DB_CONNECT_INCLUDED')) {
    require_once 'db_connect.php';
}
require_once 'material_helper.php';

/**
 * Generate daily dungeon if it doesn't exist
 */
function get_or_generate_daily_dungeon(mysqli $conn) {
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT * FROM dungeons WHERE date = ?");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        return $res->fetch_assoc();
    }

    // Generate new dungeon
    $names = ["Hang Rồng Lửa", "Mê Cung Băng", "Động Sấm Sét", "Vực Thẳm Bóng Tối", "Đảo Hư Không", "Khu Mỏ Cổ Đại"];
    $types = ['hunt', 'accumulate', 'streak', 'specialist', 'survivor', 'explorer'];
    
    $name = $names[array_rand($names)];
    $type = $types[array_rand($types)];
    $gameRequired = null;

    if ($type === 'specialist') {
        $games = ['slot', 'crash', 'baucua', 'plinko', 'mines'];
        $gameRequired = $games[array_rand($games)];
    }

    // Set targets based on type
    $targets = [
        'hunt' => [3, 7, 15],
        'accumulate' => [500000, 2000000, 10000000],
        'streak' => [2, 4, 6],
        'specialist' => [5, 10, 20],
        'survivor' => [50000, 300000, 1000000],
        'explorer' => [2, 5, 8]
    ];

    $t = $targets[$type];

    $stmt = $conn->prepare("INSERT INTO dungeons (date, name, type, game_required, tier1_target, tier2_target, tier3_target) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssiii", $today, $name, $type, $gameRequired, $t[0], $t[1], $t[2]);
    $stmt->execute();
    $dungeonId = $conn->insert_id;

    // Add rewards
    $ores = ['copper_ore', 'silver_ore', 'gold_ore'];
    for ($tier = 1; $tier <= 3; $tier++) {
        // Common reward
        $stmt = $conn->prepare("SELECT id FROM materials WHERE code = ?");
        $oreCode = $ores[$tier-1];
        $stmt->bind_param("s", $oreCode);
        $stmt->execute();
        $resMat = $stmt->get_result();
        if ($resMat->num_rows > 0) {
            $oreId = $resMat->fetch_assoc()['id'];
            $qty = ($tier == 1) ? 5 : (($tier == 2) ? 3 : 2);
            $gtlm = $tier * 10000;

            $stmtRew = $conn->prepare("INSERT INTO dungeon_rewards (dungeon_id, tier, material_id, quantity, gtlm_bonus) VALUES (?, ?, ?, ?, ?)");
            $stmtRew->bind_param("iiiii", $dungeonId, $tier, $oreId, $qty, $gtlm);
            $stmtRew->execute();
        }
    }

    // Return the newly created dungeon without recursion
    $stmt = $conn->prepare("SELECT * FROM dungeons WHERE id = ?");
    $stmt->bind_param("i", $dungeonId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Update user progress in current dungeon
 */
function update_dungeon_progress(mysqli $conn, int $userId, string $type, float $value, string $gameId = '') {
    $dungeon = get_or_generate_daily_dungeon($conn);
    if ($dungeon['type'] !== $type) return;
    if ($type === 'specialist' && $dungeon['game_required'] !== $gameId) return;

    for ($tier = 1; $tier <= 3; $tier++) {
        $stmt = $conn->prepare("SELECT * FROM dungeon_completions WHERE user_id = ? AND dungeon_id = ? AND tier = ?");
        $stmt->bind_param("iii", $userId, $dungeon['id'], $tier);
        $stmt->execute();
        $comp = $stmt->get_result()->fetch_assoc();

        if ($comp && $comp['status'] !== 'in_progress') continue;

        if (!$comp) {
            $stmt = $conn->prepare("INSERT INTO dungeon_completions (user_id, dungeon_id, tier, progress, status) VALUES (?, ?, ?, ?, 'in_progress')");
            $prog = 0;
            $stmt->bind_param("iiid", $userId, $dungeon['id'], $tier, $prog);
            $stmt->execute();
            $comp = ['progress' => 0];
        }

        $newProgress = $comp['progress'];
        if ($type === 'accumulate' || $type === 'explorer') {
            $newProgress += $value;
        } else {
            $newProgress = $value; // hunt, streak, survivor, specialist targets are usually peak or count
        }

        $target = $dungeon["tier{$tier}_target"];
        $status = ($newProgress >= $target) ? 'completed' : 'in_progress';
        $completedAt = ($status === 'completed') ? date('Y-m-d H:i:s') : null;

        $stmt = $conn->prepare("UPDATE dungeon_completions SET progress = ?, status = ?, completed_at = IFNULL(completed_at, ?) WHERE user_id = ? AND dungeon_id = ? AND tier = ?");
        $stmt->bind_param("dssiii", $newProgress, $status, $completedAt, $userId, $dungeon['id'], $tier);
        $stmt->execute();
    }
}

/**
 * Claim rewards for a completed tier
 */
function claim_dungeon_reward(mysqli $conn, int $userId, int $tier) {
    $dungeon = get_or_generate_daily_dungeon($conn);
    
    $stmt = $conn->prepare("SELECT * FROM dungeon_completions WHERE user_id = ? AND dungeon_id = ? AND tier = ? AND status = 'completed'");
    $stmt->bind_param("iii", $userId, $dungeon['id'], $tier);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) return ['success' => false, 'message' => 'Tier chưa hoàn thành hoặc đã nhận!'];

    $conn->begin_transaction();
    try {
        // Update status
        $stmt = $conn->prepare("UPDATE dungeon_completions SET status = 'claimed', claimed_at = NOW() WHERE user_id = ? AND dungeon_id = ? AND tier = ?");
        $stmt->bind_param("iii", $userId, $dungeon['id'], $tier);
        $stmt->execute();

        // Get rewards
        $stmt = $conn->prepare("SELECT dr.*, m.code FROM dungeon_rewards dr JOIN materials m ON dr.material_id = m.id WHERE dr.dungeon_id = ? AND dr.tier = ?");
        $stmt->bind_param("ii", $dungeon['id'], $tier);
        $stmt->execute();
        $rewards = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $rewardDetails = [];
        foreach ($rewards as $r) {
            add_material($conn, $userId, $r['code'], $r['quantity'], 'dungeon', "Dungeon {$dungeon['id']} Tier $tier");
            if ($r['gtlm_bonus'] > 0) {
                $conn->query("UPDATE users SET Money = Money + {$r['gtlm_bonus']} WHERE Iduser = $userId");
            }
            $rewardDetails[] = ['name' => $r['code'], 'qty' => $r['quantity'], 'gtlm' => $r['gtlm_bonus']];
        }

        $conn->commit();
        return ['success' => true, 'rewards' => $rewardDetails];
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => 'Lỗi hệ thống!'];
    }
}
?>

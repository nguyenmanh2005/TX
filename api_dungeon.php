<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập!']);
    exit();
}

require_once 'dungeon_helper.php';

$userId = $_SESSION['Iduser'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'claim_tier') {
    $tier = (int)($_POST['claim_tier'] ?? 0);
    if ($tier < 1 || $tier > 3) {
        echo json_encode(['success' => false, 'message' => 'Tier không hợp lệ!']);
        exit;
    }
    
    $result = claim_dungeon_reward($conn, $userId, $tier);
    echo json_encode($result);
    exit;
}
if ($action === 'get_status') {
    $dungeon = get_or_generate_daily_dungeon($conn);
    $dId = $dungeon['id'];

    // Lấy tiến trình của user
    $stmt = $conn->prepare("SELECT tier, progress, status FROM dungeon_completions WHERE user_id = ? AND dungeon_id = ?");
    $stmt->bind_param("ii", $userId, $dId);
    $stmt->execute();
    $completions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Lấy phần thưởng
    $stmt = $conn->prepare("SELECT dr.tier, dr.quantity, dr.gtlm_bonus, m.code, m.name as mat_name FROM dungeon_rewards dr JOIN materials m ON dr.material_id = m.id WHERE dr.dungeon_id = ?");
    $stmt->bind_param("i", $dId);
    $stmt->execute();
    $rewards = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'success' => true,
        'dungeon' => $dungeon,
        'completions' => $completions,
        'rewards' => $rewards
    ]);
    exit;
}
echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ!']);
exit;

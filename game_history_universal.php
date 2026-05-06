<?php
/**
 * Universal Game History Helper
 * Used by all games to fetch history with auto-load AJAX support
 */

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_history') {
    require 'db_connect.php';
    session_start();
    
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    if (!$isAjax || !isset($_SESSION['Iduser'])) {
        http_response_code(403);
        exit;
    }
    
    $userId = $_SESSION['Iduser'];
    $gameType = $_GET['game'] ?? 'unknown';
    $limit = (int)($_GET['limit'] ?? 20);
    
    // Map game name to table
    $gameTables = [
        'ac' => 'history_ac',
        'baucua' => 'history_baucua',
        'bingo' => 'history_bingo',
        'bj' => 'history_bj',
        'xocdia' => 'history_xocdia',
        'vq' => 'history_vq',
        'vietlott' => 'history_vietlott',
        'cs' => 'history_cs',
        'hopmu' => 'history_hopmu',
        'ruttham' => 'history_ruttham',
        'duangua' => 'history_duangua',
        'dice' => 'history_dice',
        'slot' => 'history_slot',
        'roulette' => 'history_roulette',
        'coinflip' => 'history_coinflip',
        'rps' => 'history_rps',
        'number' => 'history_number',
        'poker' => 'history_poker',
        'minesweeper' => 'history_minesweeper'
    ];
    
    $table = $gameTables[$gameType] ?? null;
    
    if (!$table) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Invalid game type'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    header('Content-Type: application/json; charset=utf-8');
    
    $sql = "SELECT * FROM $table WHERE Iduser = ? ORDER BY Time DESC LIMIT ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $conn->error,
            'history' => []
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $stmt->bind_param("ii", $userId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'history' => $history,
        'count' => count($history)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>

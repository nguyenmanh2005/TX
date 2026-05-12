<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['Iduser'];
$action = $_GET['action'] ?? 'status';

// --- CONFIG & CONSTANTS ---
$suits = ['s', 'c', 'd', 'h']; // Spades, Clubs, Diamonds, Hearts
$values = [3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15]; // 11=J, 12=Q, 13=K, 14=A, 15=2

// --- HELPERS ---

function createDeck() {
    global $suits, $values;
    $deck = [];
    foreach ($values as $v) {
        foreach ($suits as $s) {
            $deck[] = ['v' => $v, 's' => $s, 'id' => $v . '_' . $s];
        }
    }
    shuffle($deck);
    return $deck;
}

function sortHand(&$hand) {
    usort($hand, function($a, $b) {
        return $a['v'] - $b['v'];
    });
}

function getMoveType($cards) {
    $count = count($cards);
    if ($count == 0) return null;
    
    // Sort cards for easier check
    usort($cards, function($a, $b) { return $a['v'] - $b['v']; });
    
    if ($count == 1) return ['type' => 'single', 'value' => $cards[0]['v']];
    
    // Check for pairs, triples, quads
    $allSame = true;
    for ($i = 1; $i < $count; $i++) {
        if ($cards[$i]['v'] !== $cards[0]['v']) {
            $allSame = false;
            break;
        }
    }
    
    if ($allSame) {
        if ($count == 2) return ['type' => 'pair', 'value' => $cards[0]['v']];
        if ($count == 3) return ['type' => 'triple', 'value' => $cards[0]['v']];
        if ($count == 4) return ['type' => 'quad', 'value' => $cards[0]['v']];
    }
    
    // Check for straight (Sảnh)
    if ($count >= 3) {
        $isStraight = true;
        for ($i = 1; $i < $count; $i++) {
            // In Sam Loc, 2 (15) cannot be part of a straight
            if ($cards[$i]['v'] == 15 || $cards[$i-1]['v'] == 15 || $cards[$i]['v'] !== $cards[$i-1]['v'] + 1) {
                $isStraight = false;
                break;
            }
        }
        if ($isStraight) return ['type' => 'straight', 'value' => $cards[$count-1]['v'], 'count' => $count];
    }
    
    return null;
}

function canBeat($newMove, $lastMove) {
    if (!$newMove) return false;
    if (!$lastMove) return true; // Any valid move can start a round
    
    // Standard beat logic: same type, same count, higher value
    if ($newMove['type'] === $lastMove['type']) {
        if ($newMove['type'] === 'straight') {
            return ($newMove['count'] === $lastMove['count'] && $newMove['value'] > $lastMove['value']);
        }
        return ($newMove['value'] > $lastMove['value']);
    }
    
    // Special logic: Four of a kind can beat a 2 (single)
    if ($lastMove['type'] === 'single' && $lastMove['value'] === 15 && $newMove['type'] === 'quad') {
        return true;
    }
    
    return false;
}

// --- BOT AI ---

function getBotMove($hand, $lastMove) {
    // Very simple greedy AI: finds the smallest valid move
    // This is a placeholder for a better bot later
    
    $handCount = count($hand);
    
    // Try to find a single card that can beat lastMove
    if (!$lastMove || $lastMove['type'] === 'single') {
        foreach ($hand as $c) {
            $move = ['type' => 'single', 'value' => $c['v']];
            if (canBeat($move, $lastMove)) return [$c];
        }
    }
    
    // Try to find a pair
    if (!$lastMove || $lastMove['type'] === 'pair') {
        for ($i = 0; $i < $handCount - 1; $i++) {
            if ($hand[$i]['v'] === $hand[$i+1]['v']) {
                $move = ['type' => 'pair', 'value' => $hand[$i]['v']];
                if (canBeat($move, $lastMove)) return [$hand[$i], $hand[$i+1]];
            }
        }
    }

    // Try to find a triple
    if (!$lastMove || $lastMove['type'] === 'triple') {
        for ($i = 0; $i < $handCount - 2; $i++) {
            if ($hand[$i]['v'] === $hand[$i+1]['v'] && $hand[$i+1]['v'] === $hand[$i+2]['v']) {
                $move = ['type' => 'triple', 'value' => $hand[$i]['v']];
                if (canBeat($move, $lastMove)) return [$hand[$i], $hand[$i+1], $hand[$i+2]];
            }
        }
    }

    // Try to find a straight of same length
    if ($lastMove && $lastMove['type'] === 'straight') {
        $len = $lastMove['count'];
        // This is complex, for simple bot we just try to find ANY straight of length len
        // skipping for now to keep bot simple/fast
    }
    
    return null; // Pass
}

// --- GAME ACTIONS ---

if ($action === 'start') {
    $deck = createDeck();
    $hands = [
        0 => array_slice($deck, 0, 10), // Sâm Lốc normally 10 cards per person
        1 => array_slice($deck, 10, 10),
        2 => array_slice($deck, 20, 10),
        3 => array_slice($deck, 30, 10)
    ];
    
    foreach ($hands as &$h) sortHand($h);
    
    // Determine who goes first (who has 3 of Spades: 3_s)
    $turn = 0;
    foreach ($hands as $p => $h) {
        foreach ($h as $c) {
            if ($c['id'] === '3_s') {
                $turn = $p;
                break;
            }
        }
    }
    
    $_SESSION['samloc'] = [
        'hands' => $hands,
        'turn' => $turn,
        'last_move' => null,
        'last_player' => null,
        'passed' => [], // players who passed in this round
        'history' => [],
        'status' => 'playing',
        'winner' => null
    ];
    
    // If bot's turn, trigger bot cycle
    if ($turn !== 0) {
        runBotTurns();
    }
}

function runBotTurns() {
    $game = &$_SESSION['samloc'];
    while ($game['turn'] !== 0 && $game['status'] === 'playing') {
        $p = $game['turn'];
        
        // If everyone else passed, bot starts new round
        $activePlayers = 0;
        for ($i=0; $i<4; $i++) {
            if (!in_array($i, $game['passed'])) $activePlayers++;
        }
        if ($activePlayers === 1 && !in_array($p, $game['passed'])) {
            $game['last_move'] = null;
            $game['passed'] = [];
        }

        $moveCards = getBotMove($game['hands'][$p], $game['last_move']);
        
        if ($moveCards) {
            $move = getMoveType($moveCards);
            $game['last_move'] = $move;
            $game['last_player'] = $p;
            $game['history'][] = ['player' => $p, 'cards' => $moveCards, 'type' => $move['type']];
            
            // Remove cards from hand
            foreach ($moveCards as $mc) {
                foreach ($game['hands'][$p] as $idx => $hc) {
                    if ($hc['id'] === $mc['id']) {
                        array_splice($game['hands'][$p], $idx, 1);
                        break;
                    }
                }
            }
            
            // Check win
            if (count($game['hands'][$p]) === 0) {
                $game['status'] = 'ended';
                $game['winner'] = $p;
                return;
            }
        } else {
            $game['passed'][] = $p;
            $game['history'][] = ['player' => $p, 'cards' => [], 'type' => 'pass'];
        }
        
        // Next turn
        $game['turn'] = ($game['turn'] + 1) % 4;
        
        // Skip passed players
        while (in_array($game['turn'], $game['passed']) && count($game['passed']) < 3) {
            $game['turn'] = ($game['turn'] + 1) % 4;
        }
        
        // If 3 people passed, round ends
        if (count($game['passed']) >= 3) {
            $game['last_move'] = null;
            $game['passed'] = [];
            // Last player who played cards starts new round
            $game['turn'] = $game['last_player'];
        }
    }
}

if ($action === 'play') {
    $game = &$_SESSION['samloc'];
    if (!$game || $game['turn'] !== 0 || $game['status'] !== 'playing') {
        echo json_encode(['success' => false, 'message' => 'Not your turn']);
        exit();
    }
    
    $selectedIds = $_POST['cards'] ?? []; // Array of card IDs like ["3_s", "3_c"]
    $selectedCards = [];
    foreach ($selectedIds as $id) {
        foreach ($game['hands'][0] as $c) {
            if ($c['id'] === $id) {
                $selectedCards[] = $c;
                break;
            }
        }
    }
    
    $move = getMoveType($selectedCards);
    if (!$move || !canBeat($move, $game['last_move'])) {
        echo json_encode(['success' => false, 'message' => 'Nước đi không hợp lệ']);
        exit();
    }
    
    // Execute move
    $game['last_move'] = $move;
    $game['last_player'] = 0;
    $game['history'][] = ['player' => 0, 'cards' => $selectedCards, 'type' => $move['type']];
    
    // Remove cards from hand
    foreach ($selectedCards as $mc) {
        foreach ($game['hands'][0] as $idx => $hc) {
            if ($hc['id'] === $mc['id']) {
                array_splice($game['hands'][0], $idx, 1);
                break;
            }
        }
    }
    
    // Check win
    if (count($game['hands'][0]) === 0) {
        $game['status'] = 'ended';
        $game['winner'] = 0;
        // Payout logic here...
    } else {
        $game['turn'] = 1;
        runBotTurns();
    }
}

if ($action === 'pass') {
    $game = &$_SESSION['samloc'];
    if (!$game || $game['turn'] !== 0 || $game['status'] !== 'playing' || $game['last_move'] === null) {
        echo json_encode(['success' => false, 'message' => 'Cannot pass now']);
        exit();
    }
    
    $game['passed'][] = 0;
    $game['history'][] = ['player' => 0, 'cards' => [], 'type' => 'pass'];
    $game['turn'] = 1;
    runBotTurns();
}

// Final output
if (!isset($_SESSION['samloc'])) {
    echo json_encode(['success' => false, 'message' => 'No game active']);
} else {
    $game = $_SESSION['samloc'];
    // Hide bot hands from frontend
    $displayGame = $game;
    $displayGame['hands'][1] = count($game['hands'][1]);
    $displayGame['hands'][2] = count($game['hands'][2]);
    $displayGame['hands'][3] = count($game['hands'][3]);
    
    echo json_encode(['success' => true, 'game' => $displayGame]);
}

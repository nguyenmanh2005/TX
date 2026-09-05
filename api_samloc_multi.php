<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

if (!isset($_SESSION['Iduser']) && !isset($_SESSION['Iduser_temp_bot'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit;
}

$userId = isset($_SESSION['Iduser_temp_bot']) && (int)$_SESSION['Iduser_temp_bot'] > 0 ? (int)$_SESSION['Iduser_temp_bot'] : (int)$_SESSION['Iduser'];
$action = $_GET['action'] ?? 'status';
$tableId = isset($_REQUEST['table_id']) ? (int)$_REQUEST['table_id'] : 0;

if (!$tableId) {
    echo json_encode(['success' => false, 'message' => 'Table ID missing']);
    exit;
}

// Đảm bảo bảng có cột lưu người thắng ván trước
$colCheck = $conn->query("SHOW COLUMNS FROM samloc_multi_tables LIKE 'last_winner_seat'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE samloc_multi_tables ADD COLUMN last_winner_seat INT DEFAULT NULL");
}

$stmt = $conn->prepare("SELECT * FROM samloc_multi_tables WHERE id = ?");
$stmt->bind_param("i", $tableId);
$stmt->execute();
$table = $stmt->get_result()->fetch_assoc();

if (!$table) {
    echo json_encode(['success' => false, 'message' => 'Bàn không tồn tại']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM samloc_multi_players WHERE table_id = ? ORDER BY seat_index");
$stmt->bind_param("i", $tableId);
$stmt->execute();
$players = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function getPlayerAtSeat($seatIndex, $players) {
    foreach ($players as $p) {
        if ((int)$p['seat_index'] === $seatIndex) return $p;
    }
    return null;
}

function getNextTurn($currentSeat, $players, $passedList = []) {
    $seats = [];
    foreach ($players as $p) {
        if ($p['status'] !== 'won' && !in_array($p['seat_index'], $passedList)) {
            $seats[] = (int)$p['seat_index'];
        }
    }
    if (empty($seats)) return $currentSeat;
    sort($seats);
    
    $idx = array_search($currentSeat, $seats);
    if ($idx === false) {
        // Nếu ghế hiện tại không có trong danh sách (đã pass), tìm ghế kế tiếp theo chiều kim đồng hồ
        $next = $seats[0];
        foreach ($seats as $s) {
            if ($s > $currentSeat) { $next = $s; break; }
        }
        return $next;
    }
    // Lượt quay thuận theo chiều kim đồng hồ
    $nextIdx = ($idx + 1) % count($seats);
    return $seats[$nextIdx];
}

function processXinLangFail($tableId, $xinLangSeat, &$players, $minBet, $conn) {
    $penaltyMap = [];
    $totalPenalty = 0;
    
    $xinLangPlayer = null;
    $others = [];
    foreach ($players as $p) {
        if ($p['seat_index'] == $xinLangSeat) {
            $xinLangPlayer = $p;
        } else {
            $others[] = $p;
        }
    }
    
    $penaltyPerPerson = 20 * $minBet;
    foreach ($others as $o) {
        $penaltyMap[$o['seat_index']] = $penaltyPerPerson;
        $totalPenalty += $penaltyPerPerson;
        if (!$o['is_bot']) {
            $conn->query("UPDATE users SET Money = Money + $penaltyPerPerson WHERE Iduser = {$o['user_id']}");
        }
    }
    
    $penaltyMap[$xinLangSeat] = -$totalPenalty;
    if ($xinLangPlayer && !$xinLangPlayer['is_bot']) {
        $conn->query("UPDATE users SET Money = Money - $totalPenalty WHERE Iduser = {$xinLangPlayer['user_id']}");
    }
    
    $penaltyMap['note'] = "Xin Làng thất bại (Đền làng)";
    $penaltyJson = json_encode($penaltyMap);
    $nextExpiry = date('Y-m-d H:i:s', time() + 10);
    $conn->query("UPDATE samloc_multi_tables SET status = 'ended', turn_expires_at = '$nextExpiry', passed_players = '$penaltyJson' WHERE id = $tableId");
}

function processWin($tableId, $winnerId, &$players, $minBet, $conn, $isAnTrang = false, $anTrangType = '', $denLangPlayerId = null) {
    $totalWin = 0;
    $penaltyMap = []; 
    
    $stmt = $conn->prepare("SELECT * FROM samloc_multi_tables WHERE id = ?");
    $stmt->bind_param("i", $tableId);
    $stmt->execute();
    $tableData = $stmt->get_result()->fetch_assoc();
    
    $winnerPlayer = null;
    foreach ($players as $p) {
        if ($p['id'] == $winnerId) { $winnerPlayer = $p; break; }
    }
    
    // Xin làng thắng
    if ($tableData['xin_lang_player'] !== null && $winnerPlayer && $winnerPlayer['seat_index'] == $tableData['xin_lang_player']) {
        $othersCount = count($players) - 1;
        $winAmount = 20 * $minBet;
        foreach ($players as $p) {
            if ($p['id'] == $winnerId) {
                $p['status'] = 'won';
                $penaltyMap[$p['seat_index']] = $winAmount * $othersCount;
                if (!$p['is_bot']) $conn->query("UPDATE users SET Money = Money + " . ($winAmount * $othersCount) . " WHERE Iduser = {$p['user_id']}");
            } else {
                $penaltyMap[$p['seat_index']] = -$winAmount;
                if (!$p['is_bot']) $conn->query("UPDATE users SET Money = Money - $winAmount WHERE Iduser = {$p['user_id']}");
            }
        }
        $penaltyMap['note'] = "Xin làng thành công";
        $penaltyJson = json_encode($penaltyMap);
        $nextExpiry = date('Y-m-d H:i:s', time() + 10);
        $winSeat = $winnerPlayer ? (int)$winnerPlayer['seat_index'] : 'NULL';
        $conn->query("UPDATE samloc_multi_tables SET status = 'ended', turn_expires_at = '$nextExpiry', passed_players = '$penaltyJson', last_winner_seat = $winSeat WHERE id = $tableId");
        if ($winnerId != -1) $conn->query("UPDATE samloc_multi_players SET status = 'won' WHERE id = $winnerId");
        return;
    }

    $isThoi2 = false;
    $lm = null;
    if (!$isAnTrang && $winnerPlayer) {
        $lm = json_decode($tableData['last_move'] ?: 'null', true);
        if ($lm && $lm['value'] == 15 && in_array($lm['type'], ['single', 'pair', 'triple', 'quad'])) {
            $isThoi2 = true;
        }
    }

    if ($isThoi2) {
        $multiplier = 1;
        if ($lm['type'] == 'pair') $multiplier = 2;
        if ($lm['type'] == 'triple') $multiplier = 3;
        if ($lm['type'] == 'quad') $multiplier = 4;
        $penalty = 5 * $multiplier * $minBet;

        $penaltyMap[$winnerPlayer['seat_index']] = -$penalty;
        
        $others = [];
        foreach ($players as $p) { if ($p['id'] != $winnerId) $others[] = $p; }
        $share = floor($penalty / count($others));
        
        if (!$winnerPlayer['is_bot']) {
            $conn->query("UPDATE users SET Money = Money - $penalty WHERE Iduser = {$winnerPlayer['user_id']}");
        }
        foreach ($others as $o) {
            $penaltyMap[$o['seat_index']] = $share;
            if (!$o['is_bot']) {
                $conn->query("UPDATE users SET Money = Money + $share WHERE Iduser = {$o['user_id']}");
            }
        }
        $winnerId = -1;
    } else {
        $plog = json_decode($tableData['penalty_log'] ?: '[]', true);
        // Process penalty log (Chặt heo / Đè)
        $activePlog = array_filter($plog, function($e) { return $e['status'] === 'active'; });
        foreach ($activePlog as $ev) {
            // victim pays attacker
            $amt = $ev['amount'] * $minBet;
            if (!isset($penaltyMap[$ev['victim']])) $penaltyMap[$ev['victim']] = 0;
            if (!isset($penaltyMap[$ev['attacker']])) $penaltyMap[$ev['attacker']] = 0;
            $penaltyMap[$ev['victim']] -= $amt;
            $penaltyMap[$ev['attacker']] += $amt;
            
            $vp = getPlayerAtSeat($ev['victim'], $players);
            $ap = getPlayerAtSeat($ev['attacker'], $players);
            if ($vp && !$vp['is_bot']) $conn->query("UPDATE users SET Money = Money - $amt WHERE Iduser = {$vp['user_id']}");
            if ($ap && !$ap['is_bot']) $conn->query("UPDATE users SET Money = Money + $amt WHERE Iduser = {$ap['user_id']}");
        }

        foreach ($players as &$p) {
            if ($p['id'] == $winnerId) {
                $p['status'] = 'won';
                continue;
            }
            
            $cards = json_decode($p['cards'], true);
            if (!is_array($cards)) $cards = [];
            $cCount = count($cards);
            if ($cCount === 0 && !$isAnTrang) continue;
            
            $heoCount = 0; $valCounts = [];
            foreach ($cards as $c) {
                if ($c['v'] == 15) $heoCount++;
                if (!isset($valCounts[$c['v']])) $valCounts[$c['v']] = 0;
                $valCounts[$c['v']]++;
            }
            $quadCount = 0;
            foreach ($valCounts as $v => $c) {
                if ($c == 4 && $v != 15) $quadCount++;
            }
            
            $basePenalty = 0;
            if ($isAnTrang) {
                $basePenalty = 20 * $minBet;
            } else {
                if ($cCount === 10) $basePenalty = 15 * $minBet; // Cóng
                else $basePenalty = $cCount * $minBet;
            }
            
            $heoPenalty = $heoCount * ($minBet * 2);
            $quadPenalty = $quadCount * ($minBet * 2);
            $totalPenalty = $basePenalty + $heoPenalty + $quadPenalty;
            
            if ($denLangPlayerId && $p['id'] != $denLangPlayerId) {
                $totalWin += $totalPenalty;
                if (!isset($penaltyMap[$p['seat_index']])) $penaltyMap[$p['seat_index']] = 0;
            } else if ($denLangPlayerId && $p['id'] == $denLangPlayerId) {
                // Do nothing here
            } else {
                $totalWin += $totalPenalty;
                if (!isset($penaltyMap[$p['seat_index']])) $penaltyMap[$p['seat_index']] = 0;
                $penaltyMap[$p['seat_index']] -= $totalPenalty;
                if (!$p['is_bot']) {
                    $conn->query("UPDATE users SET Money = Money - $totalPenalty WHERE Iduser = {$p['user_id']}");
                }
            }
        }
        
        if ($denLangPlayerId) {
            foreach ($players as $p) {
                if ($p['id'] == $denLangPlayerId) {
                    if (!isset($penaltyMap[$p['seat_index']])) $penaltyMap[$p['seat_index']] = 0;
                    $penaltyMap[$p['seat_index']] -= $totalWin;
                    if (!$p['is_bot']) {
                        $conn->query("UPDATE users SET Money = Money - $totalWin WHERE Iduser = {$p['user_id']}");
                    }
                }
            }
        }
        
        if ($winnerPlayer && !$winnerPlayer['is_bot']) {
            $conn->query("UPDATE users SET Money = Money + $totalWin WHERE Iduser = {$winnerPlayer['user_id']}");
        }
        if ($winnerPlayer) {
            if (!isset($penaltyMap[$winnerPlayer['seat_index']])) $penaltyMap[$winnerPlayer['seat_index']] = 0;
            $penaltyMap[$winnerPlayer['seat_index']] += $totalWin;
        }
    }
    
    $note = $isAnTrang ? "Ăn Trắng: $anTrangType" : ($isThoi2 ? "Thối 2" : ($denLangPlayerId ? "Đền Báo Bài" : ""));
    if ($note != '') {
        $penaltyMap['note'] = $note;
    }
    
    $penaltyJson = json_encode($penaltyMap);
    $nextExpiry = date('Y-m-d H:i:s', time() + 10);
    $winSeat = $winnerPlayer ? (int)$winnerPlayer['seat_index'] : 'NULL';
    $conn->query("UPDATE samloc_multi_tables SET status = 'ended', turn_expires_at = '$nextExpiry', passed_players = '$penaltyJson', last_winner_seat = $winSeat WHERE id = $tableId");
    if ($winnerId != -1) $conn->query("UPDATE samloc_multi_players SET status = 'won' WHERE id = $winnerId");
}

$suits = ['s', 'c', 'd', 'h']; 
$values = [3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15]; 

function createDeck($playerCount) {
    global $suits, $values;
    $deck = [];
    foreach ($values as $v) {
        foreach ($suits as $s) {
            $deck[] = ['v' => $v, 's' => $s, 'id' => $v . '_' . $s];
        }
    }
    shuffle($deck);
    // Nếu 5 người -> 50 lá. Bỏ 2 lá ra.
    if ($playerCount == 5) {
        array_pop($deck);
        array_pop($deck);
    }
    return $deck;
}

function sortHand(&$hand) {
    usort($hand, function($a, $b) { return $a['v'] - $b['v']; });
}

function checkAnTrang($hand) {
    if (count($hand) !== 10) return false;
    usort($hand, function($a, $b) { return $a['v'] - $b['v']; });
    
    $isDragon = true;
    for ($i = 0; $i < 9; $i++) {
        if ($hand[$i]['v'] + 1 !== $hand[$i+1]['v'] || $hand[$i+1]['v'] == 15) {
            $isDragon = false; break;
        }
    }
    if ($isDragon) return 'Sảnh Rồng';
    
    $heoCount = 0;
    foreach ($hand as $c) if ($c['v'] == 15) $heoCount++;
    if ($heoCount == 4) return 'Tứ Quý 2';
    
    $isAllBlack = true; $isAllRed = true;
    foreach ($hand as $c) {
        $color = ($c['s'] == 's' || $c['s'] == 'c') ? 'black' : 'red';
        if ($color == 'black') $isAllRed = false;
        if ($color == 'red') $isAllBlack = false;
    }
    if ($isAllBlack || $isAllRed) return 'Cùng Màu';
    
    $valCounts = [];
    foreach ($hand as $c) {
        if (!isset($valCounts[$c['v']])) $valCounts[$c['v']] = 0;
        $valCounts[$c['v']]++;
    }
    
    $tripleCount = 0;
    foreach ($valCounts as $v => $c) { if ($c >= 3) $tripleCount++; }
    if ($tripleCount >= 3) return '3 Sám Cô';
    
    $pairCount = 0;
    foreach ($valCounts as $v => $c) { $pairCount += floor($c / 2); }
    if ($pairCount >= 5) return '5 Đôi';
    
    return false;
}

function isA23Straight(&$cards, $count) {
    $mappedCards = $cards;
    foreach ($mappedCards as &$mc) {
        if ($mc['v'] == 14) $mc['mapped'] = 1;
        else if ($mc['v'] == 15) $mc['mapped'] = 2;
        else $mc['mapped'] = $mc['v'];
    }
    usort($mappedCards, function($a, $b) { return $a['mapped'] - $b['mapped']; });
    if ($mappedCards[0]['mapped'] != 1) return false; // Sảnh chứa 2 phải chứa A
    
    $isStraight = true;
    for ($i = 1; $i < $count; $i++) {
        if ($mappedCards[$i]['mapped'] !== $mappedCards[$i-1]['mapped'] + 1) {
            $isStraight = false; break;
        }
    }
    if ($isStraight) return ['type' => 'straight', 'value' => $mappedCards[$count-1]['mapped'], 'count' => $count];
    return false;
}

function getMoveType($cards) {
    $count = count($cards);
    if ($count == 0) return null;
    usort($cards, function($a, $b) { return $a['v'] - $b['v']; });
    
    if ($count == 1) return ['type' => 'single', 'value' => $cards[0]['v']];
    
    $allSame = true;
    for ($i = 1; $i < $count; $i++) {
        if ($cards[$i]['v'] !== $cards[0]['v']) { $allSame = false; break; }
    }
    if ($allSame) {
        if ($count == 2) return ['type' => 'pair', 'value' => $cards[0]['v']];
        if ($count == 3) return ['type' => 'triple', 'value' => $cards[0]['v']];
        if ($count == 4) return ['type' => 'quad', 'value' => $cards[0]['v']];
    }
    
    if ($count == 8) {
        if ($cards[0]['v'] == $cards[3]['v'] && $cards[4]['v'] == $cards[7]['v'] && $cards[0]['v'] != $cards[4]['v']) {
            return ['type' => 'double_quad', 'value' => $cards[7]['v']];
        }
    }
    
    if ($count >= 3) {
        $isStraight = true;
        for ($i = 1; $i < $count; $i++) {
            if ($cards[$i]['v'] == 15 || $cards[$i-1]['v'] == 15 || $cards[$i]['v'] !== $cards[$i-1]['v'] + 1) {
                $isStraight = false; break;
            }
        }
        if ($isStraight) return ['type' => 'straight', 'value' => $cards[$count-1]['v'], 'count' => $count];
        
        return isA23Straight($cards, $count);
    }
    return null;
}

function canBeat($newMove, $lastMove) {
    if (!$newMove) return false;
    if (!$lastMove) return true;
    
    if ($newMove['type'] === $lastMove['type']) {
        if ($newMove['type'] === 'straight') {
            return ($newMove['count'] >= $lastMove['count'] && $newMove['value'] > $lastMove['value']);
        }
        return ($newMove['value'] > $lastMove['value']);
    }
    
    if ($lastMove['type'] === 'single' && $lastMove['value'] === 15) {
        if ($newMove['type'] === 'quad') return true; 
    }
    if ($lastMove['type'] === 'pair' && $lastMove['value'] === 15) {
        if ($newMove['type'] === 'double_quad') return true;
    }
    
    return false;
}

function getAllMoves($hand) {
    $moves = [];
    $count = count($hand);
    
    foreach ($hand as $c) $moves[] = ['cards' => [$c], 'move' => ['type' => 'single', 'value' => $c['v']]];
    
    $valGroups = [];
    foreach ($hand as $c) {
        if (!isset($valGroups[$c['v']])) $valGroups[$c['v']] = [];
        $valGroups[$c['v']][] = $c;
    }
    
    foreach ($valGroups as $v => $cards) {
        if (count($cards) >= 2) $moves[] = ['cards' => [$cards[0], $cards[1]], 'move' => ['type' => 'pair', 'value' => $v]];
        if (count($cards) >= 3) $moves[] = ['cards' => [$cards[0], $cards[1], $cards[2]], 'move' => ['type' => 'triple', 'value' => $v]];
        if (count($cards) == 4) $moves[] = ['cards' => $cards, 'move' => ['type' => 'quad', 'value' => $v]];
    }
    
    $uniqueVals = array_keys($valGroups);
    sort($uniqueVals);
    for ($len = 3; $len <= $count; $len++) {
        for ($i = 0; $i <= count($uniqueVals) - $len; $i++) {
            $isStraight = true;
            for ($j = 0; $j < $len - 1; $j++) {
                if ($uniqueVals[$i+$j]+1 != $uniqueVals[$i+$j+1] || $uniqueVals[$i+$j+1] == 15) {
                    $isStraight = false; break;
                }
            }
            if ($isStraight) {
                $mCards = [];
                for ($j = 0; $j < $len; $j++) $mCards[] = $valGroups[$uniqueVals[$i+$j]][0];
                $moves[] = ['cards' => $mCards, 'move' => ['type' => 'straight', 'value' => $uniqueVals[$i+$len-1], 'count' => $len]];
            }
        }
    }
    
    // Straight A-2-3
    $mappedGroups = [];
    foreach ($hand as $c) {
        $mv = $c['v'];
        if ($mv == 14) $mv = 1;
        if ($mv == 15) $mv = 2;
        if (!isset($mappedGroups[$mv])) $mappedGroups[$mv] = [];
        $mappedGroups[$mv][] = $c;
    }
    $uniqueMappeds = array_keys($mappedGroups);
    sort($uniqueMappeds);
    
    if (in_array(1, $uniqueMappeds)) {
        for ($len = 3; $len <= $count; $len++) {
            $isStraight = true;
            for ($j = 0; $j < $len; $j++) {
                if (!in_array(1 + $j, $uniqueMappeds)) { $isStraight = false; break; }
            }
            if ($isStraight) {
                $mCards = [];
                for ($j = 0; $j < $len; $j++) $mCards[] = $mappedGroups[1 + $j][0];
                $moves[] = ['cards' => $mCards, 'move' => ['type' => 'straight', 'value' => $len, 'count' => $len]];
            }
        }
    }
    return $moves;
}

function getBotMove($hand, $lastMove, $nextPlayerCardCount = 0) {
    $allMoves = getAllMoves($hand);
    $validMoves = [];
    foreach ($allMoves as $m) {
        if (canBeat($m['move'], $lastMove)) {
            $validMoves[] = $m;
        }
    }
    
    if (empty($validMoves)) return null;
    
    // [1] BẮT HEO: Nếu đối thủ vừa đánh Heo (15), kiểm tra Tứ Quý chặt ngay!
    if ($lastMove && $lastMove['type'] === 'single' && $lastMove['value'] === 15) {
        $quadMoves = array_filter($validMoves, function($m) { return $m['move']['type'] === 'quad'; });
        if (!empty($quadMoves)) {
            usort($quadMoves, function($a, $b) { return $a['move']['value'] - $b['move']['value']; });
            return array_values($quadMoves)[0]['cards'];
        }
        return null; // Không có tứ quý thì bỏ lượt
    }
    
    // [2] CHẶN CỬA BÁO BÀI: Nếu người kế tiếp chỉ còn 1 lá
    if ($nextPlayerCardCount == 1) {
        if ($lastMove && $lastMove['type'] === 'single') {
            $singleMoves = array_filter($validMoves, function($m) { return $m['move']['type'] === 'single'; });
            if (!empty($singleMoves)) {
                // Đánh lá to nhất có thể để chặn cửa, tránh bị đền làng!
                usort($singleMoves, function($a, $b) { return $b['move']['value'] - $a['move']['value']; });
                return array_values($singleMoves)[0]['cards'];
            }
        }
        return $validMoves[0]['cards'];
    }
    
    // Phân tích các lá bài thuộc Tứ Quý và Sảnh để tránh xé bài bừa bãi
    $valCounts = [];
    foreach ($hand as $c) {
        $valCounts[$c['v']] = ($valCounts[$c['v']] ?? 0) + 1;
    }
    $quadVals = [];
    foreach ($valCounts as $v => $cnt) {
        if ($cnt === 4) $quadVals[] = $v;
    }
    
    // [3] ĐẦU VÒNG (Không có lastMove)
    if (!$lastMove) {
        // 3.1 Chống Thối Heo: Nếu bài còn <= 3 lá và có Heo 15, xả Heo ngay để giành cái
        $heoMoves = array_filter($validMoves, function($m) { return $m['move']['type'] === 'single' && $m['move']['value'] === 15; });
        if (count($hand) <= 3 && !empty($heoMoves)) {
            return array_values($heoMoves)[0]['cards'];
        }
        
        // 3.2 Sắp xếp ứng viên: Ưu tiên Sảnh dài (>= 5 lá), sau đó Bộ ba, Đôi, Sảnh ngắn, Rác nhỏ
        usort($validMoves, function($a, $b) use ($quadVals) {
            // Không đánh tứ quý đầu ván (giữ lại bắt Heo)
            $isAQuad = in_array($a['move']['value'], $quadVals);
            $isBQuad = in_array($b['move']['value'], $quadVals);
            if ($isAQuad !== $isBQuad) return $isAQuad ? 1 : -1;
            
            $scoreA = 0;
            $scoreB = 0;
            if ($a['move']['type'] === 'straight') $scoreA = 50 + ($a['move']['count'] ?? 0);
            else if ($a['move']['type'] === 'triple') $scoreA = 40;
            else if ($a['move']['type'] === 'pair') $scoreA = 30;
            else if ($a['move']['type'] === 'single') $scoreA = 20;
            
            if ($b['move']['type'] === 'straight') $scoreB = 50 + ($b['move']['count'] ?? 0);
            else if ($b['move']['type'] === 'triple') $scoreB = 40;
            else if ($b['move']['type'] === 'pair') $scoreB = 30;
            else if ($b['move']['type'] === 'single') $scoreB = 20;
            
            if ($scoreA !== $scoreB) return $scoreB - $scoreA;
            return $a['move']['value'] - $b['move']['value'];
        });
        
        // Tránh đánh lá 2 cuối cùng (chống thối)
        $safeCandidates = array_filter($validMoves, function($m) use ($hand) {
            if (count($hand) - count($m['cards']) == 0 && $m['move']['value'] == 15) return false;
            return true;
        });
        if (!empty($safeCandidates)) return array_values($safeCandidates)[0]['cards'];
        
        return $validMoves[0]['cards'];
    }
    
    // [4] ĐÈ BÀI ĐỐI THỦ:
    // Sắp xếp nước đi nhỏ nhất hợp lệ, nhưng phạt nặng việc xé Tứ Quý để đánh lẻ
    usort($validMoves, function($a, $b) use ($quadVals) {
        $penaltyA = ($a['move']['type'] === 'single' && in_array($a['move']['value'], $quadVals)) ? 1000 : 0;
        $penaltyB = ($b['move']['type'] === 'single' && in_array($b['move']['value'], $quadVals)) ? 1000 : 0;
        if ($penaltyA !== $penaltyB) return $penaltyA - $penaltyB;
        return $a['move']['value'] - $b['move']['value'];
    });
    
    $safeMoves = array_filter($validMoves, function($m) use ($hand) {
        // Tránh để Heo là nước đi cuối cùng
        if (count($hand) - count($m['cards']) == 0 && $m['move']['value'] == 15 && $m['move']['type'] != 'quad') return false; 
        return true;
    });
    if (!empty($safeMoves)) return array_values($safeMoves)[0]['cards'];
    
    return $validMoves[0]['cards'];
}

// --- ACTIONS ---

if ($action === 'status') {
    if ($table['status'] === 'ended' && $table['turn_expires_at'] && strtotime($table['turn_expires_at']) <= time()) {
        $humanCount = 0;
        foreach ($players as $p) { if (!$p['is_bot']) $humanCount++; }
        if ($humanCount === 0) {
            $conn->query("DELETE FROM samloc_multi_tables WHERE id = $tableId");
            echo json_encode(['success' => true, 'redirect' => '../games/samloc.php']);
            exit;
        }
        
        $nextExpiry = date('Y-m-d H:i:s', time() + 5);
        $conn->query("UPDATE samloc_multi_tables SET status = 'waiting', turn_expires_at = '$nextExpiry' WHERE id = $tableId");
        $conn->query("UPDATE samloc_multi_players SET cards = '[]', status = 'waiting' WHERE table_id = $tableId");
        
        echo json_encode(['success' => true, 'reload' => true]);
        exit;
    }

    if ($table['status'] === 'waiting' && count($players) > 1 && $table['turn_expires_at']) {
        $timeLeft = strtotime($table['turn_expires_at']) - time();
        if ($timeLeft <= 0) {
            // 🃏 1. CHIA BÀI NGAY KHI HẾT THỜI GIAN CHỜ VÀO BÀN
            $deck = createDeck(count($players));
            $anTrangPlayer = null;
            $anTrangType = '';
            
            for ($i = 0; $i < count($players); $i++) {
                $h = array_slice($deck, $i * 10, 10);
                sortHand($h);
                if (!$anTrangPlayer) {
                    $at = checkAnTrang($h);
                    if ($at) { $anTrangPlayer = $players[$i]; $anTrangType = $at; }
                }
                $cardsJson = json_encode($h);
                $pid = $players[$i]['id'];
                $conn->query("UPDATE samloc_multi_players SET cards = '$cardsJson', status = 'playing' WHERE id = $pid");
                $players[$i]['cards'] = $cardsJson;
            }
            
            // 2. Nếu có người Ăn Trắng (Sảnh Rồng, 5 Đôi, Tứ Quý 2...), kết thúc và húp luôn GTLM!
            if ($anTrangPlayer) {
                processWin($tableId, $anTrangPlayer['id'], $players, $table['min_bet'], $conn, true, $anTrangType);
                echo json_encode(['success' => true, 'reload' => true]);
                exit;
            }
            
            // 3. Đã chia bài xong -> Chuyển sang giai đoạn Hô Sâm / Xin Làng (cho 5 giây để tay chơi ngắm 10 lá bài trên tay)
            $nextExpiry = date('Y-m-d H:i:s', time() + 5);
            $conn->query("UPDATE samloc_multi_tables SET status = 'xin_lang', turn_expires_at = '$nextExpiry', passed_players = '[]', last_move = 'null', last_player = null, penalty_log = '[]', xin_lang_player = NULL WHERE id = $tableId");
            echo json_encode(['success' => true, 'reload' => true]);
            exit;
        }
    }
    
    if ($table['status'] === 'xin_lang') {
        // Kiểm tra Bot có bài khủng (Sảnh dài >= 8 lá hoặc Tứ quý 2) thì Hô Sâm
        $xinLang = false;
        foreach ($players as $p) {
            if ($p['is_bot']) {
                $botCards = json_decode($p['cards'] ?: '[]', true);
                if (!empty($botCards) && count($botCards) === 10) {
                    $vCounts = [];
                    foreach ($botCards as $c) {
                        $vCounts[$c['v']] = ($vCounts[$c['v']] ?? 0) + 1;
                    }
                    if (in_array(4, $vCounts) && rand(1, 100) <= 20) {
                        $xinLang = $p; 
                        break;
                    }
                }
            }
        }
        
        if ($xinLang || strtotime($table['turn_expires_at']) <= time()) {
            // XÁC ĐỊNH NGƯỜI ĐÁNH ĐẦU:
            // 1. Ưu tiên người Xin Làng / Hô Sâm (nếu có)
            // 2. Nếu không ai Xin Làng: Ván trước ai thắng thì đánh trước
            // 3. Nếu phòng mới tạo (hoặc người thắng trước đã rời bàn): Ngẫu nhiên 1 người đánh trước
            $startingSeat = -1;
            $xinLangPlayerId = 'NULL';
            
            if ($xinLang) {
                $startingSeat = (int)$xinLang['seat_index'];
                $xinLangPlayerId = (int)$xinLang['seat_index'];
            } else if (isset($table['last_winner_seat']) && $table['last_winner_seat'] !== null && $table['last_winner_seat'] !== '') {
                $lastWinnerSeat = (int)$table['last_winner_seat'];
                foreach ($players as $p) {
                    if ((int)$p['seat_index'] === $lastWinnerSeat) {
                        $startingSeat = $lastWinnerSeat;
                        break;
                    }
                }
            }
            
            // Nếu không ai xin làng và (phòng mới hoặc người thắng cũ đã rời phòng) -> Ngẫu nhiên chọn 1 người
            if ($startingSeat === -1 && !empty($players)) {
                $randomPlayer = $players[array_rand($players)];
                $startingSeat = (int)$randomPlayer['seat_index'];
            }
            
            $nextExpiry = date('Y-m-d H:i:s', time() + 15);
            $conn->query("UPDATE samloc_multi_tables SET status = 'playing', current_turn = $startingSeat, passed_players = '[]', last_move = 'null', last_player = null, penalty_log = '[]', xin_lang_player = $xinLangPlayerId, turn_expires_at = '$nextExpiry' WHERE id = $tableId");
            
            echo json_encode(['success' => true, 'reload' => true]);
            exit;
        }
    }

    if ($table['status'] === 'playing') {
        $currentPlayer = getPlayerAtSeat($table['current_turn'], $players);
        
        $shouldBotPlay = false;
        if ($currentPlayer && $currentPlayer['is_bot']) {
            $turnStartedAt = strtotime($table['turn_expires_at']) - 15;
            if (time() - $turnStartedAt >= 2) {
                $shouldBotPlay = true;
            }
        } else if ($currentPlayer && !$currentPlayer['is_bot']) {
            if (strtotime($table['turn_expires_at']) < time()) {
                $_GET['action'] = 'pass';
                $_REQUEST['force_seat'] = $currentPlayer['seat_index'];
                $shouldBotPlay = true;
            }
        }

        if ($shouldBotPlay) {
            $botCards = json_decode($currentPlayer['cards'], true);
            $lastMove = json_decode($table['last_move'], true);
            
            $passedList = json_decode($table['passed_players'], true);
            $activeCount = 0;
            foreach ($players as $p) {
                if ($p['status'] !== 'won' && !in_array($p['seat_index'], $passedList)) $activeCount++;
            }
            
            if ($activeCount <= 1 && !in_array($currentPlayer['seat_index'], $passedList)) {
                $lastMove = null; 
                $passedList = [];
                $table['passed_players'] = '[]';
            }

            if (!$currentPlayer['is_bot']) {
                $moveCards = null; 
            } else {
                $nextTurnCheck = getNextTurn($table['current_turn'], $players, $passedList);
                $pNext = getPlayerAtSeat($nextTurnCheck, $players);
                $npcCount = 0;
                if ($pNext) {
                    $npcHand = json_decode($pNext['cards'], true);
                    $npcCount = is_array($npcHand) ? count($npcHand) : 0;
                }
                $moveCards = getBotMove($botCards, $lastMove, $npcCount);
            }

            if ($moveCards) {
                $move = getMoveType($moveCards);
                $move['cards'] = $moveCards; 
                
                // Track Penalty
                if ($lastMove && $lastMove['type'] == 'single' && $lastMove['value'] == 15 && $move['type'] == 'quad') {
                    $move['action'] = 'chat_heo';
                    $move['original_heo_player'] = $table['last_player'];
                    $move['victim'] = $table['last_player'];
                    $plog = json_decode($table['penalty_log'] ?: '[]', true);
                    $plog[] = ['id' => uniqid(), 'event' => 'chat_heo', 'victim' => $table['last_player'], 'attacker' => $currentPlayer['seat_index'], 'status' => 'active', 'amount' => 15];
                    $conn->query("UPDATE samloc_multi_tables SET penalty_log = '" . json_encode($plog) . "' WHERE id = $tableId");
                } else if ($lastMove && $lastMove['type'] == 'pair' && $lastMove['value'] == 15 && $move['type'] == 'double_quad') {
                    $move['action'] = 'chat_doi_heo';
                    $move['original_heo_player'] = $table['last_player'];
                    $move['victim'] = $table['last_player'];
                    $plog = json_decode($table['penalty_log'] ?: '[]', true);
                    $plog[] = ['id' => uniqid(), 'event' => 'chat_doi_heo', 'victim' => $table['last_player'], 'attacker' => $currentPlayer['seat_index'], 'status' => 'active', 'amount' => 30];
                    $conn->query("UPDATE samloc_multi_tables SET penalty_log = '" . json_encode($plog) . "' WHERE id = $tableId");
                } else if ($lastMove && in_array($lastMove['type'], ['quad', 'double_quad']) && in_array($move['type'], ['quad', 'double_quad'])) {
                    $move['action'] = 'de';
                    $move['original_heo_player'] = $lastMove['original_heo_player'];
                    $move['victim'] = $table['last_player'];
                    $plog = json_decode($table['penalty_log'] ?: '[]', true);
                    // Hủy phạt của người bị chặt cuối cùng
                    for ($i = count($plog) - 1; $i >= 0; $i--) {
                        if ($plog[$i]['status'] == 'active' && $plog[$i]['attacker'] == $table['last_player']) {
                            $plog[$i]['status'] = 'cancelled';
                            break;
                        }
                    }
                    $plog[] = ['id' => uniqid(), 'event' => 'de', 'victim' => $table['last_player'], 'attacker' => $currentPlayer['seat_index'], 'status' => 'active', 'amount' => 15 * ($move['type'] == 'double_quad' ? 2 : 1)];
                    $conn->query("UPDATE samloc_multi_tables SET penalty_log = '" . json_encode($plog) . "' WHERE id = $tableId");
                }
                
                $lastMoveStr = json_encode($move);
                $lp = $currentPlayer['seat_index'];
                
                foreach ($moveCards as $mc) {
                    foreach ($botCards as $idx => $hc) {
                        if ($hc['id'] === $mc['id']) {
                            array_splice($botCards, $idx, 1);
                            break;
                        }
                    }
                }
                $newCardsStr = json_encode($botCards);
                $conn->query("UPDATE samloc_multi_players SET cards = '$newCardsStr' WHERE id = {$currentPlayer['id']}");
                
                // Kiểm tra Đền Làng Báo Bài hoặc Xin Làng Fail
                if ($table['xin_lang_player'] !== null && $lp != $table['xin_lang_player']) {
                    processXinLangFail($tableId, $table['xin_lang_player'], $players, $table['min_bet'], $conn);
                    echo json_encode(['success' => true, 'reload' => true]); exit;
                }
                
                if (count($botCards) === 0) {
                    $denLangId = null;
                    if ($move['type'] == 'single') {
                        $lastMoveData = json_decode($table['last_move'], true);
                        if ($lastMoveData && $lastMoveData['type'] == 'single') {
                            $p1Seat = $table['last_player'];
                            $p1Player = getPlayerAtSeat($p1Seat, $players);
                            if ($p1Player) {
                                $p1Cards = json_decode($p1Player['cards'], true);
                                $p1PlayedValue = $lastMoveData['value'];
                                foreach ($p1Cards as $c) {
                                    if ($c['v'] > $p1PlayedValue) {
                                        $denLangId = $p1Player['id'];
                                        break;
                                    }
                                }
                            }
                        }
                    }
                    processWin($tableId, $currentPlayer['id'], $players, $table['min_bet'], $conn, false, '', $denLangId);
                    echo json_encode(['success' => true, 'reload' => true]);
                    exit;
                }
                
                $conn->query("UPDATE samloc_multi_tables SET last_move = '$lastMoveStr', last_player = $lp WHERE id = $tableId");
            } else {
                $passedList[] = $currentPlayer['seat_index'];
                $passedStr = json_encode($passedList);
                $conn->query("UPDATE samloc_multi_tables SET passed_players = '$passedStr' WHERE id = $tableId");
            }
            
            $nextTurn = getNextTurn($table['current_turn'], $players, $passedList);
            
            if (count($passedList) >= count($players) - 1) {
                $nextTurn = $table['last_player'] !== null ? $table['last_player'] : $nextTurn;
                $conn->query("UPDATE samloc_multi_tables SET passed_players = '[]', last_move = 'null' WHERE id = $tableId");
            }
            
            $nextExpiry = date('Y-m-d H:i:s', time() + 15);
            $conn->query("UPDATE samloc_multi_tables SET current_turn = $nextTurn, turn_expires_at = '$nextExpiry' WHERE id = $tableId");
            
            $stmt = $conn->prepare("SELECT * FROM samloc_multi_tables WHERE id = ?");
            $stmt->bind_param("i", $tableId);
            $stmt->execute();
            $table = $stmt->get_result()->fetch_assoc();
            
            $stmt = $conn->prepare("SELECT * FROM samloc_multi_players WHERE table_id = ? ORDER BY seat_index");
            $stmt->bind_param("i", $tableId);
            $stmt->execute();
            $players = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    }

    $resPlayers = [];
    $mySeat = -1;
    $myCards = [];
    
    $penaltyMap = [];
    $endNote = '';
    if ($table['status'] === 'ended') {
        $rawPassed = json_decode($table['passed_players'] ?: '[]', true);
        if (!empty($rawPassed) && is_string(array_key_first($rawPassed))) {
            $penaltyMap = $rawPassed;
            if (isset($penaltyMap['note'])) {
                $endNote = $penaltyMap['note'];
            }
        }
    }
    
    $winnerId = -1;
    if ($table['status'] === 'ended') {
        foreach ($players as $p) {
            if ($p['status'] == 'won') $winnerId = $p['id'];
        }
    }
    
    foreach ($players as $p) {
        $isMe = ($p['user_id'] == $userId);
        if ($isMe) {
            $mySeat = $p['seat_index'];
            $myCards = json_decode($p['cards'] ?: '[]', true);
        }
        $cards = json_decode($p['cards'] ?: '[]', true);
        
        $penalty = 0;
        if ($table['status'] === 'ended' && $p['id'] != $winnerId) {
            if (isset($penaltyMap[(string)$p['seat_index']])) {
                $penalty = $penaltyMap[(string)$p['seat_index']];
            } else {
                $cCount = count($cards);
                if ($cCount > 0) {
                    $penalty = ($cCount === 10) ? $table['min_bet'] * 15 : $cCount * $table['min_bet'];
                    foreach ($cards as $c) {
                        if ($c['v'] == 15) $penalty += $table['min_bet'] * 2;
                    }
                }
            }
        }
        
        $resPlayers[] = [
            'seat_index' => $p['seat_index'],
            'is_bot' => $p['is_bot'],
            'status' => $p['status'],
            'card_count' => is_array($cards) ? count($cards) : 0,
            'user_id' => $p['user_id'],
            'cards' => ($table['status'] === 'ended' || $isMe) ? $cards : null,
            'penalty' => $penalty
        ];
    }
    
    echo json_encode([
        'success' => true,
        'table' => [
            'id' => $table['id'],
            'room_name' => $table['room_name'],
            'status' => $table['status'],
            'current_turn' => (int)$table['current_turn'],
            'last_move' => json_decode($table['last_move'] ?: 'null', true),
            'last_player' => $table['last_player'],
            'passed_players' => ($table['status'] === 'ended') ? [] : json_decode($table['passed_players'] ?: '[]', true),
            'timeLeft' => $table['turn_expires_at'] ? max(0, strtotime($table['turn_expires_at']) - time()) : 0,
            'endNote' => $endNote
        ],
        'players' => $resPlayers,
        'my_seat' => $mySeat,
        'my_cards' => $myCards
    ]);
    exit;
}

if ($action === 'join') {
    if ($table['status'] !== 'waiting') {
        echo json_encode(['success' => false, 'message' => 'Phòng đang chơi']);
        exit;
    }
    foreach ($players as $p) {
        if ($p['user_id'] == $userId) {
            echo json_encode(['success' => true]); 
            exit;
        }
    }
    if (count($players) >= 5) {
        echo json_encode(['success' => false, 'message' => 'Phòng đã đầy']);
        exit;
    }
    $takenSeats = array_column($players, 'seat_index');
    $seatIndex = 0;
    for ($i=0; $i<5; $i++) {
        if (!in_array($i, $takenSeats)) { $seatIndex = $i; break; }
    }
    $stmt = $conn->prepare("INSERT INTO samloc_multi_players (table_id, user_id, seat_index, cards) VALUES (?, ?, ?, '[]')");
    $stmt->bind_param("iii", $tableId, $userId, $seatIndex);
    $stmt->execute();
    if (count($players) + 1 >= 2) {
        $nextExpiry = date('Y-m-d H:i:s', time() + 5);
        $conn->query("UPDATE samloc_multi_tables SET turn_expires_at = '$nextExpiry' WHERE id = $tableId");
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'add_bot') {
    if ($table['status'] !== 'waiting' || count($players) >= 5) {
        echo json_encode(['success' => false]);
        exit;
    }
    $takenSeats = array_column($players, 'seat_index');
    $seatIndex = 0;
    for ($i=0; $i<5; $i++) {
        if (!in_array($i, $takenSeats)) { $seatIndex = $i; break; }
    }
    $botId = -rand(1000, 9999);
    $stmt = $conn->prepare("INSERT INTO samloc_multi_players (table_id, user_id, seat_index, cards, is_bot) VALUES (?, ?, ?, '[]', 1)");
    $stmt->bind_param("iii", $tableId, $botId, $seatIndex);
    $stmt->execute();
    
    if (count($players) + 1 >= 2) {
        $nextExpiry = date('Y-m-d H:i:s', time() + 5);
        $conn->query("UPDATE samloc_multi_tables SET turn_expires_at = '$nextExpiry' WHERE id = $tableId");
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'xin_lang') {
    if ($table['status'] !== 'xin_lang') {
        echo json_encode(['success' => false, 'message' => 'Hết thời gian xin làng']);
        exit;
    }
    $myPlayer = null;
    foreach ($players as $p) {
        if ($p['user_id'] == $userId) $myPlayer = $p;
    }
    if (!$myPlayer) exit;
    
    // Bài đã chia rồi, người chơi Hô Sâm nhận luôn quyền đánh đầu và vào ván đấu
    $startingSeat = (int)$myPlayer['seat_index'];
    $nextExpiry = date('Y-m-d H:i:s', time() + 15);
    $conn->query("UPDATE samloc_multi_tables SET status = 'playing', current_turn = $startingSeat, passed_players = '[]', last_move = 'null', last_player = null, penalty_log = '[]', xin_lang_player = $startingSeat, turn_expires_at = '$nextExpiry' WHERE id = $tableId");
    
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'skip_xin_lang') {
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'play') {
    if ($table['status'] !== 'playing') {
        echo json_encode(['success' => false, 'message' => 'Phòng chưa chơi']);
        exit;
    }
    $myPlayer = null;
    foreach ($players as $p) {
        if ($p['user_id'] == $userId) $myPlayer = $p;
    }
    if (!$myPlayer || $table['current_turn'] != $myPlayer['seat_index']) {
        echo json_encode(['success' => false, 'message' => 'Chưa tới lượt']);
        exit;
    }
    
    $passedList = json_decode($table['passed_players'], true);
    if (in_array($myPlayer['seat_index'], $passedList)) {
        echo json_encode(['success' => false, 'message' => 'Đã bỏ lượt, đợi vòng mới!']);
        exit;
    }
    
    $selectedIds = $_POST['cards'] ?? []; 
    $botCards = json_decode($myPlayer['cards'], true);
    
    $selectedCards = [];
    foreach ($selectedIds as $id) {
        foreach ($botCards as $c) {
            if ($c['id'] === $id) { $selectedCards[] = $c; break; }
        }
    }
    
    $move = getMoveType($selectedCards);
    $lastMove = json_decode($table['last_move'], true);
    
    $activeCount = 0;
    foreach ($players as $p) {
        if ($p['status'] !== 'won' && !in_array($p['seat_index'], $passedList)) $activeCount++;
    }
    
    if ($activeCount <= 1 && !in_array($myPlayer['seat_index'], $passedList)) {
        $lastMove = null; 
    }
    
    if (!$move || !canBeat($move, $lastMove)) {
        echo json_encode(['success' => false, 'message' => 'Nước đi không hợp lệ']);
        exit;
    }
    
    $move['cards'] = $selectedCards; 
    
    if ($lastMove && $lastMove['type'] == 'single' && $lastMove['value'] == 15 && $move['type'] == 'quad') {
        $move['action'] = 'chat_heo';
        $move['original_heo_player'] = $table['last_player'];
        $move['victim'] = $table['last_player'];
        $plog = json_decode($table['penalty_log'] ?: '[]', true);
        $plog[] = ['id' => uniqid(), 'event' => 'chat_heo', 'victim' => $table['last_player'], 'attacker' => $myPlayer['seat_index'], 'status' => 'active', 'amount' => 15];
        $conn->query("UPDATE samloc_multi_tables SET penalty_log = '" . json_encode($plog) . "' WHERE id = $tableId");
    } else if ($lastMove && $lastMove['type'] == 'pair' && $lastMove['value'] == 15 && $move['type'] == 'double_quad') {
        $move['action'] = 'chat_doi_heo';
        $move['original_heo_player'] = $table['last_player'];
        $move['victim'] = $table['last_player'];
        $plog = json_decode($table['penalty_log'] ?: '[]', true);
        $plog[] = ['id' => uniqid(), 'event' => 'chat_doi_heo', 'victim' => $table['last_player'], 'attacker' => $myPlayer['seat_index'], 'status' => 'active', 'amount' => 30];
        $conn->query("UPDATE samloc_multi_tables SET penalty_log = '" . json_encode($plog) . "' WHERE id = $tableId");
    } else if ($lastMove && in_array($lastMove['type'], ['quad', 'double_quad']) && in_array($move['type'], ['quad', 'double_quad'])) {
        $move['action'] = 'de';
        $move['original_heo_player'] = $lastMove['original_heo_player'];
        $move['victim'] = $table['last_player'];
        $plog = json_decode($table['penalty_log'] ?: '[]', true);
        for ($i = count($plog) - 1; $i >= 0; $i--) {
            if ($plog[$i]['status'] == 'active' && $plog[$i]['attacker'] == $table['last_player']) {
                $plog[$i]['status'] = 'cancelled';
                break;
            }
        }
        $plog[] = ['id' => uniqid(), 'event' => 'de', 'victim' => $table['last_player'], 'attacker' => $myPlayer['seat_index'], 'status' => 'active', 'amount' => 15 * ($move['type'] == 'double_quad' ? 2 : 1)];
        $conn->query("UPDATE samloc_multi_tables SET penalty_log = '" . json_encode($plog) . "' WHERE id = $tableId");
    }
    
    $lastMoveStr = json_encode($move);
    foreach ($selectedCards as $mc) {
        foreach ($botCards as $idx => $hc) {
            if ($hc['id'] === $mc['id']) {
                array_splice($botCards, $idx, 1);
                break;
            }
        }
    }
    
    $newCardsStr = json_encode($botCards);
    $conn->query("UPDATE samloc_multi_players SET cards = '$newCardsStr' WHERE id = {$myPlayer['id']}");
    
    if ($table['xin_lang_player'] !== null && $myPlayer['seat_index'] != $table['xin_lang_player']) {
        processXinLangFail($tableId, $table['xin_lang_player'], $players, $table['min_bet'], $conn);
        echo json_encode(['success' => true]); exit;
    }
    
    if (count($botCards) === 0) {
        $denLangId = null;
        if ($move['type'] == 'single') {
            $lastMoveData = json_decode($table['last_move'], true);
            if ($lastMoveData && $lastMoveData['type'] == 'single') {
                $p1Seat = $table['last_player'];
                $p1Player = getPlayerAtSeat($p1Seat, $players);
                if ($p1Player) {
                    $p1Cards = json_decode($p1Player['cards'], true);
                    $p1PlayedValue = $lastMoveData['value'];
                    foreach ($p1Cards as $c) {
                        if ($c['v'] > $p1PlayedValue) {
                            $denLangId = $p1Player['id'];
                            break;
                        }
                    }
                }
            }
        }
        processWin($tableId, $myPlayer['id'], $players, $table['min_bet'], $conn, false, '', $denLangId);
        echo json_encode(['success' => true]);
        exit;
    }
    
    $lp = $myPlayer['seat_index'];
    $conn->query("UPDATE samloc_multi_tables SET last_move = '$lastMoveStr', last_player = $lp WHERE id = $tableId");
    
    $nextTurn = getNextTurn($table['current_turn'], $players, $passedList);
    
    $nextExpiry = date('Y-m-d H:i:s', time() + 15);
    $conn->query("UPDATE samloc_multi_tables SET current_turn = $nextTurn, turn_expires_at = '$nextExpiry' WHERE id = $tableId");
    
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'pass') {
    if ($table['status'] !== 'playing') {
        echo json_encode(['success' => false, 'message' => 'Phòng chưa chơi']);
        exit;
    }
    
    $seatIndex = -1;
    if (isset($_REQUEST['force_seat'])) {
        $seatIndex = (int)$_REQUEST['force_seat'];
    } else {
        foreach ($players as $p) {
            if ($p['user_id'] == $userId) $seatIndex = $p['seat_index'];
        }
    }
    
    if ($seatIndex === -1 || $table['current_turn'] != $seatIndex) {
        echo json_encode(['success' => false, 'message' => 'Không tới lượt bạn']);
        exit;
    }
    
    $lastMove = json_decode($table['last_move'], true);
    if (!$lastMove) {
        echo json_encode(['success' => false, 'message' => 'Bạn đánh đầu, không thể bỏ qua']);
        exit;
    }
    
    $passedList = json_decode($table['passed_players'], true);
    $passedList[] = $seatIndex;
    $passedStr = json_encode($passedList);
    $conn->query("UPDATE samloc_multi_tables SET passed_players = '$passedStr' WHERE id = $tableId");
    
    $nextTurn = getNextTurn($seatIndex, $players, $passedList);
    
    if (count($passedList) >= count($players) - 1) {
        $nextTurn = $table['last_player'] !== null ? $table['last_player'] : $nextTurn;
        $conn->query("UPDATE samloc_multi_tables SET passed_players = '[]', last_move = 'null' WHERE id = $tableId");
    }
    
    $nextExpiry = date('Y-m-d H:i:s', time() + 15);
    $conn->query("UPDATE samloc_multi_tables SET current_turn = $nextTurn, turn_expires_at = '$nextExpiry' WHERE id = $tableId");
    
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'leave') {
    if ($table['status'] !== 'playing') {
        $conn->query("DELETE FROM samloc_multi_players WHERE table_id = $tableId AND user_id = $userId");
        echo json_encode(['success' => true]);
        exit;
    }
    
    $leavingPlayer = null;
    foreach ($players as $p) {
        if ($p['user_id'] == $userId && !$p['is_bot']) {
            $leavingPlayer = $p;
        }
    }
    
    if ($leavingPlayer) {
        $cards = json_decode($leavingPlayer['cards'], true);
        $cCount = is_array($cards) ? count($cards) : 0;
        $heoCount = 0;
        foreach (($cards ?: []) as $c) {
            if ($c['v'] == 15) $heoCount++;
        }
        $penalty = ($cCount === 10) ? $table['min_bet'] * 15 : $cCount * $table['min_bet'];
        $penalty += $heoCount * ($table['min_bet'] * 2);
        
        if ($penalty > 0) {
            $conn->query("UPDATE users SET Money = Money - $penalty WHERE Iduser = $userId");
            
            $remaining = array_filter($players, function($p) use ($userId) {
                return $p['user_id'] != $userId && !$p['is_bot'];
            });
            if (count($remaining) > 0) {
                $share = floor($penalty / count($remaining));
                foreach ($remaining as $r) {
                    $conn->query("UPDATE users SET Money = Money + $share WHERE Iduser = {$r['user_id']}");
                }
            }
        }
        
        $botId = -rand(10000, 99999);
        $conn->query("UPDATE samloc_multi_players SET user_id = $botId, is_bot = 1 WHERE id = {$leavingPlayer['id']}");
    }
    
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>

<?php
require_once __DIR__ . '/db_connect.php';

class TournamentBracketHelper {
    public static function startTournament(mysqli $conn, int $tourId) {
        $stmt = $conn->prepare("SELECT * FROM tournament_brackets WHERE id = ?");
        $stmt->bind_param("i", $tourId);
        $stmt->execute();
        $tour = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$tour) return false;

        $stmtP = $conn->prepare("SELECT user_id FROM tournament_bracket_participants WHERE tournament_id = ? ORDER BY RAND()");
        $stmtP->bind_param("i", $tourId);
        $stmtP->execute();
        $participants = $stmtP->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtP->close();

        // Generate Round 1 matches
        $slots = (int)$tour['slots'];
        $rounds = log($slots, 2);
        
        $matchIndex = 0;
        for ($i = 0; $i < $slots; $i += 2) {
            $p1 = $participants[$i]['user_id'] ?? null;
            $p2 = $participants[$i+1]['user_id'] ?? null;
            
            $stmtM = $conn->prepare("INSERT INTO tournament_matches (tournament_id, round, match_index, player1_id, player2_id, status) VALUES (?, 1, ?, ?, ?, 'pending')");
            $stmtM->bind_param("iiii", $tourId, $matchIndex, $p1, $p2);
            $stmtM->execute();
            $stmtM->close();
            $matchIndex++;
        }

        $stmtUp = $conn->prepare("UPDATE tournament_brackets SET status = 'active' WHERE id = ?");
        $stmtUp->bind_param("i", $tourId);
        $stmtUp->execute();
        $stmtUp->close();
        return true;
    }

    public static function resolveMatch(mysqli $conn, int $matchId, int $winnerId) {
        $stmt = $conn->prepare("SELECT * FROM tournament_matches WHERE id = ?");
        $stmt->bind_param("i", $matchId);
        $stmt->execute();
        $match = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$match || $match['status'] === 'finished') return false;

        $tourId = (int)$match['tournament_id'];
        $round = (int)$match['round'];
        $matchIndex = (int)$match['match_index'];

        // 1. Update winner
        $stmtW = $conn->prepare("UPDATE tournament_matches SET winner_id = ?, status = 'finished' WHERE id = ?");
        $stmtW->bind_param("ii", $winnerId, $matchId);
        $stmtW->execute();
        $stmtW->close();

        // 2. Advance to next round
        $nextRound = $round + 1;
        $nextMatchIndex = (int)floor($matchIndex / 2);
        $isPlayer1InNext = ($matchIndex % 2 === 0);

        // Check if next match exists
        $stmtN = $conn->prepare("SELECT id FROM tournament_matches WHERE tournament_id = ? AND round = ? AND match_index = ?");
        $stmtN->bind_param("iii", $tourId, $nextRound, $nextMatchIndex);
        $stmtN->execute();
        $checkNext = $stmtN->get_result()->fetch_assoc();
        $stmtN->close();
        
        if ($checkNext) {
            $nextMatchId = (int)$checkNext['id'];
            $colName = $isPlayer1InNext ? 'player1_id' : 'player2_id';
            $stmtNextUp = $conn->prepare("UPDATE tournament_matches SET {$colName} = ? WHERE id = ?");
            $stmtNextUp->bind_param("ii", $winnerId, $nextMatchId);
            $stmtNextUp->execute();
            $stmtNextUp->close();
        } else {
            // Create next match if this is not the final
            $stmtT = $conn->prepare("SELECT slots FROM tournament_brackets WHERE id = ?");
            $stmtT->bind_param("i", $tourId);
            $stmtT->execute();
            $tour = $stmtT->get_result()->fetch_assoc();
            $stmtT->close();

            $totalRounds = log((int)$tour['slots'], 2);
            
            if ($round < $totalRounds) {
                if ($isPlayer1InNext) {
                    $stmtInsM = $conn->prepare("INSERT INTO tournament_matches (tournament_id, round, match_index, player1_id, status) VALUES (?, ?, ?, ?, 'pending')");
                } else {
                    $stmtInsM = $conn->prepare("INSERT INTO tournament_matches (tournament_id, round, match_index, player2_id, status) VALUES (?, ?, ?, ?, 'pending')");
                }
                $stmtInsM->bind_param("iiii", $tourId, $nextRound, $nextMatchIndex, $winnerId);
                $stmtInsM->execute();
                $stmtInsM->close();
            } else {
                // Final match finished, tournament ended
                $stmtFin = $conn->prepare("UPDATE tournament_brackets SET status = 'finished' WHERE id = ?");
                $stmtFin->bind_param("i", $tourId);
                $stmtFin->execute();
                $stmtFin->close();
            }
        }
        return true;
    }
}
?>

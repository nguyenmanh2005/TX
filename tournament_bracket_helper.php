<?php
require_once 'db_connect.php';

class TournamentBracketHelper {
    public static function startTournament(mysqli $conn, int $tourId) {
        $tour = $conn->query("SELECT * FROM tournament_brackets WHERE id = $tourId")->fetch_assoc();
        if (!$tour) return false;

        $participants = $conn->query("SELECT user_id FROM tournament_bracket_participants WHERE tournament_id = $tourId ORDER BY RAND()")->fetch_all(MYSQLI_ASSOC);
        
        if (count($participants) < $tour['slots']) {
            // Fill with bots if needed
            // (In a real scenario, we might wait for more humans or have bots join earlier)
        }

        // Generate Round 1 matches
        $slots = (int)$tour['slots'];
        $rounds = log($slots, 2);
        
        $matchIndex = 0;
        for ($i = 0; $i < $slots; $i += 2) {
            $p1 = $participants[$i]['user_id'] ?? null;
            $p2 = $participants[$i+1]['user_id'] ?? null;
            
            $stmt = $conn->prepare("INSERT INTO tournament_matches (tournament_id, round, match_index, player1_id, player2_id, status) VALUES (?, 1, ?, ?, ?, 'pending')");
            $stmt->bind_param("iiii", $tourId, $matchIndex, $p1, $p2);
            $stmt->execute();
            $matchIndex++;
        }

        $conn->query("UPDATE tournament_brackets SET status = 'active' WHERE id = $tourId");
        return true;
    }

    public static function resolveMatch(mysqli $conn, int $matchId, int $winnerId) {
        $match = $conn->query("SELECT * FROM tournament_matches WHERE id = $matchId")->fetch_assoc();
        if (!$match || $match['status'] === 'finished') return false;

        $tourId = $match['tournament_id'];
        $round = $match['round'];
        $matchIndex = $match['match_index'];

        // 1. Update winner
        $conn->query("UPDATE tournament_matches SET winner_id = $winnerId, status = 'finished' WHERE id = $matchId");

        // 2. Advance to next round
        $nextRound = $round + 1;
        $nextMatchIndex = floor($matchIndex / 2);
        $isPlayer1InNext = ($matchIndex % 2 === 0);

        // Check if next match exists
        $checkNext = $conn->query("SELECT id FROM tournament_matches WHERE tournament_id = $tourId AND round = $nextRound AND match_index = $nextMatchIndex")->fetch_assoc();
        
        if ($checkNext) {
            $nextMatchId = $checkNext['id'];
            $col = $isPlayer1InNext ? 'player1_id' : 'player2_id';
            $conn->query("UPDATE tournament_matches SET $col = $winnerId WHERE id = $nextMatchId");
        } else {
            // Create next match if this is not the final
            $tour = $conn->query("SELECT slots FROM tournament_brackets WHERE id = $tourId")->fetch_assoc();
            $totalRounds = log($tour['slots'], 2);
            
            if ($round < $totalRounds) {
                $col = $isPlayer1InNext ? 'player1_id' : 'player2_id';
                $stmt = $conn->prepare("INSERT INTO tournament_matches (tournament_id, round, match_index, $col, status) VALUES (?, ?, ?, ?, 'pending')");
                $stmt->bind_param("iiii", $tourId, $nextRound, $nextMatchIndex, $winnerId);
                $stmt->execute();
            } else {
                // Final match finished, tournament ended
                $conn->query("UPDATE tournament_brackets SET status = 'finished' WHERE id = $tourId");
                // Prize logic here...
            }
        }
        return true;
    }
}
?>

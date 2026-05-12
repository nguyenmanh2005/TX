<?php
session_start();
require_once 'db_connect.php';

$action = $_GET['action'] ?? '';
$userId = $_SESSION['Iduser'] ?? 0;

header('Content-Type: application/json');

switch ($action) {
    case 'get_social_data':
        // 1. Top 3 Guilds trong tuần
        $guildsSql = "SELECT g.name, g.tag, s.points, s.wins
                     FROM guild_weekly_stats s
                     JOIN guilds g ON s.guild_id = g.id
                     ORDER BY s.points DESC LIMIT 3";
        $guildsRes = $conn->query($guildsSql);
        $topGuilds = [];
        while($row = $guildsRes->fetch_assoc()) {
            $topGuilds[] = $row;
        }

        // 2. Live Win Ticker (Big wins > 1M)
        $winsSql = "SELECT u.Name, h.game_name, h.win_amount 
                   FROM game_history h
                   JOIN users u ON h.user_id = u.Iduser
                   WHERE h.win_amount >= 1000000
                   ORDER BY h.played_at DESC LIMIT 10";
        $winsRes = $conn->query($winsSql);
        $liveWins = [];
        while($row = $winsRes->fetch_assoc()) {
            $liveWins[] = $row;
        }

        // 3. Current User Challenges (Pending)
        $challenges = [];
        if ($userId) {
            $challengeSql = "SELECT c.id, u.Name as challenger_name, c.game_type, c.bet_amount
                            FROM pvp_challenges c
                            JOIN users u ON c.challenger_id = u.Iduser
                            WHERE c.opponent_id = ? AND c.status = 'pending'
                            ORDER BY c.created_at DESC LIMIT 5";
            $stmt = $conn->prepare($challengeSql);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            while($row = $res->fetch_assoc()) {
                $challenges[] = $row;
            }
            $stmt->close();
        }

        echo json_encode([
            'success' => true,
            'top_guilds' => $topGuilds,
            'live_wins' => $liveWins,
            'challenges' => $challenges
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Action not found']);
}

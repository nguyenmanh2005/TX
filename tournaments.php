<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['Iduser'];
$tourId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Lấy danh sách giải đấu
if ($tourId === 0) {
    $tours = $conn->query("SELECT * FROM tournament_brackets ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
} else {
    $tour = $conn->query("SELECT * FROM tournament_brackets WHERE id = $tourId")->fetch_assoc();
    $matches = $conn->query("
        SELECT m.*, u1.Name as p1_name, u2.Name as p2_name, w.Name as winner_name
        FROM tournament_matches m
        LEFT JOIN users u1 ON m.player1_id = u1.Iduser
        LEFT JOIN users u2 ON m.player2_id = u2.Iduser
        LEFT JOIN users w ON m.winner_id = w.Iduser
        WHERE m.tournament_id = $tourId
        ORDER BY m.round, m.match_index
    ")->fetch_all(MYSQLI_ASSOC);
    
    // Group matches by round
    $rounds = [];
    foreach ($matches as $m) {
        $rounds[$m['round']][] = $m;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Giải Đấu Bracket</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #3498db; --bg: #f4f7f6; --text: #2c3e50; }
        body { font-family: 'Outfit', sans-serif; background: var(--bg); color: var(--text); padding: 20px; }
        .bracket-container { display: flex; gap: 40px; justify-content: center; overflow-x: auto; padding: 40px 0; }
        .round { display: flex; flex-direction: column; justify-content: space-around; gap: 20px; }
        .match { 
            background: white; 
            border: 2px solid #ddd; 
            border-radius: 8px; 
            width: 200px; 
            overflow: hidden; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            transition: 0.3s;
        }
        .match:hover { transform: scale(1.05); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .player { padding: 10px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; font-size: 14px; }
        .player.winner { background: #e8f5e9; color: #2e7d32; font-weight: bold; }
        .player.loser { color: #999; text-decoration: line-through; }
        .round-title { text-align: center; font-weight: bold; margin-bottom: 20px; color: var(--primary); text-transform: uppercase; letter-spacing: 1px; }
        .tour-list { max-width: 800px; margin: 40px auto; }
        .tour-card { background: white; padding: 20px; border-radius: 15px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; border-left: 5px solid var(--primary); }
        .btn { padding: 10px 20px; background: var(--primary); color: white; border-radius: 8px; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

<?php if ($tourId === 0): ?>
    <div class="tour-list">
        <h1>🏆 Danh sách giải đấu Bracket</h1>
        <?php foreach ($tours as $t): ?>
            <div class="tour-card">
                <div>
                    <h3 style="margin: 0;"><?= htmlspecialchars($t['name']) ?></h3>
                    <small>Slots: <?= $t['slots'] ?> | Thưởng: <?= number_format($t['prize_pool']) ?> GTLM</small>
                </div>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <span style="font-size: 12px; font-weight: bold; color: #777;"><?= strtoupper($t['status']) ?></span>
                    <a href="tournaments.php?id=<?= $t['id'] ?>" class="btn">XEM NHÁNH</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div style="text-align: center;">
        <a href="tournaments.php" style="text-decoration: none; color: #777;"><i class="fas fa-arrow-left"></i> Quay lại</a>
        <h1>Giải đấu: <?= htmlspecialchars($tour['name']) ?></h1>
    </div>

    <div class="bracket-container">
        <?php foreach ($rounds as $rNum => $rMatches): ?>
            <div class="round">
                <div class="round-title">Vòng <?= $rNum ?></div>
                <?php foreach ($rMatches as $m): ?>
                    <div class="match">
                        <div class="player <?= $m['winner_id'] == $m['player1_id'] && $m['player1_id'] ? 'winner' : ($m['winner_id'] && $m['winner_id'] != $m['player1_id'] ? 'loser' : '') ?>">
                            <span><?= htmlspecialchars($m['p1_name'] ?? 'TBD') ?></span>
                            <span><?= $m['score1'] ?></span>
                        </div>
                        <div class="player <?= $m['winner_id'] == $m['player2_id'] && $m['player2_id'] ? 'winner' : ($m['winner_id'] && $m['winner_id'] != $m['player2_id'] ? 'loser' : '') ?>">
                            <span><?= htmlspecialchars($m['p2_name'] ?? 'TBD') ?></span>
                            <span><?= $m['score2'] ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

</body>
</html>

<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['Iduser'];
$tourId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Helper: Hàm tạo Avatar Fallback giống Chat
function getAvatar($url, $name) {
    if (!empty($url) && strpos($url, 'images.ico') === false) return htmlspecialchars($url);
    $initials = mb_substr(trim($name), 0, 2, 'UTF-8');
    return "https://ui-avatars.com/api/?name=" . urlencode($initials) . "&background=random&color=fff";
}

// Lấy danh sách giải đấu
if ($tourId === 0) {
    $tours = $conn->query("SELECT * FROM tournament_brackets ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
} else {
    $tour = $conn->query("SELECT * FROM tournament_brackets WHERE id = $tourId")->fetch_assoc();
    $matches = $conn->query("
        SELECT m.*, 
               u1.Name as p1_name, u1.Avatar as p1_avatar, 
               u2.Name as p2_name, u2.Avatar as p2_avatar, 
               w.Name as winner_name
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giải Đấu Tuyệt Đỉnh (Bracket)</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=Space+Mono:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { 
            --bg: #020617; 
            --surface: #0f172a;
            --primary: #38bdf8; 
            --accent: #f59e0b;
            --text: #f8fafc; 
            --muted: #64748b;
        }
        body { font-family: 'Outfit', sans-serif; background: var(--bg); color: var(--text); padding: 40px 20px; margin: 0; min-height: 100vh; }
        
        /* Glass Header */
        .header-title { text-align: center; margin-bottom: 40px; }
        .header-title h1 { font-size: 2.5rem; font-weight: 800; background: linear-gradient(90deg, #38bdf8, #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin: 10px 0; }
        .header-title p { color: var(--muted); font-size: 1.1rem; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; color: var(--muted); text-decoration: none; padding: 10px 20px; border-radius: 12px; background: rgba(255,255,255,0.05); transition: 0.2s; }
        .btn-back:hover { background: rgba(255,255,255,0.1); color: #fff; }

        /* Tournament List */
        .tour-list { max-width: 800px; margin: 0 auto; }
        .tour-card { background: var(--surface); padding: 25px; border-radius: 16px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; border: 1px solid rgba(255,255,255,0.05); border-left: 6px solid var(--primary); transition: 0.3s; }
        .tour-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(56, 189, 248, 0.1); border-color: rgba(56, 189, 248, 0.3); }
        .tour-info h3 { margin: 0 0 8px 0; font-size: 1.4rem; color: #fff; }
        .tour-meta { color: var(--muted); font-size: 0.95rem; display: flex; gap: 15px; }
        .tour-meta span { display: flex; align-items: center; gap: 5px; }
        .tour-meta i { color: var(--accent); }
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; }
        .badge.ongoing { background: rgba(52, 211, 153, 0.1); color: #34d399; }
        .badge.finished { background: rgba(148, 163, 184, 0.1); color: #94a3b8; }
        .btn { padding: 10px 24px; background: linear-gradient(135deg, var(--primary), #2563eb); color: white; border-radius: 12px; text-decoration: none; font-weight: 600; transition: 0.2s; border: none; cursor: pointer; }
        .btn:hover { box-shadow: 0 0 20px rgba(56, 189, 248, 0.4); transform: scale(1.05); }

        /* Bracket Premium */
        .bracket-wrapper { background: url('https://www.transparenttextures.com/patterns/cubes.png'); border-radius: 20px; padding: 40px; overflow-x: auto; border: 1px solid rgba(255,255,255,0.05); margin-top: 20px; }
        .bracket-container { display: flex; align-items: center; justify-content: flex-start; gap: 60px; min-width: max-content; }
        .round { display: flex; flex-direction: column; justify-content: center; gap: 40px; position: relative; }
        
        /* Lines */
        .round:not(:last-child)::after { content: ''; position: absolute; right: -30px; top: 0; bottom: 0; width: 2px; background: rgba(255,255,255,0.05); }

        .round-title { text-align: center; font-weight: 800; font-size: 1.2rem; color: var(--primary); text-transform: uppercase; letter-spacing: 2px; margin-bottom: 20px; text-shadow: 0 0 10px rgba(56, 189, 248, 0.3); }
        
        .match { 
            background: rgba(15, 23, 42, 0.8); 
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1); 
            border-radius: 12px; 
            width: 260px; 
            overflow: hidden; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            transition: 0.3s;
            position: relative;
        }
        .match:hover { border-color: var(--primary); box-shadow: 0 0 30px rgba(56, 189, 248, 0.2); transform: scale(1.02); z-index: 10; }
        
        .player { padding: 12px 15px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.05); transition: 0.2s; }
        .player:last-child { border-bottom: none; }
        
        .player.winner { background: linear-gradient(90deg, rgba(34, 197, 94, 0.15) 0%, transparent 100%); border-left: 4px solid #22c55e; }
        .player.winner .p-name { color: #4ade80; font-weight: 700; text-shadow: 0 0 10px rgba(74, 222, 128, 0.2); }
        .player.winner .score { color: #4ade80; }
        
        .player.loser { opacity: 0.5; }
        .player.loser .p-name { text-decoration: line-through; }
        
        .p-info { display: flex; align-items: center; gap: 12px; }
        .p-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(255,255,255,0.1); }
        .p-name { font-size: 0.95rem; color: #e2e8f0; font-weight: 500; }
        
        .score { font-family: 'Space Mono', monospace; font-size: 1.1rem; font-weight: 700; color: #94a3b8; }
        
        /* Glow for Final Match */
        .round:last-child .match { border: 1px solid var(--accent); box-shadow: 0 0 40px rgba(245, 158, 11, 0.2); }
        .round:last-child .round-title { color: var(--accent); text-shadow: 0 0 10px rgba(245, 158, 11, 0.4); }
    </style>
</head>
<body>

<?php if ($tourId === 0): ?>
    <div class="header-title">
        <h1>🏆 ĐẤU TRƯỜNG HUYỀN THOẠI</h1>
        <p>Danh sách các giải đấu căng thẳng nhất Vũ Trụ GTLM</p>
    </div>

    <div class="tour-list">
        <?php if (empty($tours)): ?>
            <div style="text-align: center; color: var(--muted); padding: 50px;">Hiện chưa có giải đấu Bracket nào được tổ chức.</div>
        <?php else: ?>
            <?php foreach ($tours as $t): ?>
                <div class="tour-card">
                    <div class="tour-info">
                        <h3><?= htmlspecialchars($t['name']) ?></h3>
                        <div class="tour-meta">
                            <span><i class="fa fa-users"></i> <?= $t['slots'] ?> Tuyển thủ</span>
                            <span><i class="fa fa-trophy"></i> <?= number_format($t['prize_pool']) ?> GTLM</span>
                        </div>
                    </div>
                    <div style="display: flex; gap: 15px; align-items: center;">
                        <span class="badge <?= strtolower($t['status']) ?>"><?= $t['status'] === 'Ongoing' ? 'ĐANG DIỄN RA' : ($t['status'] === 'Finished' ? 'ĐÃ KẾT THÚC' : strtoupper($t['status'])) ?></span>
                        <a href="tournaments.php?id=<?= $t['id'] ?>" class="btn"><i class="fa fa-sitemap"></i> Nhánh Đấu</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<?php else: ?>
    <?php if (!$tour): ?>
        <h2 style="text-align:center;">Giải đấu không tồn tại!</h2>
    <?php else: ?>
        <div class="header-title">
            <a href="tournaments.php" class="btn-back"><i class="fa fa-arrow-left"></i> Trở về</a>
            <h1><?= htmlspecialchars($tour['name']) ?></h1>
            <p>Tổng Thưởng: <span style="color:var(--accent);font-weight:700;"><?= number_format($tour['prize_pool']) ?> GTLM</span></p>
        </div>

        <div class="bracket-wrapper">
            <div class="bracket-container">
                <?php foreach ($rounds as $rNum => $rMatches): ?>
                    <div class="round">
                        <div class="round-title">
                            <?= ($rNum == count($rounds)) ? '🏆 Chung Kết' : (($rNum == count($rounds)-1) ? 'Bán Kết' : 'Vòng ' . $rNum) ?>
                        </div>
                        <?php foreach ($rMatches as $m): ?>
                            <div class="match">
                                <!-- Player 1 -->
                                <?php
                                    $p1isWinner = ($m['winner_id'] == $m['player1_id'] && $m['player1_id'] != null);
                                    $p1isLoser = ($m['winner_id'] != null && !$p1isWinner);
                                    $p1AvatarUrl = getAvatar($m['p1_avatar'] ?? '', $m['p1_name'] ?? '?');
                                ?>
                                <div class="player <?= $p1isWinner ? 'winner' : ($p1isLoser ? 'loser' : '') ?>">
                                    <div class="p-info">
                                        <img src="<?= $p1AvatarUrl ?>" class="p-avatar" alt="avt">
                                        <span class="p-name"><?= htmlspecialchars($m['p1_name'] ?? 'TBD') ?></span>
                                    </div>
                                    <span class="score"><?= $m['score1'] ?></span>
                                </div>
                                
                                <!-- Player 2 -->
                                <?php
                                    $p2isWinner = ($m['winner_id'] == $m['player2_id'] && $m['player2_id'] != null);
                                    $p2isLoser = ($m['winner_id'] != null && !$p2isWinner);
                                    $p2AvatarUrl = getAvatar($m['p2_avatar'] ?? '', $m['p2_name'] ?? '?');
                                ?>
                                <div class="player <?= $p2isWinner ? 'winner' : ($p2isLoser ? 'loser' : '') ?>">
                                    <div class="p-info">
                                        <img src="<?= $p2AvatarUrl ?>" class="p-avatar" alt="avt">
                                        <span class="p-name"><?= htmlspecialchars($m['p2_name'] ?? 'TBD') ?></span>
                                    </div>
                                    <span class="score"><?= $m['score2'] ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

</body>
</html>

<?php
/**
 * ⚔️ PvP Arena - Đấu Trường Trực Tuyến
 * Nơi diễn ra các trận thách đấu giữa các cao thủ Trận Địa.
 */
require_once 'db_connect.php';
require_once 'admin_helper.php';
session_start();

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['Iduser'];
$challengeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isAdmin = isAdmin($conn, $userId);

// Lấy thông tin trận đấu
if ($isAdmin) {
    $stmt = $conn->prepare("
        SELECT c.*, 
               u1.Name as challenger_name, u1.ImageURL as challenger_avatar,
               u2.Name as challenged_name, u2.ImageURL as challenged_avatar
        FROM pvp_challenges c
        JOIN users u1 ON c.challenger_id = u1.Iduser
        JOIN users u2 ON c.opponent_id = u2.Iduser
        WHERE c.id = ?
    ");
    $stmt->bind_param("i", $challengeId);
} else {
    $stmt = $conn->prepare("
        SELECT c.*, 
               u1.Name as challenger_name, u1.ImageURL as challenger_avatar,
               u2.Name as challenged_name, u2.ImageURL as challenged_avatar
        FROM pvp_challenges c
        JOIN users u1 ON c.challenger_id = u1.Iduser
        JOIN users u2 ON c.opponent_id = u2.Iduser
        WHERE c.id = ? AND (c.challenger_id = ? OR c.opponent_id = ?)
    ");
    $stmt->bind_param("iii", $challengeId, $userId, $userId);
}

$stmt->execute();
$match = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$match) {
    die("Trận đấu không tồn tại hoặc bạn không thuộc trận đấu này.");
}

$isChallenger = ($userId == $match['challenger_id']);
$opponentId = $isChallenger ? $match['opponent_id'] : $match['challenger_id'];

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đấu Trường Trận Địa | PvP Challenge</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Inter:wght@400;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #020617;
            --primary: #ef4444;
            --blue: #3b82f6;
            --gold: #fbbf24;
        }

        body {
            background: var(--bg);
            color: #fff;
            font-family: 'Inter', sans-serif;
            margin: 0;
            overflow: hidden;
            height: 100vh;
        }

        /* 🏟️ Arena Background */
        .arena-container {
            width: 100vw; height: 100vh;
            background: radial-gradient(circle at center, #312e81 0%, #020617 100%);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            position: relative;
        }

        /* ⚔️ Verses View */
        .duel-view {
            display: flex; align-items: center; gap: 80px;
            z-index: 10;
        }

        .fighter {
            text-align: center;
            transition: transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .fighter-avatar {
            width: 180px; height: 180px; border-radius: 50%;
            border: 6px solid var(--blue);
            box-shadow: 0 0 30px rgba(59, 130, 246, 0.5);
            background: #1e293b; object-fit: cover;
            margin-bottom: 20px;
        }

        .fighter.opponent .fighter-avatar { border-color: var(--primary); box-shadow: 0 0 30px rgba(239, 68, 68, 0.5); }

        .fighter-name { font-family: 'Bangers', cursive; font-size: 32px; letter-spacing: 2px; }
        .status-badge { font-size: 14px; padding: 5px 15px; border-radius: 20px; background: rgba(0,0,0,0.5); margin-top: 10px; display: inline-block; }
        .status-online { color: #10b981; border: 1px solid #10b981; }
        .status-waiting { color: #94a3b8; border: 1px solid #94a3b8; }

        .vs-logo {
            font-family: 'Bangers', cursive; font-size: 80px; color: var(--gold);
            text-shadow: 0 0 20px rgba(251, 191, 36, 0.8);
            animation: pulse 1s infinite alternate;
        }

        @keyframes pulse { from { transform: scale(1); } to { transform: scale(1.1); } }

        /* ⏲️ Countdown */
        #countdown-overlay {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.8);
            display: none; flex-direction: column; align-items: center; justify-content: center;
            z-index: 100;
        }
        #countdown-number { font-family: 'Bangers', cursive; font-size: 150px; color: #fff; }

        /* 💥 Battle FX */
        .slash-fx {
            position: absolute; width: 100%; height: 100%;
            pointer-events: none; z-index: 50; display: none;
        }
        .slash {
            position: absolute; width: 400px; height: 10px; background: #fff;
            filter: blur(2px); box-shadow: 0 0 20px #fff;
            animation: slashAnim 0.5s forwards;
        }
        @keyframes slashAnim {
            0% { transform: scaleX(0) rotate(45deg); opacity: 1; }
            100% { transform: scaleX(2) rotate(45deg); opacity: 0; }
        }

        /* 🏁 Results */
        #result-screen {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.9) 0%, rgba(2, 6, 23, 0.95) 100%);
            display: none; flex-direction: column; align-items: center; justify-content: center;
            z-index: 200; animation: zoomIn 0.5s ease-out;
        }
        @keyframes zoomIn { from { opacity: 0; transform: scale(0.5); } to { opacity: 1; transform: scale(1); } }
        .result-title { font-family: 'Bangers', cursive; font-size: 100px; color: var(--gold); }
        .result-reward { font-size: 32px; font-weight: 900; margin-top: 20px; }

        .btn-return {
            margin-top: 40px; padding: 15px 40px; border-radius: 30px; border: none;
            background: #fff; color: #000; font-weight: 900; cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="arena-container">
        <!-- 💰 Bet Amount Header -->
        <div style="position: absolute; top: 40px; text-align: center; z-index: 10;">
            <div style="font-size: 14px; opacity: 0.7; letter-spacing: 2px;">GIÁ TRỊ TIỀN CƯỢC</div>
            <div style="font-size: 32px; font-weight: 900; color: var(--gold);"><?= number_format($match['bet_amount']) ?> GTLM</div>
        </div>

        <div class="duel-view">
            <!-- 🔵 Challenger -->
            <div class="fighter <?= !$isChallenger ? 'opponent' : '' ?>" id="fighter-1">
                <img src="<?= $match['challenger_avatar'] ?: 'img/avatar_default.png' ?>" class="fighter-avatar">
                <div class="fighter-name"><?= htmlspecialchars($match['challenger_name']) ?></div>
                <div class="status-badge status-online" id="status-1">SẴN SÀNG</div>
            </div>

            <div class="vs-logo">VS</div>

            <!-- 🔴 Challenged -->
            <div class="fighter <?= $isChallenger ? 'opponent' : '' ?>" id="fighter-2">
                <img src="<?= $match['challenged_avatar'] ?: 'img/avatar_default.png' ?>" class="fighter-avatar">
                <div class="fighter-name"><?= htmlspecialchars($match['challenged_name']) ?></div>
                <div class="status-badge status-waiting" id="status-2">ĐANG CHỜ...</div>
            </div>
        </div>

        <div id="countdown-overlay">
            <div style="font-size: 24px; letter-spacing: 10px; margin-bottom: 20px;">TRẬN ĐẤU BẮT ĐẦU SAU</div>
            <div id="countdown-number">3</div>
        </div>

        <div class="slash-fx" id="slashFx">
            <div class="slash" style="top: 40%; left: 30%; transform: rotate(-45deg);"></div>
            <div class="slash" style="top: 60%; left: 40%; transform: rotate(15deg);"></div>
        </div>

        <div id="result-screen">
            <div class="result-title" id="resultTitle">VICTORY</div>
            <div class="result-reward" id="resultReward">+0 GTLM</div>
            <button class="btn-return" onclick="location.href='index.php'">RỜI ĐẤU TRƯỜNG</button>
        </div>

        <?php if ($isAdmin): ?>
            <!-- ⚡ Admin Control Panel Overlay -->
            <div id="admin-panel" style="position: absolute; bottom: 30px; left: 50%; transform: translateX(-50%); background: rgba(15, 23, 42, 0.95); border: 2px solid var(--gold); padding: 15px 30px; border-radius: 20px; box-shadow: 0 0 25px rgba(251, 191, 36, 0.4); text-align: center; z-index: 150; display: flex; gap: 15px; align-items: center; backdrop-filter: blur(10px);">
                <div style="font-size: 14px; font-weight: bold; color: var(--gold); letter-spacing: 1px; text-transform: uppercase;"><i class="fas fa-shield-alt"></i> QUẢN TRỊ VIÊN:</div>
                <button class="btn" style="background: linear-gradient(135deg, #ef4444, #b91c1c); padding: 10px 18px; border-radius: 10px; color: #fff; font-weight: bold; border: none;" onclick="adminCancelMatch()">❌ HỦY TRẬN ĐẤU</button>
                <button class="btn" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8); padding: 10px 18px; border-radius: 10px; color: #fff; font-weight: bold; border: none;" onclick="adminForceResult(<?= (int)$match['challenger_id'] ?>, '<?= htmlspecialchars($match['challenger_name']) ?>')">⚡ XỬ THẮNG P1</button>
                <button class="btn" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8); padding: 10px 18px; border-radius: 10px; color: #fff; font-weight: bold; border: none;" onclick="adminForceResult(<?= (int)$match['opponent_id'] ?>, '<?= htmlspecialchars($match['challenged_name']) ?>')">⚡ XỬ THẮNG P2</button>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const matchId = <?= $challengeId ?>;
        const userId = <?= $userId ?>;
        let isStarted = false;

        // 🔄 Sync State Polling
        async function syncState() {
            if (isStarted) return;

            const res = await fetch(`api_pvp.php?action=sync&id=${matchId}`);
            const data = await res.json();

            // Cập nhật trạng thái đối thủ
            const opponentStatus = document.getElementById('status-2');
            if (data.opponent_online) {
                opponentStatus.textContent = "SẴN SÀNG";
                opponentStatus.classList.remove('status-waiting');
                opponentStatus.classList.add('status-online');
                
                if (data.status === 'accepted' || data.status === 'fighting') {
                    startCountdown();
                }
            } else {
                opponentStatus.textContent = "ĐANG CHỜ...";
            }
        }

        function startCountdown() {
            if (isStarted) return;
            isStarted = true;

            const overlay = document.getElementById('countdown-overlay');
            const num = document.getElementById('countdown-number');
            overlay.style.display = 'flex';

            let count = 3;
            const timer = setInterval(() => {
                count--;
                num.textContent = count;
                if (count <= 0) {
                    clearInterval(timer);
                    overlay.style.display = 'none';
                    performBattle();
                }
            }, 1000);
        }

        function performBattle() {
            const slashFx = document.getElementById('slashFx');
            slashFx.style.display = 'block';

            // Hiệu ứng Avatar lao vào nhau
            document.getElementById('fighter-1').style.transform = 'translateX(100px)';
            document.getElementById('fighter-2').style.transform = 'translateX(-100px)';

            setTimeout(async () => {
                // Lấy kết quả thật từ server
                const res = await fetch(`api_pvp.php?action=get_result&id=${matchId}`);
                const data = await res.json();

                showResult(data);
            }, 1500);
        }

        function showResult(data) {
            const screen = document.getElementById('result-screen');
            const title = document.getElementById('resultTitle');
            const reward = document.getElementById('resultReward');

            if (data.winner_id === userId) {
                title.textContent = "VICTORY";
                title.style.color = "var(--gold)";
                reward.textContent = "+" + data.reward.toLocaleString() + " GTLM";
            } else {
                title.textContent = "DEFEAT";
                title.style.color = "var(--primary)";
                reward.textContent = "-" + data.bet.toLocaleString() + " GTLM";
            }

            screen.style.display = 'flex';
        }

        setInterval(syncState, 2000);

        <?php if ($isAdmin): ?>
        function adminCancelMatch() {
            Swal.fire({
                title: 'Xác nhận hủy?',
                text: "Trận đấu sẽ bị hủy và tiền cược cược sẽ hoàn trả lại cho cả 2 đấu thủ!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Đồng ý hủy',
                cancelButtonText: 'Không'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch(`api_pvp.php?action=admin_cancel&id=${matchId}`)
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Thành công', data.message, 'success').then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire('Lỗi', data.message, 'error');
                            }
                        });
                }
            });
        }

        function adminForceResult(winnerId, winnerName) {
            Swal.fire({
                title: 'Xử thắng cuộc?',
                text: `Bạn có chắc muốn trực tiếp quyết định chiến thắng cho [${winnerName}]?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3b82f6',
                confirmButtonText: 'Đồng ý xử thắng',
                cancelButtonText: 'Không'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch(`api_pvp.php?action=admin_force_result&id=${matchId}&winner_id=${winnerId}`)
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Thành công', data.message, 'success').then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire('Lỗi', data.message, 'error');
                            }
                        });
                }
            });
        }
        <?php endif; ?>
    </script>
</body>
</html>

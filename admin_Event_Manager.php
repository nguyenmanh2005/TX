<?php
session_start();
require_once 'db_connect.php';
require_once 'api_event_helper.php';

// Kiểm tra quyền admin (Giả sử có session Role hoặc Iduser = 1)
if (!isset($_SESSION['Iduser']) || $_SESSION['Iduser'] != 1) {
    die("Truy cập bị từ chối!");
}

$action = $_GET['action'] ?? '';

// Xử lý các thay đổi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['set_gotd'])) {
        $game = $_POST['game_name'];
        $today = date('Y-m-d');
        $stmt = $conn->prepare("INSERT INTO daily_tournament_records (game_name, event_date) VALUES (?, ?) ON DUPLICATE KEY UPDATE game_name = ?");
        $stmt->bind_param("sss", $game, $today, $game);
        $stmt->execute();
        $stmt->close();
        header("Location: admin_Event_Manager.php?msg=Game of the Day updated!");
        exit;
    }

    if (isset($_POST['create_season'])) {
        $name = $_POST['season_name'];
        $start = $_POST['start_date'];
        $end = $_POST['end_date'];
        $color = $_POST['theme_color'];
        $boss = $_POST['boss_name'];
        $hp = $_POST['boss_hp'];

        $stmt = $conn->prepare("INSERT INTO seasonal_pass_configs (name, start_date, end_date, theme_color, boss_name, boss_hp_max) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssi", $name, $start, $end, $color, $boss, $hp);
        $stmt->execute();
        $stmt->close();
        header("Location: admin_Event_Manager.php?msg=Season created!");
        exit;
    }

    if (isset($_POST['add_reward'])) {
        $seasonId = $_POST['season_id'];
        $level = $_POST['level'];
        $type = $_POST['reward_type'];
        $val = $_POST['reward_value'];
        $premium = isset($_POST['is_premium']) ? 1 : 0;

        $stmt = $conn->prepare("INSERT INTO seasonal_pass_levels (season_id, level, reward_type, reward_value, is_premium) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iissi", $seasonId, $level, $type, $val, $premium);
        $stmt->execute();
        $stmt->close();
        header("Location: admin_Event_Manager.php?msg=Reward added!");
        exit;
    }

    if (isset($_POST['grant_vip'])) {
        $userName = $_POST['user_name'];
        // Tìm user theo tên
        $stmt = $conn->prepare("SELECT Iduser FROM users WHERE Name = ?");
        $stmt->bind_param("s", $userName);
        $stmt->execute();
        $userRes = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($userRes) {
            $uId = $userRes['Iduser'];
            // Cộng thêm 24h vào vip_expiry
            // Nếu đã là VIP thì cộng dồn, nếu chưa thì lấy thời gian hiện tại + 24h
            $sql = "UPDATE users SET vip_expiry = IF(vip_expiry > NOW(), DATE_ADD(vip_expiry, INTERVAL 24 HOUR), DATE_ADD(NOW(), INTERVAL 24 HOUR)) WHERE Iduser = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $uId);
            $stmt->execute();
            $stmt->close();
            header("Location: admin_Event_Manager.php?msg=VIP Trial (24h) granted to $userName!");
        } else {
            header("Location: admin_Event_Manager.php?msg=User not found!");
        }
        exit;
    }
}

// Lấy dữ liệu hiển thị
$currentGotd = EventHelper::getGameOfTheDay($conn);
$seasons = $conn->query("SELECT * FROM seasonal_pass_configs ORDER BY start_date DESC")->fetch_all(MYSQLI_ASSOC);
$availableGames = ['Baccarat', 'Blackjack', 'Roulette', 'Sicbo', 'Tài Xỉu', 'RPS', 'Vietlott', 'Xóc Đĩa', 'Poker', 'Bầu Cua', 'Slot Cyber', 'Mega Spin', 'Horse Race'];

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Admin Event Manager | Premium Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-glow: rgba(99, 102, 241, 0.4);
            --bg: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.7);
            --text: #f8fafc;
            --accent: #f59e0b;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Outfit', sans-serif;
            margin: 0;
            padding: 40px;
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(245, 158, 11, 0.1) 0px, transparent 50%);
        }

        .container { max-width: 1200px; margin: 0 auto; }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }

        h1 { font-size: 2.5rem; font-weight: 800; margin: 0; background: linear-gradient(to right, #818cf8, #f472b6); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }

        .grid { display: grid; grid-template-columns: 1fr 2fr; gap: 30px; }

        .card {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
        }

        .card h2 { margin-top: 0; display: flex; align-items: center; gap: 10px; color: var(--accent); }

        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #94a3b8; }
        input, select {
            width: 100%;
            padding: 12px 16px;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: white;
            font-family: inherit;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary) 0%, #4f46e5 100%);
            border: none;
            border-radius: 12px;
            color: white;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: 0 10px 20px var(--primary-glow);
        }

        button:hover { transform: translateY(-2px); box-shadow: 0 15px 30px var(--primary-glow); }

        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-success { background: rgba(34, 197, 94, 0.2); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.4); }

        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { text-align: left; color: #94a3b8; padding: 12px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        td { padding: 12px; border-bottom: 1px solid rgba(255, 255, 255, 0.05); }

        .season-item {
            padding: 15px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.03);
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .alert {
            padding: 15px;
            border-radius: 12px;
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
            margin-bottom: 20px;
            border: 1px solid rgba(34, 197, 94, 0.4);
        }
    </style>
</head>
<body>

    <div class="container">
        <header>
            <div>
                <h1>Event Manager</h1>
                <p style="color: #94a3b8; margin: 5px 0 0;">Manage Tournaments, Combos, and Seasonal Passes</p>
            </div>
            <a href="index.php" style="color: white; text-decoration: none;"><i class="fa fa-home"></i> Back to Home</a>
        </header>

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert"><?php echo htmlspecialchars($_GET['msg']); ?></div>
        <?php endif; ?>

        <div class="grid">
            <!-- Left Column: Daily GOTD -->
            <div class="card">
                <h2><i class="fa fa-calendar-day"></i> Game of the Day</h2>
                <div style="text-align: center; margin: 20px 0; padding: 20px; background: rgba(255,255,255,0.05); border-radius: 20px;">
                    <div style="font-size: 0.9rem; color: #94a3b8;">HÔM NAY</div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: #ffd700;"><?php echo $currentGotd; ?></div>
                    <div class="badge badge-success" style="margin-top: 10px;">x2 XP ACTIVE</div>
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label>Thay đổi Game of the Day</label>
                        <select name="game_name">
                            <?php foreach ($availableGames as $g): ?>
                                <option value="<?php echo $g; ?>" <?php echo $g == $currentGotd ? 'selected' : ''; ?>><?php echo $g; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="set_gotd">Update Today's Game</button>
                </form>
            </div>

            <!-- Right Column: Seasonal Pass -->
            <div class="card">
                <h2><i class="fa fa-trophy"></i> Seasonal Seasons</h2>
                
                <div style="display: flex; gap: 20px; margin-bottom: 30px;">
                    <div style="flex: 1;">
                        <h3>Create New Season</h3>
                        <form method="POST">
                            <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div class="form-group">
                                    <label>Season Name</label>
                                    <input type="text" name="season_name" placeholder="Mùa 1: Khởi Đầu" required>
                                </div>
                                <div class="form-group">
                                    <label>Theme Color</label>
                                    <input type="color" name="theme_color" value="#6366f1">
                                </div>
                                <div class="form-group">
                                    <label>Start Date</label>
                                    <input type="date" name="start_date" required>
                                </div>
                                <div class="form-group">
                                    <label>End Date</label>
                                    <input type="date" name="end_date" required>
                                </div>
                                <div class="form-group">
                                    <label>World Boss Name</label>
                                    <input type="text" name="boss_name" value="Rồng Thần Lam">
                                </div>
                                <div class="form-group">
                                    <label>Boss HP</label>
                                    <input type="number" name="boss_hp" value="5000000">
                                </div>
                            </div>
                            <button type="submit" name="create_season">Launch New Season</button>
                        </form>
                    </div>
                </div>

                <h3>Active & Past Seasons</h3>
                <?php foreach ($seasons as $s): ?>
                    <div class="season-item" style="border-left: 5px solid <?php echo $s['theme_color']; ?>">
                        <div>
                            <strong style="font-size: 1.1rem;"><?php echo $s['name']; ?></strong>
                            <div style="font-size: 0.8rem; color: #94a3b8;">
                                <?php echo $s['start_date']; ?> to <?php echo $s['end_date']; ?>
                                | Boss: <?php echo $s['boss_name']; ?> (<?php echo number_format($s['boss_hp_max']); ?> HP)
                            </div>
                        </div>
                        <div>
                            <button style="width: auto; padding: 8px 15px; font-size: 0.8rem; background: rgba(255,255,255,0.1); color: white;" onclick="openRewardModal(<?php echo $s['id']; ?>, '<?php echo $s['name']; ?>')">Manage Rewards</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Reward Management Section -->
        <div class="card" style="margin-top: 30px;" id="reward-section">
            <h2><i class="fa fa-gift"></i> Manage Rewards for <span id="selected-season-name">...</span></h2>
            <form method="POST" class="grid" style="grid-template-columns: 1fr 1fr 1fr 1fr 1fr auto; align-items: end;">
                <input type="hidden" name="season_id" id="form-season-id">
                <div class="form-group">
                    <label>Level</label>
                    <input type="number" name="level" value="1" required>
                </div>
                <div class="form-group">
                    <label>Reward Type</label>
                    <select name="reward_type">
                        <option value="money">Money (GTLM)</option>
                        <option value="item">Item/Frame ID</option>
                        <option value="exclusive">Exclusive Content</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Value</label>
                    <input type="text" name="reward_value" placeholder="100000" required>
                </div>
                <div class="form-group" style="display:flex; align-items:center; gap:10px; padding-bottom: 12px;">
                    <input type="checkbox" name="is_premium" id="is_premium" style="width:auto;">
                    <label for="is_premium" style="margin:0;">Premium?</label>
                </div>
                <button type="submit" name="add_reward" style="margin-bottom: 20px;">Add Reward</button>
            </form>
        </div>

        <!-- VIP TRIAL SECTION -->
        <div class="card" style="margin-top: 30px;">
            <h2><i class="fa fa-crown"></i> VIP Trial (24 Hours)</h2>
            <p style="color: #94a3b8; font-size: 0.9rem;">Tặng 24h VIP miễn phí cho người dùng. Nếu người dùng đang là VIP, thời gian sẽ được cộng dồn.</p>
            <form method="POST" style="display: flex; gap: 15px; align-items: flex-end;">
                <div class="form-group" style="flex: 1; margin-bottom: 0;">
                    <label>Tên người dùng</label>
                    <input type="text" name="user_name" placeholder="Nhập tên chính xác..." required>
                </div>
                <button type="submit" name="grant_vip" style="width: auto; padding: 12px 30px; margin-bottom: 0;">
                    <i class="fa fa-magic"></i> Tặng 24h VIP
                </button>
            </form>
        </div>
    </div>

    <script>
        function openRewardModal(id, name) {
            document.getElementById('selected-season-name').innerText = name;
            document.getElementById('form-season-id').value = id;
            document.getElementById('reward-section').scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>

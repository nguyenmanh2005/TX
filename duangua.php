<?php
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}
require 'db_connect.php';

// Lấy thông tin người dùng
$userId = $_SESSION['Iduser'];
$userData = $conn->prepare("SELECT Money, Name FROM users WHERE Iduser = ?");
$userData->bind_param("i", $userId);
$userData->execute();
$result = $userData->get_result();
$user = $result->fetch_assoc();
$currentMoney = $user['Money'];
$userName = $user['Name'];

$message = "";
$animalWin = null;
$betAnimal = null;
$betAmount = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $betAnimal = (int)$_POST['animal'];
    $betAmount = (int)$_POST['amount'];

    if ($betAmount <= 0 || $betAnimal < 1 || $betAnimal > 8) {
        $message = "❌ Dữ liệu không hợp lệ!";
    } elseif ($betAmount > $currentMoney) {
        $message = "⚠️ Số dư không đủ!";
    } else {
        $animalWin = rand(1, 8); // Random kết quả giữa 1 đến 8
        $animalNames = ["Chó", "Mèo", "Sư Tử", "Khỉ", "Ngựa Vằn", "Hổ", "Cáo", "Thỏ"]; // Danh sách động vật

        if ($betAnimal === $animalWin) {
            $reward = $betAmount * 7;
            $message = "🎉 Con " . $animalNames[$animalWin - 1] . " Thắng Cuộc! Món Quà Của Nhà Tiên Tri " . number_format($reward) . " VNĐ!";
            $currentMoney += $reward;
        } else {
            $message = "😢 Con " . $animalNames[$animalWin - 1] . " Thắng Cuộc! Bạn Quá Đen, Bạn Đã Bị Đớp " . number_format($betAmount) . " VNĐ.";
            $currentMoney -= $betAmount;
        }

        // Cập nhật số dư
        $update = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        $update->bind_param("di", $currentMoney, $userId);
        $update->execute();
        $update->close();
        
        // Track quest progress và tự động cập nhật streak, VIP, reward points, social feed
        require_once 'game_history_helper.php';
        $winAmount = ($betAnimal === $animalWin) ? ($betAmount * 7) : 0;
        $isWin = ($betAnimal === $animalWin);
        logGameHistoryWithAll($conn, $userId, 'Đua Thú', $betAmount, $winAmount, $isWin);
    }
}

?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title> Đua Thú </title>
        <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
        <link rel="stylesheet" href="assets/css/game-effects.css">
        <link rel="stylesheet" href="assets/css/game-ui-enhancements.css">
        <link rel="stylesheet" href="assets/css/game-effects.css">
        <link rel="stylesheet" href="assets/css/game-ui-enhancements.css">
    <style>
        body {
            cursor: url('chuot.png'), auto;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%);
            text-align: center;
            padding: 40px 20px;
            min-height: 100vh;
        }

        button, a, input[type="button"], input[type="submit"], label, select {
            cursor: url('img/tay.png'), pointer !important;
        }

        .game-container {
            max-width: 900px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.98);
            padding: 40px;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.5);
        }

        h1 {
            color: var(--primary-color);
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }

        .money {
            font-size: 22px;
            font-weight: 700;
            color: var(--success-color);
            margin: 20px 0;
            padding: 15px;
            background: rgba(232, 245, 233, 0.5);
            border-radius: var(--border-radius);
            border: 2px solid var(--success-color);
        }

        form {
            margin: 30px auto;
            padding: 25px;
            background: rgba(240, 248, 255, 0.5);
            border-radius: var(--border-radius);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        label {
            display: block;
            margin: 15px 0 8px;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 16px;
        }

        select, input[type="number"] {
            padding: 12px 18px;
            font-size: 16px;
            margin: 10px;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            background: rgba(255, 255, 255, 0.95);
            color: var(--text-dark);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            width: 200px;
        }

        select:focus, input[type="number"]:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        button {
            padding: 14px 28px;
            font-size: 18px;
            font-weight: 700;
            background: linear-gradient(135deg, #388e3c 0%, #2e7d32 100%);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(56, 142, 60, 0.4);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            margin-top: 15px;
        }

        button:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(56, 142, 60, 0.6);
        }

        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .tracks {
            margin: 40px 0;
        }

        .track {
            position: relative;
            width: 95%;
            margin: 15px auto;
            background: linear-gradient(135deg, #c8e6c9 0%, #a5d6a7 100%);
            border: 3px solid #388e3c;
            height: 90px;
            overflow: hidden;
            border-radius: var(--border-radius);
            box-shadow: inset 0 2px 10px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .track::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: repeating-linear-gradient(
                90deg,
                transparent,
                transparent 20px,
                rgba(255, 255, 255, 0.3) 20px,
                rgba(255, 255, 255, 0.3) 40px
            );
            pointer-events: none;
        }

        .track::after {
            content: '🏁';
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 40px;
            z-index: 10;
        }

        .animal {
            width: 70px;
            height: 70px;
            position: absolute;
            top: 10px;
            left: 10px;
            font-size: 50px;
            transition: left 4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            z-index: 5;
            filter: drop-shadow(2px 2px 4px rgba(0, 0, 0, 0.3));
            animation: animalBounce 0.5s ease-in-out infinite;
        }

        @keyframes animalBounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        .animal.racing {
            animation: animalBounce 0.3s ease-in-out infinite, animalRun 0.5s ease-in-out infinite;
        }

        @keyframes animalRun {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-8px) scale(1.1); }
        }

        .animal.winner {
            animation: winnerCelebration 1s ease-in-out infinite;
            filter: drop-shadow(0 0 20px rgba(255, 215, 0, 0.8));
            z-index: 15;
        }

        @keyframes winnerCelebration {
            0%, 100% {
                transform: scale(1) rotate(0deg);
                filter: drop-shadow(0 0 20px rgba(255, 215, 0, 0.8));
            }
            25% {
                transform: scale(1.2) rotate(-10deg);
                filter: drop-shadow(0 0 30px rgba(255, 215, 0, 1));
            }
            50% {
                transform: scale(1.3) rotate(10deg);
                filter: drop-shadow(0 0 40px rgba(255, 215, 0, 1));
            }
            75% {
                transform: scale(1.2) rotate(-10deg);
                filter: drop-shadow(0 0 30px rgba(255, 215, 0, 1));
            }
        }

        .animal.loser {
            opacity: 0.6;
            filter: grayscale(0.5);
        }

        .message {
            margin-top: 30px;
            font-size: 22px;
            font-weight: 700;
            padding: 20px;
            border-radius: var(--border-radius);
            min-height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .message.win {
            color: #00ff00;
            background: rgba(0, 255, 0, 0.2);
            border: 3px solid #00ff00;
            box-shadow: 0 0 25px rgba(0, 255, 0, 0.5);
            animation: messageWin 0.6s ease;
        }

        .message.lose {
            color: #ff6b6b;
            background: rgba(255, 107, 107, 0.2);
            border: 3px solid #ff6b6b;
            animation: messageLose 0.6s ease;
        }

        @keyframes messageWin {
            0% {
                transform: scale(0.8);
                opacity: 0;
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        @keyframes messageLose {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        a {
            display: inline-block;
            margin-top: 25px;
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        a:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.5);
        }

        .race-start {
            font-size: 48px;
            font-weight: 700;
            color: #ffd700;
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.5);
            margin: 20px 0;
            animation: countdown 1s ease;
        }

        @keyframes countdown {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="game-container">
        <h1>🐎 Đua Thú</h1>
        <p style="font-size: 18px; margin: 10px 0;">Xin chào <b><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></b></p>
        <p class="money">💰 Số dư: <b><?= number_format($currentMoney, 0, ',', '.') ?> VNĐ</b></p>

        <form method="post" id="raceForm">
            <label>🏆 Chọn Con Vật: </label>
            <select name="animal">
                <?php 
                $animalNames = ["Chó", "Mèo", "Sư Tử", "Khỉ", "Ngựa Vằn", "Hổ", "Cáo", "Thỏ"];
                $animalEmojis = ["🐶", "🐱", "🦁", "🐵", "🦓", "🐯", "🦊", "🐰"];
                for ($i = 0; $i < 8; $i++): ?>
                    <option value="<?= $i + 1 ?>" <?= $betAnimal == $i + 1 ? "selected" : "" ?>>
                        <?= $animalEmojis[$i] ?> <?= $animalNames[$i] ?>
                    </option>
                <?php endfor; ?>
            </select>
            <br>
            <label>💰 Số tiền cược: </label>
            <input type="number" name="amount" placeholder="Nhập số tiền" required value="<?= $betAmount ?? '' ?>" min="1">
            <br>
            <button type="submit" id="raceBtn">🏁 Bắt đầu đua</button>
        </form>

        <div id="countdown" class="race-start" style="display: none;"></div>

        <div class="tracks">
            <?php 
            $animals = ["🐶", "🐱", "🦁", "🐵", "🦓", "🐯", "🦊", "🐰"];
            for ($i = 0; $i < 8; $i++): 
            ?>
                <div class="track">
                    <div class="animal" id="animal<?= $i ?>" data-animal="<?= $i + 1 ?>"><?= $animals[$i] ?></div>
                </div>
            <?php endfor; ?>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?= ($betAnimal === $animalWin) ? 'win' : 'lose' ?>">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <a href="index.php">🏠 Quay lại trang chủ</a>
    </div>

    <script>
        const form = document.getElementById('raceForm');
        const countdownEl = document.getElementById('countdown');
        const raceBtn = document.getElementById('raceBtn');
        let isSubmitting = false;
        
        <?php if ($_SERVER["REQUEST_METHOD"] === "POST" && $animalWin !== null): ?>
            // Đã có kết quả từ server, chỉ hiển thị animation
            const hasResult = true;
            const winner = <?= $animalWin - 1 ?>;
        <?php else: ?>
            // Chưa có kết quả, chờ người dùng submit
            const hasResult = false;
        <?php endif; ?>
        
        form.addEventListener('submit', function(e) {
            if (hasResult || isSubmitting) {
                e.preventDefault();
                return;
            }
            
            e.preventDefault();
            isSubmitting = true;
            
            // Disable button
            raceBtn.disabled = true;
            raceBtn.textContent = 'Đang chuẩn bị...';
            
            // Countdown
            countdownEl.style.display = 'block';
            let count = 3;
            countdownEl.textContent = count;
            
            const countdownInterval = setInterval(() => {
                count--;
                if (count > 0) {
                    countdownEl.textContent = count;
                } else {
                    countdownEl.textContent = '🏁 GO!';
                    clearInterval(countdownInterval);
                    
                    setTimeout(() => {
                        countdownEl.style.display = 'none';
                        // Submit form thật để gửi dữ liệu lên server
                        form.submit();
                    }, 500);
                }
            }, 1000);
        });

        function showRaceAnimation() {
            if (!hasResult) return;
            
            // Start all animals racing
            for (let i = 0; i < 8; i++) {
                const animal = document.getElementById("animal" + i);
                if (animal) {
                    animal.classList.add('racing');
                }
            }
            
            // Calculate distances with winner going further
            setTimeout(() => {
                for (let i = 0; i < 8; i++) {
                    const animal = document.getElementById("animal" + i);
                    if (!animal) continue;
                    
                    let distance;
                    
                    if (i === winner) {
                        distance = 88 + Math.random() * 5; // Winner goes to finish
                    } else {
                        distance = 20 + Math.random() * 65; // Others stop earlier
                    }
                    
                    animal.style.left = distance + "%";
                }
            }, 100);
            
            // Stop racing animation and show winner
            setTimeout(() => {
                for (let i = 0; i < 8; i++) {
                    const animal = document.getElementById("animal" + i);
                    if (!animal) continue;
                    
                    animal.classList.remove('racing');
                    
                    if (i === winner) {
                        animal.classList.add('winner');
                    } else {
                        animal.classList.add('loser');
                    }
                }
            }, 4100);
        }

        // Auto show race animation if there's a result from server
        window.onload = function () {
            if (hasResult) {
                setTimeout(() => {
                    showRaceAnimation();
                }, 500);
            }
        };
    </script>

    <script src="assets/js/game-effects.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script src="assets/js/game-effects-auto.js"></script>

        <script src="assets/js/game-enhancements.js"></script>
<script>
    // Auto initialize game effects
    if (typeof GameEffectsAuto !== 'undefined') {
        GameEffectsAuto.init();
    }
</script>
</body>
</html>

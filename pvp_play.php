<?php
session_start();

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require 'db_connect.php';

$userId = $_SESSION['Iduser'];
$challengeId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($challengeId <= 0) {
    header("Location: pvp_challenge.php");
    exit();
}

// Load theme
require_once 'load_theme.php';

// Kiểm tra loại game để chuyển hướng nếu cần
$checkGame = $conn->prepare("SELECT game_type FROM pvp_challenges WHERE id = ?");
$checkGame->bind_param("i", $challengeId);
$checkGame->execute();
$gameInfo = $checkGame->get_result()->fetch_assoc();
$checkGame->close();

if ($gameInfo && $gameInfo['game_type'] == 'caro') {
    header("Location: pvp_caro.php?id=" . $challengeId);
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚔️ Chơi PvP Challenge</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        .play-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
        }

        .game-area {
            background: rgba(0, 0, 0, 0.3);
            padding: 30px;
            border-radius: 15px;
            text-align: center;
        }

        .players-info {
            display: flex;
            justify-content: space-around;
            margin: 30px 0;
        }

        .player-card {
            text-align: center;
        }

        .player-card img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid var(--primary-color);
            margin-bottom: 10px;
        }

        .player-choice {
            margin-top: 20px;
            font-size: 24px;
            font-weight: bold;
        }

        .vs-text {
            font-size: 48px;
            font-weight: bold;
            color: var(--primary-color);
            align-self: center;
        }

        .coin-flip-area {
            margin: 40px 0;
        }

        .coin {
            width: 200px;
            height: 200px;
            margin: 30px auto;
            border-radius: 50%;
            background: linear-gradient(135deg, #f39c12, #e67e22);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 80px;
            border: 8px solid #fff;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.5);
            transition: all 0.5s;
        }

        .coin.flipping {
            animation: flip 1s infinite;
        }

        @keyframes flip {
            0% {
                transform: rotateY(0deg);
            }

            100% {
                transform: rotateY(360deg);
            }
        }

        .choice-buttons {
            display: flex;
            gap: 30px;
            justify-content: center;
            margin: 30px 0;
        }

        .choice-btn {
            padding: 20px 40px;
            font-size: 24px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-radius: 15px;
            background: rgba(0, 0, 0, 0.3);
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            min-width: 150px;
        }

        .choice-btn:hover:not(:disabled) {
            border-color: var(--primary-color);
            background: rgba(102, 126, 234, 0.3);
            transform: scale(1.1);
        }

        .choice-btn.selected {
            border-color: var(--primary-color);
            background: var(--primary-color);
        }

        .choice-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .waiting-message {
            padding: 30px;
            background: rgba(241, 196, 15, 0.2);
            border-radius: 15px;
            margin: 30px 0;
            font-size: 20px;
        }

        .result-area {
            margin: 40px 0;
            padding: 30px;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 15px;
        }

        .result-winner {
            font-size: 36px;
            font-weight: bold;
            color: var(--success-color);
            margin: 20px 0;
        }

        .result-details {
            font-size: 18px;
            margin: 15px 0;
        }

        .btn-back {
            display: inline-block;
            padding: 12px 24px;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div style="text-align: center; padding: 20px;">
        <a href="pvp_challenge.php" style="color: var(--primary-color); text-decoration: none; font-size: 18px;">← Quay
            Lại</a>
    </div>

    <div class="play-container">
        <div class="game-area">
            <h1>⚔️ PvP Challenge</h1>

            <div id="gameContent">
                <div class="waiting-message">
                    Đang tải thông tin challenge...
                </div>
            </div>

            <a href="pvp_challenge.php" class="btn-back">← Quay Lại</a>
        </div>
    </div>

    <script>
        const challengeId = <?= $challengeId ?>;
        const userId = <?= $userId ?>;
        let challenge = null;
        let myChoice = null;
        let refreshInterval = null;

        function loadChallenge() {
            fetch(`api_pvp_challenge.php?action=get_challenge&challenge_id=${challengeId}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        document.getElementById('gameContent').innerHTML = `
                            <div class="waiting-message" style="background: rgba(231, 76, 60, 0.2);">
                                ${data.message || 'Không tìm thấy challenge!'}
                            </div>
                        `;
                        return;
                    }

                    challenge = data.challenge;
                    renderGame();
                });
        }

        function renderGame() {
            const isChallenger = challenge.challenger_id == userId;
            const isOpponent = challenge.opponent_id == userId;
            const gameType = challenge.game_type;

            // Kiểm tra đã nộp choice chưa
            const myChoiceValue = isChallenger ? challenge.challenger_choice : challenge.opponent_choice;
            const opponentChoiceValue = isChallenger ? challenge.opponent_choice : challenge.challenger_choice;

            if (myChoiceValue) {
                myChoice = myChoiceValue;
            }

            let gameHtml = '';

            if (challenge.status === 'pending') {
                gameHtml = `
                    <div class="waiting-message">
                        ${isChallenger ? 'Đang chờ đối thủ chấp nhận challenge...' : 'Bạn có một challenge mới! Vui lòng chấp nhận trước khi chơi.'}
                    </div>
                `;
            } else if (challenge.status === 'accepted') {
                if (challenge.result) {
                    // Đã có kết quả
                    gameHtml = renderResult();
                } else if (!myChoiceValue) {
                    // Chưa nộp choice
                    gameHtml = renderGamePlay(gameType);
                } else if (!opponentChoiceValue) {
                    // Đã nộp, chờ đối thủ
                    gameHtml = `
                        <div class="waiting-message">
                            Bạn đã chọn: <strong>${formatChoice(myChoiceValue, gameType)}</strong><br>
                            Đang chờ đối thủ nộp lựa chọn...
                        </div>
                    `;

                    // Auto refresh để check kết quả
                    if (!refreshInterval) {
                        refreshInterval = setInterval(loadChallenge, 2000);
                    }
                } else {
                    // Cả 2 đã nộp, đang tính kết quả
                    gameHtml = `
                        <div class="waiting-message">
                            Đang tính kết quả...
                        </div>
                    `;
                    setTimeout(loadChallenge, 1000);
                }
            } else if (challenge.status === 'completed') {
                gameHtml = renderResult();
            }

            document.getElementById('gameContent').innerHTML = gameHtml;
        }

        function renderGamePlay(gameType) {
            const playersHtml = `
                <div class="players-info">
                    <div class="player-card">
                        <img src="${challenge.challenger_avatar || 'images.ico'}" alt="${challenge.challenger_name}">
                        <div><strong>${challenge.challenger_name}</strong></div>
                        <div class="player-choice" id="challengerChoice">-</div>
                    </div>
                    <div class="vs-text">VS</div>
                    <div class="player-card">
                        <img src="${challenge.opponent_avatar || 'images.ico'}" alt="${challenge.opponent_name}">
                        <div><strong>${challenge.opponent_name}</strong></div>
                        <div class="player-choice" id="opponentChoice">-</div>
                    </div>
                </div>
            `;

            let choicesHtml = '';

            if (gameType === 'coinflip') {
                choicesHtml = `
                    <div class="coin-flip-area">
                        <h2>Chọn Ngửa hoặc Sấp:</h2>
                        <div class="choice-buttons">
                            <button class="choice-btn" onclick="submitChoice('heads')">
                                🪙 Ngửa
                            </button>
                            <button class="choice-btn" onclick="submitChoice('tails')">
                                🪙 Sấp
                            </button>
                        </div>
                    </div>
                `;
            } else if (gameType === 'dice') {
                choicesHtml = `
                    <div class="coin-flip-area">
                        <h2>Chọn số xúc xắc (1-6):</h2>
                        <div class="choice-buttons">
                            ${[1, 2, 3, 4, 5, 6].map(n => `
                                <button class="choice-btn" onclick="submitChoice('${n}')">
                                    🎲 ${n}
                                </button>
                            `).join('')}
                        </div>
                    </div>
                `;
            } else if (gameType === 'rps') {
                choicesHtml = `
                    <div class="coin-flip-area">
                        <h2>Chọn:</h2>
                        <div class="choice-buttons">
                            <button class="choice-btn" onclick="submitChoice('rock')">
                                ✊ Đá
                            </button>
                            <button class="choice-btn" onclick="submitChoice('paper')">
                                ✋ Giấy
                            </button>
                            <button class="choice-btn" onclick="submitChoice('scissors')">
                                ✌️ Kéo
                            </button>
                        </div>
                    </div>
                `;
            } else if (gameType === 'number') {
                choicesHtml = `
                    <div class="coin-flip-area">
                        <h2>Đoán số từ 1-100:</h2>
                        <div style="margin: 20px 0;">
                            <input type="number" id="numberInput" min="1" max="100" 
                                   style="padding: 15px; font-size: 20px; width: 150px; text-align: center; border-radius: 8px; border: 2px solid var(--primary-color);">
                        </div>
                        <button class="choice-btn" onclick="submitNumberChoice()">
                            Xác Nhận
                        </button>
                    </div>
                `;
            }

            return playersHtml + choicesHtml;
        }

        function renderResult() {
            const isChallenger = challenge.challenger_id == userId;
            const isWinner = challenge.winner_id == userId;
            const isDraw = challenge.result === 'draw';

            let resultText = '';
            if (isDraw) {
                resultText = 'Hòa! gtlm được hoàn lại.';
            } else if (isWinner) {
                resultText = '🎉 Bạn Thắng!';
            } else {
                resultText = '😢 Bạn Thua!';
            }

            return `
                <div class="players-info">
                    <div class="player-card">
                        <img src="${challenge.challenger_avatar || 'images.ico'}" alt="${challenge.challenger_name}">
                        <div><strong>${challenge.challenger_name}</strong></div>
                        <div class="player-choice">${formatChoice(challenge.challenger_choice, challenge.game_type)}</div>
                    </div>
                    <div class="vs-text">VS</div>
                    <div class="player-card">
                        <img src="${challenge.opponent_avatar || 'images.ico'}" alt="${challenge.opponent_name}">
                        <div><strong>${challenge.opponent_name}</strong></div>
                        <div class="player-choice">${formatChoice(challenge.opponent_choice, challenge.game_type)}</div>
                    </div>
                </div>
                <div class="result-area">
                    <div class="result-winner">${resultText}</div>
                    <div class="result-details">
                        Cược: ${formatMoney(challenge.bet_amount)} gtlm<br>
                        ${isWinner ? `Bạn nhận được: ${formatMoney(challenge.bet_amount * 2)} gtlm` : ''}
                    </div>
                </div>
            `;
        }

        function formatChoice(choice, gameType) {
            if (gameType === 'coinflip') {
                return choice === 'heads' ? '🪙 Ngửa' : '🪙 Sấp';
            } else if (gameType === 'dice') {
                return `🎲 ${choice}`;
            } else if (gameType === 'rps') {
                const rps = { rock: '✊ Đá', paper: '✋ Giấy', scissors: '✌️ Kéo' };
                return rps[choice] || choice;
            } else if (gameType === 'number') {
                return `🔢 ${choice}`;
            }
            return choice;
        }

        function formatMoney(amount) {
            return new Intl.NumberFormat('vi-VN').format(amount);
        }

        function submitChoice(choice) {
            if (myChoice) {
                alert('Bạn đã nộp lựa chọn rồi!');
                return;
            }

            myChoice = choice;

            const formData = new FormData();
            formData.append('action', 'submit_choice');
            formData.append('challenge_id', challengeId);
            formData.append('choice', choice);

            fetch('api_pvp_challenge.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        if (data.both_submitted) {
                            // Cả 2 đã nộp, reload để xem kết quả
                            setTimeout(loadChallenge, 1000);
                        } else {
                            // Chờ đối thủ
                            renderGame();
                            refreshInterval = setInterval(loadChallenge, 2000);
                        }
                    } else {
                        alert(data.message || 'Lỗi!');
                        myChoice = null;
                    }
                });
        }

        function submitNumberChoice() {
            const number = document.getElementById('numberInput').value;
            if (!number || number < 1 || number > 100) {
                alert('Vui lòng nhập số từ 1-100!');
                return;
            }
            submitChoice(number);
        }

        // Load challenge on page load
        loadChallenge();

        // Cleanup interval on page unload
        window.addEventListener('beforeunload', () => {
            if (refreshInterval) clearInterval(refreshInterval);
        });
    </script>
</body>

</html>
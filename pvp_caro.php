<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['Iduser'];
$challengeId = (int) ($_GET['id'] ?? 0);

if (!$challengeId) {
    die("ID Thách đấu không hợp lệ!");
}

// Lấy thông tin ban đầu
$sql = "SELECT c.*, u1.Name as challenger_name, u2.Name as opponent_name 
        FROM pvp_challenges c
        JOIN users u1 ON c.challenger_id = u1.Iduser
        JOIN users u2 ON c.opponent_id = u2.Iduser
        WHERE c.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $challengeId);
$stmt->execute();
$challenge = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$challenge || $challenge['game_type'] != 'caro') {
    die("Trò chơi không hợp lệ!");
}

if ($challenge['challenger_id'] != $userId && $challenge['opponent_id'] != $userId) {
    die("Bạn không tham gia trận đấu này!");
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cờ Caro PvP - <?= htmlspecialchars($challenge['challenger_name']) ?> vs <?= htmlspecialchars($challenge['opponent_name']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/lobby.css">
    <style>
        body {
            background: radial-gradient(circle at center, #2c3e50 0%, #000000 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            color: #fff;
        }

        .game-header {
            margin: 20px 0;
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            padding: 15px 40px;
            border-radius: 50px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .player-info {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .player-name {
            font-weight: bold;
            font-size: 1.1em;
        }

        .player-symbol {
            font-size: 2em;
            margin-top: 5px;
        }

        .symbol-x { color: #e74c3c; text-shadow: 0 0 10px rgba(231, 76, 60, 0.5); }
        .symbol-o { color: #3498db; text-shadow: 0 0 10px rgba(52, 152, 219, 0.5); }

        .vs-badge {
            background: #f1c40f;
            color: #000;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 800;
            font-style: italic;
        }

        .board-container {
            position: relative;
            background: rgba(255, 255, 255, 0.05);
            padding: 10px;
            border-radius: 10px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        }

        .caro-board {
            display: grid;
            grid-template-columns: repeat(15, 35px);
            grid-template-rows: repeat(15, 35px);
            gap: 1px;
            background: rgba(255,255,255,0.2);
        }

        .cell {
            background: #1a1a1a;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
        }

        .cell:hover:not(.filled) {
            background: #333;
        }

        .cell.filled {
            cursor: default;
        }

        .status-bar {
            margin-top: 20px;
            padding: 15px 30px;
            border-radius: 10px;
            font-weight: bold;
            font-size: 1.2em;
            text-align: center;
            min-width: 300px;
            transition: all 0.3s;
        }

        .status-my-turn {
            background: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
            border: 1px solid #2ecc71;
            animation: pulse 2s infinite;
        }

        .status-opponent-turn {
            background: rgba(241, 196, 15, 0.2);
            color: #f1c40f;
            border: 1px solid #f1c40f;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .winner-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.8);
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 10;
            border-radius: 8px;
            backdrop-filter: blur(5px);
        }

        .winner-msg {
            font-size: 3em;
            color: #f1c40f;
            text-transform: uppercase;
            letter-spacing: 5px;
            margin-bottom: 20px;
            text-shadow: 0 0 20px rgba(241, 196, 15, 0.5);
        }

        .btn-exit {
            padding: 10px 30px;
            background: #e74c3c;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            margin: 5px;
        }

        .btn-rematch {
            padding: 10px 30px;
            background: #2ecc71;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            margin: 5px;
        }
    </style>
</head>
<body>

    <div class="game-header">
        <div class="player-info" id="player1">
            <span class="player-name"><?= htmlspecialchars($challenge['challenger_name']) ?></span>
            <span class="player-symbol symbol-x">X</span>
        </div>
        <div class="vs-badge">VS</div>
        <div class="player-info" id="player2">
            <span class="player-name"><?= htmlspecialchars($challenge['opponent_name']) ?></span>
            <span class="player-symbol symbol-o">O</span>
        </div>
    </div>

    <div class="board-container">
        <div class="winner-overlay" id="winnerOverlay">
            <div class="winner-msg" id="winnerMsg">BẠN THẮNG!</div>
            <p id="rewardMsg">Bạn nhận được +<?= number_format($challenge['bet_amount'] * 2) ?> gtlm</p>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button onclick="requestRematch()" class="btn-rematch">TÁI ĐẤU</button>
                <a href="pvp_challenge.php" class="btn-exit">THOÁT</a>
            </div>
        </div>
        <div class="caro-board" id="board">
            <!-- Board cells will be generated by JS -->
        </div>
    </div>

    <div class="status-bar" id="statusBar"> Đang kết nối... </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        const challengeId = <?= $challengeId ?>;
        const myId = <?= $userId ?>;
        const challengerId = <?= $challenge['challenger_id'] ?>;
        let isMyTurn = false;
        let gameActive = true;
        let lastMoveTimestamp = 0;

        function initBoard() {
            const board = $('#board');
            board.empty();
            for (let r = 0; r < 15; r++) {
                for (let c = 0; c < 15; c++) {
                    board.append(`<div class="cell" data-row="${r}" data-col="${c}" onclick="makeMove(${r}, ${c})"></div>`);
                }
            }
        }

        function updateUI(challenge) {
            const boardData = JSON.parse(challenge.board_state);
            const cells = $('.cell');
            
            boardData.forEach((row, r) => {
                row.forEach((cell, c) => {
                    const cellEl = $(`.cell[data-row="${r}"][data-col="${c}"]`);
                    if (cell === 1) {
                        cellEl.html('<span class="symbol-x">X</span>').addClass('filled');
                    } else if (cell === 2) {
                        cellEl.html('<span class="symbol-o">O</span>').addClass('filled');
                    }
                });
            });

            if (challenge.status === 'completed') {
                gameActive = false;
                $('#winnerOverlay').css('display', 'flex');
                if (challenge.winner_id == myId) {
                    $('#winnerMsg').text('BẠN THẮNG!').css('color', '#2ecc71');
                } else if (challenge.winner_id) {
                    $('#winnerMsg').text('BẠN THUA!').css('color', '#e74c3c');
                    $('#rewardMsg').text('Bạn bị trừ -' + (<?= $challenge['bet_amount'] ?>).toLocaleString() + ' gtlm');
                } else {
                    $('#winnerMsg').text('HÒA!').css('color', '#bdc3c7');
                }
                $('#statusBar').text('Trận đấu kết thúc').removeClass('status-my-turn status-opponent-turn');
                return;
            }

            isMyTurn = (challenge.current_turn_id == myId);
            if (isMyTurn) {
                $('#statusBar').text('Đến lượt bạn đánh!').addClass('status-my-turn').removeClass('status-opponent-turn');
            } else {
                $('#statusBar').text('Đang chờ đối thủ...').addClass('status-opponent-turn').removeClass('status-my-turn');
            }
        }

        function pollGame() {
            if (!gameActive) return;

            $.get('api_pvp_challenge.php', { action: 'get_challenge', challenge_id: challengeId }, function(res) {
                if (res.success) {
                    updateUI(res.challenge);
                }
                setTimeout(pollGame, 2000);
            });
        }

        function makeMove(row, col) {
            if (!isMyTurn || !gameActive) return;

            $.post('api_pvp_challenge.php', {
                action: 'make_move',
                challenge_id: challengeId,
                row: row,
                col: col
            }, function(res) {
                if (res.success) {
                    // Update board immediately for responsiveness
                    const playerSymbol = (myId == challengerId) ? 'X' : 'O';
                    const symbolClass = (myId == challengerId) ? 'symbol-x' : 'symbol-o';
                    $(`.cell[data-row="${row}"][data-col="${col}"]`).html(`<span class="${symbolClass}">${playerSymbol}</span>`).addClass('filled');
                    
                    if (res.is_win) {
                        gameActive = false;
                        setTimeout(() => {
                            $('#winnerOverlay').css('display', 'flex');
                            $('#winnerMsg').text('BẠN THẮNG!').css('color', '#2ecc71');
                            $('#statusBar').text('Trận đấu kết thúc');
                        }, 500);
                    } else {
                        isMyTurn = false;
                        $('#statusBar').text('Đang chờ đối thủ...').addClass('status-opponent-turn').removeClass('status-my-turn');
                    }
                } else {
                    alert(res.message);
                }
            });
        }

        $(document).ready(function() {
            initBoard();
            pollGame();
        });
    </script>
</body>
</html>

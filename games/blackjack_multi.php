<?php
session_start();
if (!isset($_SESSION['Iduser'])) {
    header("Location: ../login.php");
    exit;
}
require_once '../db_connect.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Multiplayer Blackjack | Elite Table</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/game-blackjack-multi.css">
    <script>window.currentUserId = <?= $_SESSION['Iduser'] ?>;</script>
</head>
<body>

    <div class="table-container">
        <div class="blackjack-table">
            <!-- Dealer -->
            <div class="dealer-area">
                <div style="font-weight: 800; letter-spacing: 2px;">DEALER</div>
                <div class="dealer-cards" id="dealer-cards">
                    <!-- Cards from JS -->
                </div>
            </div>

            <!-- Players Seats -->
            <div class="seats-container">
                <?php for($i=0; $i<5; $i++): ?>
                    <div class="seat" id="seat-<?= $i ?>">
                        <div class="player-cards" id="player-cards-<?= $i ?>"></div>
                        <div class="status-badge" style="position:absolute; top:-30px; width:100%; background:#fbbf24; color:#000; border-radius:10px; font-size:10px; font-weight:800; display:none;">HIT</div>
                        <div class="player-avatar">
                            <img src="https://ui-avatars.com/api/?name=User&background=random" style="width:100%; height:100%;" alt="">
                        </div>
                        <div class="player-name" style="font-weight:600; font-size:12px;">TRỐNG</div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <div class="controls" id="game-controls">
            <input type="number" id="bet-amount" value="10000" step="1000" style="background:rgba(255,255,255,0.1); border:1px solid #fff; color:#fff; padding:10px; border-radius:10px; width:120px;">
            <button class="btn-game btn-bet" id="btn-bet">CƯỢC (DEAL)</button>
            <button class="btn-game btn-hit" id="btn-hit">HIT</button>
            <button class="btn-game btn-stand" id="btn-stand">STAND</button>
        </div>

        <a href="../index.php" style="position:fixed; top:20px; left:20px; color:#94a3b8; text-decoration:none; font-weight:600;">🏠 THOÁT</a>
    </div>

    <!-- Chat Box -->
    <div class="chat-box">
        <div style="padding:15px; border-bottom:1px solid rgba(255,255,255,0.1); font-weight:800; font-size:12px;">CHAT BÀN CHƠI</div>
        <div class="chat-messages" id="chat-messages"></div>
        <input type="text" class="chat-input" id="chat-input" placeholder="Nhập tin nhắn...">
    </div>

    <script src="../assets/js/game-blackjack-multi.js"></script>
</body>
</html>

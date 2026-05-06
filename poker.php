<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Texas Hold'em Poker AI - Antigravity Edition</title>
    <link rel="stylesheet" href="assets/css/poker.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Outfit:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
    <div id="game-container">
        <!-- Background Decor -->
        <div class="table-background"></div>
        
        <!-- Game HUD -->
        <div class="hud top-hud">
            <div class="pot-container">
                <span class="pot-label">POT</span>
                <span id="pot-amount">$0</span>
            </div>
        </div>

        <!-- Poker Table -->
        <div class="poker-table">
            <div class="felt"></div>
            
            <!-- AI Section (Top) -->
            <div class="player-area ai-area" id="ai-player">
                <div class="player-info">
                    <div class="avatar">🤖</div>
                    <div class="details">
                        <span class="name">AI Opponent</span>
                        <span class="chips" id="ai-chips">$10,000</span>
                    </div>
                </div>
                <div class="hand" id="ai-hand">
                    <!-- Cards will be injected here -->
                </div>
                <div class="action-bubble" id="ai-action">Thinking...</div>
            </div>

            <!-- Community Cards -->
            <div class="community-cards" id="community-cards">
                <!-- 5 cards -->
            </div>

            <!-- Player Section (Bottom) -->
            <div class="player-area player-own-area" id="human-player">
                <div class="action-bubble" id="player-action">Your Turn</div>
                <div class="hand" id="player-hand">
                    <!-- Cards will be injected here -->
                </div>
                <div class="player-info">
                    <div class="avatar">👤</div>
                    <div class="details">
                        <span class="name">You</span>
                        <span class="chips" id="player-chips">$10,000</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Game Controls -->
        <div class="controls-overlay">
            <div class="game-log" id="game-log">
                <div class="log-entry">Chào mừng bạn đến với Poker AI!</div>
            </div>
            
            <div class="action-buttons" id="action-buttons">
                <button id="btn-fold" class="btn btn-danger">Fold</button>
                <button id="btn-check" class="btn btn-secondary">Check</button>
                <button id="btn-call" class="btn btn-primary">Call</button>
                <button id="btn-raise" class="btn btn-success">Raise 2x</button>
            </div>

            <div class="meta-controls">
                <button id="btn-new-game" class="btn btn-accent">Ván mới</button>
                <a href="index.php" class="btn btn-secondary" style="text-decoration: none; display: inline-block;">Trang chủ</a>
            </div>
        </div>

        <!-- Showdown Result Modal -->
        <div id="result-overlay" class="overlay hidden">
            <div class="result-modal">
                <h1 id="result-title">You Win!</h1>
                <p id="result-desc">Straight Flush beats Two Pair</p>
                <button id="btn-next-round" class="btn btn-primary">Tiếp tục</button>
            </div>
        </div>
    </div>

    <!-- Game Logic Scripts -->
    <script src="assets/js/poker-evaluator.js"></script>
    <script src="assets/js/poker-game.js"></script>
</body>
</html>

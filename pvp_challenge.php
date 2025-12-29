<?php
session_start();

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require 'db_connect.php';

$userId = $_SESSION['Iduser'];
$challengeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Lấy thông tin user
$userSql = "SELECT Name, Money, ImageURL FROM users WHERE Iduser = ?";
$userStmt = $conn->prepare($userSql);
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

// Load theme
require_once 'load_theme.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚔️ Thách Đấu PvP - <?= htmlspecialchars($user['Name']) ?></title>
        <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <style>
        .pvp-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .pvp-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .pvp-header h1 {
            font-size: 32px;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .pvp-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }
        
        .pvp-tab {
            padding: 12px 24px;
            background: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
            font-size: 16px;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .pvp-tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        
        .pvp-tab-content {
            display: none;
        }
        
        .pvp-tab-content.active {
            display: block;
        }
        
        .challenge-form {
            background: rgba(0, 0, 0, 0.3);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-color);
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.3);
            color: var(--text-color);
            font-size: 16px;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .user-search {
            position: relative;
        }
        
        .user-search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.95);
            border: 2px solid var(--primary-color);
            border-radius: 8px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        
        .user-result-item {
            padding: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: background 0.2s;
        }
        
        .user-result-item:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .user-result-item img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }
        
        .challenge-list {
            display: grid;
            gap: 15px;
        }
        
        .challenge-card {
            background: rgba(0, 0, 0, 0.3);
            padding: 20px;
            border-radius: 15px;
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s;
        }
        
        .challenge-card:hover {
            transform: translateX(5px);
            background: rgba(0, 0, 0, 0.4);
        }
        
        .challenge-card.pending {
            border-left-color: #f39c12;
        }
        
        .challenge-card.accepted {
            border-left-color: #27ae60;
        }
        
        .challenge-card.completed {
            border-left-color: #3498db;
        }
        
        .challenge-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .challenge-players {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .challenge-player {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }
        
        .challenge-player img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: 3px solid var(--primary-color);
        }
        
        .vs-text {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .challenge-info {
            text-align: center;
            margin: 15px 0;
        }
        
        .challenge-bet {
            font-size: 20px;
            font-weight: bold;
            color: var(--success-color);
        }
        
        .challenge-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 15px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #27ae60, #229954);
            color: white;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        .game-play-area {
            background: rgba(0, 0, 0, 0.3);
            padding: 30px;
            border-radius: 15px;
            text-align: center;
        }
        
        .coin-flip-area {
            margin: 30px 0;
        }
        
        .coin {
            width: 150px;
            height: 150px;
            margin: 20px auto;
            border-radius: 50%;
            background: linear-gradient(135deg, #f39c12, #e67e22);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            cursor: pointer;
            transition: all 0.3s;
            border: 5px solid #fff;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }
        
        .coin:hover {
            transform: scale(1.1);
        }
        
        .coin.selected {
            border-color: var(--primary-color);
            box-shadow: 0 0 20px var(--primary-color);
        }
        
        .choice-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin: 20px 0;
        }
        
        .choice-btn {
            padding: 15px 30px;
            font-size: 18px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            background: rgba(0, 0, 0, 0.3);
            color: white;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .choice-btn:hover {
            border-color: var(--primary-color);
            background: rgba(102, 126, 234, 0.2);
        }
        
        .choice-btn.selected {
            border-color: var(--primary-color);
            background: var(--primary-color);
        }
        
        .waiting-message {
            padding: 20px;
            background: rgba(241, 196, 15, 0.2);
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .result-area {
            margin: 30px 0;
            padding: 20px;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 15px;
        }
        
        .result-winner {
            font-size: 28px;
            font-weight: bold;
            color: var(--success-color);
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div style="text-align: center; padding: 20px;">
        <a href="index.php" style="color: var(--primary-color); text-decoration: none; font-size: 18px;">← Về Trang Chủ</a>
    </div>
    
    <div class="pvp-container">
        <div class="pvp-header">
            <h1>⚔️ Thách Đấu PvP</h1>
            <p>Đấu 1-1 với người chơi khác và giành chiến thắng!</p>
        </div>
        
        <div class="pvp-tabs">
            <button class="pvp-tab active" onclick="switchTab('create')">Tạo Challenge</button>
            <button class="pvp-tab" onclick="switchTab('pending')">Chờ Chấp Nhận</button>
            <button class="pvp-tab" onclick="switchTab('active')">Đang Chơi</button>
            <button class="pvp-tab" onclick="switchTab('history')">Lịch Sử</button>
        </div>
        
        <!-- Tab Tạo Challenge -->
        <div id="createTab" class="pvp-tab-content active">
            <div class="challenge-form">
                <h2>Tạo Challenge Mới</h2>
                <form id="createChallengeForm">
                    <div class="form-group">
                        <label>Tìm Người Chơi:</label>
                        <div class="user-search">
                            <input type="text" id="userSearch" placeholder="Nhập tên người chơi..." autocomplete="off">
                            <div id="userSearchResults" class="user-search-results"></div>
                        </div>
                        <input type="hidden" id="selectedOpponentId">
                        <div id="selectedOpponent" style="margin-top: 10px; display: none;">
                            <div style="display: flex; align-items: center; gap: 10px; padding: 10px; background: rgba(102, 126, 234, 0.2); border-radius: 8px;">
                                <img id="selectedOpponentAvatar" width="40" height="40" style="border-radius: 50%;">
                                <span id="selectedOpponentName"></span>
                                <button type="button" onclick="clearOpponent()" style="margin-left: auto; background: #e74c3c; border: none; color: white; padding: 5px 10px; border-radius: 5px; cursor: pointer;">X</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Loại Game:</label>
                        <select id="gameType" name="game_type">
                            <option value="coinflip">Coin Flip (Ngửa/Sấp)</option>
                            <option value="dice">Dice (Xúc Xắc)</option>
                            <option value="rps">Rock Paper Scissors (Oẳn Tù Tì)</option>
                            <option value="number">Đoán Số (1-100)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Số Tiền Cược (VNĐ):</label>
                        <input type="number" id="betAmount" name="bet_amount" min="1000" step="1000" value="10000" required>
                        <small style="color: rgba(255, 255, 255, 0.6);">Số dư hiện tại: <?= number_format($user['Money'], 0, ',', '.') ?> VNĐ</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">⚔️ Tạo Challenge</button>
                </form>
            </div>
        </div>
        
        <!-- Tab Chờ Chấp Nhận -->
        <div id="pendingTab" class="pvp-tab-content">
            <h2>Challenges Đang Chờ</h2>
            <div id="pendingChallenges" class="challenge-list"></div>
        </div>
        
        <!-- Tab Đang Chơi -->
        <div id="activeTab" class="pvp-tab-content">
            <h2>Challenges Đang Chơi</h2>
            <div id="activeChallenges" class="challenge-list"></div>
        </div>
        
        <!-- Tab Lịch Sử -->
        <div id="historyTab" class="pvp-tab-content">
            <h2>Lịch Sử Đấu</h2>
            <div id="historyChallenges" class="challenge-list"></div>
        </div>
    </div>
    
    <script>
        let selectedOpponentId = null;
        let currentChallengeId = null;
        
        function switchTab(tab) {
            document.querySelectorAll('.pvp-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.pvp-tab-content').forEach(c => c.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById(tab + 'Tab').classList.add('active');
            
            if (tab === 'pending') loadPendingChallenges();
            if (tab === 'active') loadActiveChallenges();
            if (tab === 'history') loadHistoryChallenges();
        }
        
        // User search
        document.getElementById('userSearch').addEventListener('input', function(e) {
            const query = e.target.value.trim();
            if (query.length < 2) {
                document.getElementById('userSearchResults').style.display = 'none';
                return;
            }
            
            fetch(`api_profile.php?action=search&q=${encodeURIComponent(query)}`)
                .then(r => r.json())
                .then(data => {
                    const results = document.getElementById('userSearchResults');
                    results.innerHTML = '';
                    
                    if (data.success && data.users) {
                        data.users.forEach(user => {
                            if (user.id == <?= $userId ?>) return; // Bỏ qua chính mình
                            
                            const item = document.createElement('div');
                            item.className = 'user-result-item';
                            item.innerHTML = `
                                <img src="${user.avatar || 'images.ico'}" alt="${user.name}">
                                <span>${user.name}</span>
                            `;
                            item.onclick = () => selectOpponent(user.id, user.name, user.avatar);
                            results.appendChild(item);
                        });
                        
                        results.style.display = results.children.length > 0 ? 'block' : 'none';
                    }
                });
        });
        
        function selectOpponent(id, name, avatar) {
            selectedOpponentId = id;
            document.getElementById('selectedOpponentId').value = id;
            document.getElementById('selectedOpponentName').textContent = name;
            document.getElementById('selectedOpponentAvatar').src = avatar || 'images.ico';
            document.getElementById('selectedOpponent').style.display = 'block';
            document.getElementById('userSearch').value = '';
            document.getElementById('userSearchResults').style.display = 'none';
        }
        
        function clearOpponent() {
            selectedOpponentId = null;
            document.getElementById('selectedOpponentId').value = '';
            document.getElementById('selectedOpponent').style.display = 'none';
        }
        
        // Create challenge
        document.getElementById('createChallengeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!selectedOpponentId) {
                alert('Vui lòng chọn đối thủ!');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'create_challenge');
            formData.append('opponent_id', selectedOpponentId);
            formData.append('game_type', document.getElementById('gameType').value);
            formData.append('bet_amount', document.getElementById('betAmount').value);
            
            fetch('api_pvp_challenge.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Đã tạo challenge thành công!');
                    clearOpponent();
                    document.getElementById('createChallengeForm').reset();
                    switchTab('pending');
                } else {
                    alert(data.message || 'Lỗi!');
                }
            });
        });
        
        function loadPendingChallenges() {
            fetch('api_pvp_challenge.php?action=get_my_challenges&status=pending')
                .then(r => r.json())
                .then(data => {
                    const container = document.getElementById('pendingChallenges');
                    container.innerHTML = '';
                    
                    if (!data.success || !data.challenges.length) {
                        container.innerHTML = '<p style="text-align: center; color: rgba(255,255,255,0.6);">Chưa có challenge nào đang chờ.</p>';
                        return;
                    }
                    
                    data.challenges.forEach(challenge => {
                        const card = createChallengeCard(challenge);
                        container.appendChild(card);
                    });
                });
        }
        
        function loadActiveChallenges() {
            fetch('api_pvp_challenge.php?action=get_my_challenges&status=accepted')
                .then(r => r.json())
                .then(data => {
                    const container = document.getElementById('activeChallenges');
                    container.innerHTML = '';
                    
                    if (!data.success || !data.challenges.length) {
                        container.innerHTML = '<p style="text-align: center; color: rgba(255,255,255,0.6);">Chưa có challenge nào đang chơi.</p>';
                        return;
                    }
                    
                    data.challenges.forEach(challenge => {
                        const card = createChallengeCard(challenge, true);
                        container.appendChild(card);
                    });
                });
        }
        
        function loadHistoryChallenges() {
            fetch('api_pvp_challenge.php?action=get_my_challenges&status=completed')
                .then(r => r.json())
                .then(data => {
                    const container = document.getElementById('historyChallenges');
                    container.innerHTML = '';
                    
                    if (!data.success || !data.challenges.length) {
                        container.innerHTML = '<p style="text-align: center; color: rgba(255,255,255,0.6);">Chưa có lịch sử đấu.</p>';
                        return;
                    }
                    
                    data.challenges.forEach(challenge => {
                        const card = createChallengeCard(challenge);
                        container.appendChild(card);
                    });
                });
        }
        
        function createChallengeCard(challenge, showPlay = false) {
            const card = document.createElement('div');
            card.className = `challenge-card ${challenge.status}`;
            
            const isChallenger = challenge.challenger_id == <?= $userId ?>;
            const isOpponent = challenge.opponent_id == <?= $userId ?>;
            
            let actionsHtml = '';
            if (challenge.status === 'pending' && isOpponent) {
                actionsHtml = `<button class="btn btn-success" onclick="acceptChallenge(${challenge.id})">Chấp Nhận</button>`;
            } else if (challenge.status === 'pending' && isChallenger) {
                actionsHtml = `<button class="btn btn-danger" onclick="cancelChallenge(${challenge.id})">Hủy</button>`;
            } else if (challenge.status === 'accepted' && showPlay) {
                actionsHtml = `<button class="btn btn-primary" onclick="playChallenge(${challenge.id}, '${challenge.game_type}')">Chơi Ngay</button>`;
            }
            
            card.innerHTML = `
                <div class="challenge-header">
                    <span>Challenge #${challenge.id}</span>
                    <span style="text-transform: uppercase; color: rgba(255,255,255,0.6);">${challenge.status}</span>
                </div>
                <div class="challenge-players">
                    <div class="challenge-player">
                        <img src="${challenge.challenger_avatar || 'images.ico'}" alt="${challenge.challenger_name}">
                        <span>${challenge.challenger_name}</span>
                    </div>
                    <div class="vs-text">VS</div>
                    <div class="challenge-player">
                        <img src="${challenge.opponent_avatar || 'images.ico'}" alt="${challenge.opponent_name}">
                        <span>${challenge.opponent_name}</span>
                    </div>
                </div>
                <div class="challenge-info">
                    <div>Game: <strong>${getGameTypeName(challenge.game_type)}</strong></div>
                    <div class="challenge-bet">Cược: ${formatMoney(challenge.bet_amount)} VNĐ</div>
                    ${challenge.result ? `<div class="result-winner">Kết quả: ${getResultText(challenge, isChallenger)}</div>` : ''}
                </div>
                ${actionsHtml ? `<div class="challenge-actions">${actionsHtml}</div>` : ''}
            `;
            
            return card;
        }
        
        function getGameTypeName(type) {
            const names = {
                'coinflip': 'Coin Flip',
                'dice': 'Dice',
                'rps': 'Rock Paper Scissors',
                'number': 'Đoán Số'
            };
            return names[type] || type;
        }
        
        function formatMoney(amount) {
            return new Intl.NumberFormat('vi-VN').format(amount);
        }
        
        function getResultText(challenge, isChallenger) {
            if (challenge.result === 'draw') return 'Hòa';
            if (challenge.result === 'challenger_win' && isChallenger) return 'Bạn Thắng! 🎉';
            if (challenge.result === 'opponent_win' && !isChallenger) return 'Bạn Thắng! 🎉';
            return 'Bạn Thua 😢';
        }
        
        function acceptChallenge(challengeId) {
            const formData = new FormData();
            formData.append('action', 'accept_challenge');
            formData.append('challenge_id', challengeId);
            
            fetch('api_pvp_challenge.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Đã chấp nhận challenge!');
                    loadPendingChallenges();
                    switchTab('active');
                } else {
                    alert(data.message || 'Lỗi!');
                }
            });
        }
        
        function cancelChallenge(challengeId) {
            if (!confirm('Bạn có chắc muốn hủy challenge này?')) return;
            
            const formData = new FormData();
            formData.append('action', 'cancel_challenge');
            formData.append('challenge_id', challengeId);
            
            fetch('api_pvp_challenge.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Đã hủy challenge!');
                    loadPendingChallenges();
                } else {
                    alert(data.message || 'Lỗi!');
                }
            });
        }
        
        function playChallenge(challengeId, gameType) {
            // Mở modal hoặc chuyển trang để chơi
            window.location.href = `pvp_play.php?id=${challengeId}`;
        }
        
        // Load pending challenges on page load
        loadPendingChallenges();
    </script>
</body>
</html>


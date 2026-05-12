<?php
session_start();
require '../db_connect.php';
require_once '../load_theme.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: ../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>🎴 Tứ Sắc Cổ Truyền 🎴</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background: #022c22; /* Even deeper green */
            color: #fff;
            font-family: 'Exo 2', serif;
            margin: 0;
            overflow: hidden;
            background-image: radial-gradient(circle at center, #065f46 0%, #022c22 100%);
        }
        .table-area {
            width: 100vw;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 1.5rem;
            box-sizing: border-box;
            position: relative;
        }
        .opponent {
            display: flex;
            justify-content: center;
            gap: 8px;
            opacity: 0.6;
        }
        .player-hand {
            display: flex;
            justify-content: center;
            gap: 2px;
            perspective: 2000px;
            height: 180px;
            align-items: flex-end;
        }
        .card {
            width: 40px;
            height: 130px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 8px;
            border: 1px solid rgba(0,0,0,0.1);
            color: #000;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            box-shadow: 2px 5px 15px rgba(0,0,0,0.3);
            user-select: none;
        }
        .card:hover {
            transform: translateY(-40px) rotate(2deg);
            z-index: 100;
            box-shadow: 0 15px 30px rgba(0,0,0,0.5);
        }
        .card.selected {
            transform: translateY(-60px);
            box-shadow: 0 0 20px #fbbf24;
            border: 2px solid #fbbf24;
            z-index: 101;
        }
        .card .rank { 
            writing-mode: vertical-rl; 
            text-orientation: upright; 
            letter-spacing: 2px;
        }
        .card.red { color: #e11d48; border-bottom: 8px solid #e11d48; }
        .card.yellow { color: #d97706; border-bottom: 8px solid #d97706; }
        .card.blue { color: #2563eb; border-bottom: 8px solid #2563eb; }
        .card.white { color: #4b5563; border-bottom: 8px solid #4b5563; }

        .center-deck {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 3rem;
            flex-grow: 1;
        }
        .deck-back {
            width: 45px; height: 140px;
            background: linear-gradient(135deg, #991b1b, #7f1d1d);
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            box-shadow: 0 10px 20px rgba(0,0,0,0.4);
            cursor: pointer;
            transition: 0.3s;
        }
        .deck-back:hover { transform: scale(1.05); border-color: #fff; }
        
        .discard-pile {
            width: 45px; height: 140px;
            border: 2px dashed rgba(255,255,255,0.1);
            border-radius: 8px;
            background: rgba(0,0,0,0.1);
        }

        .controls {
            position: fixed;
            bottom: 40px;
            right: 40px;
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
            z-index: 1000;
        }
        .btn-action {
            padding: 1.2rem 2.5rem;
            background: #fbbf24;
            color: #000;
            border: none;
            border-radius: 50px;
            font-weight: 900;
            cursor: pointer;
            box-shadow: 0 8px 20px rgba(180, 83, 9, 0.4);
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.8rem;
        }
        .btn-action:hover { transform: translateY(-3px); box-shadow: 0 12px 25px rgba(180, 83, 9, 0.6); }
        .btn-action:active { transform: translateY(2px); box-shadow: none; }
        
        .hint-box {
            position: fixed;
            top: 40px; right: 40px;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(10px);
            padding: 1.5rem;
            border-radius: 1.5rem;
            border: 1px solid rgba(251, 191, 36, 0.3);
            font-size: 0.85rem;
            max-width: 280px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            line-height: 1.5;
        }
        .legend-box {
            position: absolute; left: 40px; top: 40px;
            background: rgba(0,0,0,0.4); backdrop-filter: blur(5px); 
            padding: 1.2rem; border-radius: 1.5rem; border: 1px solid rgba(255,255,255,0.1);
            font-size: 0.75rem; line-height: 1.8;
        }
    </style>
</head>
<body>

<div class="table-area">
    <div class="opponent" id="opp-top"></div>

    <div class="center-deck">
        <div class="deck-back">🎴</div>
        <div class="discard-pile" id="discard"></div>
        <div class="turn-box">
            <div id="current-turn-info" style="font-size: 1.2rem; font-weight: bold; color: #fbbf24;">Lượt của bạn</div>
            <div style="font-size: 0.8rem; opacity: 0.8;">Hành động: Húp bài hoặc Thả thính mới</div>
        </div>
    </div>

    <div class="legend-box">
        <b>🎴 QUÂN GIAO LƯU:</b><br>
        🔴 Đỏ | 🟡 Vàng | 🔵 Xanh | ⚪ Trắng<br>
        <i>Tướng, Sĩ, Tượng, Xe, Pháo, Mã, Tốt</i>
    </div>

    <div class="player-hand" id="hand"></div>
</div>

<div class="controls">
    <button class="btn-action" style="background: #10b981; color: white;" onclick="drawCard()">THẢ THÍNH</button>
    <button class="btn-action" style="background: #3b82f6; color: white;" onclick="eatCard()">HÚP BÀI</button>
    <button class="btn-action" style="background: #ef4444; color: white;" onclick="discardCard()">RA CHIÊU</button>
    <button class="btn-action" style="background: #6366f1; color: white;" onclick="showGuide()">THỬ VẬN</button>
</div>

<div class="hint-box" id="hint-text">
    💡 <b>Gợi ý:</b> Hãy chọn 1 quân bài rồi nhấn "Ra chiêu" để kết thúc lượt.
</div>

<script>
    const ranks = ['Tướng', 'Sĩ', 'Tượng', 'Xe', 'Pháo', 'Mã', 'Tốt'];
    const colors = ['red', 'yellow', 'blue', 'white'];
    let hand = [];

    function showGuide() {
        Swal.fire({
            title: 'HƯỚNG DẪN GIAO LƯU',
            html: `<div style="text-align: left; font-size: 0.9rem;"><p><b>1. Mục tiêu:</b> Làm tròn bài bằng cách kết hợp các quân bài thành bộ.</p><p><b>2. Các bộ hợp lệ:</b></p><ul><li><b>Bộ ba:</b> 3 quân giống hệt nhau (Khàn).</li><li><b>Bộ bốn:</b> 4 quân giống hệt nhau (Quàn).</li><li><b>Bộ tướng:</b> 1-4 quân Tướng.</li><li><b>Bộ ba màu:</b> Xe-Pháo-Mã hoặc Sĩ-Tượng-Xe (cùng màu).</li><li><b>Bộ Tốt:</b> 3-4 quân Tốt khác màu.</li></ul><p><b>3. Luật chơi:</b> Húp bài từ đối thủ để tạo bộ hoặc thả thính từ xấp bài chung. Sau đó phải ra chiêu 1 quân bài "rác".</p></div>`,
            icon: 'question'
        });
    }

    function initGame() {
        for(let i=0; i<20; i++) {
            hand.push({
                rank: ranks[Math.floor(Math.random() * ranks.length)],
                color: colors[Math.floor(Math.random() * colors.length)]
            });
        }
        renderHand();
        renderOpponents();
    }

    function renderHand() {
        let html = '';
        hand.sort((a,b) => {
            if(a.color !== b.color) return a.color.localeCompare(b.color);
            return ranks.indexOf(a.rank) - ranks.indexOf(b.rank);
        });
        hand.forEach((c, i) => {
            html += `<div class="card ${c.color}" onclick="selectCard(${i})" id="card-${i}"><div class="rank">${c.rank}</div></div>`;
        });
        $('#hand').html(html);
    }

    function renderOpponents() {
        let html = '';
        for(let i=0; i<15; i++) {
            html += `<div class="card" style="background: #991b1b; border-color: #fff; width: 30px; height: 100px;"></div>`;
        }
        $('#opp-top').html(html);
    }

    let selectedIndex = -1;
    function selectCard(i) {
        $('.card').removeClass('selected');
        $(`#card-${i}`).addClass('selected');
        selectedIndex = i;
    }

    function discardCard() {
        if (selectedIndex === -1) {
            Swal.fire('!', 'Hãy chọn 1 lá bài trong tay để ra chiêu!', 'warning');
            return;
        }
        const card = hand.splice(selectedIndex, 1)[0];
        $('#discard').html(`<div class="card ${card.color}"><div class="rank">${card.rank}</div></div>`);
        selectedIndex = -1;
        renderHand();
        $('#current-turn-info').text('Chờ đối thủ...');
        setTimeout(() => {
            $('#current-turn-info').text('Lượt của bạn');
            $('#hint-text').html('💡 <b>Gợi ý:</b> Đến lượt bạn thả thính hoặc húp bài vừa ra chiêu.');
        }, 1500);
    }

    function drawCard() {
        const newCard = { rank: ranks[Math.floor(Math.random() * ranks.length)], color: colors[Math.floor(Math.random() * colors.length)] };
        hand.push(newCard);
        renderHand();
        $('#hint-text').html(`💡 Bạn vừa thả thính được <b>${newCard.rank} ${newCard.color}</b>. Hãy ra chiêu 1 lá rác.`);
    }

    function eatCard() {
        Swal.fire('Thông báo', 'Bạn không thể húp lá bài này vì không tạo được bộ hợp lệ!', 'info');
    }

    initGame();
</script>
</body>
</html>

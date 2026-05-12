<?php
session_start();
require_once 'db_connect.php';
if (!isset($_SESSION['Iduser'])) { header("Location: login.php"); exit(); }
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Săn Boss Thế Giới - Hắc Long Thần</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #000;
            color: #fff;
            font-family: 'Outfit', sans-serif;
            margin: 0;
            overflow-x: hidden;
        }

        .boss-container {
            height: 100vh;
            background: radial-gradient(circle at center, #2c0000 0%, #000 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .fire-bg {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: url('https://www.transparenttextures.com/patterns/carbon-fibre.png');
            opacity: 0.2;
            pointer-events: none;
        }

        .boss-img {
            width: 400px;
            filter: drop-shadow(0 0 50px #ff4500);
            animation: float 3s ease-in-out infinite;
            cursor: pointer;
            transition: transform 0.1s;
        }

        .boss-img:active { transform: scale(0.9) rotate(2deg); }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }

        .boss-info {
            width: 80%;
            max-width: 800px;
            text-align: center;
            margin-top: 30px;
        }

        .hp-bar-container {
            height: 40px;
            background: #222;
            border-radius: 20px;
            border: 2px solid #555;
            overflow: hidden;
            position: relative;
            box-shadow: 0 0 20px rgba(255, 69, 0, 0.3);
        }

        .hp-fill {
            height: 100%;
            background: linear-gradient(90deg, #ff4500, #ff0000);
            width: 100%;
            transition: width 0.5s cubic-bezier(0, 0, 0, 1);
        }

        .hp-text {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            font-weight: 800;
            text-shadow: 2px 2px #000;
        }

        .damage-popup {
            position: absolute;
            color: #ff4500;
            font-size: 2em;
            font-weight: 900;
            pointer-events: none;
            animation: moveUp 1s ease-out forwards;
            z-index: 100;
        }

        @keyframes moveUp {
            0% { transform: translateY(0) scale(1); opacity: 1; }
            100% { transform: translateY(-100px) scale(1.5); opacity: 0; }
        }

        .rank-panel {
            position: absolute;
            left: 20px; top: 20px;
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .btn-attack {
            margin-top: 20px;
            padding: 15px 60px;
            background: #ff4500;
            color: #fff;
            border: none;
            border-radius: 30px;
            font-size: 1.5em;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(255, 69, 0, 0.4);
            text-transform: uppercase;
        }

        .btn-attack:hover { background: #ff6347; }
        .btn-attack:disabled { background: #555; cursor: not-allowed; }
    </style>
</head>
<body>

    <div class="boss-container" id="gameArea">
        <div class="fire-bg"></div>

        <!-- Bảng xếp hạng sát thương -->
        <div class="rank-panel">
            <h3 style="color: #ff4500; margin-top: 0;"><i class="fa fa-trophy"></i> TOP SÁT THƯƠNG</h3>
            <div id="damageRank">
                <!-- Data from JS -->
            </div>
        </div>

        <img src="https://img.itch.zone/aW1nLzExNjA2Njg3LnBuZw==/original/k%2FPhV8.png" class="boss-img" id="bossImg" onclick="attackBoss()">
        
        <div class="boss-info">
            <h1 id="bossName">HẮC LONG THẦN</h1>
            <div class="hp-bar-container">
                <div class="hp-fill" id="hpFill"></div>
                <div class="hp-text" id="hpText">1.000.000.000 / 1.000.000.000</div>
            </div>
            <button class="btn-attack" id="btnAttack" onclick="attackBoss()">TẤN CÔNG!</button>
            <div style="margin-top: 20px;">
                <a href="index.php" style="color: #aaa; text-decoration: none;"><i class="fa fa-arrow-left"></i> Rút lui về sảnh</a>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        let isAttacking = false;

        function updateStatus() {
            $.get('api_world_boss.php', { action: 'get_status' }, function(res) {
                if (res.success) {
                    const boss = res.boss;
                    $('#bossName').text(boss.name);
                    const hpPercent = (boss.health / boss.max_health) * 100;
                    $('#hpFill').css('width', hpPercent + '%');
                    $('#hpText').text(Number(boss.health).toLocaleString() + ' / ' + Number(boss.max_health).toLocaleString());

                    if (boss.status === 'dead') {
                        $('#btnAttack').prop('disabled', true).text('ĐÃ BỊ TIÊU DIỆT');
                        $('#bossImg').css('filter', 'grayscale(100%)');
                    }

                    // Update Rank
                    let rankHtml = '';
                    res.rank.forEach((r, idx) => {
                        rankHtml += `<div style="font-size: 0.9em; margin-bottom: 5px;">#${idx+1} <b>${r.Name}</b>: ${Number(r.damage).toLocaleString()}</div>`;
                    });
                    $('#damageRank').html(rankHtml);
                }
            });
        }

        function attackBoss() {
            if (isAttacking) return;
            isAttacking = true;

            // Rung màn hình
            $('#gameArea').css('animation', 'shake 0.1s infinite');
            
            $.post('api_world_boss.php', { action: 'attack' }, function(res) {
                isAttacking = false;
                $('#gameArea').css('animation', 'none');

                if (res.success) {
                    showDamage(res.damage);
                    updateStatus();
                } else {
                    alert(res.message);
                }
            }, 'json');
        }

        function showDamage(dmg) {
            const popup = $('<div class="damage-popup">-' + dmg.toLocaleString() + '</div>');
            const x = window.innerWidth / 2 + (Math.random() * 200 - 100);
            const y = window.innerHeight / 2 + (Math.random() * 100 - 50);
            popup.css({ left: x, top: y });
            $('body').append(popup);
            setTimeout(() => popup.remove(), 1000);
        }

        // Add shake animation style
        const style = document.createElement('style');
        style.innerHTML = `
            @keyframes shake {
                0% { transform: translate(1px, 1px) rotate(0deg); }
                10% { transform: translate(-1px, -2px) rotate(-1deg); }
                20% { transform: translate(-3px, 0px) rotate(1deg); }
                30% { transform: translate(3px, 2px) rotate(0deg); }
                40% { transform: translate(1px, -1px) rotate(1deg); }
                50% { transform: translate(-1px, 2px) rotate(-1deg); }
            }
        `;
        document.head.appendChild(style);

        $(document).ready(function() {
            updateStatus();
            setInterval(updateStatus, 3000);
        });
    </script>
</body>
</html>

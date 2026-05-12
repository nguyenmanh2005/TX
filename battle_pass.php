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
    <title>Battle Pass - Mùa 1: Khởi Đầu</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/lobby.css">
    <style>
        body {
            background: #0f0c29;
            background: linear-gradient(to bottom, #0f0c29, #302b63, #24243e);
            color: #fff;
            min-height: 100vh;
            padding: 20px;
        }

        .bp-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .bp-header {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 40px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .bp-header::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 5px;
            background: linear-gradient(90deg, #00f2fe 0%, #4facfe 100%);
        }

        .level-badge {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            box-shadow: 0 0 30px rgba(79, 172, 254, 0.5);
        }

        .level-number { font-size: 2.5em; font-weight: 900; line-height: 1; }
        .level-label { font-size: 0.8em; font-weight: bold; }

        .progress-section { flex: 1; margin: 0 40px; }
        .bp-progress-bar {
            height: 20px;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            margin: 15px 0;
            overflow: hidden;
        }
        .bp-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4facfe, #00f2fe);
            width: 0%;
            transition: width 1s ease-out;
        }

        .main-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        .section-card {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 20px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .mission-item {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid #4facfe;
        }

        .mission-item.completed { border-left-color: #2ecc71; opacity: 0.7; }

        .reward-item {
            padding: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .reward-item.claimed { opacity: 0.5; text-decoration: line-through; }

        .btn-claim {
            background: #2ecc71;
            color: #fff;
            border: none;
            padding: 5px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.8em;
        }
    </style>
</head>
<body>

    <div class="bp-container">
        <div class="bp-header">
            <div class="level-badge">
                <span class="level-label">CẤP</span>
                <span class="level-number" id="bp-level">1</span>
            </div>
            <div class="progress-section">
                <h2 style="margin:0">BATTLE PASS: MÙA 1</h2>
                <div class="bp-progress-bar">
                    <div class="bp-progress-fill" id="bp-progress-fill"></div>
                </div>
                <div style="display:flex; justify-content: space-between; font-size: 0.9em; color: #aaa;">
                    <span id="bp-xp-text">0 / 1000 XP</span>
                    <span>Level kế tiếp: <span id="next-level-reward">...</span></span>
                </div>
            </div>
            <a href="index.php" class="btn btn-secondary">Quay lại</a>
        </div>

        <div class="main-content">
            <!-- Cột Nhiệm Vụ -->
            <div class="section-card">
                <h3><i class="fa fa-tasks"></i> Nhiệm Vụ Hàng Ngày</h3>
                <div id="mission-list" style="margin-top: 20px;">
                    <!-- Missions load here -->
                </div>
            </div>

            <!-- Cột Phần Thưởng -->
            <div class="section-card">
                <h3><i class="fa fa-gift"></i> Phần Thưởng Cấp Độ</h3>
                <div id="reward-list" style="margin-top: 20px;">
                    <!-- Rewards load here -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function loadBP() {
            $.get('api_battle_pass.php', { action: 'get_status' }, function(res) {
                if (res.success) {
                    $('#bp-level').text(res.level);
                    $('#bp-xp-text').text(`${res.xp} / ${res.xp_max} XP`);
                    const percent = (res.xp / res.xp_max) * 100;
                    $('#bp-progress-fill').css('width', percent + '%');

                    // Load Missions
                    let mHtml = '';
                    res.missions.forEach(m => {
                        const isDone = m.status !== 'active';
                        mHtml += `
                            <div class="mission-item ${isDone ? 'completed' : ''}">
                                <div>
                                    <div style="font-weight: bold;">${m.title}</div>
                                    <div style="font-size: 0.8em; color: #aaa;">Tiến độ: ${m.progress || 0}/${m.goal}</div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="color: #4facfe; font-weight: bold;">+${m.reward_xp} XP</div>
                                    ${isDone ? '<span style="color: #2ecc71;"><i class="fa fa-check-circle"></i> Xong</span>' : ''}
                                </div>
                            </div>
                        `;
                    });
                    $('#mission-list').html(mHtml || '<p>Đang cập nhật nhiệm vụ...</p>');

                    // Load Rewards (Hiển thị 10 level gần nhất)
                    let rHtml = '';
                    for (let i = 1; i <= res.level + 5; i++) {
                        const isClaimed = res.claimed.includes(i);
                        const canClaim = i <= res.level && !isClaimed;
                        rHtml += `
                            <div class="reward-item ${isClaimed ? 'claimed' : ''}">
                                <span>Cấp ${i}: ${(i*50000).toLocaleString()} GTLM</span>
                                ${canClaim ? `<button onclick="claimBP(${i})" class="btn-claim">NHẬN</button>` : ''}
                                ${isClaimed ? '<span style="color: #aaa;">ĐÃ NHẬN</span>' : ''}
                            </div>
                        `;
                    }
                    $('#reward-list').html(rHtml);
                }
            }, 'json');
        }

        function claimBP(lvl) {
            $.post('api_battle_pass.php', { action: 'claim_reward', level: lvl }, function(res) {
                if (res.success) {
                    Swal.fire('Thành công!', `Bạn đã nhận được ${res.reward.toLocaleString()} GTLM!`, 'success');
                    loadBP();
                } else {
                    Swal.fire('Lỗi', res.message, 'error');
                }
            }, 'json');
        }

        $(document).ready(function() {
            loadBP();
        });
    </script>
</body>
</html>

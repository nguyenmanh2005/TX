<?php
session_start();
require_once 'db_connect.php';
if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Battle Pass Premium - Mùa 1: Khởi Đầu</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=Orbitron:wght@400;900&display=swap');

        body {
            background: #050505;
            color: #fff;
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 20px;
            overflow-x: hidden;
        }

        .bp-container {
            max-width: 1300px;
            margin: 0 auto;
        }

        /* Header Premium Styling */
        .bp-header {
            background: linear-gradient(135deg, rgba(20, 20, 20, 0.95) 0%, rgba(10, 10, 10, 0.95) 100%);
            border-radius: 24px;
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            position: relative;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
        }

        .level-badge {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #ffd700 0%, #daa520 100%);
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            box-shadow: 0 0 40px rgba(218, 165, 32, 0.4);
            border: 4px solid rgba(255, 255, 255, 0.2);
            color: #000;
        }

        .level-number { font-family: 'Orbitron', sans-serif; font-size: 3em; font-weight: 900; line-height: 1; }
        .level-label { font-size: 0.7em; font-weight: 900; letter-spacing: 2px; }

        .progress-section { flex: 1; margin: 0 40px; }
        .bp-title { font-family: 'Orbitron', sans-serif; font-size: 1.8rem; margin: 0; letter-spacing: 2px; }
        
        .bp-progress-bar {
            height: 14px;
            background: rgba(255,255,255,0.05);
            border-radius: 7px;
            margin: 20px 0;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .bp-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #ffd700, #ff8c00);
            width: 0%;
            transition: width 1s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 0 15px #ffd700;
        }

        .premium-status {
            text-align: center;
            min-width: 200px;
        }

        .btn-upgrade {
            background: linear-gradient(135deg, #ffd700 0%, #ff8c00 100%);
            color: #000;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 900;
            text-transform: uppercase;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.3);
        }

        .btn-upgrade:hover { transform: scale(1.05); filter: brightness(1.1); }

        /* Main Grid */
        .main-content {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
        }

        .section-card {
            background: rgba(15, 15, 15, 0.8);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        h3 { font-family: 'Orbitron', sans-serif; margin-top: 0; color: #ffd700; letter-spacing: 1px; }

        .mission-item {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 16px;
            padding: 18px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: 0.3s;
        }

        .mission-item:hover { background: rgba(255, 255, 255, 0.06); }

        /* Reward Tracks Styling */
        .reward-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
        }

        .reward-row {
            transition: 0.3s;
        }

        .level-cell {
            width: 60px;
            text-align: center;
            font-family: 'Orbitron', sans-serif;
            font-weight: 900;
            color: #888;
            font-size: 1.2rem;
        }

        .track-cell {
            padding: 15px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            width: 45%;
        }

        .premium-track {
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.05) 0%, rgba(218, 165, 32, 0.1) 100%);
            border: 1px solid rgba(255, 215, 0, 0.2);
        }

        .locked { opacity: 0.5; filter: grayscale(1); }

        .reward-box {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .reward-icon {
            font-size: 24px;
            width: 50px;
            height: 50px;
            background: rgba(0,0,0,0.3);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .reward-info { flex: 1; }
        .reward-name { font-size: 0.9rem; font-weight: 700; display: block; }
        .reward-type { font-size: 0.7rem; color: #888; text-transform: uppercase; }

        .btn-claim {
            background: #4ade80;
            color: #000;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 900;
            cursor: pointer;
            transition: 0.3s;
            font-size: 0.75rem;
        }

        .btn-claim:hover { transform: scale(1.1); box-shadow: 0 0 15px #4ade80; }
        .claimed-text { color: #4ade80; font-size: 0.7rem; font-weight: 900; }

        .track-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            padding: 0 20px;
            font-weight: 900;
            font-size: 0.8rem;
            letter-spacing: 2px;
            color: #aaa;
        }
    </style>
</head>
<body>

    <div class="bp-container">
        <div class="bp-header">
            <div class="level-badge" id="levelBadge">
                <span class="level-label">RANK</span>
                <span class="level-number" id="bp-level">1</span>
            </div>
            <div class="progress-section">
                <h2 class="bp-title">BATTLE PASS: SEASON 1</h2>
                <div class="bp-progress-bar">
                    <div class="bp-progress-fill" id="bp-progress-fill"></div>
                </div>
                <div style="display:flex; justify-content: space-between; font-size: 0.85em; color: #888; font-weight: 700;">
                    <span id="bp-xp-text">0 / 1000 XP</span>
                    <span id="premium-status-text">TRACK: FREE</span>
                </div>
            </div>
            <div class="premium-status" id="premium-action-box">
                <!-- Buy Button or Premium Badge -->
            </div>
        </div>

        <div class="main-content">
            <!-- Missions -->
            <div class="section-card">
                <h3>DAILY MISSIONS</h3>
                <div id="mission-list">
                    <!-- Missions -->
                </div>
                <a href="index.php" style="display:block; margin-top:30px; color:#888; text-decoration:none; font-size:0.8rem; font-weight:900;">
                    <i class="fa fa-arrow-left"></i> BACK TO LOBBY
                </a>
            </div>

            <!-- Rewards Tracks -->
            <div class="section-card">
                <div class="track-header">
                    <span>FREE TRACK</span>
                    <span>PREMIUM TRACK</span>
                </div>
                <div id="reward-tracks">
                    <table class="reward-table" id="reward-table">
                        <!-- Rewards injected here -->
                    </table>
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
                    $('#bp-xp-text').text(`${res.xp.toLocaleString()} / ${res.xp_max.toLocaleString()} XP`);
                    const percent = (res.xp / res.xp_max) * 100;
                    $('#bp-progress-fill').css('width', percent + '%');

                    if (res.has_premium) {
                        $('#levelBadge').css('background', 'linear-gradient(135deg, #ffd700, #ff8c00)');
                        $('#premium-status-text').text('TRACK: PREMIUM ACTIVATED').css('color', '#ffd700');
                        $('#premium-action-box').html('<div style="color:#ffd700; font-weight:900; font-size:1.2rem;"><i class="fa fa-crown"></i> PREMIUM</div>');
                    } else {
                        $('#premium-action-box').html('<button onclick="buyPremium()" class="btn-upgrade">UPGRADE PREMIUM</button><p style="font-size:10px; margin-top:10px; color:#aaa;">200,000 GTLM / Mùa</p>');
                    }

                    // Missions
                    let mHtml = '';
                    res.missions.forEach(m => {
                        const isDone = m.status !== 'active';
                        mHtml += `
                            <div class="mission-item" style="${isDone ? 'opacity:0.5' : ''}">
                                <div>
                                    <div style="font-weight: 900; font-size:0.85rem;">${m.title}</div>
                                    <div style="font-size: 0.7rem; color: #888;">PROGRESS: ${m.progress || 0}/${m.goal}</div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="color: #ffd700; font-weight: 900; font-size:0.8rem;">+${m.reward_xp} XP</div>
                                    ${isDone ? '<span style="color: #4ade80; font-size:0.7rem;"><i class="fa fa-check"></i></span>' : ''}
                                </div>
                            </div>
                        `;
                    });
                    $('#mission-list').html(mHtml);

                    // Dual Track Rewards
                    let rTable = '';
                    // Group rewards by level
                    const rewardsByLevel = {};
                    res.rewards.forEach(r => {
                        if (!rewardsByLevel[r.level]) rewardsByLevel[r.level] = {};
                        rewardsByLevel[r.level][r.type] = r;
                    });

                    // Render rows (only first 15 levels for demo)
                    for (let lv = 1; lv <= 15; lv++) {
                        const free = rewardsByLevel[lv]?.free;
                        const premium = rewardsByLevel[lv]?.premium;
                        if (!free || !premium) continue;

                        const isFreeClaimed = res.claimed.includes(lv);
                        const isPremiumClaimed = res.premium_claimed.includes(lv);
                        const canFreeClaim = lv <= res.level && !isFreeClaimed;
                        const canPremiumClaim = lv <= res.level && res.has_premium && !isPremiumClaimed;

                        rTable += `
                            <tr class="reward-row">
                                <td class="level-cell">${lv}</td>
                                <td class="track-cell">
                                    <div class="reward-box">
                                        <div class="reward-icon">${free.icon}</div>
                                        <div class="reward-info">
                                            <span class="reward-type">Free Reward</span>
                                            <span class="reward-name">${free.reward_name}</span>
                                        </div>
                                        ${canFreeClaim ? `<button onclick="claimReward(${lv}, 'free')" class="btn-claim">CLAIM</button>` : ''}
                                        ${isFreeClaimed ? '<span class="claimed-text">CLAIMED</span>' : ''}
                                    </div>
                                </td>
                                <td class="track-cell premium-track ${!res.has_premium ? 'locked' : ''}">
                                    <div class="reward-box">
                                        <div class="reward-icon">${premium.icon}</div>
                                        <div class="reward-info">
                                            <span class="reward-type">Premium Reward</span>
                                            <span class="reward-name">${premium.reward_name}</span>
                                        </div>
                                        ${canPremiumClaim ? `<button onclick="claimReward(${lv}, 'premium')" class="btn-claim">CLAIM</button>` : ''}
                                        ${isPremiumClaimed ? '<span class="claimed-text">CLAIMED</span>' : ''}
                                        ${!res.has_premium ? '<i class="fa fa-lock" style="color:#888;"></i>' : ''}
                                    </div>
                                </td>
                            </tr>
                        `;
                    }
                    $('#reward-table').html(rTable);
                }
            }, 'json');
        }

        function buyPremium() {
            Swal.fire({
                title: 'NÂNG CẤP PREMIUM?',
                text: 'Bạn sẽ mở khóa Premium Track với giá 200,000 GTLM và có thể nhận tất cả phần thưởng cao cấp!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ffd700',
                confirmButtonText: '<span style="color:#000">KÍCH HOẠT</span>'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('api_battle_pass.php', { action: 'buy_premium' }, function(res) {
                        if (res.success) {
                            Swal.fire('Thành công!', res.message, 'success');
                            loadBP();
                        } else {
                            Swal.fire('Lỗi', res.message, 'error');
                        }
                    }, 'json');
                }
            });
        }

        function claimReward(lvl, track) {
            $.post('api_battle_pass.php', { action: 'claim_reward', level: lvl, track: track }, function(res) {
                if (res.success) {
                    Swal.fire('Chúc mừng!', res.message, 'success');
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

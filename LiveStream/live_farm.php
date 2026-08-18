<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
$botUser = getOrCreateBotStreamerUser($conn, 'bot_farm', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

if (!isset($botUserId)) {
    header('Location: ../login.php');
    exit;
}
include '../db_connect.php';
$userId = $botUserId;
$stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$userMoney = $stmt->get_result()->fetch_assoc()['Money'];

require_once '../load_theme.php';
if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #1a1c29 0%, #2a2d3e 100%)';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nông Trại AFK - VIP</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=Space+Grotesk:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: <?= $bgGradientCSS ?>;
            color: #fff;
            min-height: 100vh;
        }

        .game-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .game-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 30px;
            background: rgba(15, 17, 21, 0.8);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        .game-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 2.2rem;
            font-weight: 800;
            background: linear-gradient(90deg, #4ade80, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0;
        }
        
        .user-balance {
            background: rgba(255,255,255,0.05); 
            padding: 15px 25px; 
            border-radius: 12px; 
            border: 1px solid rgba(255,255,255,0.1);
            text-align: right;
        }
        .user-balance span { font-size: 1.5rem; font-family: 'Space Grotesk'; font-weight: bold; color: #fbbf24; }

        .layout-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
        }
        @media (max-width: 900px) { .layout-grid { grid-template-columns: 1fr; } }

        .glass-panel {
            background: rgba(20, 24, 30, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .farm-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            aspect-ratio: 1;
            background: #452c1e;
            padding: 15px;
            border-radius: 16px;
            border: 4px solid #5a3a29;
            box-shadow: inset 0 0 50px rgba(0,0,0,0.8);
        }

        .plot {
            background: #5c3a21;
            border-radius: 12px;
            cursor: url('../img/tay.png'), pointer !important;
            position: relative;
            transition: 0.2s;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 2px solid #3d2412;
            box-shadow: inset 0 0 15px rgba(0,0,0,0.5);
            user-select: none;
            -webkit-user-select: none;
        }
        .plot:hover {
            filter: brightness(1.2);
            border-color: #8b5a33;
        }

        .plot-icon {
            font-size: 3rem;
            margin-bottom: 10px;
            animation: bounce 2s infinite ease-in-out;
        }
        @keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }

        .plot-timer {
            font-size: 1rem;
            font-weight: bold;
            font-family: 'Space Grotesk';
            background: rgba(0,0,0,0.6);
            padding: 4px 8px;
            border-radius: 6px;
        }

        .plot-status {
            position: absolute;
            top: -10px;
            right: -10px;
            background: #ef4444;
            color: #fff;
            padding: 5px;
            border-radius: 50%;
            font-size: 0.8rem;
            display: none;
        }
        .plot.ready .plot-status {
            display: block;
            background: #22c55e;
        }

        .shop-list { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; max-height: 350px; overflow-y: auto; padding-right: 5px; }
        .shop-list::-webkit-scrollbar { width: 6px; }
        .shop-list::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); border-radius: 10px; }
        .shop-list::-webkit-scrollbar-thumb { background: #4ade80; border-radius: 10px; }
        .shop-item {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: rgba(255,255,255,0.05);
            padding: 10px;
            border-radius: 12px;
            transition: 0.2s;
            border: 1px solid rgba(255,255,255,0.05);
            height: 100%;
        }
        .shop-item:hover {
            background: rgba(255,255,255,0.1);
        }

        .item-info { display: flex; align-items: center; gap: 10px; }
        .item-icon { font-size: 1.3rem; background: rgba(0,0,0,0.3); width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; border-radius: 8px; }
        .item-details h4 { margin: 0; font-size: 0.9rem; }
        .item-details p { margin: 3px 0 0; color: #94a3b8; font-size: 0.75rem; }
        .item-count { font-size: 0.9rem; font-weight: bold; color: #4ade80; }

        .btn-buy { background: #3b82f6; border: none; padding: 5px 10px; font-size: 0.8rem; border-radius: 6px; color: #fff; font-weight: bold; cursor: url('../img/tay.png'), pointer !important; transition: 0.2s; }
        .btn-buy:hover { background: #2563eb; }
        
        .btn-market { background: linear-gradient(90deg, #ef4444, #f59e0b); display: block; text-align: center; color: #fff; text-decoration: none; padding: 15px; border-radius: 12px; font-weight: bold; font-size: 1.1rem; margin-top: 20px; transition: 0.3s; cursor: url('../img/tay.png'), pointer !important; }
        .btn-market:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(239, 68, 68, 0.4); }

        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(5px); z-index: 100; align-items: center; justify-content: center; }
        .modal-content { background: #1e293b; padding: 30px; border-radius: 20px; width: 600px; max-width: 95%; border: 1px solid rgba(255,255,255,0.1); max-height: 90vh; overflow-y: auto; }
        .modal-title { margin-top: 0; text-align: center; font-size: 1.5rem; color: #4ade80; }
        #seedList { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .seed-btn { display: flex; align-items: center; justify-content: space-between; padding: 15px; background: rgba(255,255,255,0.05); border-radius: 12px; cursor: url('../img/tay.png'), pointer !important; transition: 0.2s; border: 1px solid transparent; }
        .seed-btn:hover { background: rgba(255,255,255,0.1); border-color: #4ade80; }
        .close-modal { width: 100%; background: #ef4444; border: none; padding: 12px; border-radius: 10px; color: #fff; font-weight: bold; cursor: url('../img/tay.png'), pointer !important; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="game-container">
        <div class="game-header">
            <div>
                <h1 class="game-title">🌾 NÔNG TRẠI AFK</h1>
                <div style="color: #94a3b8; margin-top: 5px;">Trồng cây - Bón phân - Bán lấy GTLM!</div>
            </div>
            <div class="user-balance">
                <div style="font-size: 0.9rem; color: #94a3b8;">Số dư của bạn</div>
                <span id="userMoney"><?= number_format($userMoney) ?></span> GTLM
            </div>
        </div>

        <div class="layout-grid">
            <!-- Khu Vườn -->
            <div class="glass-panel">
                <h3 style="margin-top:0;"><i class="fas fa-seedling"></i> Khu Vườn Của Bạn</h3>
                <div class="farm-grid" id="farmGrid">
                    <!-- JS sẽ render 9 ô đất -->
                </div>
            </div>

            <!-- Cửa Hàng & Nick Đồ -->
            <div class="glass-panel">
                <h3 style="margin-top:0;"><i class="fas fa-store"></i> Cửa Hàng & Túi Đồ</h3>
                
                <div class="shop-list">
                    <!-- Hạt Lúa Mì -->
                    <div class="shop-item">
                        <div class="item-info">
                            <div class="item-icon">🌾</div>
                            <div class="item-details">
                                <h4>Hạt Lúa Mì</h4>
                                <p>1 Phút | 200 GTLM</p>
                            </div>
                        </div>
                        <div style="text-align: right; margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <div class="item-count">Kho: <span id="inv_seed_wheat">0</span></div>
                            <button class="btn-buy" onclick="buyItem('WHEAT')">MUA</button>
                        </div>
                    </div>

                    <!-- Hạt Ngô -->
                    <div class="shop-item">
                        <div class="item-info">
                            <div class="item-icon">🌽</div>
                            <div class="item-details">
                                <h4>Hạt Ngô</h4>
                                <p>3 Phút | 500 GTLM</p>
                            </div>
                        </div>
                        <div style="text-align: right; margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <div class="item-count">Kho: <span id="inv_seed_corn">0</span></div>
                            <button class="btn-buy" onclick="buyItem('CORN')">MUA</button>
                        </div>
                    </div>

                    <!-- Hạt Cà Chua -->
                    <div class="shop-item">
                        <div class="item-info">
                            <div class="item-icon">🍅</div>
                            <div class="item-details">
                                <h4>Hạt Cà Chua</h4>
                                <p>5 Phút | 1,500 GTLM</p>
                            </div>
                        </div>
                        <div style="text-align: right; margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <div class="item-count">Kho: <span id="inv_seed_tomato">0</span></div>
                            <button class="btn-buy" onclick="buyItem('TOMATO')">MUA</button>
                        </div>
                    </div>

                    <!-- Táo -->
                    <div class="shop-item">
                        <div class="item-info">
                            <div class="item-icon">🍎</div>
                            <div class="item-details">
                                <h4>Hạt Táo</h4>
                                <p>10 Phút | 4,000 GTLM</p>
                            </div>
                        </div>
                        <div style="text-align: right; margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <div class="item-count">Kho: <span id="inv_seed_apple">0</span></div>
                            <button class="btn-buy" onclick="buyItem('APPLE')">MUA</button>
                        </div>
                    </div>

                    <!-- Dưa Hấu -->
                    <div class="shop-item">
                        <div class="item-info">
                            <div class="item-icon">🍉</div>
                            <div class="item-details">
                                <h4>Hạt Dưa Hấu</h4>
                                <p>30 Phút | 15,000 GTLM</p>
                            </div>
                        </div>
                        <div style="text-align: right; margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <div class="item-count">Kho: <span id="inv_seed_watermelon">0</span></div>
                            <button class="btn-buy" onclick="buyItem('WATERMELON')">MUA</button>
                        </div>
                    </div>

                    <!-- Dâu Tây -->
                    <div class="shop-item">
                        <div class="item-info">
                            <div class="item-icon">🍓</div>
                            <div class="item-details">
                                <h4>Hạt Dâu Tây</h4>
                                <p>1 Giờ | 30,000 GTLM</p>
                            </div>
                        </div>
                        <div style="text-align: right; margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <div class="item-count">Kho: <span id="inv_seed_strawberry">0</span></div>
                            <button class="btn-buy" onclick="buyItem('STRAWBERRY')">MUA</button>
                        </div>
                    </div>

                    <!-- Nho -->
                    <div class="shop-item">
                        <div class="item-info">
                            <div class="item-icon">🍇</div>
                            <div class="item-details">
                                <h4>Hạt Nho</h4>
                                <p>2 Giờ | 70,000 GTLM</p>
                            </div>
                        </div>
                        <div style="text-align: right; margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <div class="item-count">Kho: <span id="inv_seed_grape">0</span></div>
                            <button class="btn-buy" onclick="buyItem('GRAPE')">MUA</button>
                        </div>
                    </div>

                    <!-- Đào -->
                    <div class="shop-item">
                        <div class="item-info">
                            <div class="item-icon">🍑</div>
                            <div class="item-details">
                                <h4>Hạt Đào Tiên</h4>
                                <p>4 Giờ | 150,000 GTLM</p>
                            </div>
                        </div>
                        <div style="text-align: right; margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <div class="item-count">Kho: <span id="inv_seed_peach">0</span></div>
                            <button class="btn-buy" onclick="buyItem('PEACH')">MUA</button>
                        </div>
                    </div>

                    <!-- Cherry -->
                    <div class="shop-item">
                        <div class="item-info">
                            <div class="item-icon">🍒</div>
                            <div class="item-details">
                                <h4>Hạt Cherry</h4>
                                <p>15 Phút | 8,000 GTLM</p>
                            </div>
                        </div>
                        <div style="text-align: right; margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <div class="item-count">Kho: <span id="inv_seed_cherry">0</span></div>
                            <button class="btn-buy" onclick="buyItem('CHERRY')">MUA</button>
                        </div>
                    </div>

                    <!-- Chanh -->
                    <div class="shop-item">
                        <div class="item-info">
                            <div class="item-icon">🍋</div>
                            <div class="item-details">
                                <h4>Hạt Chanh</h4>
                                <p>45 Phút | 20,000 GTLM</p>
                            </div>
                        </div>
                        <div style="text-align: right; margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <div class="item-count">Kho: <span id="inv_seed_lemon">0</span></div>
                            <button class="btn-buy" onclick="buyItem('LEMON')">MUA</button>
                        </div>
                    </div>

                    <!-- Chuối -->
                    <div class="shop-item">
                        <div class="item-info">
                            <div class="item-icon">🍌</div>
                            <div class="item-details">
                                <h4>Hạt Chuối</h4>
                                <p>1.5 Giờ | 45,000 GTLM</p>
                            </div>
                        </div>
                        <div style="text-align: right; margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <div class="item-count">Kho: <span id="inv_seed_banana">0</span></div>
                            <button class="btn-buy" onclick="buyItem('BANANA')">MUA</button>
                        </div>
                    </div>

                    <!-- Kiwi -->
                    <div class="shop-item">
                        <div class="item-info">
                            <div class="item-icon">🥝</div>
                            <div class="item-details">
                                <h4>Hạt Kiwi</h4>
                                <p>2.5 Giờ | 90,000 GTLM</p>
                            </div>
                        </div>
                        <div style="text-align: right; margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <div class="item-count">Kho: <span id="inv_seed_kiwi">0</span></div>
                            <button class="btn-buy" onclick="buyItem('KIWI')">MUA</button>
                        </div>
                    </div>

                    <!-- Xoài -->
                    <div class="shop-item">
                        <div class="item-info">
                            <div class="item-icon">🥭</div>
                            <div class="item-details">
                                <h4>Hạt Xoài</h4>
                                <p>3.5 Giờ | 120,000 GTLM</p>
                            </div>
                        </div>
                        <div style="text-align: right; margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <div class="item-count">Kho: <span id="inv_seed_mango">0</span></div>
                            <button class="btn-buy" onclick="buyItem('MANGO')">MUA</button>
                        </div>
                    </div>

                    <!-- Dứa -->
                    <div class="shop-item">
                        <div class="item-info">
                            <div class="item-icon">🍍</div>
                            <div class="item-details">
                                <h4>Hạt Dứa</h4>
                                <p>5 Giờ | 200,000 GTLM</p>
                            </div>
                        </div>
                        <div style="text-align: right; margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <div class="item-count">Kho: <span id="inv_seed_pineapple">0</span></div>
                            <button class="btn-buy" onclick="buyItem('PINEAPPLE')">MUA</button>
                        </div>
                    </div>

                    <!-- Dừa -->
                    <div class="shop-item">
                        <div class="item-info">
                            <div class="item-icon">🥥</div>
                            <div class="item-details">
                                <h4>Hạt Dừa</h4>
                                <p>8 Giờ | 350,000 GTLM</p>
                            </div>
                        </div>
                        <div style="text-align: right; margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <div class="item-count">Kho: <span id="inv_seed_coconut">0</span></div>
                            <button class="btn-buy" onclick="buyItem('COCONUT')">MUA</button>
                        </div>
                    </div>

                    <!-- Dưa Lưới -->
                    <div class="shop-item">
                        <div class="item-info">
                            <div class="item-icon">🍈</div>
                            <div class="item-details">
                                <h4>Hạt Dưa Lưới</h4>
                                <p>12 Giờ | 600,000 GTLM</p>
                            </div>
                        </div>
                        <div style="text-align: right; margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <div class="item-count">Kho: <span id="inv_seed_melon">0</span></div>
                            <button class="btn-buy" onclick="buyItem('MELON')">MUA</button>
                        </div>
                    </div>

                    <!-- Cam -->
                    <div class="shop-item">
                        <div class="item-info">
                            <div class="item-icon">🍊</div>
                            <div class="item-details">
                                <h4>Hạt Cam</h4>
                                <p>10 Giờ | 450,000 GTLM</p>
                            </div>
                        </div>
                        <div style="text-align: right; margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <div class="item-count">Kho: <span id="inv_seed_orange">0</span></div>
                            <button class="btn-buy" onclick="buyItem('ORANGE')">MUA</button>
                        </div>
                    </div>

                    <!-- Bơ -->
                    <div class="shop-item">
                        <div class="item-info">
                            <div class="item-icon">🥑</div>
                            <div class="item-details">
                                <h4>Hạt Bơ</h4>
                                <p>16 Giờ | 800,000 GTLM</p>
                            </div>
                        </div>
                        <div style="text-align: right; margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <div class="item-count">Kho: <span id="inv_seed_avocado">0</span></div>
                            <button class="btn-buy" onclick="buyItem('AVOCADO')">MUA</button>
                        </div>
                    </div>

                    <!-- Lê -->
                    <div class="shop-item">
                        <div class="item-info">
                            <div class="item-icon">🍐</div>
                            <div class="item-details">
                                <h4>Hạt Lê</h4>
                                <p>20 Giờ | 1,200,000 GTLM</p>
                            </div>
                        </div>
                        <div style="text-align: right; margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <div class="item-count">Kho: <span id="inv_seed_pear">0</span></div>
                            <button class="btn-buy" onclick="buyItem('PEAR')">MUA</button>
                        </div>
                    </div>

                    <!-- Lựu -->
                    <div class="shop-item">
                        <div class="item-info">
                            <div class="item-icon">🍎</div>
                            <div class="item-details">
                                <h4>Hạt Lựu</h4>
                                <p>24 Giờ | 2,000,000 GTLM</p>
                            </div>
                        </div>
                        <div style="text-align: right; margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <div class="item-count">Kho: <span id="inv_seed_pomegranate">0</span></div>
                            <button class="btn-buy" onclick="buyItem('POMEGRANATE')">MUA</button>
                        </div>
                    </div>

                    <!-- Phân Bón -->
                    <div class="shop-item">
                        <div class="item-info">
                            <div class="item-icon">💩</div>
                            <div class="item-details">
                                <h4>Phân Bón</h4>
                                <p>Giảm 50% TG | 500 GTLM</p>
                            </div>
                        </div>
                        <div style="text-align: right; margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <div class="item-count">Kho: <span id="inv_fertilizer">0</span></div>
                            <button class="btn-buy" onclick="buyItem('FERTILIZER')">MUA</button>
                        </div>
                    </div>
                </div>

                <hr style="border-color: rgba(255,255,255,0.1); margin: 20px 0;">
                
                <h3 style="margin-top:0; font-size: 1.1rem;"><i class="fas fa-box"></i> Kho Nông Sản</h3>
                <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; background: rgba(0,0,0,0.3); padding: 12px; border-radius: 12px; font-size: 0.85rem;">
                    <div style="text-align: center;">🌾 <br><b id="inv_crop_wheat">0</b></div>
                    <div style="text-align: center;">🌽 <br><b id="inv_crop_corn">0</b></div>
                    <div style="text-align: center;">🍅 <br><b id="inv_crop_tomato">0</b></div>
                    <div style="text-align: center;">🍎 <br><b id="inv_crop_apple">0</b></div>
                    <div style="text-align: center;">🍉 <br><b id="inv_crop_watermelon">0</b></div>
                    <div style="text-align: center;">🍓 <br><b id="inv_crop_strawberry">0</b></div>
                    <div style="text-align: center;">🍇 <br><b id="inv_crop_grape">0</b></div>
                    <div style="text-align: center;">🍑 <br><b id="inv_crop_peach">0</b></div>
                    <div style="text-align: center;">🍒 <br><b id="inv_crop_cherry">0</b></div>
                    <div style="text-align: center;">🍋 <br><b id="inv_crop_lemon">0</b></div>
                    <div style="text-align: center;">🍌 <br><b id="inv_crop_banana">0</b></div>
                    <div style="text-align: center;">🥝 <br><b id="inv_crop_kiwi">0</b></div>
                    <div style="text-align: center;">🥭 <br><b id="inv_crop_mango">0</b></div>
                    <div style="text-align: center;">🍍 <br><b id="inv_crop_pineapple">0</b></div>
                    <div style="text-align: center;">🥥 <br><b id="inv_crop_coconut">0</b></div>
                    <div style="text-align: center;">🍈 <br><b id="inv_crop_melon">0</b></div>
                    <div style="text-align: center;">🍊 <br><b id="inv_crop_orange">0</b></div>
                    <div style="text-align: center;">🥑 <br><b id="inv_crop_avocado">0</b></div>
                    <div style="text-align: center;">🍐 <br><b id="inv_crop_pear">0</b></div>
                    <div style="text-align: center;">🍎 <br><b id="inv_crop_pomegranate">0</b></div>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <a href="market.php" class="btn-market" style="flex: 1; margin-top: 0; font-size: 0.9rem; padding: 10px;"><i class="fas fa-chart-line"></i> LÊN SÀN BÁN</a>
                    <button class="btn-market" onclick="harvestAll()" style="flex: 1; margin-top: 0; background: linear-gradient(90deg, #eab308, #f59e0b); border: none; font-size: 0.9rem; padding: 10px;"><i class="fas fa-bolt"></i> THU HOẠCH NHANH</button>
                    <button class="btn-market" onclick="openBotConfigModal()" style="flex: 1; margin-top: 0; background: linear-gradient(90deg, #10b981, #3b82f6); border: none; font-size: 0.9rem; padding: 10px;"><i class="fas fa-robot"></i> CÀI ĐẶT BOT</button>
                </div>
            </div>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="../index.php" style="display: inline-block; color: #94a3b8; text-decoration: none; font-weight: 600; font-size: 1.1rem; transition: 0.3s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">
                🏠 QUAY LẠI TRANG CHỦ
            </a>
        </div>
    </div>

    <!-- Modal Chọn Hạt -->
    <div class="modal" id="seedModal">
        <div class="modal-content">
            <h3 class="modal-title">Gieo Hạt / Bón Phân</h3>
            <div id="seedList">
                <div class="seed-btn" onclick="plantSeed('WHEAT')">
                    <div>🌾 Lúa Mì</div>
                    <div><b id="modal_seed_wheat">0</b> hạt</div>
                </div>
                <div class="seed-btn" onclick="plantSeed('CORN')">
                    <div>🌽 Ngô</div>
                    <div><b id="modal_seed_corn">0</b> hạt</div>
                </div>
                <div class="seed-btn" onclick="plantSeed('TOMATO')">
                    <div>🍅 Cà Chua</div>
                    <div><b id="modal_seed_tomato">0</b> hạt</div>
                </div>
                <div class="seed-btn" onclick="plantSeed('APPLE')">
                    <div>🍎 Táo</div>
                    <div><b id="modal_seed_apple">0</b> hạt</div>
                </div>
                <div class="seed-btn" onclick="plantSeed('WATERMELON')">
                    <div>🍉 Dưa Hấu</div>
                    <div><b id="modal_seed_watermelon">0</b> hạt</div>
                </div>
                <div class="seed-btn" onclick="plantSeed('STRAWBERRY')">
                    <div>🍓 Dâu Tây</div>
                    <div><b id="modal_seed_strawberry">0</b> hạt</div>
                </div>
                <div class="seed-btn" onclick="plantSeed('GRAPE')">
                    <div>🍇 Nho</div>
                    <div><b id="modal_seed_grape">0</b> hạt</div>
                </div>
                <div class="seed-btn" onclick="plantSeed('PEACH')">
                    <div>🍑 Đào Tiên</div>
                    <div><b id="modal_seed_peach">0</b> hạt</div>
                </div>
                <div class="seed-btn" onclick="plantSeed('CHERRY')">
                    <div>🍒 Cherry</div>
                    <div><b id="modal_seed_cherry">0</b> hạt</div>
                </div>
                <div class="seed-btn" onclick="plantSeed('LEMON')">
                    <div>🍋 Chanh</div>
                    <div><b id="modal_seed_lemon">0</b> hạt</div>
                </div>
                <div class="seed-btn" onclick="plantSeed('BANANA')">
                    <div>🍌 Chuối</div>
                    <div><b id="modal_seed_banana">0</b> hạt</div>
                </div>
                <div class="seed-btn" onclick="plantSeed('KIWI')">
                    <div>🥝 Kiwi</div>
                    <div><b id="modal_seed_kiwi">0</b> hạt</div>
                </div>
                <div class="seed-btn" onclick="plantSeed('MANGO')">
                    <div>🥭 Xoài</div>
                    <div><b id="modal_seed_mango">0</b> hạt</div>
                </div>
                <div class="seed-btn" onclick="plantSeed('PINEAPPLE')">
                    <div>🍍 Dứa</div>
                    <div><b id="modal_seed_pineapple">0</b> hạt</div>
                </div>
                <div class="seed-btn" onclick="plantSeed('COCONUT')">
                    <div>🥥 Dừa</div>
                    <div><b id="modal_seed_coconut">0</b> hạt</div>
                </div>
                <div class="seed-btn" onclick="plantSeed('MELON')">
                    <div>🍈 Dưa Lưới</div>
                    <div><b id="modal_seed_melon">0</b> hạt</div>
                </div>
                <div class="seed-btn" onclick="plantSeed('ORANGE')">
                    <div>🍊 Cam</div>
                    <div><b id="modal_seed_orange">0</b> hạt</div>
                </div>
                <div class="seed-btn" onclick="plantSeed('AVOCADO')">
                    <div>🥑 Bơ</div>
                    <div><b id="modal_seed_avocado">0</b> hạt</div>
                </div>
                <div class="seed-btn" onclick="plantSeed('PEAR')">
                    <div>🍐 Lê</div>
                    <div><b id="modal_seed_pear">0</b> hạt</div>
                </div>
                <div class="seed-btn" onclick="plantSeed('POMEGRANATE')">
                    <div>🍎 Lựu</div>
                    <div><b id="modal_seed_pomegranate">0</b> hạt</div>
                </div>
                <div class="seed-btn" onclick="fertilizePlot()" style="border-color: #f59e0b; grid-column: span 2;">
                    <div>💩 Bón Phân (-50% TG)</div>
                    <div><b id="modal_fertilizer">0</b> bao</div>
                </div>
            </div>
            <button class="close-modal" onclick="$('#seedModal').hide()">HỦY BỎ</button>
        </div>
    </div>

    <script src="../assets/js/game-farm.js"></script>

<!-- AUTO-GENERATED BOT SCRIPT -->
<script>
if (typeof jQuery === "undefined") document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
if (typeof gsap === "undefined") document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"><\/script>');
</script>
<script src="../assets/js/bot_virtual_cursor.js"></script>
<script>
    if (typeof BotVirtualCursor !== "undefined") {
        BotVirtualCursor.init("Bot Streamer");
        setInterval(() => {
            const allBtns = Array.from(document.querySelectorAll("button, .btn-bet, .chip, .spin-btn, #btnSpin, .bet-button, .card, .btn-primary, .btn-success, input[type='button'], input[type='submit']"));
            const btns = allBtns.filter(b => {
                if(b.offsetParent === null || b.disabled) return false;
                const txt = (b.innerText || b.value || "").toLowerCase();
                const cls = (b.className || "").toLowerCase();
                const id = (b.id || "").toLowerCase();
                
                // Exclude common navigation/help buttons
                if(txt.includes("hướng dẫn") || txt.includes("trang chủ") || txt.includes("nạp") || txt.includes("rút") || txt.includes("lịch sử") || txt.includes("quay lại") || txt.includes("thoát")) return false;
                if(cls.includes("back") || cls.includes("help") || cls.includes("guide") || cls.includes("close") || cls.includes("swal") || cls.includes("nav")) return false;
                if(id.includes("guide") || id.includes("back") || id.includes("close") || id.includes("nav")) return false;
                
                return true;
            });
            
            if(btns.length > 0) {
                const btn = btns[Math.floor(Math.random() * btns.length)];
                BotVirtualCursor.moveToElement($(btn), 1, 0, () => {
                    setTimeout(() => { 
                        BotVirtualCursor.simulateClick(() => {
                            try { btn.click(); } catch(e){}
                        });
                    }, 500);
                });
            }
        }, 3000 + Math.random() * 4000);
    }
</script>

</body>
</html>

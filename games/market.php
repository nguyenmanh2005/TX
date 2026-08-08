<?php
session_start();
if (!isset($_SESSION['Iduser'])) {
    header('Location: ../login.php');
    exit;
}
include '../db_connect.php';
$userId = $_SESSION['Iduser'];
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
    <title>Sàn Giao Dịch Khoáng Sản - VIP</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=Space+Grotesk:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: <?= $bgGradientCSS ?>;
            color: #fff;
            min-height: 100vh;
        }

        .game-container {
            max-width: 98%;
            margin: 0 auto;
            padding: 10px;
        }

        .game-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 25px;
            background: rgba(15, 17, 21, 0.8);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        .market-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(90deg, #f59e0b, #ef4444);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0;
        }
        .game-subtitle { color: #94a3b8; font-size: 1rem; }
        .back-btn { color: #94a3b8; text-decoration: none; margin-bottom: 10px; display: inline-block; transition: 0.2s; }
        .back-btn:hover { color: #fff; }

        .user-balance {
            background: rgba(255,255,255,0.05); 
            padding: 15px 25px; 
            border-radius: 12px; 
            border: 1px solid rgba(255,255,255,0.1);
            text-align: right;
        }
        .user-balance span { font-size: 1.5rem; font-family: 'Space Grotesk'; font-weight: bold; color: #fbbf24; }
        .guide-box {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        .guide-box i { font-size: 2rem; color: #3b82f6; }
        .guide-box h4 { margin: 0 0 5px 0; color: #60a5fa; }
        .guide-box p { margin: 0; color: #cbd5e1; font-size: 0.95rem; line-height: 1.5; }
        
        .market-grid {
            display: grid;
            grid-template-columns: 1.3fr 1.7fr;
            gap: 20px;
        }
        @media (max-width: 1024px) { 
            .market-grid { grid-template-columns: 1fr; }
            #commoditiesList { grid-template-columns: repeat(2, 1fr) !important; }
        }
        
        #commoditiesList {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            max-height: 650px;
            overflow-y: auto;
            padding-right: 5px;
        }

        /* Tùy chỉnh thanh cuộn cho danh sách */
        #commoditiesList::-webkit-scrollbar { width: 6px; }
        #commoditiesList::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); border-radius: 10px; }
        #commoditiesList::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 10px; }
        
        .glass-panel {
            background: rgba(20, 24, 30, 0.6);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        }
        
        /* Ticker Style */
        .ticker-wrapper {
            background: #000;
            padding: 10px 0;
            overflow: hidden;
            border-bottom: 1px solid #222;
        }
        .ticker-content {
            display: flex;
            gap: 40px;
            animation: ticker 20s linear infinite;
            white-space: nowrap;
        }
        @keyframes ticker { 0% { transform: translateX(100%); } 100% { transform: translateX(-100%); } }
        .ticker-item { font-family: 'Space Grotesk', sans-serif; font-weight: bold; }
        
        /* Order Book & Inventory */
        .stock-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            border-radius: 12px;
            background: rgba(0,0,0,0.4);
            margin-bottom: 0;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
        }
        .stock-item:hover, .stock-item.active {
            background: rgba(245, 158, 11, 0.1);
            border-color: rgba(245, 158, 11, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(245, 158, 11, 0.15);
        }
        
        .price-up { color: #10b981; text-shadow: 0 0 10px rgba(16, 185, 129, 0.4); font-family: 'Space Grotesk'; }
        .price-down { color: #ef4444; text-shadow: 0 0 10px rgba(239, 68, 68, 0.4); font-family: 'Space Grotesk'; }
        
        /* Trade Panel */
        .trade-box {
            background: #0f1115;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
            border: 1px solid #222;
        }
        .trade-input-group {
            display: flex;
            background: #1a1d24;
            border-radius: 8px;
            padding: 5px;
            border: 1px solid #333;
            margin-bottom: 15px;
        }
        .trade-input-group input {
            flex: 1;
            background: transparent;
            border: none;
            color: #fff;
            padding: 12px;
            font-size: 1.2rem;
            text-align: right;
            font-family: 'Space Grotesk';
            outline: none;
        }
        .trade-input-group span {
            padding: 12px;
            color: #888;
            font-weight: bold;
        }
        
        .btn-trade-action {
            width: 100%;
            padding: 15px;
            font-size: 1.1rem;
            font-weight: 800;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-transform: uppercase;
            transition: all 0.3s;
            margin-bottom: 10px;
        }
        .btn-buy-action {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        .btn-buy-action:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(16, 185, 129, 0.5); }
        
        .btn-sell-action {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }
        .btn-sell-action:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(239, 68, 68, 0.5); }

        .inv-badge {
            background: #222;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            color: #fbbf24;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- Ticker -->
    <div class="ticker-wrapper">
        <div class="ticker-content" id="tickerContent">
            <!-- Đổ dữ liệu từ JS -->
            <span style="color:#888;">Đang tải dữ liệu thị trường...</span>
        </div>
    </div>

    <div class="game-container">
        <!-- Header -->
        <div class="game-header">
            <div class="header-left">
                <a href="../index.php" class="back-btn"><i class="fas fa-arrow-left"></i> Quay lại</a>
                <div class="game-title">
                    <h1 class="market-title"><i class="fas fa-chart-pie"></i> SÀN CHỨNG KHOÁN MỎ KHOÁNG</h1>
                    <span class="game-subtitle">Mua đáy, Bán đỉnh - Chạm tới vinh quang</span>
                </div>
            </div>
            <div class="user-balance">
                <i class="fas fa-wallet"></i>
                <span id="userMoney"><?= number_format($userMoney, 0, ',', '.') ?></span> GTLM
            </div>
        </div>

        <div class="market-grid">
            <!-- Main Chart Area -->
            <div class="glass-panel chart-area">
                <div class="guide-box">
                    <i class="fas fa-lightbulb"></i>
                    <div>
                        <h4>Sàn Giao Dịch Hoạt Động Thế Nào?</h4>
                        <p>
                            <b>1. Nguồn Gốc:</b> Quặng (Đá, Sắt, Vàng, Kim Cương) được rớt ra khi bạn ấn <b>Thu Hoạch</b> ở Khu Mỏ AFK.<br>
                            <b>2. Biến Động:</b> Giá quặng sẽ thay đổi lên/xuống ngẫu nhiên mỗi <b>20 giây</b>. Biểu đồ sẽ cập nhật liên tục.<br>
                            <b>3. Mẹo Làm Giàu:</b> Găm hàng chờ lúc giá hiện màu <span class="price-up">Xanh Lá (Tăng)</span> thì ấn <b>BÁN RA</b>. Nếu thấy giá Rẻ (Sập sàn), hãy dùng GTLM <b>MUA VÀO</b> để đầu cơ tích trữ!
                        </p>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px;">
                    <div>
                        <h2 id="chartTitle" style="margin: 0; color: #fff; font-size: 2rem;">--</h2>
                        <div id="chartPrice" style="font-size: 2.5rem; font-family: 'Space Grotesk'; font-weight: bold; margin-top: 5px;">0.00</div>
                    </div>
                </div>

                <div style="width: 100%; height: 280px;">
                    <canvas id="marketChart"></canvas>
                </div>
            </div>

            <!-- Right Sidebar -->
            <div>
                <!-- Trade Panel -->
                <div class="glass-panel" id="tradePanel" style="display: none; margin-bottom: 25px;">
                    <h3 style="margin-top: 0; color: #fff;" id="tradeTitle">Giao Dịch</h3>
                    
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 0.9rem;">
                        <span color="#888">Túi Đồ Hiện Có:</span>
                        <span id="inventoryQty" class="inv-badge">0</span>
                    </div>

                    <div class="trade-box">
                        <div class="trade-input-group">
                            <input type="number" id="tradeQty" min="1" value="1">
                            <span>Số lượng</span>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 0.9rem;">
                            <span color="#888">Tổng Thanh Toán:</span>
                            <span id="totalCost" style="font-weight: bold; color: #fbbf24;">0 GTLM</span>
                        </div>

                        <div style="display: flex; gap: 10px;">
                            <button class="btn-trade-action btn-buy-action" id="btnBuy" style="flex:1;">MUA</button>
                            <button class="btn-trade-action btn-sell-action" id="btnSell" style="flex:1;">BÁN</button>
                        </div>
                    </div>
                </div>

                <!-- Order Book / Danh sách Quặng -->
                <div class="glass-panel">
                    <h3 style="margin-top:0; border-bottom: 1px solid #333; padding-bottom: 10px;">Bảng Giá (Cập nhật 20s/lần)</h3>
                    <div id="commoditiesList">
                        <!-- JS Render -->
                    </div>
                </div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="../index.php" style="display: inline-block; color: #94a3b8; text-decoration: none; font-weight: 600; font-size: 1.1rem; transition: 0.3s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#94a3b8'">
                🏠 QUAY LẠI TRANG CHỦ
            </a>
        </div>
    </div>

    <script src="../assets/js/game-market.js"></script>
</body>
</html>

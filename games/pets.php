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
    <title>Chuồng Thú Cưng - Pet House</title>
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
            background: linear-gradient(90deg, #3b82f6, #8b5cf6);
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

        .glass-panel {
            background: rgba(20, 24, 30, 0.6);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        }

        .pets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .pet-card {
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            transition: 0.3s;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .pet-card:hover {
            transform: translateY(-5px);
            border-color: rgba(255,255,255,0.3);
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
        }

        .pet-icon {
            font-size: 4rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }

        .pet-img {
            max-width: 80px;
            max-height: 80px;
            object-fit: contain;
        }

        .pet-name {
            font-size: 1.3rem;
            font-weight: bold;
            color: #fff;
            margin-bottom: 5px;
        }

        .pet-desc {
            font-size: 0.9rem;
            color: #94a3b8;
            margin-bottom: 15px;
            min-height: 40px;
        }

        .pet-price {
            font-family: 'Space Grotesk';
            font-size: 1.2rem;
            font-weight: bold;
            color: #fbbf24;
            margin-bottom: 15px;
        }

        .btn-pet {
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1rem;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-buy {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        .btn-buy:hover { box-shadow: 0 0 15px rgba(16, 185, 129, 0.5); }

        .btn-equip {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
        }
        .btn-equip:hover { box-shadow: 0 0 15px rgba(59, 130, 246, 0.5); }

        .btn-equipped {
            background: rgba(255,255,255,0.1);
            color: #10b981;
            border: 1px solid #10b981;
            cursor: default;
        }

        .btn-unequip {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: 1px solid #ef4444;
            margin-top: 10px;
        }
        .btn-unequip:hover { background: rgba(239, 68, 68, 0.4); }

    </style>
</head>
<body>
    <div class="game-container">
        <!-- Header -->
        <div class="game-header">
            <div class="header-left">
                <a href="../index.php" class="back-btn"><i class="fas fa-arrow-left"></i> Quay lại Lobby</a>
                <div class="game-title">
                    <h1 class="market-title"><i class="fas fa-paw"></i> CHUỒNG THÚ CƯNG</h1>
                    <span class="game-subtitle">Mua và Trang bị Thú Cưng để nhận Buff nội tại vĩnh viễn!</span>
                </div>
            </div>
            <div class="user-balance">
                <i class="fas fa-wallet"></i>
                <span id="userMoney"><?= number_format($userMoney, 0, ',', '.') ?></span> GTLM
            </div>
        </div>

        <div class="glass-panel">
            <div class="pets-grid" id="petsList">
                <!-- JS Render -->
            </div>
        </div>
        
    </div>

    <script src="../assets/js/game-pets.js"></script>
</body>
</html>

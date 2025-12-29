<?php
session_start();

if (!isset($_SESSION['Iduser'])) {
    header("Location: index.php");
    exit();
}

require 'db_connect.php';

// Kiểm tra kết nối database
if (!$conn || $conn->connect_error) {
    die("Lỗi kết nối database: " . ($conn ? $conn->connect_error : "Không thể kết nối"));
}

// Load theme
require_once 'load_theme.php';

$userId = $_SESSION['Iduser'];

// Lấy số dư (sửa Users thành users)
$sql = "SELECT Money FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Lỗi prepare statement: " . $conn->error);
}
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($money);
$stmt->fetch();
$money = $money ?? 0; // Đảm bảo có giá trị mặc định
$stmt->close();

$message = "";
$winningNumbers = [];
$matchedNumbers = [];
$prize = 0;

// Xử lý vé xổ số
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['numbers']) && is_array($_POST['numbers'])) {
    $selected = array_map('intval', $_POST['numbers']);
    $selected = array_unique($selected);

    $count = count($selected);
    if ($count < 1 || $count > 6) {
        $message = "Vui lòng chọn từ 1 đến 6 số.";
    } else {
        $ticketCost = 10000;
        if ($money < $ticketCost) {
            $message = "❌ Không đủ tiền! Mỗi vé 10.000 VNĐ.";
        } else {
            // Trừ tiền
            $money -= $ticketCost;

            // Quay số
            $pool = range(1, 45);
            shuffle($pool);
            $winningNumbers = array_slice($pool, 0, 6);
            sort($winningNumbers);

            sort($selected);
            $matchedNumbers = array_intersect($selected, $winningNumbers);
            $matchCount = count($matchedNumbers);

            // Tính thưởng
            switch ($matchCount) {
                case 1: $prize = 1000; break;
                case 2: $prize = 5000; break;
                case 3: $prize = 20000; break;
                case 4: $prize = 100000; break;
                case 5: $prize = 1000000; break;
                case 6: $prize = 10000000; break;
                default: $prize = 0;
            }

            $money += $prize;

            // Cập nhật tiền mới (sửa Users thành users)
            $update = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
            if ($update) {
                $update->bind_param("di", $money, $userId);
                $update->execute();
                $update->close();
            } else {
                $message = "❌ Lỗi cập nhật tiền: " . $conn->error;
            }
            
            // Track quest progress và tự động cập nhật streak, VIP, reward points, social feed
            require_once 'game_history_helper.php';
            $betAmount = $ticketCost;
            $winAmount = $prize;
            $isWin = ($prize > 0);
            logGameHistoryWithAll($conn, $userId, 'Vietlott', $betAmount, $winAmount, $isWin);
            
            // Big win notification (nếu thắng lớn)
            if ($prize >= 10000000) {
                // Gửi thông báo toàn server
                if (file_exists('api_check_big_win.php')) {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/api_check_big_win.php');
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, 'win_amount=' . $prize);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
                    @curl_exec($ch);
                    curl_close($ch);
                }
            }

            $message = "🎯 Kết quả: " . implode(", ", $winningNumbers) . "<br>";
            $message .= "✅ Bạn chọn: " . implode(", ", $selected) . "<br>";
            $message .= "🔢 Trùng: " . implode(", ", $matchedNumbers) . " ({$matchCount} số)<br>";
            $message .= ($prize > 0) ? "🎉 Thưởng: " . number_format($prize) . " VNĐ!" : "🙁 Không trúng thưởng.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Xổ Số Vietlott</title>
      <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
        <link rel="stylesheet" href="assets/css/game-effects.css">
        <link rel="stylesheet" href="assets/css/game-ui-enhancements.css">
        <link rel="stylesheet" href="assets/css/game-effects.css">
        <link rel="stylesheet" href="assets/css/game-ui-enhancements.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
  <style>
    /* Three.js canvas background */
    #threejs-background {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      z-index: -1;
      opacity: 0.8;
    }
    
    body {
        cursor: url('chuot.png'), url('../chuot.png'), auto !important;
        font-family: 'Segoe UI', Arial, sans-serif;
        background: <?= $bgGradientCSS ?>;
        background-attachment: fixed;
        text-align: center;
        padding: 30px 20px;
        min-height: 100vh;
        position: relative;
    }
    
    * {
        cursor: inherit;
    }

    button, a, input[type="button"], input[type="submit"], label, select, input[type="checkbox"] {
        cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
    }
    
    .game-container {
        max-width: 700px;
        margin: 0 auto;
        background: rgba(255, 255, 255, 0.98);
        padding: 40px;
        border-radius: var(--border-radius-lg);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
        border: 2px solid rgba(255, 255, 255, 0.5);
        position: relative;
        z-index: 1;
    }
    
    h1 {
        color: var(--primary-color);
        font-size: 32px;
        font-weight: 700;
        margin-bottom: 20px;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .balance-display {
        font-size: 22px;
        font-weight: 700;
        color: var(--success-color);
        padding: 15px;
        background: rgba(232, 245, 233, 0.5);
        border-radius: var(--border-radius);
        border: 2px solid var(--success-color);
        margin: 20px 0;
    }
    
    .number-grid {
        display: grid;
        grid-template-columns: repeat(9, 1fr);
        gap: 8px;
        max-width: 600px;
        margin: 30px auto;
    }
    
    input[type="checkbox"] {
        display: none;
    }
    
    label {
        display: block;
        padding: 12px;
        background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        color: #fff;
        border-radius: var(--border-radius);
        cursor: pointer;
        font-weight: 600;
        font-size: 16px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
        position: relative;
        overflow: hidden;
    }
    
    label::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.3);
        transform: translate(-50%, -50%);
        transition: width 0.6s, height 0.6s;
    }
    
    label:hover::before {
        width: 300px;
        height: 300px;
    }
    
    label:hover {
        transform: translateY(-3px) scale(1.05);
        box-shadow: 0 4px 15px rgba(52, 152, 219, 0.5);
    }
    
    input[type="checkbox"]:checked + label {
        background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
        box-shadow: 0 0 20px rgba(46, 204, 113, 0.6);
        animation: numberSelect 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    }
    
    @keyframes numberSelect {
        0% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.2) rotate(5deg);
        }
        100% {
            transform: scale(1);
        }
    }
    
    .btn {
        margin-top: 20px;
        padding: 14px 32px;
        font-size: 18px;
        font-weight: 700;
        background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        color: white;
        border: none;
        border-radius: var(--border-radius);
        cursor: pointer;
        box-shadow: 0 4px 15px rgba(39, 174, 96, 0.4);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .btn:hover:not(:disabled) {
        transform: translateY(-3px) scale(1.05);
        box-shadow: 0 8px 25px rgba(39, 174, 96, 0.6);
        background: linear-gradient(135deg, #229954 0%, #27ae60 100%);
    }
    
    .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed !important;
    }
    
    .message {
        margin-top: 30px;
        font-size: 18px;
        font-weight: 600;
        color: var(--text-dark);
        padding: 20px;
        border-radius: var(--border-radius);
        background: rgba(240, 248, 255, 0.8);
        border: 2px solid rgba(255, 255, 255, 0.5);
        animation: messageAppear 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        line-height: 1.8;
    }
    
    .message.win {
        background: rgba(40, 167, 69, 0.2);
        border: 3px solid #28a745;
        color: #00ff00;
        box-shadow: 0 0 25px rgba(40, 167, 69, 0.6);
        animation: messageAppear 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55), winPulse 1.5s ease infinite;
    }
    
    .message.lose {
        background: rgba(220, 53, 69, 0.2);
        border: 3px solid #dc3545;
        color: #ff6b6b;
    }
    
    @keyframes messageAppear {
        0% {
            opacity: 0;
            transform: translateY(-30px) scale(0.8);
        }
        50% {
            transform: translateY(5px) scale(1.1);
        }
        100% {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
    
    @keyframes winPulse {
        0%, 100% {
            transform: scale(1);
            box-shadow: 0 0 25px rgba(40, 167, 69, 0.6);
        }
        50% {
            transform: scale(1.02);
            box-shadow: 0 0 40px rgba(40, 167, 69, 0.9);
        }
    }
    
    a {
        display: inline-block;
        margin-top: 25px;
        padding: 12px 24px;
        background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
        color: white;
        text-decoration: none;
        border-radius: var(--border-radius);
        font-weight: 600;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
    }
    
    a:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(52, 152, 219, 0.5);
    }
    
    .selected-count {
        font-size: 18px;
        font-weight: 600;
        color: var(--primary-color);
        margin: 15px 0;
        padding: 10px;
        background: rgba(52, 152, 219, 0.1);
        border-radius: var(--border-radius);
    }
  </style>
</head>
<body>
  <canvas id="threejs-background"></canvas>
  <div class="game-container">
    <h1>🎰 Xổ Số Vietlott</h1>
    <div class="balance-display">💰 Số dư: <strong><?= number_format($money, 0, ',', '.') ?> VNĐ</strong></div>

    <!-- Chọn số -->
    <form method="POST" id="lotteryForm">
      <input type="hidden" name="action" value="buy_ticket">
      <div class="selected-count" id="selectedCount">Đã chọn: 0/6 số</div>
      <div class="number-grid">
        <?php for ($i = 1; $i <= 45; $i++): ?>
          <input type="checkbox" id="n<?= $i ?>" name="numbers[]" value="<?= $i ?>" hidden>
          <label for="n<?= $i ?>"><?= $i ?></label>
        <?php endfor; ?>
      </div>
      <button type="submit" class="btn" id="submitBtn">🎫 Mua vé - 10.000 VNĐ</button>
    </form>

    <?php if ($message): ?>
      <div class="message <?= $prize > 0 ? 'win' : 'lose' ?>">
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <a href="index.php">🏠 Quay Lại Trang Chủ</a>
  </div>
  
  <script>
    // Đảm bảo cursor luôn hoạt động
    document.addEventListener('DOMContentLoaded', function() {
        document.body.style.cursor = "url('chuot.png'), url('../chuot.png'), auto";
        
        const interactiveElements = document.querySelectorAll('button, a, input, label, select');
        interactiveElements.forEach(el => {
            el.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
        });
    });
    
    // Giới hạn tối đa 6 số và cập nhật số đã chọn
    const checkboxes = document.querySelectorAll('input[type="checkbox"]');
    const selectedCountEl = document.getElementById('selectedCount');
    const submitBtn = document.getElementById('submitBtn');
    const form = document.getElementById('lotteryForm');
    
    function updateSelectedCount() {
        const selected = document.querySelectorAll('input[type="checkbox"]:checked');
        const count = selected.length;
        selectedCountEl.textContent = `Đã chọn: ${count}/6 số`;
        
        if (count >= 6) {
            selectedCountEl.style.background = 'rgba(46, 204, 113, 0.2)';
            selectedCountEl.style.color = '#27ae60';
        } else {
            selectedCountEl.style.background = 'rgba(52, 152, 219, 0.1)';
            selectedCountEl.style.color = 'var(--primary-color)';
        }
    }
    
    checkboxes.forEach(cb => {
        cb.addEventListener('change', () => {
            const selected = document.querySelectorAll('input[type="checkbox"]:checked');
            if (selected.length > 6) {
                cb.checked = false;
                alert("Chỉ được chọn tối đa 6 số.");
            }
            updateSelectedCount();
        });
    });
    
    // Xử lý form submit
    if (form) {
        form.addEventListener('submit', function(e) {
            const selected = document.querySelectorAll('input[type="checkbox"]:checked');
            if (selected.length === 0) {
                e.preventDefault();
                alert("Vui lòng chọn ít nhất 1 số!");
                return false;
            }
            submitBtn.disabled = true;
            submitBtn.textContent = 'Đang xử lý... 🎰';
        });
    }
    
    // Cập nhật số đã chọn ban đầu
    updateSelectedCount();
  </script>

  <script>
    // Three.js 3D Background với Theme Config - Hỗ trợ Vũ Trụ và Anime
    (function() {
        const canvas = document.getElementById('threejs-background');
        if (!canvas) return;
        
        // Lấy config từ PHP
        const themeConfig = {
            particleCount: <?= $particleCount ?>,
            particleSize: <?= $particleSize ?>,
            particleColor: '<?= $particleColor ?>',
            particleOpacity: <?= $particleOpacity ?>,
            shapeCount: <?= $shapeCount ?>,
            shapeColors: <?= json_encode($shapeColors) ?>,
            shapeOpacity: <?= $shapeOpacity ?>,
            bgGradient: <?= json_encode($bgGradient) ?>,
            themeName: '<?= addslashes($themeName) ?>'
        };
        
        // Kiểm tra xem có phải theme Anime không
        const isAnimeTheme = themeConfig.themeName.toLowerCase().includes('anime') || 
                            themeConfig.themeName.toLowerCase().includes('sakura') ||
                            themeConfig.themeName.toLowerCase().includes('pastel');
        
        // Áp dụng background gradient
        if (themeConfig.bgGradient && themeConfig.bgGradient.length >= 2) {
            const gradient = `linear-gradient(135deg, ${themeConfig.bgGradient[0]} 0%, ${themeConfig.bgGradient[1]} 50%, ${themeConfig.bgGradient[2] || themeConfig.bgGradient[1]} 100%)`;
            document.body.style.background = gradient;
        }
        
        const scene = new THREE.Scene();
        const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
        const renderer = new THREE.WebGLRenderer({ 
            canvas: canvas, 
            alpha: true, 
            antialias: false, // Tắt antialias để giảm lag
            powerPreference: "high-performance"
        });
        renderer.setSize(window.innerWidth, window.innerHeight);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5)); // Giảm pixel ratio
        renderer.setClearColor(0x000000, 0); // Transparent background
        
        // Tạo các particles (ngôi sao) với config
        const particlesGeometry = new THREE.BufferGeometry();
        const particlesCount = themeConfig.particleCount;
        const posArray = new Float32Array(particlesCount * 3);
        const velocityArray = new Float32Array(particlesCount * 3);
        const sizeArray = new Float32Array(particlesCount);
        
        for (let i = 0; i < particlesCount * 3; i += 3) {
            if (isAnimeTheme) {
                // Hiệu ứng Sakura rơi cho anime theme
                posArray[i] = (Math.random() - 0.5) * 60; // Rộng hơn
                posArray[i + 1] = Math.random() * 30 + 10; // Bắt đầu từ trên
                posArray[i + 2] = (Math.random() - 0.5) * 20;
                
                // Vận tốc rơi nhẹ nhàng như sakura
                velocityArray[i] = (Math.random() - 0.5) * 0.03; // Rơi ngang nhẹ
                velocityArray[i + 1] = -(Math.random() * 0.05 + 0.02); // Rơi xuống
                velocityArray[i + 2] = (Math.random() - 0.5) * 0.01; // Xoay nhẹ
                
                // Kích thước sakura lớn hơn
                sizeArray[i / 3] = Math.random() * themeConfig.particleSize * 3 + themeConfig.particleSize;
            } else {
                // Vị trí ngẫu nhiên trong không gian 3D (theme thường)
                posArray[i] = (Math.random() - 0.5) * 50;
                posArray[i + 1] = (Math.random() - 0.5) * 50;
                posArray[i + 2] = (Math.random() - 0.5) * 50;
                
                // Vận tốc ngẫu nhiên cho hiệu ứng di chuyển
                velocityArray[i] = (Math.random() - 0.5) * 0.02;
                velocityArray[i + 1] = (Math.random() - 0.5) * 0.02;
                velocityArray[i + 2] = (Math.random() - 0.5) * 0.02;
                
                // Kích thước ngẫu nhiên cho các ngôi sao
                sizeArray[i / 3] = Math.random() * themeConfig.particleSize * 2 + themeConfig.particleSize * 0.5;
            }
        }
        
        particlesGeometry.setAttribute('position', new THREE.BufferAttribute(posArray, 3));
        particlesGeometry.setAttribute('aVelocity', new THREE.BufferAttribute(velocityArray, 3));
        particlesGeometry.setAttribute('aSize', new THREE.BufferAttribute(sizeArray, 1));
        
        // Convert hex color to number
        const particleColorNum = parseInt(themeConfig.particleColor.replace('#', ''), 16);
        
        // Material với shader để tạo hiệu ứng twinkle (nhấp nháy) hoặc sakura
        const particlesMaterial = new THREE.PointsMaterial({
            size: themeConfig.particleSize,
            color: particleColorNum,
            transparent: true,
            opacity: themeConfig.particleOpacity,
            blending: isAnimeTheme ? THREE.NormalBlending : THREE.AdditiveBlending, // Normal blending cho sakura
            sizeAttenuation: true,
            vertexColors: false
        });
        
        const particlesMesh = new THREE.Points(particlesGeometry, particlesMaterial);
        scene.add(particlesMesh);
        
        // Hàm tạo hình trái tim 3D đẹp hơn
        function createHeartGeometry(size) {
            const shape = new THREE.Shape();
            const heartSize = size * 0.08; // Scale down để phù hợp
            const segments = 30;
            
            // Sử dụng phương trình parametric cho trái tim
            for (let i = 0; i <= segments; i++) {
                const t = (i / segments) * Math.PI * 2;
                // Phương trình trái tim parametric (heart curve)
                const x = heartSize * 16 * Math.pow(Math.sin(t), 3);
                const y = heartSize * (13 * Math.cos(t) - 5 * Math.cos(2*t) - 2 * Math.cos(3*t) - Math.cos(4*t));
                
                if (i === 0) {
                    shape.moveTo(x, y);
                } else {
                    shape.lineTo(x, y);
                }
            }
            shape.closePath();
            
            const extrudeSettings = {
                depth: size * 0.25,
                bevelEnabled: true,
                bevelThickness: size * 0.04,
                bevelSize: size * 0.04,
                bevelSegments: 4
            };
            return new THREE.ExtrudeGeometry(shape, extrudeSettings);
        }
        
        // Hàm tạo hình ngôi sao 3D
        function createStarGeometry(size, points = 5) {
            const shape = new THREE.Shape();
            const outerRadius = size;
            const innerRadius = size * 0.4;
            
            for (let i = 0; i < points * 2; i++) {
                const angle = (i * Math.PI) / points;
                const radius = i % 2 === 0 ? outerRadius : innerRadius;
                const x = Math.cos(angle) * radius;
                const y = Math.sin(angle) * radius;
                
                if (i === 0) {
                    shape.moveTo(x, y);
                } else {
                    shape.lineTo(x, y);
                }
            }
            shape.lineTo(outerRadius, 0);
            
            const extrudeSettings = {
                depth: size * 0.2,
                bevelEnabled: true,
                bevelThickness: size * 0.03,
                bevelSize: size * 0.03,
                bevelSegments: 2
            };
            return new THREE.ExtrudeGeometry(shape, extrudeSettings);
        }
        
        // Hàm tạo hình hoa sakura 5 cánh đẹp hơn
        function createSakuraGeometry(size) {
            const shape = new THREE.Shape();
            const petals = 5;
            const petalLength = size;
            const petalWidth = size * 0.5;
            const centerRadius = size * 0.15;
            
            // Bắt đầu từ tâm
            shape.moveTo(0, -centerRadius);
            
            for (let i = 0; i < petals; i++) {
                const angle = (i * Math.PI * 2) / petals - Math.PI / 2;
                const nextAngle = ((i + 1) * Math.PI * 2) / petals - Math.PI / 2;
                
                // Điểm đầu cánh hoa
                const x1 = Math.cos(angle) * petalLength;
                const y1 = Math.sin(angle) * petalLength;
                
                // Điểm giữa cánh hoa (rộng nhất)
                const midAngle = angle + (nextAngle - angle) / 2;
                const xMid = Math.cos(midAngle) * petalWidth;
                const yMid = Math.sin(midAngle) * petalWidth;
                
                // Điểm cuối cánh hoa
                const x2 = Math.cos(nextAngle) * petalLength;
                const y2 = Math.sin(nextAngle) * petalLength;
                
                // Vẽ cánh hoa với đường cong mềm mại
                shape.quadraticCurveTo(xMid, yMid, x1, y1);
                shape.quadraticCurveTo(xMid, yMid, x2, y2);
            }
            
            // Đóng hình về tâm
            shape.lineTo(0, -centerRadius);
            shape.closePath();
            
            const extrudeSettings = {
                depth: size * 0.2,
                bevelEnabled: true,
                bevelThickness: size * 0.03,
                bevelSize: size * 0.03,
                bevelSegments: 3
            };
            return new THREE.ExtrudeGeometry(shape, extrudeSettings);
        }
        
        // Hàm tạo sparkle hình ngôi sao nhỏ
        function createSparkleGeometry(size) {
            return createStarGeometry(size, 4); // Ngôi sao 4 cánh nhỏ
        }
        
        // Tạo các hình dạng 3D (hành tinh/thiên thể cho vũ trụ, trái tim/ngôi sao cho anime) với config
        const shapes = [];
        const colors = themeConfig.shapeColors.map(c => parseInt(c.replace('#', ''), 16));
        
        for (let i = 0; i < themeConfig.shapeCount; i++) {
            let geometry;
            const shapeType = Math.random();
            const size = Math.random() * 0.5 + 0.3;
            
            if (isAnimeTheme) {
                // Cho anime theme: tạo hình trái tim, ngôi sao, hoa sakura thật
                if (shapeType < 0.35) {
                    // Hình trái tim 3D
                    geometry = createHeartGeometry(size);
                } else if (shapeType < 0.65) {
                    // Hình ngôi sao 5 cánh 3D
                    geometry = createStarGeometry(size, 5);
                } else if (shapeType < 0.85) {
                    // Hoa sakura 5 cánh
                    geometry = createSakuraGeometry(size);
                } else {
                    // Sparkles hình ngôi sao nhỏ
                    geometry = createSparkleGeometry(size * 0.6);
                }
            } else {
                // Sử dụng các hình dạng khác nhau: Sphere, Icosahedron, Octahedron (theme thường)
                if (shapeType < 0.4) {
                    geometry = new THREE.SphereGeometry(Math.random() * 0.8 + 0.3, 32, 32);
                } else if (shapeType < 0.7) {
                    geometry = new THREE.IcosahedronGeometry(Math.random() * 0.6 + 0.3, 0);
                } else {
                    geometry = new THREE.OctahedronGeometry(Math.random() * 0.5 + 0.3, 0);
                }
            }
            
            const material = new THREE.MeshStandardMaterial({
                color: colors[Math.floor(Math.random() * colors.length)],
                transparent: true,
                opacity: themeConfig.shapeOpacity,
                emissive: isAnimeTheme ? colors[Math.floor(Math.random() * colors.length)] : colors[Math.floor(Math.random() * colors.length)],
                emissiveIntensity: isAnimeTheme ? 0.5 : 0.3, // Sáng hơn cho anime
                metalness: isAnimeTheme ? 0.1 : 0.3,
                roughness: isAnimeTheme ? 0.9 : 0.7, // Mềm mại hơn cho anime
                wireframe: isAnimeTheme ? false : (Math.random() > 0.7) // Không wireframe cho anime
            });
            
            const mesh = new THREE.Mesh(geometry, material);
            
            // Vị trí
            const angle = (Math.PI * 2 * i) / themeConfig.shapeCount;
            const radius = isAnimeTheme ? (6 + Math.random() * 4) : (8 + Math.random() * 5);
            mesh.position.set(
                Math.cos(angle) * radius + (Math.random() - 0.5) * 3,
                isAnimeTheme ? (Math.random() * 8 - 4) : ((Math.random() - 0.5) * 10),
                Math.sin(angle) * radius + (Math.random() - 0.5) * 3
            );
            
            // Rotation ban đầu
            mesh.rotation.set(
                Math.random() * Math.PI,
                Math.random() * Math.PI,
                Math.random() * Math.PI
            );
            
            // Lưu thông tin để animation
            mesh.userData = {
                rotationSpeed: {
                    x: isAnimeTheme ? ((Math.random() - 0.5) * 0.03) : ((Math.random() - 0.5) * 0.02),
                    y: isAnimeTheme ? ((Math.random() - 0.5) * 0.03) : ((Math.random() - 0.5) * 0.02),
                    z: isAnimeTheme ? ((Math.random() - 0.5) * 0.03) : ((Math.random() - 0.5) * 0.02)
                },
                orbitRadius: radius,
                orbitAngle: angle,
                orbitSpeed: isAnimeTheme ? ((Math.random() - 0.5) * 0.015) : ((Math.random() - 0.5) * 0.01),
                originalY: mesh.position.y,
                floatSpeed: isAnimeTheme ? (Math.random() * 0.015 + 0.008) : (Math.random() * 0.01 + 0.005),
                floatAmount: isAnimeTheme ? (Math.random() * 3 + 1.5) : (Math.random() * 2 + 1),
                isAnime: isAnimeTheme
            };
            
            shapes.push(mesh);
            scene.add(mesh);
        }
        
        // Ánh sáng - khác nhau cho anime và theme thường
        if (isAnimeTheme) {
            // Ánh sáng mềm mại, pastel cho anime
            const ambientLight = new THREE.AmbientLight(0xffffff, 0.7);
            scene.add(ambientLight);
            
            // Ánh sáng chính nhẹ nhàng
            const pointLight1 = new THREE.PointLight(colors[0] || 0xFFB6C1, 1.2, 100);
            pointLight1.position.set(8, 8, 8);
            scene.add(pointLight1);
            
            // Ánh sáng phụ pastel
            if (colors[1]) {
                const pointLight2 = new THREE.PointLight(colors[1], 0.9, 100);
                pointLight2.position.set(-8, 6, -8);
                scene.add(pointLight2);
            }
            
            // Ánh sáng thứ ba
            if (colors[2]) {
                const pointLight3 = new THREE.PointLight(colors[2], 0.7, 100);
                pointLight3.position.set(0, 10, 0);
                scene.add(pointLight3);
            }
            
            camera.position.z = 12;
            camera.position.y = 3;
            camera.lookAt(0, 0, 0);
        } else {
            // Ánh sáng cho hiệu ứng vũ trụ (theme thường)
            const ambientLight = new THREE.AmbientLight(0xffffff, 0.4);
            scene.add(ambientLight);
            
            // Điểm sáng chính (như mặt trời)
            const pointLight1 = new THREE.PointLight(0xffffff, 1.5, 100);
            pointLight1.position.set(10, 10, 10);
            scene.add(pointLight1);
            
            // Điểm sáng phụ (nebula)
            const pointLight2 = new THREE.PointLight(colors[0] || 0x4169E1, 1, 100);
            pointLight2.position.set(-10, -10, -10);
            scene.add(pointLight2);
            
            // Điểm sáng thứ ba
            if (colors[1]) {
                const pointLight3 = new THREE.PointLight(colors[1], 0.8, 100);
                pointLight3.position.set(0, 10, -15);
                scene.add(pointLight3);
            }
            
            camera.position.z = 15;
            camera.position.y = 5;
            camera.lookAt(0, 0, 0);
        }
        
        let time = 0;
        let lastTime = 0;
        const targetFPS = 30; // Giảm FPS để tối ưu
        const frameInterval = 1000 / targetFPS;
        
        // Animation loop với hiệu ứng theo theme (Vũ Trụ hoặc Anime)
        function animate(currentTime) {
            requestAnimationFrame(animate);
            
            // Throttle FPS để giảm lag
            const deltaTime = currentTime - lastTime;
            if (deltaTime < frameInterval) {
                return;
            }
            lastTime = currentTime - (deltaTime % frameInterval);
            
            time += 0.01;
            
            if (isAnimeTheme) {
                // Hiệu ứng Sakura rơi cho anime theme - Tối ưu
                particlesMesh.rotation.y += 0.0002;
                particlesMesh.rotation.x += 0.0001;
                particlesMesh.rotation.z += 0.00015; // Xoay nhẹ như sakura
                
                // Di chuyển sakura rơi - tối ưu
                const positions = particlesGeometry.attributes.position.array;
                const velocities = particlesGeometry.attributes.aVelocity.array;
                const sinValue = Math.sin(time * 0.5); // Tính toán 1 lần
                for (let i = 0; i < positions.length; i += 3) {
                    positions[i] += velocities[i] + sinValue * 0.008; // Giảm tính toán
                    positions[i + 1] += velocities[i + 1];
                    positions[i + 2] += velocities[i + 2];
                    
                    // Reset khi rơi quá thấp (sakura rơi từ trên xuống)
                    if (positions[i + 1] < -15) {
                        positions[i + 1] = 30; // Đưa lên trên lại
                        positions[i] = (Math.random() - 0.5) * 60;
                        positions[i + 2] = (Math.random() - 0.5) * 20;
                    }
                }
                particlesGeometry.attributes.position.needsUpdate = true;
            } else {
                // Xoay các particles (ngôi sao) chậm (theme thường)
                particlesMesh.rotation.y += 0.0005;
                particlesMesh.rotation.x += 0.0003;
                
                // Di chuyển particles (hiệu ứng drift)
                const positions = particlesGeometry.attributes.position.array;
                const velocities = particlesGeometry.attributes.aVelocity.array;
                for (let i = 0; i < positions.length; i += 3) {
                    positions[i] += velocities[i];
                    positions[i + 1] += velocities[i + 1];
                    positions[i + 2] += velocities[i + 2];
                    
                    // Reset nếu ra ngoài giới hạn
                    if (Math.abs(positions[i]) > 25) velocities[i] *= -1;
                    if (Math.abs(positions[i + 1]) > 25) velocities[i + 1] *= -1;
                    if (Math.abs(positions[i + 2]) > 25) velocities[i + 2] *= -1;
                }
                particlesGeometry.attributes.position.needsUpdate = true;
            }
            
            // Animation cho các shapes - Tối ưu: chỉ update mỗi frame nhất định
            if (Math.floor(time * 10) % 2 === 0) {
                shapes.forEach((shape, index) => {
                    const userData = shape.userData;
                    
                    if (userData.isAnime) {
                        // Hiệu ứng anime - giảm tính toán
                        shape.rotation.x += userData.rotationSpeed.x;
                        shape.rotation.y += userData.rotationSpeed.y;
                        shape.rotation.z += userData.rotationSpeed.z;
                        
                        // Quỹ đạo nhẹ nhàng
                        userData.orbitAngle += userData.orbitSpeed;
                        const cosAngle = Math.cos(userData.orbitAngle);
                        const sinAngle = Math.sin(userData.orbitAngle);
                        shape.position.x = cosAngle * userData.orbitRadius + Math.sin(time * 0.3) * 0.4;
                        shape.position.z = sinAngle * userData.orbitRadius + Math.cos(time * 0.3) * 0.4;
                        
                        // Float effect mềm mại hơn
                        shape.position.y = userData.originalY + Math.sin(time * userData.floatSpeed) * userData.floatAmount;
                        
                        // Hiệu ứng pulsing nhẹ nhàng như sparkles
                        const pulse = Math.sin(time * 1.5) * 0.12 + 1;
                        shape.scale.set(pulse, pulse, pulse);
                        
                        // Thay đổi độ sáng mềm mại
                        if (shape.material.emissiveIntensity !== undefined) {
                            shape.material.emissiveIntensity = 0.3 + Math.sin(time * 1.2) * 0.25;
                        }
                    } else {
                        // Animation cho theme thường (vũ trụ) - đơn giản hơn
                        shape.rotation.x += userData.rotationSpeed.x;
                        shape.rotation.y += userData.rotationSpeed.y;
                        shape.rotation.z += userData.rotationSpeed.z;
                        
                        // Quỹ đạo (orbit)
                        userData.orbitAngle += userData.orbitSpeed;
                        const cosAngle = Math.cos(userData.orbitAngle);
                        const sinAngle = Math.sin(userData.orbitAngle);
                        shape.position.x = cosAngle * userData.orbitRadius;
                        shape.position.z = sinAngle * userData.orbitRadius;
                        
                        // Float effect (nổi lên xuống)
                        shape.position.y = userData.originalY + Math.sin(time * userData.floatSpeed) * userData.floatAmount;
                        
                        // Hiệu ứng pulsing (nhấp nháy như nebula)
                        const pulse = Math.sin(time * 2) * 0.08 + 1;
                        shape.scale.set(pulse, pulse, pulse);
                        
                        // Thay đổi độ sáng
                        if (shape.material.emissiveIntensity !== undefined) {
                            shape.material.emissiveIntensity = 0.2 + Math.sin(time * 1.5) * 0.15;
                        }
                    }
                });
            }
            
            // Di chuyển camera
            if (isAnimeTheme) {
                // Camera nhẹ nhàng cho anime
                camera.position.x = Math.sin(time * 0.08) * 1.5;
                camera.position.y = 3 + Math.cos(time * 0.12) * 0.8;
                camera.lookAt(Math.sin(time * 0.08) * 0.5, Math.cos(time * 0.12) * 0.3, 0);
            } else {
                // Di chuyển camera nhẹ (hiệu ứng drift) cho theme thường
                camera.position.x = Math.sin(time * 0.1) * 2;
                camera.position.y = 5 + Math.cos(time * 0.15) * 1;
                camera.lookAt(Math.sin(time * 0.1) * 1, Math.cos(time * 0.15) * 0.5, 0);
            }
            
            // Di chuyển ánh sáng (chỉ cho theme thường)
            if (!isAnimeTheme) {
                const lights = scene.children.filter(child => child instanceof THREE.PointLight);
                if (lights[0]) {
                    lights[0].position.x = 10 + Math.sin(time * 0.2) * 3;
                    lights[0].position.y = 10 + Math.cos(time * 0.25) * 3;
                }
                if (lights[1]) {
                    lights[1].position.x = -10 + Math.cos(time * 0.3) * 2;
                    lights[1].position.z = -10 + Math.sin(time * 0.3) * 2;
                }
            }
            
            renderer.render(scene, camera);
        }
        
        // Resize handler
        window.addEventListener('resize', () => {
            camera.aspect = window.innerWidth / window.innerHeight;
            camera.updateProjectionMatrix();
            renderer.setSize(window.innerWidth, window.innerHeight);
        });
        
        animate();
    })();
  </script>


    <script src="assets/js/game-effects.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script src="assets/js/game-effects-auto.js"></script>

        <script src="assets/js/game-enhancements.js"></script>
<script>
    // Auto initialize game effects
    if (typeof GameEffectsAuto !== 'undefined') {
        GameEffectsAuto.init();
    }
</script>
</body>
</html>

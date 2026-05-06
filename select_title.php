<?php
session_start();

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require 'db_connect.php';

// Load theme
require_once 'load_theme.php';

$userId = $_SESSION['Iduser'];

// Lấy thông tin người dùng
$sql = "SELECT Iduser, Name, Money, active_title_id FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$message = '';
$messageType = '';

// Xử lý chọn danh hiệu
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['select_title'])) {
    $titleId = (int)$_POST['title_id'];
    
    // Kiểm tra xem người dùng có achievement này không
    $checkSql = "SELECT * FROM user_achievements WHERE user_id = ? AND achievement_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    if ($checkStmt) {
        $checkStmt->bind_param("ii", $userId, $titleId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $checkStmt->close();
        
        if ($checkResult && $checkResult->num_rows > 0) {
            // Cập nhật active_title_id
            $updateSql = "UPDATE users SET active_title_id = ? WHERE Iduser = ?";
            $updateStmt = $conn->prepare($updateSql);
            if ($updateStmt) {
                $updateStmt->bind_param("ii", $titleId, $userId);
                $updateStmt->execute();
                $updateStmt->close();
                
                $message = '✅ Đã kích hoạt danh hiệu!';
                $messageType = 'success';
                
                // Cập nhật lại user data
                $user['active_title_id'] = $titleId;
            }
        } else {
            $message = '❌ Bạn chưa đạt được danh hiệu này!';
            $messageType = 'error';
        }
    }
}

// Lấy danh sách achievements đã đạt được (chỉ rank achievements)
$titlesSql = "SELECT a.* FROM achievements a
              INNER JOIN user_achievements ua ON a.id = ua.achievement_id
              WHERE ua.user_id = ? AND a.requirement_type = 'rank'
              ORDER BY a.requirement_value ASC";
$titlesStmt = $conn->prepare($titlesSql);
$titles = [];
if ($titlesStmt) {
    $titlesStmt->bind_param("i", $userId);
    $titlesStmt->execute();
    $titlesResult = $titlesStmt->get_result();
    while ($row = $titlesResult->fetch_assoc()) {
        $titles[] = $row;
    }
    $titlesStmt->close();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chọn Danh Hiệu</title>
        <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
        }
        
        * {
            cursor: inherit;
        }

        button, a, input[type="button"], input[type="submit"], label, select {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        .title-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .header-title {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: var(--border-radius-lg);
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            text-align: center;
        }
        
        .titles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .title-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--border-radius-lg);
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 3px solid transparent;
            text-align: center;
            position: relative;
        }
        
        .title-card.active {
            border-color: var(--primary-color);
            box-shadow: 0 0 20px rgba(102, 126, 234, 0.5);
        }
        
        .title-card.active::after {
            content: '✓ Đang dùng';
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--primary-color);
            color: white;
            padding: 5px 10px;
            border-radius: var(--border-radius);
            font-size: 12px;
            font-weight: 600;
        }
        
        .title-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }
        
        .title-icon {
            font-size: 64px;
            margin-bottom: 15px;
        }
        
        .title-name {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .title-description {
            color: var(--text-dark);
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .title-rank {
            font-size: 18px;
            font-weight: 600;
            color: var(--success-color);
            margin-bottom: 15px;
        }
        
        .select-button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 16px;
            font-weight: 600;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            transition: all 0.3s ease;
        }
        
        .select-button:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
        }
        
        .select-button.active {
            background: linear-gradient(135deg, var(--success-color) 0%, #27ae60 100%);
        }
        
        .select-button:disabled {
            opacity: 0.5;
            cursor: not-allowed !important;
        }
        
        .message {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            font-weight: 600;
            animation: slideIn 0.3s ease;
        }
        
        .message.success {
            background: rgba(40, 167, 69, 0.2);
            border: 2px solid #28a745;
            color: #28a745;
        }
        
        .message.error {
            background: rgba(220, 53, 69, 0.2);
            border: 2px solid #dc3545;
            color: #dc3545;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(-100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        .back-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
        }
        
        .no-titles {
            text-align: center;
            padding: 40px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--border-radius-lg);
            color: var(--text-dark);
        }
            \n
    </style>
</head>
<body>
    

    <div class="title-container">
        <div class="header-title">
            <h1>🏆 Chọn Danh Hiệu</h1>
            <p>Chọn danh hiệu để hiển thị trong profile và chat</p>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if (empty($titles)): ?>
            <div class="no-titles">
                <h2>😔 Bạn chưa có danh hiệu nào!</h2>
                <p>Hãy cố gắng lên top 10 server để nhận danh hiệu nhé!</p>
                <a href="index.php" class="back-link">🏠 Về Trang Chủ</a>
            </div>
        <?php else: ?>
            <div class="titles-grid">
                <?php foreach ($titles as $title): ?>
                    <div class="title-card <?= $user['active_title_id'] == $title['id'] ? 'active' : '' ?>">
                        <div class="title-icon"><?= htmlspecialchars($title['icon'] ?? '🏆') ?></div>
                        <div class="title-name"><?= htmlspecialchars($title['name']) ?></div>
                        <div class="title-description"><?= htmlspecialchars($title['description']) ?></div>
                        <div class="title-rank">Top <?= $title['requirement_value'] ?></div>
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="title_id" value="<?= $title['id'] ?>">
                            <button type="submit" name="select_title" 
                                    class="select-button <?= $user['active_title_id'] == $title['id'] ? 'active' : '' ?>">
                                <?php if ($user['active_title_id'] == $title['id']): ?>
                                    ✓ Đang sử dụng
                                <?php else: ?>
                                    Kích hoạt
                                <?php endif; ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <a href="index.php" class="back-link">🏠 Về Trang Chủ</a>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.cursor = "url('chuot.png'), url('../chuot.png'), auto";
            
            const interactiveElements = document.querySelectorAll('button, a, input, label, select');
            interactiveElements.forEach(el => {
                el.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
            });
        });
    </script>



    
    


    <!-- Three.js Background System -->
    <canvas id="threejs-background"></canvas>
    <script>
        (function() {
            window.themeConfig = {
                particleCount: <?= $particleCount ?? 800 ?>,
                particleSize: <?= $particleSize ?? 0.05 ?>,
                particleColor: '<?= $particleColor ?? "#ffffff" ?>',
                particleOpacity: <?= $particleOpacity ?? 0.6 ?>,
                shapeCount: <?= $shapeCount ?? 10 ?>,
                shapeColors: <?= json_encode($shapeColors ?? ["#667eea", "#764ba2", "#4facfe", "#00f2fe"]) ?>,
                shapeOpacity: <?= $shapeOpacity ?? 0.3 ?>,
                bgGradient: <?= json_encode($bgGradient ?? ["#667eea", "#764ba2", "#4facfe"]) ?>
            };
            const isInGames = window.location.pathname.includes('/games/');
            const script = document.createElement('script');
            script.src = isInGames ? '../threejs-background.js' : 'threejs-background.js';
            document.head.appendChild(script);
        })();
    </script>

</body>
</html>


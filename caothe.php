<?php
session_start();
if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}
$userId = $_SESSION['Iduser']; // ✅ Sửa lỗi chính
require 'db_connect.php';

// Kiểm tra kết nối database
if (!$conn || $conn->connect_error) {
    die("Lỗi kết nối database: " . ($conn ? $conn->connect_error : "Không thể kết nối"));
}

// Load theme
require_once 'load_theme.php';

// Kiểm tra bảng scratch_log có tồn tại không
$checkTable = $conn->query("SHOW TABLES LIKE 'scratch_log'");
$scratchLogExists = $checkTable && $checkTable->num_rows > 0;

$today = date('Y-m-d');
$playCount = 0;
$error = "";
$rewardText = "+ 0 VNĐ";
$amountWon = 0;

if ($scratchLogExists) {
    $checkSql = "SELECT play_count FROM scratch_log WHERE user_id = ? AND play_date = ?";
    $stmt = $conn->prepare($checkSql);
    if ($stmt) {
        $stmt->bind_param("is", $userId, $today);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $playCount = $row['play_count'];
        }
        $stmt->close();
    }
} else {
    // Nếu bảng không tồn tại, tạo bảng tạm thời hoặc cho phép chơi tự do
    $playCount = 0;
}

if ($playCount >= 5) {
    $error = "Bạn đã hết lượt cào hôm nay! Quay lại vào ngày mai nhé.";
    $rewardText = "Hết lượt!";
    $amountWon = 0;
} else {
    $amountWon = rand(1000, 500000);
    $rewardText = "+ " . number_format($amountWon, 0, ',', '.') . " VNĐ";

    $updateSql = "UPDATE users SET Money = Money + ? WHERE Iduser = ?";
    $stmt = $conn->prepare($updateSql);
    if ($stmt) {
        $stmt->bind_param("di", $amountWon, $userId);
        $stmt->execute();
        $stmt->close();
    }

    if ($scratchLogExists) {
        $insertSql = "
            INSERT INTO scratch_log (user_id, play_date, play_count)
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE play_count = play_count + 1
        ";
        $stmt = $conn->prepare($insertSql);
        if ($stmt) {
            $stmt->bind_param("is", $userId, $today);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// Lấy lại số dư hiện tại
$userQuery = "SELECT Money FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if (!$user) {
    $user = ['Money' => 0]; // tránh lỗi nếu không tìm thấy người dùng
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Cào Thẻ Nhân Phẩm</title>
  <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="assets/css/game-effects.css">

  <style>
    body {
        cursor: url('chuot.png'), url('../chuot.png'), auto !important;
        background: <?= $bgGradientCSS ?>;
        background-attachment: fixed;
        font-family: 'Segoe UI', sans-serif;
        text-align: center;
        padding: 50px 20px;
        min-height: 100vh;
    }

    * {
        cursor: inherit;
    }

    button, a, input[type="button"], input[type="submit"], label, select {
        cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
    }

    .game-container {
        max-width: 600px;
        margin: 0 auto;
        background: rgba(255, 255, 255, 0.98);
        padding: 40px;
        border-radius: var(--border-radius-lg);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
        border: 2px solid rgba(255, 255, 255, 0.5);
    }

    h1 {
        color: var(--primary-color);
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 20px;
    }

    .scratch-container {
        position: relative;
        width: 320px;
        height: 150px;
        margin: auto;
    }

    .result {
        position: absolute;
        top: 0;
        left: 0;
        width: 320px;
        height: 150px;
        line-height: 150px;
        font-size: 24px;
        font-weight: bold;
        color: #e74c3c;
        background: #ffffffcc;
        border-radius: 15px;
        box-shadow: 0 0 10px #888;
        z-index: 1;
    }

    #scratchCanvas {
        position: absolute;
        top: 0;
        left: 0;
        width: 320px;
        height: 150px;
        border-radius: 15px;
        cursor: crosshair;
        z-index: 2;
    }

  </style>
</head>
<body>
    <div class="game-container">
        <h1>🎉 Cào Thẻ Miễn Phí - Mỗi Ngày 5 Lượt 🎉</h1>
        <div style="background: rgba(40, 167, 69, 0.1); border: 2px solid #28a745; border-radius: var(--border-radius); padding: 15px; margin: 20px 0; font-size: 20px; font-weight: 600; color: #28a745;">
            Số dư hiện tại: <?= number_format($user['Money'], 0, ',', '.') ?> VNĐ
        </div>

        <?php if (!empty($error)): ?>
            <div style="background: rgba(220, 53, 69, 0.1); border: 2px solid #dc3545; border-radius: var(--border-radius); padding: 15px; margin: 20px 0; color: #dc3545; font-weight: 600;">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="scratch-container">
            <div class="result" id="prizeText"><?= htmlspecialchars($rewardText, ENT_QUOTES, 'UTF-8') ?></div>
            <canvas id="scratchCanvas" width="320" height="150"></canvas>
        </div>

        <div style="margin-top: 30px;">
            <a href="caothe.php" style="display: inline-block; margin: 10px; padding: 12px 24px; background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%); color: white; text-decoration: none; border-radius: var(--border-radius); font-weight: 600; transition: all 0.3s ease;">🔁 Cào tiếp</a>
            <a href="index.php" style="display: inline-block; margin: 10px; padding: 12px 24px; background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%); color: white; text-decoration: none; border-radius: var(--border-radius); font-weight: 600; transition: all 0.3s ease;">🏠 Trang chủ</a>
        </div>
    </div>

<script>
  const canvas = document.getElementById("scratchCanvas");
  const ctx = canvas.getContext("2d");
  let isDrawing = false;

  const silver = ctx.createLinearGradient(0, 0, 320, 0);
  silver.addColorStop(0, "#bdc3c7");
  silver.addColorStop(1, "#ecf0f1");
  ctx.fillStyle = silver;
  ctx.fillRect(0, 0, canvas.width, canvas.height);

  canvas.addEventListener("mousedown", () => isDrawing = true);
  canvas.addEventListener("mouseup", () => isDrawing = false);
  canvas.addEventListener("mousemove", function (e) {
    if (!isDrawing) return;
    const rect = canvas.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    ctx.globalCompositeOperation = "destination-out";
    ctx.beginPath();
    ctx.arc(x, y, 15, 0, Math.PI * 2);
    ctx.fill();
  });

  document.addEventListener('DOMContentLoaded', function() {
      document.body.style.cursor = "url('chuot.png'), url('../chuot.png'), auto";
      
      const interactiveElements = document.querySelectorAll('button, a, input, label, select');
      interactiveElements.forEach(el => {
          el.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
      });
  });
</script>


    <script src="assets/js/game-effects.js"></script>
    <script src="assets/js/game-effects-auto.js"></script>

<script>
    // Auto initialize game effects
    if (typeof GameEffectsAuto !== 'undefined') {
        GameEffectsAuto.init();
    }
</script>
</body>
</html>

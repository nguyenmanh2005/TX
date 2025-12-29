<?php
session_start();
require 'db_connect.php';

// Load theme (chỉ nếu đã đăng nhập)
if (isset($_SESSION['Iduser'])) {
    require_once 'load_theme.php';
} else {
    // Default theme nếu chưa đăng nhập
    $bgGradientCSS = 'linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%)';
}

$userName = isset($_SESSION['Name']) ? $_SESSION['Name'] : "Khách";
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Giới Thiệu - Hệ Thống Game</title>
      <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
  <style>
    :root {
      --primary: #3498db;
      --accent: #2980b9;
      --background: #f4fafd;
      --text: #2c3e50;
      --light: #ffffff;
      --shadow: rgba(0, 0, 0, 0.1);
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      cursor: url('chuot.png'), url('../chuot.png'), auto !important;
      font-family: 'Segoe UI', sans-serif;
      background: <?= $bgGradientCSS ?>;
      background-attachment: fixed;
      color: var(--text);
      line-height: 1.6;
      padding: 30px 15px;
      min-height: 100vh;
    }
    
    * {
      cursor: inherit;
    }

    button, a, input[type="button"], input[type="submit"], label, select {
      cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
    }

    .container {
      max-width: 900px;
      margin: 0 auto;
      background: rgba(255, 255, 255, 0.98);
      border-radius: var(--border-radius-lg);
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
      border: 2px solid rgba(255, 255, 255, 0.5);
      padding: 40px 30px;
      animation: fadeInUp 0.6s ease;
    }

    @keyframes fadeInUp {
      from { 
        opacity: 0; 
        transform: translateY(30px); 
      }
      to { 
        opacity: 1; 
        transform: translateY(0); 
      }
    }

    h1 {
      font-size: 32px;
      color: var(--primary-color);
      text-align: center;
      margin-bottom: 30px;
      font-weight: 700;
      text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
      animation: fadeInDown 0.8s ease;
    }
    
    @keyframes fadeInDown {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    p {
      font-size: 17px;
      margin-bottom: 20px;
    }

    .highlight {
      background: linear-gradient(135deg, rgba(0, 121, 107, 0.1) 0%, rgba(52, 152, 219, 0.1) 100%);
      padding: 25px;
      border-left: 5px solid var(--primary-color);
      border-radius: var(--border-radius);
      margin: 30px 0;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      animation: slideInLeft 0.8s ease;
    }
    
    @keyframes slideInLeft {
      from {
        opacity: 0;
        transform: translateX(-30px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }
    
    .highlight:hover {
      transform: translateX(5px);
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
    }

    .highlight ul {
      margin-top: 10px;
      padding-left: 20px;
    }

    .highlight li {
      margin-bottom: 10px;
      font-size: 16px;
    }

    footer {
      text-align: center;
      margin-top: 40px;
      font-size: 14px;
      color: #7f8c8d;
    }

    strong {
      color: var(--accent);
    }

    @media (max-width: 600px) {
      .container {
        padding: 25px 20px;
      }

      h1 {
        font-size: 24px;
      }

      p, .highlight li {
        font-size: 15px;
      }
    }

    .back-btn {
      text-align: center;
      margin-bottom: 30px;
    }

    .back-btn a {
      display: inline-block;
      background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
      color: white;
      padding: 12px 24px;
      border-radius: var(--border-radius);
      text-decoration: none;
      font-weight: 600;
      font-size: 16px;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
      cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
      position: relative;
      overflow: hidden;
    }
    
    .back-btn a::before {
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
    
    .back-btn a:hover::before {
      width: 300px;
      height: 300px;
    }

    .back-btn a:hover {
      background: linear-gradient(135deg, var(--secondary-dark) 0%, var(--secondary-color) 100%);
      transform: translateY(-3px) scale(1.05);
      box-shadow: 0 6px 25px rgba(52, 152, 219, 0.6);
      cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
    }

    #timer {
      text-align: center;
      font-weight: 700;
      color: var(--success-color);
      font-size: 18px;
      margin-bottom: 20px;
      padding: 15px;
      background: rgba(232, 245, 233, 0.5);
      border-radius: var(--border-radius);
      border: 2px solid var(--success-color);
      animation: pulse 2s ease infinite;
    }
    
    @keyframes pulse {
      0%, 100% {
        transform: scale(1);
      }
      50% {
        transform: scale(1.02);
      }
    }
    
    .highlight ul {
      list-style-type: none;
    }
    
    .highlight li {
      padding: 8px 0;
      transition: transform 0.3s ease, padding-left 0.3s ease;
    }
    
    .highlight li:hover {
      transform: translateX(5px);
      padding-left: 10px;
    }
    
    p {
      animation: fadeIn 0.8s ease;
    }
    
    @keyframes fadeIn {
      from {
        opacity: 0;
      }
      to {
        opacity: 1;
      }
    }
    
    footer {
      animation: fadeIn 1s ease;
      padding-top: 20px;
      border-top: 2px solid rgba(0, 0, 0, 0.1);
    }
  </style>
</head>
<body>


<div class="back-btn">
  <a href="index.php">⬅️ Quay về Trang Chủ</a>
</div>


<p id="timer">⏳ Vui lòng chờ 40 giây để tiếp tục...</p>


<div class="container">
  <h1>🎮 Giới Thiệu Hệ Thống Game</h1>

  <p>Chào <strong><?= htmlspecialchars($userName) ?></strong>, chào mừng bạn đến với hệ thống trò chơi giải trí được phát triển với mục tiêu đem lại sự hứng khởi và vui vẻ!</p>

  <div class="highlight">
    <strong>✨ Web tạo bởi nhóm 6hondai_alone:</strong>
    <ul>
      <li> <strong>Người Code Web</strong>: Mạnh 2hondaito</li>
      <li> <strong>Người Hỗ Trợ</strong>: Phúc 2hondaisun</li>
      <li> <strong>Người Hỗ Trợ</strong>: Thành 2hondaixe</li>
      <li> <strong></strong></li>
    </ul>

  </div>

  <p>Lưu ý: Web không hỗ trợ nạp hay rút tiền tất cả mọi thứ trong web chỉ là ảo</p>
  <p>Nếu phát hiện những hành vi lạm dụng web làm những điều sai trái hãy liên hệ cho chúng tôi</p>
  <p>Mọi thông tin tài khoản, điểm số, và lịch sử chơi đều được lưu trữ trong cơ sở dữ liệu và bảo mật trên hệ thống.</p>
  <p>Hệ thống làm ra chỉ để cho các anh em trong Discord giải trí. Nếu có người chia sẻ ra bên ngoài, vui lòng liên hệ qua Facebook hoặc Zalo để được hỗ trợ nhé!</p>
  <p>Nếu có thêm ý tưởng để phát triển trang web thì hãy liên hệ Facebook hoặc Zalo để web được cải tiến hơn nha </p>

  <footer>
    &copy; <?= date("Y") ?> - Hệ Thống Game Giải Trí. Phát triển bởi GTLM
  </footer>
</div>


<script>
  // Đảm bảo cursor luôn hoạt động
  document.addEventListener('DOMContentLoaded', function() {
    document.body.style.cursor = "url('chuot.png'), url('../chuot.png'), auto";
    
    // Set cursor cho tất cả buttons và links
    const interactiveElements = document.querySelectorAll('button, a, label, select');
    interactiveElements.forEach(el => {
      el.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
      // Đảm bảo cursor không bị mất khi hover
      el.addEventListener('mouseenter', function() {
        this.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
      });
      el.addEventListener('mouseleave', function() {
        this.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
      });
    });
  });

  history.pushState(null, null, location.href);
  window.onpopstate = function () {
    history.go(1);
  };

  // Giữ người dùng ở lại 40s
  let allowLeave = false;
  window.addEventListener("beforeunload", function (e) {
    if (!allowLeave) {
      e.preventDefault();
      e.returnValue = "Bạn cần ở lại trang ít nhất 40 giây trước khi thoát.";
    }
  });

  // Đếm ngược 40s
  let secondsLeft = 40;
  const timerEl = document.getElementById("timer");
  const countdown = setInterval(() => {
    timerEl.textContent = `⏳ Vui lòng chờ ${secondsLeft} giây để tiếp tục...`;
    secondsLeft--;
    if (secondsLeft < 0) {
      clearInterval(countdown);
      allowLeave = true;
      timerEl.textContent = "✅ Bạn đã đủ thời gian! Giờ có thể tiếp tục.";
      timerEl.style.animation = "none";
    }
  }, 1000);
</script>

</body>
</html>

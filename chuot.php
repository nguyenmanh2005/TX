<?php
  // Bắt đầu session để truy cập biến $_SESSION
  session_start();
  require 'db_connect.php';

  // Lấy thông tin người dùng hiện tại từ bảng users
  if (isset($_SESSION['Iduser'])) {
      $userId = $_SESSION['Iduser'];
      $sql = "SELECT cursor_image FROM users WHERE Iduser = ?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("i", $userId);
      $stmt->execute();
      $result = $stmt->get_result();
      if ($result && $result->num_rows === 1) {
          $user = $result->fetch_assoc();
          $currentCursor = $user['cursor_image'];
      } else {
          $currentCursor = 'chuot.png'; // Mặc định nếu không có
      }
      $stmt->close();
  } else {
      $currentCursor = 'chuot.png'; // Nếu không có session thì mặc định
  }

  if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['submit_cursor'])) {
      $newCursor = $_POST['cursor_image'];

      // Cập nhật lại con chuột
      if (isset($_SESSION['Iduser'])) {
          $updateSql = "UPDATE users SET cursor_image = ? WHERE Iduser = ?";
          $updateStmt = $conn->prepare($updateSql);
          $updateStmt->bind_param("si", $newCursor, $userId);
          $updateStmt->execute();
          $updateStmt->close();

          // Cập nhật lại biến
          $currentCursor = $newCursor;
      }
  }
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Chọn Cursor</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%);
            padding: 40px 20px;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        * {
            cursor: inherit;
        }

        button, a, input[type="button"], input[type="submit"], label, select {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        .container {
            background: rgba(255, 255, 255, 0.98);
            padding: 40px;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.5);
            max-width: 500px;
            width: 100%;
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
            color: var(--primary-color);
            font-size: 32px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 30px;
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
        
        label {
            display: block;
            margin-bottom: 10px;
            font-weight: 700;
            color: var(--text-dark);
            font-size: 16px;
        }
        
        select {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 16px;
            background: rgba(255, 255, 255, 0.95);
            color: var(--text-dark);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            margin-bottom: 20px;
        }
        
        select:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.15);
            background: rgba(255, 255, 255, 1);
        }
        
        button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 18px;
            font-weight: 600;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
            position: relative;
            overflow: hidden;
        }
        
        button::before {
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
        
        button:hover::before {
            width: 400px;
            height: 400px;
        }
        
        button:hover:not(:disabled) {
            background: linear-gradient(135deg, var(--secondary-dark) 0%, var(--secondary-color) 100%);
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 6px 25px rgba(52, 152, 219, 0.6);
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        button:active:not(:disabled) {
            transform: translateY(-1px) scale(1);
        }
        
        button:disabled {
            opacity: 0.6;
            cursor: not-allowed !important;
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        .back-link:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 6px 25px rgba(52, 152, 219, 0.6);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🖱️ Chọn Cursor</h1>
        <form method="POST" id="cursorForm">
            <label for="cursor_image">Chọn con chuột:</label>
            <select name="cursor_image" id="cursor_image">
                <option value="chuot.png" <?= $currentCursor === 'chuot.png' ? 'selected' : '' ?>>Con chuột mặc định</option>
                <option value="tay.png" <?= $currentCursor === 'tay.png' ? 'selected' : '' ?>>Con tay</option>
            </select>
            <button type="submit" name="submit_cursor" id="submitBtn">💾 Lưu</button>
        </form>
        <a href="index.php" class="back-link">🏠 Trang Chủ</a>
    </div>

    <script>
        // Đảm bảo cursor luôn hoạt động
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.cursor = "url('chuot.png'), url('../chuot.png'), auto";
            
            const interactiveElements = document.querySelectorAll('button, a, label, select');
            interactiveElements.forEach(el => {
                el.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
                el.addEventListener('mouseenter', function() {
                    this.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
                });
                el.addEventListener('mouseleave', function() {
                    this.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
                });
            });
        });
        
        const form = document.getElementById('cursorForm');
        const submitBtn = document.getElementById('submitBtn');
        
        if (form) {
            form.addEventListener('submit', function(e) {
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Đang lưu...';
                }
            });
        }
    </script>
</body>
</html>

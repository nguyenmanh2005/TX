<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
require 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    header("Content-Type: application/json");
    $email = trim($_POST["email"] ?? '');
    $newpass = trim($_POST["password"] ?? '');

    if (empty($email) || empty($newpass)) {
        echo json_encode(["status" => "error", "message" => "Vui lòng nhập đầy đủ email và mật khẩu."]);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["status" => "error", "message" => "Email không hợp lệ."]);
        exit;
    }

    // Kiểm tra xem email có tồn tại
    $stmt = $conn->prepare("SELECT * FROM users WHERE Email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Email không tồn tại trong hệ thống."]);
        exit;
    }

    // Cập nhật mật khẩu
    $hashed_pass = password_hash($newpass, PASSWORD_DEFAULT);
    $stmt_update = $conn->prepare("UPDATE users SET Pass = ? WHERE Email = ?");
    $stmt_update->bind_param("ss", $hashed_pass, $email);

    if ($stmt_update->execute()) {
        echo json_encode(["status" => "success", "message" => "Mật khẩu đã được cập nhật."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Không thể cập nhật mật khẩu."]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đặt lại mật khẩu</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%);
            font-family: 'Segoe UI', sans-serif;
            color: white;
            text-align: center;
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
        
        input[type="text"], input[type="email"], input[type="password"] {
            cursor: text !important;
        }
        
        main {
            background: rgba(255, 255, 255, 0.98);
            border-radius: var(--border-radius-lg);
            padding: 40px;
            width: 100%;
            max-width: 450px;
            margin: auto;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.5);
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
        
        h2 {
            color: var(--primary-color);
            font-size: 28px;
            font-weight: 700;
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
        
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 14px 18px;
            margin: 15px 0;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 16px;
            background: rgba(255, 255, 255, 0.95);
            color: var(--text-dark);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            cursor: text !important;
            box-sizing: border-box;
        }
        
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.15);
            background: rgba(255, 255, 255, 1);
        }
        
        button {
            width: 100%;
            padding: 14px 28px;
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
            margin-top: 10px;
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

    </style>
</head>
<body>

<main>
    <h2>Đặt lại mật khẩu</h2>
    <form id="resetForm">
        <input type="email" name="email" placeholder="Nhập email" required>
        <input type="password" name="password" placeholder="Mật khẩu mới" required>
        <button type="submit">Cập nhật</button>
        <br>
        <br>
        
        <button type="button" onclick="window.location.href='login.php'">Đăng Nhập</button>

    </form>
</main>

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
        
        const textInputs = document.querySelectorAll('input[type="text"], input[type="email"], input[type="password"]');
        textInputs.forEach(input => {
            input.style.cursor = "text";
            input.addEventListener('focus', function() {
                this.style.cursor = "text";
            });
        });
    });
    
    $("#resetForm").submit(function (e) {
        e.preventDefault();
        
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.text();
        
        // Disable button khi đang submit
        submitBtn.prop('disabled', true);
        submitBtn.text('Đang xử lý...');
        
        var formData = $(this).serialize();

        $.ajax({
            type: "POST",
            url: "ngu1.php", // chính file này
            data: formData,
            dataType: "json",
            success: function (res) {
                if (res.status === "success") {
                    Swal.fire("✅ Thành công", res.message, "success");
                } else {
                    Swal.fire("❌ Lỗi", res.message, "error");
                }
                // Re-enable button
                submitBtn.prop('disabled', false);
                submitBtn.text(originalText);
            },
            error: function () {
                Swal.fire("❌ Lỗi", "Không thể kết nối đến server!", "error");
                // Re-enable button
                submitBtn.prop('disabled', false);
                submitBtn.text(originalText);
            }
        });
    });
</script>

</body>
</html>

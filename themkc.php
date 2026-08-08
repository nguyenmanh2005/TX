<?php
session_start();
if (!isset($_SESSION['Iduser'])) {
    die("Vui lòng đăng nhập!");
}

require 'db_connect.php';

$message = "";

// Xử lý khi submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $frame_name = $_POST['frame_name'];
    $description = $_POST['description'];
    $rarity = $_POST['rarity'];

    // Xử lý upload ảnh
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $targetDir = "uploads/";
        $fileName = time() . "_" . basename($_FILES["image"]["name"]);
        $targetFile = $targetDir . $fileName;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
            $stmt = $conn->prepare("INSERT INTO chat_frames (frame_name, ImageURL, description, rarity) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $frame_name, $targetFile, $description, $rarity);
            $stmt->execute();
            $stmt->close();

            $message = "<p style='color: green; font-weight: bold;'>✅ Đã thêm khung chat thành công!</p>";
        } else {
            $message = "<p style='color: red;'>❌ Lỗi khi upload ảnh.</p>";
        }
    } else {
        $message = "<p style='color: red;'>❌ Vui lòng chọn hình ảnh hợp lệ.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thêm khung chat mới</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%);
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }
        
        * {
            cursor: inherit;
        }

        button, a, input[type="button"], input[type="submit"], label, select {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        input[type="text"], textarea {
            cursor: text !important;
        }
        
        input[type="file"] {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        form {
            background: rgba(255, 255, 255, 0.98);
            padding: 40px;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.5);
            width: 100%;
            max-width: 600px;
        }

        h2 {
            text-align: center;
            color: var(--primary-color);
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 30px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
            animation: fadeInDown 0.6s ease;
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
            font-weight: 700;
            margin-top: 20px;
            margin-bottom: 8px;
            display: block;
            color: var(--text-dark);
            font-size: 16px;
        }

        input[type="text"], textarea, select {
            width: 100%;
            padding: 14px 18px;
            border-radius: var(--border-radius);
            border: 2px solid var(--border-color);
            margin-top: 5px;
            background: rgba(255, 255, 255, 0.95);
            font-size: 16px;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            cursor: text !important;
        }
        
        input[type="text"]:focus, textarea:focus, select:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.15);
            background: rgba(255, 255, 255, 1);
        }

        input[type="file"] {
            width: 100%;
            padding: 12px;
            border-radius: var(--border-radius);
            border: 2px dashed var(--border-color);
            margin-top: 5px;
            background: rgba(255, 255, 255, 0.9);
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            transition: border-color 0.3s ease, background 0.3s ease;
        }
        
        input[type="file"]:hover {
            border-color: var(--secondary-color);
            background: rgba(232, 244, 248, 0.5);
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }

        button {
            margin-top: 30px;
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

        .msg {
            margin-top: 25px;
            text-align: center;
            padding: 15px;
            border-radius: var(--border-radius);
            animation: messageAppear 0.6s ease;
        }
        
        @keyframes messageAppear {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .msg p {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }

        a {
            display: inline-block;
            margin-top: 25px;
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white;
            text-decoration: none;
            font-weight: 600;
            border-radius: var(--border-radius);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            position: relative;
            overflow: hidden;
        }
        
        a::before {
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
        
        a:hover::before {
            width: 300px;
            height: 300px;
        }

        a:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 6px 25px rgba(52, 152, 219, 0.6);
            text-decoration: none;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
    </style>
</head>
<body>

    <form method="POST" enctype="multipart/form-data">
        <h2>➕ Thêm khung chat mới</h2>

        <label for="frame_name">Tên khung:</label>
        <input type="text" name="frame_name" required>

        <label for="description">Mô tả:</label>
        <textarea name="description" rows="3"></textarea>

        <label for="rarity">Độ hiếm:</label>
        <select name="rarity">
            <option value="common">🟢 Thường</option>
            <option value="rare">🔵 Hiếm</option>
            <option value="legendary">🟡 Huyền thoại</option>
        </select>

        <label for="image">Hình ảnh khung:</label>
        <input type="file" name="image" accept="image/*" required>

        <button type="submit">📤 Thêm khung</button>

        <div class="msg"><?= $message ?></div>
    </form>

    <a href="khungchat.php">⬅ Quay lại chọn khung</a>

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
            
            // Xử lý input file
            const fileInput = document.querySelector('input[type="file"]');
            if (fileInput) {
                fileInput.addEventListener('mouseenter', function() {
                    this.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
                });
            }
            
            // Xử lý text inputs
            const textInputs = document.querySelectorAll('input[type="text"], textarea');
            textInputs.forEach(input => {
                input.style.cursor = "text";
                input.addEventListener('focus', function() {
                    this.style.cursor = "text";
                });
            });
        });
        
        // Xử lý form submit
        const form = document.querySelector('form');
        const submitButton = form.querySelector('button[type="submit"]');
        
        if (form) {
            form.addEventListener('submit', function(e) {
                const frameName = form.querySelector('input[name="frame_name"]').value.trim();
                const imageFile = form.querySelector('input[name="image"]').files[0];
                
                if (!frameName) {
                    e.preventDefault();
                    if (typeof Swal !== 'undefined') { Swal.fire('Thông báo', String('Vui lòng nhập tên khung!'), 'warning'); } else { alert('Vui lòng nhập tên khung!'); };
                    return false;
                }
                
                if (!imageFile) {
                    e.preventDefault();
                    if (typeof Swal !== 'undefined') { Swal.fire('Thông báo', String('Vui lòng chọn hình ảnh!'), 'warning'); } else { alert('Vui lòng chọn hình ảnh!'); };
                    return false;
                }
                
                // Disable button khi đang submit
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Đang thêm...';
                }
            });
        }
    </script>
</body>
</html>

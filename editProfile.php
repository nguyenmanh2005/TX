<?php
session_start();

// Kiểm tra đăng nhập: nếu chưa đăng nhập thì chuyển về trang đăng nhập
if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require 'db_connect.php';

// Lấy thông tin người dùng hiện tại từ bảng users (không dùng get_result để tương thích host)
$userId = (int)$_SESSION['Iduser'];
$sql = "SELECT Iduser, Name, Email, Money, ImageURL, chat_frame_id FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Lỗi prepare: " . $conn->error);
}
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($id, $nameCurrent, $emailCurrent, $moneyCurrent, $imageUrlCurrent, $chatFrameCurrent);
if ($stmt->fetch()) {
    $user = [
        'Iduser' => $id,
        'Name' => $nameCurrent,
        'Email' => $emailCurrent,
        'Money' => $moneyCurrent,
        'ImageURL' => $imageUrlCurrent,
        'chat_frame_id' => $chatFrameCurrent,
    ];
} else {
    $stmt->close();
    die("Không tìm thấy thông tin người dùng!");
}
$stmt->close();

// Xử lý cập nhật thông tin khi người dùng gửi form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $chatFrame = isset($_POST['chat_frame']) ? (int)$_POST['chat_frame'] : 0;

    // Cập nhật thông tin người dùng
    $updateSql = "UPDATE users SET Name = ?, Email = ?, chat_frame_id = ? WHERE Iduser = ?";
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("sssi", $name, $email, $chatFrame, $userId);
    $stmt->execute();
    $stmt->close();

    // Cập nhật mật khẩu nếu có
    if (!empty($password)) {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $updatePasswordSql = "UPDATE users SET Pass = ? WHERE Iduser = ?";
        $stmt = $conn->prepare($updatePasswordSql);
        $stmt->bind_param("si", $hashedPassword, $userId);
        $stmt->execute();
        $stmt->close();
    }

    // Cập nhật ảnh đại diện nếu có
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];
        
        $filename = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // 1. Kiểm tra extension
        if (in_array($ext, $allowedExts)) {
            // 2. Kiểm tra MIME thực tế
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['image']['tmp_name']);
            if (PHP_VERSION_ID < 80500) {
                finfo_close($finfo);
            }
            
            if (in_array($mime, $allowedMimes)) {
                // 3. Đổi tên file ngẫu nhiên để tránh shell execution và overwrite
                $newFilename = uniqid('avatar_', true) . '.' . $ext;
                $imagePath = 'uploads/' . $newFilename;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $imagePath)) {
                    $updateImageSql = "UPDATE users SET ImageURL = ? WHERE Iduser = ?";
                    $stmt = $conn->prepare($updateImageSql);
                    $stmt->bind_param("si", $imagePath, $userId);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }

    // Đảm bảo cập nhật thành công và chuyển hướng về trang hồ sơ
    header("Location: in4.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh Sửa Hồ Sơ</title>
        <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <style>
        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%);
            min-height: 100vh;
        }
        
        * {
            cursor: inherit;
        }

        button, a, input[type="button"], input[type="submit"], label, select {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        input[type="text"], input[type="email"], textarea {
            cursor: text !important;
        }
        
        input[type="file"] {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 40px;
            background: linear-gradient(135deg, rgba(0, 121, 107, 0.98) 0%, rgba(0, 90, 79, 0.98) 100%);
            color: #fff;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .header a {
            color: white;
            text-decoration: none;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: var(--border-radius);
            background: rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        .header a:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        .form-container {
            display: flex;
            justify-content: center;
            margin-top: 30px;
            padding: 20px;
        }

        .form-box {
            background: rgba(255, 255, 255, 0.98);
            padding: 40px;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.5);
            max-width: 600px;
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
        
        .form-box h2 {
            color: var(--primary-color);
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 30px;
            text-align: center;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }

        .form-box label {
            display: block;
            margin: 20px 0 8px 0;
            font-weight: 700;
            color: var(--text-dark);
            font-size: 16px;
        }

        .form-box input[type="text"],
        .form-box input[type="email"],
        .form-box textarea {
            width: 100%;
            padding: 14px 18px;
            margin: 0;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 16px;
            background: rgba(255, 255, 255, 0.95);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            cursor: text !important;
            box-sizing: border-box;
        }
        
        .form-box input[type="text"]:focus,
        .form-box input[type="email"]:focus,
        .form-box textarea:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.15);
            background: rgba(255, 255, 255, 1);
        }

        .form-box input[type="file"] {
            width: 100%;
            padding: 12px;
            margin: 0;
            border: 2px dashed var(--border-color);
            border-radius: var(--border-radius);
            background: rgba(255, 255, 255, 0.9);
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            transition: border-color 0.3s ease, background 0.3s ease;
            box-sizing: border-box;
        }
        
        .form-box input[type="file"]:hover {
            border-color: var(--secondary-color);
            background: rgba(232, 244, 248, 0.5);
        }

        .form-box button {
            width: 100%;
            padding: 16px;
            margin-top: 30px;
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: #fff;
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
        
        .form-box button::before {
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
        
        .form-box button:hover::before {
            width: 400px;
            height: 400px;
        }

        .form-box button:hover:not(:disabled) {
            background: linear-gradient(135deg, var(--secondary-dark) 0%, var(--secondary-color) 100%);
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 6px 25px rgba(52, 152, 219, 0.6);
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }
        
        .form-box button:active:not(:disabled) {
            transform: translateY(-1px) scale(1);
        }
        
        .form-box button:disabled {
            opacity: 0.6;
            cursor: not-allowed !important;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>Chỉnh Sửa Hồ Sơ</h1>
        <a href="in4.php" style="color: white;">Trở về Hồ Sơ</a>
    </div>

    <div class="form-container">
        <div class="form-box">
            <h2>✏️ Chỉnh Sửa Hồ Sơ</h2>
            <form method="POST" enctype="multipart/form-data" id="editForm">
                <label for="name">👤 Tên:</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars((string)($user['Name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>

                <label for="email">📧 Email:</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars((string)($user['Email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>

                <label for="password">🔒 Mật khẩu mới (để trống nếu không đổi):</label>
                <input type="password" id="password" name="password" placeholder="Nhập mật khẩu mới...">





                <label for="image">🖼️ Ảnh Đại Diện:</label>
                <input type="file" id="image" name="image" accept="image/*">

                <button type="submit" id="submitBtn">💾 Lưu Thay Đổi</button>
            </form>
        </div>
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
            
            // Xử lý input file
            const fileInput = document.querySelector('input[type="file"]');
            if (fileInput) {
                fileInput.addEventListener('mouseenter', function() {
                    this.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
                });
            }
            
            // Xử lý text inputs
            const textInputs = document.querySelectorAll('input[type="text"], input[type="email"], input[type="password"]');
            textInputs.forEach(input => {
                input.style.cursor = "text";
                input.addEventListener('focus', function() {
                    this.style.cursor = "text";
                });
            });
        });
        
        // Xử lý form submit
        const form = document.getElementById('editForm');
        const submitBtn = document.getElementById('submitBtn');
        
        if (form) {
            form.addEventListener('submit', function(e) {
                const name = form.querySelector('input[name="name"]').value.trim();
                const email = form.querySelector('input[name="email"]').value.trim();
                
                if (!name) {
                    e.preventDefault();
                    alert('Vui lòng nhập tên!');
                    return false;
                }
                
                if (!email) {
                    e.preventDefault();
                    alert('Vui lòng nhập email!');
                    return false;
                }
                
                // Disable button khi đang submit
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Đang lưu...';
                }
            });
        }
    </script>
</body>
</html>

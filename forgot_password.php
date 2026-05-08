<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/html; charset=utf-8');

require 'db_connect.php';

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Xử lý POST từ AJAX
if ($_SERVER["REQUEST_METHOD"] === "POST" && $_POST["action"] === "forgot") {
    header("Content-Type: application/json");

    $email = trim($_POST["email"] ?? '');

    if (empty($email)) {
        echo json_encode(["status" => "error", "message" => "Vui lòng nhập email."]);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["status" => "error", "message" => "Email không hợp lệ."]);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM users WHERE Email = ?");
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Lỗi truy vấn CSDL (1)."]);
        exit;
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Email không tồn tại trong hệ thống."]);
    } else {
        $token = bin2hex(random_bytes(16));
        $stmt_upd = $conn->prepare("UPDATE users SET reset_token = ?, token_expiry = NOW() + INTERVAL 15 MINUTE WHERE Email = ?");
        if (!$stmt_upd) {
            echo json_encode(["status" => "error", "message" => "Lỗi truy vấn CSDL (2)."]);
            exit;
        }

        $stmt_upd->bind_param("ss", $token, $email);
        $stmt_upd->execute();

        $reset_link = "ngu1.php";

        echo json_encode([
            "status" => "success",
            "message" => "Vì Lười Làm Click Vào Đây Để Nhập Lại Mật Khẩu.",
            "link" => $reset_link
        ]);
    }

    exit;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quên mật khẩu</title>
    <link rel="icon" href="images.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background: #927057;
            color: #ffffff;
            font-family: 'Oxygen', sans-serif;
            padding: 20px;
        }
        main {
            width: 470px;
            margin: 50px auto;
            padding: 50px 60px 70px;
            background: #d5c395;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        h1 {
            margin-bottom: 20px;
        }
        input {
            outline: none;
            border: none;
            background: #efe1c4;
            padding: 10px;
            width: 85%;
            font-size: 1.1em;
            color: #927057;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        button {
            background: #927057;
            color: #ffffff;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1.1em;
        }
        button:hover {
            background: #7a6047;
        }
        footer {
            font-size: 1em;
            color: #EFE1C4;
            text-align: center;
            margin-top: 30px;
        }
        .image-container {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        .image-container img {
            border: 5px solid #d5c395;
            height: 120px;
            border-radius: 50%;
        }
    </style>
</head>
<body>

<div class="image-container">
    <img src="images.ico" alt="Logo">
</div>

<main>
    <h1>Quên mật khẩu</h1>
    <form id="forgotForm" method="post">
        <input type="email" name="email" id="email" placeholder="Nhập email của bạn" required>
        <input type="hidden" name="action" value="forgot">
        <button type="submit">Gửi liên kết đặt lại</button>
    </form>

    <h2>Đã nhớ lại mật khẩu? <a href="login.php">Đăng nhập</a></h2>
</main>

<footer>
    &copy; Giải Trí Lành Mạnh
</footer>

<script>
    $(document).ready(function () {
        $("#forgotForm").submit(function (e) {
            e.preventDefault();

            var formData = $(this).serialize();

            $.ajax({
                type: "POST",
                url: "", // chính file này
                data: formData,
                dataType: "json",
                success: function (response) {
                    if (response.status === "success") {
                        Swal.fire({
                            title: "✅ Thành công!",
                            html: response.message + "<br><a href='" + response.link + "' target='_blank'>" + response.link + "</a>",
                            icon: "success",
                            confirmButtonText: "OK"
                        });
                    } else {
                        Swal.fire({
                            title: "❌ Lỗi!",
                            text: response.message,
                            icon: "error"
                        });
                    }
                },
                error: function () {
                    Swal.fire("❌ Lỗi!", "Không thể kết nối đến server!", "error");
                }
            });
        });
    });
</script>

</body>
</html>

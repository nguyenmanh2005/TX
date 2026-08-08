<?php
session_start();
require 'db_connect.php';

// Kiểm tra kết nối
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Kết nối thất bại: " . $conn->connect_error]));
}

// Xử lý POST từ AJAX
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    header("Content-Type: application/json");
    $action = $_POST["action"] ?? '';

    // BƯỚC 1: LẤY TOKEN BẰNG EMAIL
    if ($action === "forgot") {
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
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            echo json_encode(["status" => "error", "message" => "Email không tồn tại trong hệ thống."]);
        } else {
            $token = bin2hex(random_bytes(6)); // Tạo token ngắn gọn 12 ký tự để dễ nhìn
            $stmt_upd = $conn->prepare("UPDATE users SET reset_token = ?, token_expiry = NOW() + INTERVAL 15 MINUTE WHERE Email = ?");
            $stmt_upd->bind_param("ss", $token, $email);
            
            if ($stmt_upd->execute()) {
                echo json_encode([
                    "status" => "success",
                    "message" => "Mã xác nhận đã được tạo thành công!",
                    "token" => $token // Trả về token trực tiếp (để điền tự động)
                ]);
            } else {
                echo json_encode(["status" => "error", "message" => "Lỗi CSDL khi tạo mã."]);
            }
        }
        exit;
    }

    // BƯỚC 2: ĐẶT LẠI MẬT KHẨU
    if ($action === "reset") {
        $email = trim($_POST["email"] ?? '');
        $token = trim($_POST["token"] ?? '');
        $newpass = trim($_POST["password"] ?? '');

        if (empty($email) || empty($newpass) || empty($token)) {
            echo json_encode(["status" => "error", "message" => "Vui lòng nhập đầy đủ thông tin."]);
            exit;
        }

        // Kiểm tra email và token hợp lệ + còn hạn
        $stmt = $conn->prepare("SELECT Iduser FROM users WHERE Email = ? AND reset_token = ? AND token_expiry > NOW()");
        $stmt->bind_param("ss", $email, $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            echo json_encode(["status" => "error", "message" => "Mã xác nhận không đúng hoặc đã hết hạn."]);
            exit;
        }

        // Cập nhật mật khẩu và xóa token
        $hashed_pass = password_hash($newpass, PASSWORD_DEFAULT);
        $stmt_update = $conn->prepare("UPDATE users SET Pass = ?, reset_token = NULL, token_expiry = NULL WHERE Email = ?");
        $stmt_update->bind_param("ss", $hashed_pass, $email);

        if ($stmt_update->execute()) {
            echo json_encode(["status" => "success", "message" => "Mật khẩu đã được cập nhật thành công!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Lỗi CSDL: Không thể cập nhật mật khẩu."]);
        }
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khôi phục mật khẩu - GTLM</title>
    <link rel="icon" href="images.ico">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            font-family: 'Segoe UI', 'Roboto', Arial, sans-serif;
            min-height: 100vh;
            margin: 0;
            overflow-x: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
        }

        * { cursor: inherit; box-sizing: border-box; }
        button, a { cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important; }
        input { cursor: text !important; }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .auth-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 480px;
            padding: 20px;
        }

        .auth-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 30px;
            padding: 50px 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.5);
            animation: slideUp 0.8s ease;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(50px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid rgba(102, 126, 234, 0.3);
            margin-bottom: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2); }
            50% { box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6); }
        }

        .title {
            font-size: 28px;
            font-weight: 700;
            color: #4a4a4a;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #777;
            font-size: 15px;
            margin-bottom: 30px;
        }

        .form-group {
            position: relative;
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
            font-size: 14px;
        }

        .input-icon {
            position: absolute;
            left: 18px;
            top: 42px;
            color: #999;
            font-size: 16px;
        }

        .form-group input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
            background: rgba(255, 255, 255, 0.9);
        }

        .form-group input:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: bold;
            margin-top: 10px;
            transition: all 0.3s;
            text-transform: uppercase;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }

        .submit-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed !important;
            transform: none;
        }

        .back-link {
            display: inline-block;
            margin-top: 25px;
            color: #764ba2;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
        }

        .back-link:hover {
            color: #667eea;
            text-decoration: underline;
        }

        /* Step transitions */
        #step2 { display: none; }
        
        .fade-in { animation: fadeIn 0.5s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

    </style>
</head>
<body>

    <div class="auth-container">
        <div class="auth-card">
            <img src="images.ico" alt="Logo" class="logo">
            
            <!-- STEP 1: XÁC MINH EMAIL -->
            <div id="step1" class="fade-in">
                <div class="title">Quên Mật Khẩu? 🤫</div>
                <div class="subtitle">Nhập email của bạn để lấy mã xác nhận</div>
                
                <form id="forgotForm">
                    <input type="hidden" name="action" value="forgot">
                    <div class="form-group">
                        <label>📧 Email đăng ký</label>
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" id="emailInput" name="email" placeholder="Ví dụ: hotboy@gmail.com" required>
                    </div>
                    <button type="submit" class="submit-btn" id="btnForgot">
                        <i class="fas fa-paper-plane"></i> GỬI MÃ XÁC NHẬN
                    </button>
                </form>
            </div>

            <!-- STEP 2: ĐẶT MẬT KHẨU MỚI -->
            <div id="step2">
                <div class="title">Tạo Mật Khẩu Mới ✨</div>
                <div class="subtitle">Mã xác nhận đã được cấp. Hãy nhập mật khẩu mới!</div>
                
                <form id="resetForm">
                    <input type="hidden" name="action" value="reset">
                    <input type="hidden" name="email" id="hiddenEmail">
                    
                    <div class="form-group">
                        <label>🔑 Mã xác nhận (Token)</label>
                        <i class="fas fa-key input-icon"></i>
                        <input type="text" id="tokenInput" name="token" placeholder="Mã xác nhận" readonly style="background: #f0f0f0; color: #667eea; font-weight: bold; cursor: not-allowed !important;">
                    </div>

                    <div class="form-group">
                        <label>🔒 Mật khẩu mới</label>
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" id="newPassInput" name="password" placeholder="Nhập mật khẩu mới (Ít nhất 6 ký tự)" required>
                    </div>

                    <button type="submit" class="submit-btn" id="btnReset">
                        <i class="fas fa-check-circle"></i> ĐỔI MẬT KHẨU
                    </button>
                </form>
            </div>

            <a href="login.php" class="back-link"><i class="fas fa-arrow-left"></i> Quay lại đăng nhập</a>
        </div>
    </div>

    <script>
        $(document).ready(function () {
            
            // Xử lý Bước 1: Gửi Email
            $("#forgotForm").submit(function (e) {
                e.preventDefault();
                const btn = $("#btnForgot");
                const originalText = btn.html();
                
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Đang xử lý...');

                $.ajax({
                    type: "POST",
                    url: "forgot_password.php",
                    data: $(this).serialize(),
                    dataType: "json",
                    success: function (res) {
                        btn.prop('disabled', false).html(originalText);
                        
                        if (res.status === "success") {
                            Swal.fire({
                                title: "✅ Đã cấp mã!",
                                text: "Hệ thống đã tạo mã xác nhận an toàn cho bạn.",
                                icon: "success",
                                timer: 1500,
                                showConfirmButton: false
                            });

                            // Tự động chuyển qua Bước 2
                            $("#hiddenEmail").val($("#emailInput").val());
                            $("#tokenInput").val(res.token); // Tự động điền token để user đỡ vất vả
                            
                            $("#step1").hide();
                            $("#step2").fadeIn().addClass('fade-in');
                            $("#newPassInput").focus();
                            
                        } else {
                            Swal.fire("❌ Lỗi!", res.message, "error");
                        }
                    },
                    error: function () {
                        btn.prop('disabled', false).html(originalText);
                        Swal.fire("❌ Lỗi!", "Không thể kết nối máy chủ!", "error");
                    }
                });
            });

            // Xử lý Bước 2: Cập nhật mật khẩu
            $("#resetForm").submit(function (e) {
                e.preventDefault();
                const btn = $("#btnReset");
                const originalText = btn.html();
                const pwd = $("#newPassInput").val();

                if (pwd.length < 6) {
                    Swal.fire("⚠️ Chú ý", "Mật khẩu phải có ít nhất 6 ký tự!", "warning");
                    return;
                }
                
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Đang cập nhật...');

                $.ajax({
                    type: "POST",
                    url: "forgot_password.php",
                    data: $(this).serialize(),
                    dataType: "json",
                    success: function (res) {
                        btn.prop('disabled', false).html(originalText);
                        
                        if (res.status === "success") {
                            Swal.fire({
                                title: "🎉 Thành công!",
                                text: "Mật khẩu của bạn đã được cập nhật.",
                                icon: "success",
                                confirmButtonText: "Đăng Nhập Ngay"
                            }).then(() => {
                                window.location.href = "login.php";
                            });
                        } else {
                            Swal.fire("❌ Lỗi!", res.message, "error");
                        }
                    },
                    error: function () {
                        btn.prop('disabled', false).html(originalText);
                        Swal.fire("❌ Lỗi!", "Không thể kết nối máy chủ!", "error");
                    }
                });
            });
            
        });
    </script>
</body>
</html>

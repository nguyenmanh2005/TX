<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

require 'db_connect.php';
require_once 'mail_helper.php';
require_once 'referral_helper.php';

function jsonResponse(array $payload)
{
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

// Nếu có ref trong URL thì lưu vào session để sử dụng lúc đăng ký
if (isset($_GET['ref'])) {
    $_SESSION['ref_code'] = preg_replace('/[^A-Z0-9]/i', '', $_GET['ref']);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? '';

    if ($action === 'register') {
        $fullname = trim($_POST["fullname"] ?? '');
        $email = trim($_POST["email"] ?? '');
        $password_plain = trim($_POST["password"] ?? '');

        if (empty($fullname) || empty($email) || empty($password_plain)) {
            jsonResponse(["status" => "error", "message" => "Vui lòng điền đầy đủ thông tin! 💭"]);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(["status" => "error", "message" => "Email không hợp lệ! 📧"]);
        }

        if (strlen($password_plain) < 6) {
            jsonResponse(["status" => "error", "message" => "Mật khẩu phải có ít nhất 6 ký tự! 🔒"]);
        }

        $check_sql = "SELECT 1 FROM users WHERE Email = ?";
        $stmt_check = $conn->prepare($check_sql);
        if (!$stmt_check) {
            jsonResponse(["status" => "error", "message" => "Lỗi truy vấn SQL: " . $conn->error]);
        }
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $result = $stmt_check->get_result();

        if ($result && $result->num_rows > 0) {
            $stmt_check->close();
            jsonResponse(["status" => "error", "message" => "Email đã tồn tại! 📭"]);
        }

        $stmt_check->close();
        $password_hash = password_hash($password_plain, PASSWORD_DEFAULT);

        // Xử lý referral nếu có
        $referrerId = 0;
        if (!empty($_SESSION['ref_code'])) {
            $refCode = $_SESSION['ref_code'];
            $refUserId = ref_find_user_by_code($conn, $refCode);
            if ($refUserId && $refUserId > 0) {
                $referrerId = (int)$refUserId;
            }
        }

        // Tạo tài khoản trực tiếp, không cần OTP
        $insertSql = "INSERT INTO users (Name, Email, Pass) VALUES (?, ?, ?)";
        $stmtInsert = $conn->prepare($insertSql);
        if (!$stmtInsert) {
            jsonResponse(["status" => "error", "message" => "Không thể tạo tài khoản: " . $conn->error]);
        }
        $stmtInsert->bind_param("sss", $fullname, $email, $password_hash);

        if ($stmtInsert->execute()) {
            $newUserId = $stmtInsert->insert_id;
            if ($referrerId > 0) {
                ref_reward_on_register($conn, $referrerId, $newUserId);
            }

            unset($_SESSION['ref_code']);
            jsonResponse([
                "status" => "success",
                "message" => "Đăng ký thành công! Bạn có thể đăng nhập ngay bây giờ. 🎉",
                "redirect" => "login.php"
            ]);
        }

        jsonResponse(["status" => "error", "message" => "Không thể tạo tài khoản: " . $stmtInsert->error]);
    }

    // Nếu POST nhưng không rơi vào các action trên
    exit;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>✨ Đăng Ký - Giải Trí Lành Mạnh</title>
    <link rel="icon" href="images.ico">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            font-family: 'Segoe UI', 'Roboto', Arial, sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Floating Particles Background */
        .particles-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            pointer-events: none;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            animation: float 20s infinite ease-in-out;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0) translateX(0) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) translateX(100px) rotate(360deg);
                opacity: 0;
            }
        }

        /* Floating Emojis */
        .floating-emoji {
            position: absolute;
            font-size: 24px;
            animation: floatEmoji 15s infinite ease-in-out;
            pointer-events: none;
        }

        @keyframes floatEmoji {
            0%, 100% {
                transform: translateY(100vh) translateX(0) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) translateX(50px) rotate(360deg);
                opacity: 0;
            }
        }

        /* Main Container */
        .auth-container {
            position: relative;
            z-index: 10;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .landing-banner {
            position: relative;
            margin-bottom: 20px;
            padding: 16px 22px;
            border-radius: 18px;
            background: rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.25);
            color: #fff;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: inline-flex;
            gap: 12px;
            align-items: center;
        }

        .landing-banner:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.35);
        }

        .landing-banner span {
            font-weight: 600;
        }

        /* Logo Animation */
        .logo-container {
            text-align: center;
            margin-bottom: 30px;
            animation: bounceIn 1s ease;
        }

        @keyframes bounceIn {
            0% {
                opacity: 0;
                transform: scale(0.3) translateY(-50px);
            }
            50% {
                opacity: 1;
                transform: scale(1.05);
            }
            70% {
                transform: scale(0.9);
            }
            100% {
                transform: scale(1);
            }
        }

        .logo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 5px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            animation: pulse 2s infinite ease-in-out, rotate 20s linear infinite;
            transition: transform 0.3s ease;
        }

        .logo:hover {
            transform: scale(1.1) rotate(10deg);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
            animation-play-state: paused;
        }

        @keyframes pulse {
            0%, 100% {
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3), 0 0 0 0 rgba(255, 255, 255, 0.7);
            }
            50% {
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3), 0 0 0 20px rgba(255, 255, 255, 0);
            }
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Register Card */
        .register-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 30px;
            padding: 50px 40px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.5);
            animation: slideUp 0.8s ease;
            position: relative;
            overflow: hidden;
        }

        .register-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(102, 126, 234, 0.1), transparent);
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Title */
        .register-title {
            text-align: center;
            margin-bottom: 40px;
            animation: fadeInDown 1s ease;
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

        .register-title h1 {
            font-size: 36px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
            text-shadow: none;
        }

        .register-title p {
            color: #666;
            font-size: 16px;
            margin-top: 10px;
        }

        /* Form Group */
        .form-group {
            position: relative;
            margin-bottom: 25px;
            animation: fadeIn 1s ease;
            animation-fill-mode: both;
        }

        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 18px;
            color: #999;
            transition: color 0.3s ease;
            z-index: 2;
        }

        .form-group input {
            width: 100%;
            padding: 16px 18px 16px 50px;
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
            color: #333;
            cursor: text !important;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        .form-group input:focus ~ .input-icon,
        .form-group input:not(:placeholder-shown) ~ .input-icon {
            color: #667eea;
        }

        /* Input Validation */
        .form-group input.valid {
            border-color: #2ecc71;
            background: rgba(46, 204, 113, 0.05);
        }

        .form-group input.valid + .input-icon {
            color: #2ecc71;
        }

        .form-group input.invalid {
            border-color: #e74c3c;
            background: rgba(231, 76, 60, 0.05);
            animation: shake 0.5s ease;
        }

        .form-group input.invalid + .input-icon {
            color: #e74c3c;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        /* Validation Message */
        .validation-message {
            margin-top: 5px;
            font-size: 12px;
            padding: 5px 10px;
            border-radius: 5px;
            display: none;
            animation: slideDown 0.3s ease;
        }

        .validation-message.show {
            display: block;
        }

        .validation-message.success {
            background: rgba(46, 204, 113, 0.1);
            color: #27ae60;
            border-left: 3px solid #2ecc71;
        }

        .validation-message.error {
            background: rgba(231, 76, 60, 0.1);
            color: #c0392b;
            border-left: 3px solid #e74c3c;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Typing Indicator */
        .typing-indicator {
            position: absolute;
            right: 60px;
            top: 50%;
            transform: translateY(-50%);
            display: none;
        }

        .typing-indicator.show {
            display: block;
        }

        .typing-indicator span {
            display: inline-block;
            width: 4px;
            height: 4px;
            background: #667eea;
            border-radius: 50%;
            margin: 0 2px;
            animation: typing 1.4s infinite ease-in-out;
        }

        .typing-indicator span:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-indicator span:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes typing {
            0%, 60%, 100% {
                transform: translateY(0);
                opacity: 0.7;
            }
            30% {
                transform: translateY(-10px);
                opacity: 1;
            }
        }

        /* Password Strength with Emoji */
        .password-strength {
            margin-top: 8px;
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
            display: none;
            position: relative;
        }

        .password-strength.show {
            display: block;
        }

        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease, background 0.3s ease;
            border-radius: 3px;
            position: relative;
        }

        .password-strength-bar.weak {
            width: 33%;
            background: linear-gradient(90deg, #e74c3c, #c0392b);
        }

        .password-strength-bar.medium {
            width: 66%;
            background: linear-gradient(90deg, #f39c12, #e67e22);
        }

        .password-strength-bar.strong {
            width: 100%;
            background: linear-gradient(90deg, #2ecc71, #27ae60);
        }

        .password-strength-emoji {
            position: absolute;
            right: -25px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .password-strength-bar.weak .password-strength-emoji::after {
            content: '😰';
        }

        .password-strength-bar.medium .password-strength-emoji::after {
            content: '😊';
        }

        .password-strength-bar.strong .password-strength-emoji::after {
            content: '🔥';
        }

        /* Password Tips */
        .password-tips {
            margin-top: 10px;
            padding: 10px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 8px;
            border-left: 3px solid #667eea;
            display: none;
            font-size: 12px;
            color: #666;
        }

        .password-tips.show {
            display: block;
            animation: slideDown 0.3s ease;
        }

        .password-tips ul {
            margin: 5px 0;
            padding-left: 20px;
        }

        .password-tips li {
            margin: 5px 0;
        }

        /* Shake Error Animation */
        .shake-error {
            animation: shakeCard 0.5s ease;
        }

        @keyframes shakeCard {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-10px) rotate(-1deg); }
            20%, 40%, 60%, 80% { transform: translateX(10px) rotate(1deg); }
        }

        /* Success Celebration */
        .celebration-emoji {
            position: absolute;
            font-size: 40px;
            animation: celebrate 2s ease forwards;
        }

        @keyframes celebrate {
            0% {
                opacity: 1;
                transform: translateY(50vh) scale(0) rotate(0deg);
            }
            50% {
                opacity: 1;
                transform: translateY(30vh) scale(1.5) rotate(180deg);
            }
            100% {
                opacity: 0;
                transform: translateY(10vh) scale(0.5) rotate(360deg);
            }
        }


        /* Password Toggle */
        .password-toggle {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 24px;
            color: #999;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            z-index: 2;
            padding: 8px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(102, 126, 234, 0.1);
        }

        .password-toggle:hover {
            color: #667eea;
            background: rgba(102, 126, 234, 0.2);
            transform: translateY(-50%) scale(1.1) rotate(5deg);
        }

        .password-toggle:active {
            transform: translateY(-50%) scale(0.95);
        }

        /* Eye animation when hiding password */
        .password-toggle .eye-icon {
            display: inline-block;
            position: relative;
            transition: all 0.4s ease;
        }

        .password-toggle .eye-icon.hidden {
            animation: eyeBlink 0.5s ease;
        }

        .password-toggle .eye-icon.visible {
            animation: eyeOpen 0.5s ease;
        }

        @keyframes eyeBlink {
            0% { transform: scaleY(1); }
            50% { transform: scaleY(0.1); }
            100% { transform: scaleY(1); }
        }

        @keyframes eyeOpen {
            0% { transform: scaleY(0.1); opacity: 0.5; }
            50% { transform: scaleY(1.2); }
            100% { transform: scaleY(1); opacity: 1; }
        }

        /* Hand covering eye animation */
        .password-toggle .hand-cover {
            position: absolute;
            font-size: 22px;
            opacity: 0;
            transform: translateX(-15px) scale(0) rotate(-10deg);
            transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            pointer-events: none;
            z-index: 3;
        }

        .password-toggle.password-hidden .hand-cover {
            opacity: 1;
            transform: translateX(0) scale(1) rotate(0deg);
            animation: handCoverEye 0.6s ease;
        }

        @keyframes handCoverEye {
            0% {
                transform: translateX(-15px) scale(0.5) rotate(-20deg);
                opacity: 0;
            }
            50% {
                transform: translateX(2px) scale(1.1) rotate(5deg);
            }
            100% {
                transform: translateX(0) scale(1) rotate(0deg);
                opacity: 1;
            }
        }

        .password-toggle.password-visible .hand-cover {
            animation: handMoveAway 0.5s ease forwards;
        }

        @keyframes handMoveAway {
            0% {
                transform: translateX(0) scale(1) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateX(15px) scale(0) rotate(20deg);
                opacity: 0;
            }
        }

        .password-toggle.password-visible .eye-icon {
            opacity: 1;
        }

        .password-toggle.password-hidden .eye-icon {
            opacity: 0.4;
        }

        /* Submit Button */
        .submit-btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%);
            background-size: 200% 200%;
            animation: gradientShift 3s ease infinite;
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 18px;
            font-weight: 700;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            position: relative;
            overflow: hidden;
            margin-top: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .submit-btn::before {
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

        .submit-btn:hover::before {
            width: 400px;
            height: 400px;
        }

        .submit-btn:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.6);
        }

        .submit-btn:active {
            transform: translateY(-1px) scale(0.98);
        }

        .submit-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed !important;
            transform: none;
        }

        .submit-btn .btn-text {
            position: relative;
            z-index: 1;
        }

        .submit-btn .btn-loader {
            display: none;
            position: relative;
            z-index: 1;
        }

        .submit-btn.loading .btn-text {
            display: none;
        }

        .submit-btn.loading .btn-loader {
            display: inline-block;
        }

        /* Login Link */
        .login-link {
            text-align: center;
            margin-top: 30px;
            animation: fadeInUp 0.8s ease;
            animation-delay: 0.4s;
            animation-fill-mode: both;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-link p {
            color: #666;
            font-size: 16px;
            margin-bottom: 10px;
        }

        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 700;
            font-size: 16px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        .login-link a:hover {
            color: #764ba2;
            text-decoration: underline;
            transform: translateX(5px);
        }

        /* Footer */
        footer {
            text-align: center;
            margin-top: 30px;
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
            animation: fadeIn 1s ease;
            animation-delay: 0.6s;
            animation-fill-mode: both;
        }

        /* Confetti */
        .confetti {
            position: fixed;
            width: 10px;
            height: 10px;
            background: #f0f;
            position: absolute;
            animation: confetti-fall 3s linear forwards;
            z-index: 9999;
        }

        @keyframes confetti-fall {
            to {
                transform: translateY(100vh) rotate(720deg);
                opacity: 0;
            }
        }

        /* Success Animation */
        @keyframes successPop {
            0% {
                transform: scale(0);
                opacity: 0;
            }
            50% {
                transform: scale(1.2);
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        /* Responsive */
        @media (max-width: 600px) {
            .register-card {
                padding: 40px 25px;
                border-radius: 20px;
            }

            .register-title h1 {
                font-size: 28px;
            }

            .logo {
                width: 100px;
                height: 100px;
            }
        }

        /* Cursor fix */
        * {
            cursor: inherit;
        }

        button, a, input[type="button"], input[type="submit"], label, select {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        input[type="text"], input[type="email"], input[type="password"] {
            cursor: text !important;
        }
    </style>
</head>
<body>
    <!-- Floating Particles -->
    <div class="particles-container" id="particlesContainer"></div>

    <div class="auth-container">
        <div class="landing-banner" onclick="window.location.href='landing.php'">
            <span>🚀 Khám phá Landing UI mới:</span>
            <span>Preview quest, Lucky Wheel & statistics realtime trước khi đăng ký.</span>
            <i class="fas fa-arrow-right"></i>
        </div>
        <div class="register-card">
            <!-- Logo -->
            <div class="logo-container">
                <img src="images.ico" alt="Logo" class="logo">
            </div>

            <!-- Title -->
            <div class="register-title">
                <h1>✨ Đăng Ký</h1>
                <p>Tham gia cùng chúng tôi ngay hôm nay! 🎉</p>
            </div>

            <!-- Form -->
            <form id="registerForm" method="post">
                <div class="form-group">
                    <label for="fullname">👤 Họ Tên</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" id="fullname" name="fullname" placeholder="Nhập họ tên của bạn" required autocomplete="name">
                        <div class="typing-indicator" id="fullnameTyping">
                            <span></span><span></span><span></span>
                        </div>
                    </div>
                    <div class="validation-message" id="fullnameValidation"></div>
                </div>

                <div class="form-group">
                    <label for="email">📧 Email</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" id="email" name="email" placeholder="Nhập email của bạn" required autocomplete="email">
                        <div class="typing-indicator" id="emailTyping">
                            <span></span><span></span><span></span>
                        </div>
                    </div>
                    <div class="validation-message" id="emailValidation"></div>
                </div>

                <div class="form-group">
                    <label for="password">🔒 Mật Khẩu</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" id="password" name="password" placeholder="Nhập mật khẩu (tối thiểu 6 ký tự)" required autocomplete="new-password">
                        <button type="button" class="password-toggle password-hidden" id="togglePassword">
                            <span class="hand-cover">🫣</span>
                            <span class="eye-icon hidden">👁️</span>
                        </button>
                        <div class="typing-indicator" id="passwordTyping">
                            <span></span><span></span><span></span>
                        </div>
                    </div>
                    <div class="password-strength" id="passwordStrength">
                        <div class="password-strength-bar" id="passwordStrengthBar">
                            <span class="password-strength-emoji"></span>
                        </div>
                    </div>
                    <div class="validation-message" id="passwordValidation"></div>
                    <div class="password-tips" id="passwordTips">
                        <strong>💡 Mẹo tạo mật khẩu mạnh:</strong>
                        <ul>
                            <li>Ít nhất 6 ký tự</li>
                            <li>Kết hợp chữ hoa và chữ thường</li>
                            <li>Thêm số và ký tự đặc biệt</li>
                            <li>Tránh thông tin cá nhân</li>
                        </ul>
                    </div>
                </div>

                <input type="hidden" name="action" value="register">
                <button type="submit" class="submit-btn" id="submitBtn">
                    <span class="btn-text">🚀 Đăng Ký Ngay</span>
                    <span class="btn-loader">
                        <i class="fas fa-spinner fa-spin"></i> Đang xử lý...
                    </span>
                </button>
            </form>

            <!-- Login Link -->
            <div class="login-link">
                <p>Bạn đã có tài khoản? 🎯</p>
                <a href="login.php">
                    <i class="fas fa-sign-in-alt"></i>
                    Đăng nhập ngay
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <!-- Footer -->
            <footer>
                <p>&copy; <?= date('Y') ?> Giải Trí Lành Mạnh - Made with ❤️</p>
            </footer>
        </div>
    </div>

    <script>
        // Create floating particles
        function createParticles() {
            const container = document.getElementById('particlesContainer');
            const particleCount = 30;
            const emojis = ['🎉', '✨', '🌟', '💫', '⭐', '🎊', '🎈', '🎁'];

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                const size = Math.random() * 8 + 4;
                particle.style.width = size + 'px';
                particle.style.height = size + 'px';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 20 + 's';
                particle.style.animationDuration = (Math.random() * 10 + 15) + 's';
                container.appendChild(particle);
            }

            // Create floating emojis
            for (let i = 0; i < 10; i++) {
                const emoji = document.createElement('div');
                emoji.className = 'floating-emoji';
                emoji.innerHTML = emojis[Math.floor(Math.random() * emojis.length)];
                emoji.style.left = Math.random() * 100 + '%';
                emoji.style.animationDelay = Math.random() * 15 + 's';
                emoji.style.animationDuration = (Math.random() * 5 + 10) + 's';
                container.appendChild(emoji);
            }
        }

        // Password toggle with cute animation
        const cuteEmojis = ['🫣', '🤭', '🙈', '🫢', '😶'];
        let emojiIndex = 0;
        
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = this;
            const eyeIcon = toggleBtn.querySelector('.eye-icon');
            const handCover = toggleBtn.querySelector('.hand-cover');
            
            if (passwordInput.type === 'password') {
                // Show password - remove hand cover, show eye
                passwordInput.type = 'text';
                toggleBtn.classList.remove('password-hidden');
                toggleBtn.classList.add('password-visible');
                eyeIcon.classList.remove('hidden');
                eyeIcon.classList.add('visible');
                
                // Animation: hand moves away, eye opens with blink
                setTimeout(() => {
                    eyeIcon.innerHTML = '👁️';
                    eyeIcon.style.animation = 'eyeOpen 0.5s ease';
                }, 100);
            } else {
                // Hide password - hand covers eye with random emoji
                passwordInput.type = 'password';
                toggleBtn.classList.remove('password-visible');
                toggleBtn.classList.add('password-hidden');
                eyeIcon.classList.remove('visible');
                eyeIcon.classList.add('hidden');
                
                // Change emoji randomly for variety
                emojiIndex = (emojiIndex + 1) % cuteEmojis.length;
                handCover.innerHTML = cuteEmojis[emojiIndex];
                
                // Animation: eye closes, hand covers
                setTimeout(() => {
                    eyeIcon.innerHTML = '👁️';
                    eyeIcon.style.animation = 'eyeBlink 0.5s ease';
                }, 50);
            }
        });

        // Real-time validation
        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        function showValidation(input, message, type) {
            const validationDiv = document.getElementById(input.id + 'Validation');
            if (validationDiv) {
                validationDiv.textContent = message;
                validationDiv.className = 'validation-message show ' + type;
            }
        }

        function hideValidation(input) {
            const validationDiv = document.getElementById(input.id + 'Validation');
            if (validationDiv) {
                validationDiv.className = 'validation-message';
            }
        }

        // Fullname validation
        document.getElementById('fullname').addEventListener('input', function() {
            const fullname = this.value;
            const typingIndicator = document.getElementById('fullnameTyping');
            
            if (fullname.length > 0) {
                typingIndicator.classList.add('show');
                setTimeout(() => typingIndicator.classList.remove('show'), 500);
            }

            if (fullname.length === 0) {
                this.classList.remove('valid', 'invalid');
                hideValidation(this);
            } else if (fullname.length >= 2) {
                this.classList.remove('invalid');
                this.classList.add('valid');
                showValidation(this, '✅ Họ tên hợp lệ!', 'success');
            } else {
                this.classList.remove('valid');
                this.classList.add('invalid');
                showValidation(this, '❌ Họ tên phải có ít nhất 2 ký tự!', 'error');
            }
        });

        // Email validation
        document.getElementById('email').addEventListener('input', function() {
            const email = this.value;
            const typingIndicator = document.getElementById('emailTyping');
            
            if (email.length > 0) {
                typingIndicator.classList.add('show');
                setTimeout(() => typingIndicator.classList.remove('show'), 500);
            }

            if (email.length === 0) {
                this.classList.remove('valid', 'invalid');
                hideValidation(this);
            } else if (validateEmail(email)) {
                this.classList.remove('invalid');
                this.classList.add('valid');
                showValidation(this, '✅ Email hợp lệ!', 'success');
            } else {
                this.classList.remove('valid');
                this.classList.add('invalid');
                showValidation(this, '❌ Email không hợp lệ!', 'error');
            }
        });

        // Password strength indicator with emoji
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrengthBar');
            const strengthContainer = document.getElementById('passwordStrength');
            const passwordTips = document.getElementById('passwordTips');
            const typingIndicator = document.getElementById('passwordTyping');
            
            if (password.length > 0) {
                typingIndicator.classList.add('show');
                setTimeout(() => typingIndicator.classList.remove('show'), 500);
                passwordTips.classList.add('show');
            } else {
                strengthContainer.classList.remove('show');
                passwordTips.classList.remove('show');
                this.classList.remove('valid', 'invalid');
                hideValidation(this);
                return;
            }

            strengthContainer.classList.add('show');

            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;

            strengthBar.className = 'password-strength-bar';
            if (strength <= 2) {
                strengthBar.classList.add('weak');
                showValidation(this, '😰 Mật khẩu yếu! Hãy thêm ký tự, số và chữ hoa.', 'error');
                this.classList.remove('valid');
                this.classList.add('invalid');
            } else if (strength <= 4) {
                strengthBar.classList.add('medium');
                showValidation(this, '😊 Mật khẩu trung bình! Có thể mạnh hơn.', 'error');
                this.classList.remove('valid', 'invalid');
            } else {
                strengthBar.classList.add('strong');
                showValidation(this, '🔥 Mật khẩu mạnh! Tuyệt vời!', 'success');
                this.classList.remove('invalid');
                this.classList.add('valid');
                passwordTips.classList.remove('show');
            }
        });

        // Focus on password field shows tips
        document.getElementById('password').addEventListener('focus', function() {
            if (this.value.length === 0) {
                document.getElementById('passwordTips').classList.add('show');
            }
        });

        // Easter Egg - Konami Code
        let konamiCode = [];
        const konamiSequence = ['ArrowUp', 'ArrowUp', 'ArrowDown', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'ArrowLeft', 'ArrowRight', 'b', 'a'];
        
        document.addEventListener('keydown', function(e) {
            konamiCode.push(e.key);
            if (konamiCode.length > konamiSequence.length) {
                konamiCode.shift();
            }
            
            if (konamiCode.length === konamiSequence.length && 
                konamiCode.every((key, index) => key === konamiSequence[index])) {
                createEasterEgg();
                konamiCode = [];
            }
        });

        function createEasterEgg() {
            const emojis = ['🎉', '🎊', '✨', '🌟', '💫', '⭐', '🎈', '🎁', '🦄', '🌈'];
            for (let i = 0; i < 50; i++) {
                setTimeout(() => {
                    const emoji = document.createElement('div');
                    emoji.className = 'celebration-emoji';
                    emoji.textContent = emojis[Math.floor(Math.random() * emojis.length)];
                    emoji.style.left = Math.random() * 100 + '%';
                    emoji.style.animationDelay = Math.random() * 0.5 + 's';
                    document.body.appendChild(emoji);
                    setTimeout(() => emoji.remove(), 2000);
                }, i * 50);
            }
            
            Swal.fire({
                title: '🎉 Easter Egg!',
                text: 'Bạn đã tìm thấy bí mật! 🎊',
                icon: 'success',
                timer: 3000,
                showConfirmButton: false
            });
        }

        // Form submission
        $(document).ready(function() {
            // Create particles on load
            createParticles();

            // Form submit
            $('#registerForm').submit(function(e) {
                e.preventDefault();

                const fullname = $('#fullname').val().trim();
                const email = $('#email').val().trim();
                const password = $('#password').val().trim();
                const submitBtn = $('#submitBtn');

                // Validate
                if (!fullname || !email || !password) {
                    Swal.fire({
                        title: '❌ Thiếu thông tin!',
                        text: 'Vui lòng điền đầy đủ thông tin! 💭',
                        icon: 'error',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#667eea'
                    });
                    return;
                }

                if (password.length < 6) {
                    Swal.fire({
                        title: '❌ Mật khẩu quá ngắn!',
                        text: 'Mật khẩu phải có ít nhất 6 ký tự! 🔒',
                        icon: 'error',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#667eea'
                    });
                    return;
                }

                // Show loading
                submitBtn.addClass('loading').prop('disabled', true);

                // AJAX request
                $.ajax({
                    type: 'POST',
                    url: 'auth.php',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            // Create confetti
                            createConfetti();
                            
                            // Create celebration
                            createCelebration();

                            Swal.fire({
                                title: '🎉 Thành công!',
                                text: response.message,
                                icon: 'success',
                                timer: 2500,
                                showConfirmButton: false,
                                background: '#fff',
                                color: '#333'
                            }).then(() => {
                                window.location.href = response.redirect;
                            });
                        } else {
                            // Shake form on error
                            $('.register-card').addClass('shake-error');
                            setTimeout(() => $('.register-card').removeClass('shake-error'), 500);
                            
                            submitBtn.removeClass('loading').prop('disabled', false);
                            Swal.fire({
                                title: '❌ Lỗi!',
                                text: response.message,
                                icon: 'error',
                                confirmButtonText: 'Thử lại',
                                confirmButtonColor: '#667eea'
                            });
                        }
                    },
                    error: function() {
                        submitBtn.removeClass('loading').prop('disabled', false);
                        Swal.fire({
                            title: '❌ Lỗi!',
                            text: 'Không thể kết nối đến server! 🔌',
                            icon: 'error',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#667eea'
                        });
                    }
                });
            });

            // Input focus animation
            $('input').on('focus', function() {
                $(this).parent().addClass('focused');
            }).on('blur', function() {
                if (!$(this).val()) {
                    $(this).parent().removeClass('focused');
                }
            });
        });

        // Create confetti
        function createConfetti() {
            const colors = ['#ff6b6b', '#4ecdc4', '#45b7d1', '#f9ca24', '#f0932b', '#eb4d4b', '#6c5ce7', '#a29bfe', '#ff9ff3', '#54a0ff'];
            const confettiCount = 150;

            for (let i = 0; i < confettiCount; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.left = Math.random() * 100 + '%';
                confetti.style.background = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.animationDelay = Math.random() * 2 + 's';
                confetti.style.animationDuration = (Math.random() * 2 + 2) + 's';
                document.body.appendChild(confetti);

                setTimeout(() => confetti.remove(), 5000);
            }
        }

        // Create celebration animation
        function createCelebration() {
            const emojis = ['🎉', '🎊', '✨', '🌟', '💫', '⭐', '🎈', '🎁', '👏', '👍', '🔥', '💯', '🎯', '🏆'];
            for (let i = 0; i < 40; i++) {
                setTimeout(() => {
                    const emoji = document.createElement('div');
                    emoji.className = 'celebration-emoji';
                    emoji.textContent = emojis[Math.floor(Math.random() * emojis.length)];
                    emoji.style.left = Math.random() * 100 + '%';
                    emoji.style.animationDelay = Math.random() * 0.3 + 's';
                    document.body.appendChild(emoji);
                    setTimeout(() => emoji.remove(), 2000);
                }, i * 50);
            }
        }

        // Cursor fix
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.cursor = "url('chuot.png'), url('../chuot.png'), auto";
            
            const interactiveElements = document.querySelectorAll('button, a, label, select');
            interactiveElements.forEach(el => {
                el.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
            });

            const textInputs = document.querySelectorAll('input[type="text"], input[type="email"], input[type="password"]');
            textInputs.forEach(input => {
                input.style.cursor = "text";
            });
        });
    </script>
</body>
</html>

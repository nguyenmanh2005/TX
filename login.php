<?php
session_start();
require 'db_connect.php';
require_once 'mail_helper.php';
require_once 'user_progress_helper.php';

function jsonResponse(array $payload)
{
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit();
}

if ($conn->connect_error) {
    jsonResponse(["status" => "error", "message" => "Kết nối thất bại: " . $conn->connect_error]);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        jsonResponse(["status" => "error", "message" => "Vui lòng nhập email và mật khẩu! 💭"]);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(["status" => "error", "message" => "Email không hợp lệ! 📧"]);
    }

    $sql = "SELECT Iduser, Name, Pass, Role FROM users WHERE Email = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        jsonResponse(["status" => "error", "message" => "Lỗi truy vấn SQL: " . $conn->error]);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result || $result->num_rows !== 1) {
        jsonResponse(["status" => "error", "message" => "Email không tồn tại! 📭"]);
    }

    $row = $result->fetch_assoc();

    if (!password_verify($password, $row['Pass'])) {
        jsonResponse(["status" => "error", "message" => "Sai mật khẩu! 🔒"]);
    }

    // Đăng nhập trực tiếp, không cần OTP
    $_SESSION['Iduser'] = $row['Iduser'];
    $_SESSION['Name'] = $row['Name'];

    // Cập nhật streak + thưởng ngày + XP sau khi đăng nhập thành công
    $loginBonus = up_handle_successful_login($conn, (int)$row['Iduser']);

    $successMessage = ($row['Role'] == 1)
        ? "Đăng nhập thành công với quyền admin! 👑"
        : "Đăng nhập thành công! 🎉";

    jsonResponse([
        "status" => "success",
        "message" => $successMessage,
        "redirect" => "index.php",
        "login_bonus" => $loginBonus,
    ]);
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔐 Đăng Nhập - Giải Trí Lành Mạnh</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/animations.css">

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

        /* Floating Hearts */
        .floating-heart {
            position: absolute;
            font-size: 20px;
            color: rgba(255, 182, 193, 0.6);
            animation: floatHeart 15s infinite ease-in-out;
            pointer-events: none;
        }

        @keyframes floatHeart {
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

        /* Floating Stars */
        .floating-star {
            position: absolute;
            font-size: 15px;
            color: rgba(255, 255, 0, 0.6);
            animation: floatStar 12s infinite ease-in-out;
            pointer-events: none;
        }

        @keyframes floatStar {
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
                transform: translateY(-100vh) translateX(-50px) rotate(-360deg);
                opacity: 0;
            }
        }

        /* Main Container */
        .login-container {
            position: relative;
            z-index: 10;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
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

        /* Login Card */
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 30px;
            padding: 50px 40px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.5);
            animation: slideUp 0.8s ease;
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
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
        .login-title {
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

        .login-title h1 {
            font-size: 36px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
            text-shadow: none;
        }

        .login-title p {
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

        .form-group input:focus + .input-icon,
        .form-group input:not(:placeholder-shown) + .input-icon {
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

        /* Remember Me Checkbox */
        .remember-me {
            display: flex;
            align-items: center;
            margin: 20px 0;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        .remember-me input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 10px;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            appearance: none;
            border: 2px solid #ddd;
            border-radius: 5px;
            position: relative;
            transition: all 0.3s ease;
        }

        .remember-me input[type="checkbox"]:checked {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: #667eea;
            animation: checkmark 0.3s ease;
        }

        .remember-me input[type="checkbox"]:checked::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 14px;
            font-weight: bold;
        }

        @keyframes checkmark {
            0% { transform: scale(0.8); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .remember-me label {
            font-size: 14px;
            color: #666;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            user-select: none;
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

        /* Character Counter */
        .char-counter {
            position: absolute;
            right: 60px;
            top: -25px;
            font-size: 11px;
            color: #999;
            display: none;
        }

        .char-counter.show {
            display: block;
        }

        /* Success Celebration */
        .celebration {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 10000;
            pointer-events: none;
        }

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

        /* Link Cards */
        .link-cards {
            margin-top: 30px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .link-card {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            padding: 20px;
            border-radius: 15px;
            border: 2px solid rgba(102, 126, 234, 0.2);
            transition: all 0.3s ease;
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
            text-align: left;
            animation: fadeInUp 0.8s ease;
            animation-fill-mode: both;
        }

        .link-card:nth-child(1) { animation-delay: 0.4s; }
        .link-card:nth-child(2) { animation-delay: 0.5s; }

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

        /* Shake Error Animation */
        .shake-error {
            animation: shakeCard 0.5s ease;
        }

        @keyframes shakeCard {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-10px) rotate(-1deg); }
            20%, 40%, 60%, 80% { transform: translateX(10px) rotate(1deg); }
        }

        .link-card:hover {
            transform: translateY(-5px) scale(1.02);
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2) 0%, rgba(118, 75, 162, 0.2) 100%);
            border-color: rgba(102, 126, 234, 0.4);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .link-card .card-title {
            font-weight: 700;
            color: #667eea;
            margin-bottom: 8px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .link-card .card-link {
            color: #764ba2;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            transition: color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .link-card .card-link:hover {
            color: #667eea;
            text-decoration: underline;
        }

        .link-card.funny {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.1) 0%, rgba(255, 152, 0, 0.1) 100%);
            border-color: rgba(255, 193, 7, 0.3);
        }

        .link-card.funny:hover {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.2) 0%, rgba(255, 152, 0, 0.2) 100%);
            border-color: rgba(255, 193, 7, 0.5);
        }

        .link-card.funny .card-title {
            color: #f39c12;
        }

        .link-card.funny .card-link {
            color: #e67e22;
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

        /* Responsive */
        @media (max-width: 600px) {
            .login-card {
                padding: 40px 25px;
                border-radius: 20px;
            }

            .login-title h1 {
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

    <div class="login-container">
        <div class="login-card">
            <!-- Logo -->
            <div class="logo-container">
                <img src="images.ico" alt="Logo" class="logo">
            </div>

            <!-- Title -->
            <div class="login-title">
                <h1>🔐 Đăng Nhập</h1>
                <p>Chào mừng bạn trở lại! 💙</p>
            </div>

            <!-- Form -->
            <form id="loginForm" method="post">
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
                        <input type="password" id="password" name="password" placeholder="Nhập mật khẩu" required autocomplete="current-password">
                        <button type="button" class="password-toggle password-hidden" id="togglePassword">
                            <span class="hand-cover">🫣</span>
                            <span class="eye-icon hidden">👁️</span>
                        </button>
                        <div class="typing-indicator" id="passwordTyping">
                            <span></span><span></span><span></span>
                        </div>
                    </div>
                    <div class="validation-message" id="passwordValidation"></div>
                </div>

                <div class="remember-me">
                    <input type="checkbox" id="rememberMe" name="rememberMe">
                    <label for="rememberMe">💾 Ghi nhớ đăng nhập</label>
                </div>

                <button type="submit" class="submit-btn" id="submitBtn">
                    <span class="btn-text">🚀 Đăng Nhập</span>
                    <span class="btn-loader">
                        <i class="fas fa-spinner fa-spin"></i> Đang xử lý...
                    </span>
                </button>
            </form>

            <!-- Link Cards -->
            <div class="link-cards">
                <div class="link-card" onclick="window.location.href='auth.php'">
                    <div class="card-title">
                        <span>✨</span>
                        <span>Bạn chưa có tài khoản?</span>
                    </div>
                    <a href="auth.php" class="card-link">
                        👉 Đăng ký ngay
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <div class="link-card highlight" onclick="window.location.href='landing.php'">
                    <div class="card-title">
                        <span>🚀</span>
                        <span>Khám phá Landing UI</span>
                    </div>
                    <p>
                        Xem preview đầy đủ về quest, lucky wheel, gift system và statistics realtime trước khi đăng nhập.
                    </p>
                    <a href="landing.php" class="card-link">
                        Xem trang giới thiệu
                        <i class="fas fa-play"></i>
                    </a>
                </div>

                <div class="link-card funny" onclick="window.location.href='ngu1.php'">
                    <div class="card-title">
                        <span>🤔</span>
                        <span>Bạn quên mật khẩu?</span>
                    </div>
                    <a href="ngu1.php" class="card-link">
                        🔑 Vào đây để đổi mật khẩu
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
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

            // Create floating hearts
            for (let i = 0; i < 5; i++) {
                const heart = document.createElement('div');
                heart.className = 'floating-heart';
                heart.innerHTML = '❤️';
                heart.style.left = Math.random() * 100 + '%';
                heart.style.animationDelay = Math.random() * 15 + 's';
                heart.style.animationDuration = (Math.random() * 5 + 10) + 's';
                container.appendChild(heart);
            }

            // Create floating stars
            for (let i = 0; i < 8; i++) {
                const star = document.createElement('div');
                star.className = 'floating-star';
                star.innerHTML = '✨';
                star.style.left = Math.random() * 100 + '%';
                star.style.animationDelay = Math.random() * 12 + 's';
                star.style.animationDuration = (Math.random() * 4 + 8) + 's';
                container.appendChild(star);
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

        // Password validation
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const typingIndicator = document.getElementById('passwordTyping');
            
            if (password.length > 0) {
                typingIndicator.classList.add('show');
                setTimeout(() => typingIndicator.classList.remove('show'), 500);
            }

            if (password.length === 0) {
                this.classList.remove('valid', 'invalid');
                hideValidation(this);
            } else if (password.length >= 6) {
                this.classList.remove('invalid');
                this.classList.add('valid');
                showValidation(this, '✅ Mật khẩu hợp lệ!', 'success');
            } else {
                this.classList.remove('valid');
                this.classList.add('invalid');
                showValidation(this, '❌ Mật khẩu phải có ít nhất 6 ký tự!', 'error');
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
                // Easter egg activated!
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
            $('#loginForm').submit(function(e) {
                e.preventDefault();

                const email = $('#email').val().trim();
                const password = $('#password').val().trim();
                const submitBtn = $('#submitBtn');

                // Validate
                if (!email || !password) {
                    Swal.fire({
                        title: '❌ Thiếu thông tin!',
                        text: 'Vui lòng điền đầy đủ email và mật khẩu! 💭',
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
                    url: 'login.php',
                    data: { action: 'login_request', email: email, password: password },
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
                                timer: 2000,
                                showConfirmButton: false,
                                background: '#fff',
                                color: '#333'
                            }).then(() => {
                                window.location.href = response.redirect;
                            });
                        } else {
                            // Shake form on error
                            $('.login-card').addClass('shake-error');
                            setTimeout(() => $('.login-card').removeClass('shake-error'), 500);
                            
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
            const colors = ['#ff6b6b', '#4ecdc4', '#45b7d1', '#f9ca24', '#f0932b', '#eb4d4b', '#6c5ce7', '#a29bfe'];
            const confettiCount = 100;

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
            const emojis = ['🎉', '🎊', '✨', '🌟', '💫', '⭐', '🎈', '🎁', '👏', '👍', '🔥', '💯'];
            for (let i = 0; i < 30; i++) {
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
            
            const interactiveElements = document.querySelectorAll('button, a, label, select, .link-card');
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

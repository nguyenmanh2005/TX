<?php
/**
 * Advanced User Simulation Bot
 * Features: Auto-login, Daily tasks, Error reporting to Chat
 */

session_start();
set_time_limit(0);

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/1";
$cookieFile = __DIR__ . '/bot_session.txt';

// Helpers
function botRequest($url, $postData = null, $useCookies = true) {
    global $cookieFile;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) BotSimulator/1.0');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    if ($useCookies) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }

    if ($postData) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => $response,
        'error' => $error
    ];
}

function reportToChat($message) {
    global $baseUrl;
    botRequest($baseUrl . "/chat.php", ['message' => "🤖 [BOT]: " . $message]);
}

// Logic
$status = "";
$logs = [];

if (isset($_POST['run_bot'])) {
    $email = $_POST['email'];
    $pass = $_POST['password'];

    // 1. LOGIN
    $logs[] = " đang đăng nhập với $email...";
    $res = botRequest($baseUrl . "/login.php", ['email' => $email, 'password' => $pass]);
    $data = json_decode($res['body'], true);

    if ($res['code'] == 200 && isset($data['status']) && $data['status'] === 'success') {
        $logs[] = "✅ Đăng nhập thành công!";
        
        // 2. DAILY LOGIN
        $logs[] = " đang thực hiện điểm danh...";
        $res = botRequest($baseUrl . "/api_daily_login.php", ['action' => 'claim']); 
        $logs[] = "📡 API Daily: " . ($res['code'] == 200 ? "OK" : "Lỗi " . $res['code']);
        if ($res['code'] != 200) reportToChat("Lỗi Điểm danh tại api_daily_login.php (Mã: {$res['code']})");

        // 3. LUCKY WHEEL
        $logs[] = " đang thử vận may với Vòng quay...";
        $res = botRequest($baseUrl . "/api_lucky_wheel.php", ['action' => 'spin']);
        $wheelData = json_decode($res['body'], true);
        if (isset($wheelData['status']) && $wheelData['status'] == 'success') {
            $logs[] = "🎉 Vòng quay: " . $wheelData['message'];
        } else {
            $logs[] = "ℹ️ Vòng quay: " . ($wheelData['message'] ?? 'Đã quay hoặc lỗi');
        }

        // 4. RANDOM SURF (To test analytics)
        $logs[] = " đang dạo quanh website...";
        $randomPages = ['/index.php', '/profile.php', '/shop.php', '/leaderboard.php'];
        foreach($randomPages as $p) {
            botRequest($baseUrl . $p);
        }

        reportToChat("Đã hoàn thành chu trình giả lập cho tài khoản $email. Mọi thứ hoạt động tốt! ✅");
        $status = "success";
    } else {
        $errorMsg = $data['message'] ?? "Lỗi kết nối";
        $logs[] = "❌ Thất bại: $errorMsg";
        reportToChat("Cảnh báo! Bot không thể đăng nhập tài khoản $email. Lý do: $errorMsg");
        $status = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>User Simulation Bot</title>
    <style>
        body { background: #0e111a; color: #fff; font-family: 'Segoe UI', sans-serif; padding: 40px; }
        .card { background: #161b22; padding: 25px; border-radius: 12px; max-width: 500px; margin: auto; border: 1px solid #30363d; }
        input { width: 100%; padding: 12px; margin: 10px 0; border-radius: 6px; border: 1px solid #30363d; background: #0d1117; color: #fff; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #238636; border: none; color: #fff; border-radius: 6px; cursor: pointer; font-weight: bold; }
        .log-box { margin-top: 20px; background: #000; padding: 15px; border-radius: 8px; font-family: monospace; font-size: 13px; color: #34d399; }
    </style>
</head>
<body>
    <div class="card">
        <h2>🤖 User Simulator</h2>
        <p style="color: #8b949e; font-size: 14px;">Giả lập hành vi đăng nhập, điểm danh và quay thưởng.</p>
        
        <form method="POST">
            <label>Email tài khoản test:</label>
            <input type="email" name="email" value="manh@gmail.com" required>
            <label>Mật khẩu:</label>
            <input type="password" name="password" required>
            <button type="submit" name="run_bot">Bắt đầu giả lập</button>
        </form>

        <?php if (!empty($logs)): ?>
        <div class="log-box">
            <?php foreach($logs as $l) echo "> $l<br>"; ?>
        </div>
        <?php endif; ?>
    </div>
    <div style="text-align:center; margin-top:20px;">
        <a href="../admin_analytics.php" style="color:#58a6ff; text-decoration:none;">← Quay lại Dashboard</a>
    </div>
</body>
</html>

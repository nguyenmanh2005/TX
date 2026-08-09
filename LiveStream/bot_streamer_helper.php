<?php
/**
 * 🤖 Bot Streamer Database Helper v3.0
 * Quản lý 5 tài khoản Bot thực tế trong bảng `users` MySQL.
 * Tích hợp Trí Tuệ Nhân Tạo Tính Toán Mức Cược Thông Minh (Smart Bet Sizing).
 * Lấy Theme ThreeJS 3D của Bot (Mặc định nếu Bot chưa mua).
 */

function getOrCreateBotStreamerUser($conn, $botName, $defaultMoney = 88888000) {
    if (!$conn) return ['Iduser' => 0, 'Name' => $botName, 'Money' => $defaultMoney];

    $stmt = $conn->prepare("SELECT Iduser, Name, Money FROM users WHERE Name = ?");
    $stmt->bind_param("s", $botName);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($row = $res->fetch_assoc()) {
        $stmt->close();
        return [
            'Iduser' => (int)$row['Iduser'],
            'Name' => $row['Name'],
            'Money' => (float)$row['Money']
        ];
    }
    $stmt->close();

    // Tự động tạo user Bot thật trong bảng users với mật khẩu '123456'
    $email = strtolower($botName) . "@gtlm.live";
    $passHash = password_hash('123456', PASSWORD_DEFAULT);
    $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($botName) . "&background=random";

    $ins = $conn->prepare("INSERT INTO users (Name, Email, Pass, Money, ImageURL) VALUES (?, ?, ?, ?, ?)");
    $ins->bind_param("sssds", $botName, $email, $passHash, $defaultMoney, $avatarUrl);
    $ins->execute();
    $botId = (int)$ins->insert_id;
    $ins->close();

    return [
        'Iduser' => $botId,
        'Name' => $botName,
        'Money' => (float)$defaultMoney
    ];
}

/**
 * 🧠 Tính toán mức cược thông minh cho Bot dựa trên lịch sử thắng/thua và số dư tài khoản
 */
function calculateSmartBotBet($conn, $botId, $historyTable = 'history_baucua', $baseBet = 30000) {
    if (!$conn || !$botId) return $baseBet;

    $stmtMoney = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
    $stmtMoney->bind_param("i", $botId);
    $stmtMoney->execute();
    $resMoney = $stmtMoney->get_result()->fetch_assoc();
    $stmtMoney->close();
    $botMoney = (float)($resMoney['Money'] ?? 1000000);

    $winStreak = 0;
    $lossStreak = 0;

    // Truy vấn 5 ván gần nhất của Bot
    $query = "SELECT WinAmount FROM $historyTable WHERE Iduser = ? ORDER BY Time DESC LIMIT 5";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("i", $botId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (!empty($rows)) {
            foreach ($rows as $i => $r) {
                $win = (float)$r['WinAmount'] > 0;
                if ($i === 0) {
                    if ($win) $winStreak++; else $lossStreak++;
                } else {
                    if ($win && $lossStreak === 0) $winStreak++;
                    elseif (!$win && $winStreak === 0) $lossStreak++;
                    else break;
                }
            }
        }
    }

    // Chiến thuật tăng cược thông minh:
    // Nếu đang húp liên tiếp -> Tăng cược tự tin (x2, x3, x5)
    // Nếu vừa bay màu -> Dùng Martingale gấp đôi để gỡ lại
    $smartBet = $baseBet;
    if ($winStreak > 0) {
        $multiplier = min(10, pow(1.8, $winStreak));
        $smartBet = $baseBet * $multiplier;
    } elseif ($lossStreak > 0) {
        $multiplier = min(8, pow(2, $lossStreak));
        $smartBet = $baseBet * $multiplier;
    }

    // Giới hạn trong khoảng 10k - 5 triệu GTLM và không vượt quá 10% số dư bot
    $maxAllowed = max(10000, min(5000000, $botMoney * 0.1));
    $smartBet = min($smartBet, $maxAllowed);
    $smartBet = max(10000, round($smartBet / 10000) * 10000);

    return (int)$smartBet;
}

/**
 * 🎨 Lấy Theme ThreeJS 3D của Bot Streamer (Mặc định nếu Bot chưa mua Theme)
 */
function getBotStreamerTheme($conn, $botId) {
    $defaultGradient = ['#667eea', '#764ba2', '#4facfe'];
    $defaultTheme = [
        'bgGradient' => $defaultGradient,
        'bgGradientCSS' => 'linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%)',
        'particleCount' => 300,
        'particleColor' => '#ffffff',
        'particleOpacity' => 0.4,
        'shapeCount' => 10,
        'shapeColors' => ['#667eea', '#764ba2', '#4facfe', '#00f2fe'],
        'shapeOpacity' => 0.2
    ];

    if (!$conn || !$botId) return $defaultTheme;

    $stmt = $conn->prepare("SELECT t.* FROM users u JOIN themes t ON u.current_theme_id = t.id WHERE u.Iduser = ?");
    if ($stmt) {
        $stmt->bind_param("i", $botId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $grad = json_decode($row['background_gradient'] ?? '[]', true);
            if (is_array($grad) && count($grad) >= 2) {
                $defaultTheme['bgGradient'] = $grad;
                $defaultTheme['bgGradientCSS'] = 'linear-gradient(135deg, ' . $grad[0] . ' 0%, ' . $grad[1] . ' 50%, ' . ($grad[2] ?? $grad[1]) . ' 100%)';
            }
            $defaultTheme['particleCount'] = (int)($row['particle_count'] ?? 300);
            $defaultTheme['particleColor'] = $row['particle_color'] ?? '#ffffff';
            $shColors = json_decode($row['shape_colors'] ?? '[]', true);
            if (is_array($shColors) && !empty($shColors)) $defaultTheme['shapeColors'] = $shColors;
        }
        $stmt->close();
    }

    return $defaultTheme;
}
?>

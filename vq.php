<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/htdocs/a/php_errors.log');
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['Iduser'])) {
    error_log("Session Iduser not set. Redirecting to login.");
    header("Location: login.php");
    exit();
}

require 'db_connect.php';

// Load theme
require_once 'load_theme.php';

$soDu = 0;
$betAmounts = ["JoJo" => 0, "Dio" => 0];
$playerBets = ["JoJo" => 0, "Dio" => 0];
$nhanVat = ["JoJo", "Dio"];
$emoji = ["JoJo" => "🥸", "Dio" => "🕶️"];
$roundId = isset($_POST['round_id']) ? (int)$_POST['round_id'] : time();

$userId = $_SESSION['Iduser'];
$sql = "SELECT Money FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Lỗi server!"]);
    exit();
}
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $soDu = (float)$user['Money'];
} else {
    error_log("User not found: Iduser = $userId");
    session_destroy();
    header("Location: login.php");
    exit();
}
$stmt->close();

$stmt = $conn->prepare("SELECT chosen_character, SUM(amount) as total_amount FROM bets WHERE round_id = ? GROUP BY chosen_character");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Lỗi server!"]);
    exit();
}
$stmt->bind_param("i", $roundId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $betAmounts[$row['chosen_character']] = (float)($row['total_amount'] ?? 0);
}
$stmt->close();

$stmt = $conn->prepare("SELECT chosen_character, SUM(amount) as total_amount FROM bets WHERE round_id = ? AND user_id = ? GROUP BY chosen_character");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Lỗi server!"]);
    exit();
}
$stmt->bind_param("ii", $roundId, $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $playerBets[$row['chosen_character']] = (float)($row['total_amount'] ?? 0);
}
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'place_bet') {
    error_log("Place bet request: " . print_r($_POST, true));
    header('Content-Type: application/json');
    
    $chon = $_POST["chon"] ?? "";
    $cuoc = (float) str_replace(",", "", $_POST["cuoc"] ?? "0");
    $roundId = (int) ($_POST["round_id"] ?? 0);

    if (!in_array($chon, $nhanVat)) {
        error_log("Invalid character: $chon");
        echo json_encode(["success" => false, "message" => "❌ Chọn nhân vật hợp lệ!"]);
        exit();
    }
    if ($cuoc > $soDu || $cuoc <= 0) {
        error_log("Invalid bet amount: cuoc = $cuoc, soDu = $soDu");
        echo json_encode(["success" => false, "message" => "⚠️ Số tiền cược không hợp lệ!"]);
        exit();
    }
    if ($roundId <= 0) {
        error_log("Invalid round_id: $roundId");
        echo json_encode(["success" => false, "message" => "❌ Mã vòng không hợp lệ!"]);
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO bets (user_id, round_id, chosen_character, amount) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi server!"]);
        exit();
    }
    $stmt->bind_param("iisd", $userId, $roundId, $chon, $cuoc);
    if ($stmt->execute()) {
        $soDu -= $cuoc;
        $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
        if (!$capNhat) {
            error_log("Prepare failed: " . $conn->error);
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Lỗi server!"]);
            exit();
        }
        $capNhat->bind_param("di", $soDu, $userId);
        $capNhat->execute();
        $capNhat->close();

        $stmt = $conn->prepare("SELECT chosen_character, SUM(amount) as total_amount FROM bets WHERE round_id = ? GROUP BY chosen_character");
        $stmt->bind_param("i", $roundId);
        $stmt->execute();
        $result = $stmt->get_result();
        $betAmounts = ["JoJo" => 0, "Dio" => 0];
        while ($row = $result->fetch_assoc()) {
            $betAmounts[$row['chosen_character']] = (float)($row['total_amount'] ?? 0);
        }
        $stmt->close();

        $stmt = $conn->prepare("SELECT chosen_character, SUM(amount) as total_amount FROM bets WHERE round_id = ? AND user_id = ? GROUP BY chosen_character");
        $stmt->bind_param("ii", $roundId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $playerBets = ["JoJo" => 0, "Dio" => 0];
        while ($row = $result->fetch_assoc()) {
            $playerBets[$row['chosen_character']] = (float)($row['total_amount'] ?? 0);
        }
        $stmt->close();

        $response = [
            "success" => true,
            "message" => "✅ Đã đặt cược!",
            "betAmounts" => $betAmounts,
            "playerBets" => $playerBets,
            "playerPoints" => $soDu
        ];
        echo json_encode($response);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON encode error: " . json_last_error_msg());
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Lỗi server!"]);
        }
    } else {
        error_log("Bet insertion failed: " . $stmt->error);
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "❌ Lỗi khi đặt cược!"]);
    }
    $stmt->close();
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'cancel_bet') {
    error_log("Cancel bet request: " . print_r($_POST, true));
    header('Content-Type: application/json');
    
    $roundId = (int) ($_POST["round_id"] ?? 0);
    if ($roundId <= 0) {
        error_log("Invalid round_id: $roundId");
        echo json_encode(["success" => false, "message" => "❌ Mã vòng không hợp lệ!"]);
        exit();
    }

    $stmt = $conn->prepare("SELECT SUM(amount) as total_bet FROM bets WHERE round_id = ? AND user_id = ?");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi server!"]);
        exit();
    }
    $stmt->bind_param("ii", $roundId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $totalBet = (float)($result->fetch_assoc()['total_bet'] ?? 0);
    $stmt->close();

    $soDu += $totalBet;
    $capNhat = $conn->prepare("UPDATE users SET Money = ? WHERE Iduser = ?");
    if (!$capNhat) {
        error_log("Prepare failed: " . $conn->error);
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi server!"]);
        exit();
    }
    $capNhat->bind_param("di", $soDu, $userId);
    $capNhat->execute();
    $capNhat->close();

    $stmt = $conn->prepare("DELETE FROM bets WHERE round_id = ? AND user_id = ?");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi server!"]);
        exit();
    }
    $stmt->bind_param("ii", $roundId, $userId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("SELECT chosen_character, SUM(amount) as total_amount FROM bets WHERE round_id = ? GROUP BY chosen_character");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi server!"]);
        exit();
    }
    $stmt->bind_param("i", $roundId);
    $stmt->execute();
    $result = $stmt->get_result();
    $betAmounts = ["JoJo" => 0, "Dio" => 0];
    while ($row = $result->fetch_assoc()) {
        $betAmounts[$row['chosen_character']] = (float)($row['total_amount'] ?? 0);
    }
    $stmt->close();

    $playerBets = ["JoJo" => 0, "Dio" => 0];
    $response = [
        "success" => true,
        "message" => "✅ Đã hủy cược!",
        "betAmounts" => $betAmounts,
        "playerBets" => $playerBets,
        "playerPoints" => $soDu
    ];
    echo json_encode($response);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON encode error: " . json_last_error_msg());
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi server!"]);
    }
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'get_result') {
    error_log("Get result request: " . print_r($_POST, true));
    header('Content-Type: application/json');
    
    $roundId = (int) ($_POST["round_id"] ?? 0);
    if ($roundId <= 0) {
        error_log("Invalid round_id: $roundId");
        echo json_encode(["success" => false, "message" => "❌ Mã vòng không hợp lệ!"]);
        exit();
    }

    $ketQuaNhanVat = rand(0, 1) ? "JoJo" : "Dio";

    $stmt = $conn->prepare("INSERT INTO rounds (round_id, winner) VALUES (?, ?)");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi server!"]);
        exit();
    }
    $stmt->bind_param("is", $roundId, $ketQuaNhanVat);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("SELECT user_id, chosen_character, amount FROM bets WHERE round_id = ?");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi server!"]);
        exit();
    }
    $stmt->bind_param("i", $roundId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($bet = $result->fetch_assoc()) {
        $betUserId = $bet['user_id'];
        $chon = $bet['chosen_character'];
        $cuoc = (float)$bet['amount'];

        if ($chon === $ketQuaNhanVat) {
            $thang = $cuoc * 2;
            $capNhat = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
            if (!$capNhat) {
                error_log("Prepare failed: " . $conn->error);
                http_response_code(500);
                echo json_encode(["success" => false, "message" => "Lỗi server!"]);
                exit();
            }
            $capNhat->bind_param("di", $thang, $betUserId);
            $capNhat->execute();
            $capNhat->close();
            
            // Track quest progress và tự động cập nhật streak, VIP, reward points, social feed
            require_once 'game_history_helper.php';
            logGameHistoryWithAll($conn, $betUserId, 'Vòng Quay', $cuoc, $thang, true);
        } else {
            // Track quest progress (thua) và tự động cập nhật streak, VIP, reward points
            require_once 'game_history_helper.php';
            logGameHistoryWithAll($conn, $betUserId, 'Vòng Quay', $cuoc, 0, false);
        }
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT Money FROM users WHERE Iduser = ?");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi server!"]);
        exit();
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $soDu = (float)$result->fetch_assoc()['Money'] ?? 0;
    $stmt->close();

    $response = ["success" => true, "winner" => $ketQuaNhanVat, "emoji" => $emoji[$ketQuaNhanVat], "playerPoints" => $soDu];
    echo json_encode($response);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON encode error: " . json_last_error_msg());
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Lỗi server!"]);
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt Cược Online: JoJo vs Dio</title>
    <link rel="icon" type="image/png" href="/a/image-Photoroom.png">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700;900&display=swap" rel="stylesheet">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
        <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
        <link rel="stylesheet" href="assets/css/game-effects.css">
        <link rel="stylesheet" href="assets/css/game-ui-enhancements.css">
        <link rel="stylesheet" href="assets/css/game-effects.css">
        <link rel="stylesheet" href="assets/css/game-ui-enhancements.css">
    <style>
        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            font-family: 'Roboto', Arial, sans-serif;
            font-weight: 700;
            color: #ffd700;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            overflow: auto;
            text-rendering: optimizeLegibility;
            -webkit-font-smoothing: antialiased;
            position: relative;
        }
        
        /* Three.js canvas background */
        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }
        
        .game-container {
            position: relative;
            z-index: 1;
        }
        
        * {
            cursor: inherit;
        }

        button, a, input[type="button"], input[type="submit"], label, select, input[type="number"] {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        .game-container {
            background: linear-gradient(135deg, rgba(74, 44, 14, 0.98) 0%, rgba(45, 27, 7, 0.98) 100%);
            border: 5px solid #ffd700;
            border-radius: var(--border-radius-lg);
            padding: 30px 50px;
            text-align: center;
            width: 800px;
            max-width: 95%;
            box-shadow: 0 0 30px rgba(255, 215, 0, 0.6);
            position: relative;
            font-size: 16px;
            margin: 20px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            z-index: 1;
        }

        .game-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 0 50px rgba(255, 215, 0, 0.9);
        }

        .header {
            font-size: 45px;
            color: #ffd700;
            text-shadow: 2px 2px 4px #000;
            margin-bottom: 10px;
            background: url('https://via.placeholder.com/200x50/ffd700/000?text=JoJo+vs+Dio') no-repeat center;
            background-size: contain;
            height: 50px;
            line-height: 50px;
            font-weight: 900;
        }

        .stats {
            display: flex;
            justify-content: center;
            font-size: 18px;
            margin-bottom: 10px;
            padding: 5px;
            background: rgba(255, 215, 0, 0.2);
            border-radius: 10px;
        }

        .timer {
            font-size: 22px;
            margin-top: 5px;
            margin-bottom: 15px;
            color: #ffeb3b;
            text-shadow: 1px 1px 3px #000;
        }

        .fight-scene {
            position: relative;
            width: 300px;
            height: 300px;
            margin: 0 auto 20px;
            border: 3px solid #ffd700;
            border-radius: 50%;
            background: rgba(255, 215, 0, 0.1);
            overflow: hidden;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
        }

        .character {
            width: 120px;
            text-align: center;
            transition: filter 0.5s ease, opacity 0.5s ease, border 0.5s ease;
        }

        .character img {
            width: 100%;
            transition: opacity 0.5s ease;
        }

        .character.loser {
            filter: brightness(0.3);
            opacity: 0.5;
            border: none;
        }

        .character.winner {
            filter: brightness(1.3);
            opacity: 1;
            border: 3px solid #ff0000;
            border-radius: 5px;
            box-shadow: 0 0 25px rgba(255, 0, 0, 0.7);
        }

        .health-bar-container {
            width: 100px;
            height: 15px;
            background: #ccc;
            border: 2px solid #000;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 5px;
        }

        .health-bar {
            height: 100%;
            background: #4caf50;
            transition: width 0.5s ease;
        }

        .icon {
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 24px;
            display: none;
        }

        .fight-scene.fight-animation .jojo {
            animation: jojo-attack 2s ease-in-out infinite;
        }

        .fight-scene.fight-animation .dio {
            animation: dio-attack 2s ease-in-out infinite;
        }

        @keyframes jojo-attack {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(25px); }
        }

        @keyframes dio-attack {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(-25px); }
        }
        
        .character.winner {
            animation: winnerGlow 2s ease-in-out infinite;
        }
        
        @keyframes winnerGlow {
            0%, 100% {
                filter: brightness(1.3);
                box-shadow: 0 0 25px rgba(255, 0, 0, 0.7);
            }
            50% {
                filter: brightness(1.5);
                box-shadow: 0 0 40px rgba(255, 0, 0, 1);
            }
        }

        .betting-area {
            margin-top: 10px;
            display: flex;
            justify-content: space-around;
            align-items: center;
            background: rgba(255, 215, 0, 0.3);
            padding: 10px;
            border-radius: 10px;
        }

        .bet-option-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .bet-option {
            background: transparent;
            border: 2px solid #ffd700;
            border-radius: 5px;
            cursor: pointer;
            padding: 5px;
            transition: border-color 0.3s ease, transform 0.3s ease;
        }

        .bet-option img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
        }

        .bet-option:hover:not(:disabled) {
            border-color: #ffa500;
            transform: scale(1.1) translateY(-3px);
            box-shadow: 0 0 20px rgba(255, 165, 0, 0.7);
        }

        .bet-option:active:not(:disabled) {
            background: rgba(255, 165, 0, 0.3);
            transform: scale(1.05) translateY(-1px);
        }

        .bet-option:disabled {
            border-color: #8b7d6b;
            cursor: not-allowed !important;
            opacity: 0.6;
        }

        .bet-option:disabled img {
            opacity: 0.5;
        }

        .bet-amount-display {
            font-size: 18px;
            color: #ffeb3b;
            text-shadow: 1px 1px 3px #000;
            font-weight: 700;
        }

        .bet-amount {
            margin-top: 15px;
            display: flex;
            justify-content: center;
            gap: 5px;
            flex-wrap: wrap;
        }

        .custom-bet-input {
            padding: 12px 18px;
            font-size: 16px;
            border: 2px solid #ffd700;
            border-radius: var(--border-radius);
            background: rgba(255, 255, 255, 0.95);
            color: #2d1b07;
            width: 150px;
            text-align: center;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        
        .custom-bet-input:focus {
            outline: none;
            border-color: #ffa500;
            box-shadow: 0 0 0 4px rgba(255, 165, 0, 0.2);
        }

        .result {
            font-size: 20px;
            margin-top: 10px;
            color: #ffeb3b;
            text-shadow: 1px 1px 3px #000;
            position: relative;
            z-index: 1001;
            font-weight: bold;
        }

        .result.popup {
            position: absolute;
            top: -81px;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgb(19, 23, 32);
            padding: 8px 20px;
            border-radius: 10px;
            border: 2px solid #ffd700;
            font-size: 24px;
            font-weight: bold;
            color: #ffeb3b;
            text-shadow: 2px 2px 4px #000;
            animation: pulse 1.5s infinite;
            z-index: 1001;
        }

        @keyframes pulse {
            0% { transform: translateX(-50%) scale(1); }
            50% { transform: translateX(-50%) scale(1.1); }
            100% { transform: translateX(-50%) scale(1); }
        }

        .action-buttons {
            margin-top: 15px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .action-btn {
            background-color: #ffd700;
            color: #2d1b07;
            padding: 12px 24px;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            border: 2px solid #2d1b07;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.3);
        }
        
        .action-btn:hover:not(:disabled) {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 6px 20px rgba(255, 215, 0, 0.5);
            background-color: #ffed4e;
        }
        
        .action-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed !important;
        }

        .bet-labels {
            position: relative;
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .bet-label {
            font-size: 40px;
            font-weight: bold;
            color: #ffd700;
            text-shadow: 2px 2px 4px #000;
        }

        .bet-label.jojo {
            position: absolute;
            right: 75px;
            top: 65px;
            text-align: center;
        }

        .bet-label.dio {
            position: absolute;
            left: 75px;
            top: 65px;
            text-align: center;
        }

        .bet-label.jojo.winner {
            text-shadow: 0 0 10px #ff0000, 0 0 20px #ff0000, 0 0 30px #ff0000;
        }

        .bet-label.dio.winner {
            text-shadow: 0 0 10px #ff0000, 0 0 20px #ff0000, 0 0 30px #ff0000;
        }

        .bet-label.jojo.loser {
            text-shadow: 0 0 10px #000000, 0 0 20px #000, 0 0 30px #000;
        }

        .bet-label.dio.loser {
            text-shadow: 0 0 10px #000, 0 0 20px #000, 0 0 30px #000;
        }

        .bet-total {
            font-size: 18px;
            margin-top: 5px;
            color: #ffeb3b;
            text-shadow: 1px 1px 3px #000;
            font-weight: bold;
        }

        .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 20px;
            color: #ffd700;
            cursor: pointer;
            z-index: 4000;
            transition: color 0.3s;
        }

        .close-btn:hover {
            color: #ffa500;
        }

        canvas {
            position: fixed;
            pointer-events: none;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 999;
        }

        @media (max-width: 600px) {
            .game-container { padding: 15px; width: 90%; }
            .fight-scene { width: 200px; height: 200px; }
            .character { width: 80px; }
            .health-bar-container { width: 70px; }
            .bet-label { font-size: 30px; }
            .bet-label.jojo { right: 50px; top: 50px; }
            .bet-label.dio { left: 50px; top: 50px; }
            .custom-bet-input { width: 120px; }
            .bet-option img { width: 60px; height: 60px; }
        }
    </style>
</head>
<body>
    <canvas id="threejs-background"></canvas>
    <div class="game-container">
        <div class="header">JOJO VS DIO</div>
        <div class="stats">
            <div>💰 Tiền của bạn: <span id="player-points" style="color: #ffeb3b; font-weight: 700;"><?= number_format($soDu, 0, ',', '.') ?> VNĐ</span></div>
        </div>
        <div class="timer" id="timer">Thời gian đặt cược: 30</div>
        <div class="bet-labels">
            <div class="bet-label dio" id="dio-label">DIO<div class="bet-total" id="dio-bet"><?php echo number_format($betAmounts['Dio']); ?> VNĐ</div></div>
            <div class="fight-scene" id="fight-scene">
                <div class="character dio">
                    <div class="health-bar-container">
                        <div class="health-bar" id="dio-health" style="width: 100%"></div>
                    </div>
                    <span class="icon" id="dio-icon">🕶️</span>
                    <img src="dio.gif" alt="Dio">
                </div>
                <div class="character jojo">
                    <div class="health-bar-container">
                        <div class="health-bar" id="jojo-health" style="width: 100%"></div>
                    </div>
                    <span class="icon" id="jojo-icon">🥸</span>
                    <img src="jotaro.gif" alt="JoJo">
                </div>
            </div>
            <div class="bet-label jojo" id="jojo-label">JOJO<div class="bet-total" id="jojo-bet"><?php echo number_format($betAmounts['JoJo']); ?> VNĐ</div></div>
        </div>
        <div class="betting-area">
            <div class="bet-option-container">
                <button class="bet-option" id="dio-btn"><img src="dio.gif" alt="Bet on Dio"></button>
                <span class="bet-amount-display" id="dio-bet-amount"><?php echo number_format($playerBets['Dio']); ?> VNĐ</span>
            </div>
            <div class="bet-option-container">
                <button class="bet-option" id="jojo-btn"><img src="jotaro.gif" alt="Bet on JoJo"></button>
                <span class="bet-amount-display" id="jojo-bet-amount"><?php echo number_format($playerBets['JoJo']); ?> VNĐ</span>
            </div>
        </div>
        <div class="bet-amount">
            <input type="number" id="custom-bet-amount" class="custom-bet-input" placeholder="Nhập số tiền cược" min="1" max="<?php echo $soDu; ?>" step="1">
        </div>
        <div class="result" id="result"></div>
        <div class="action-buttons">
            <button class="action-btn" id="all-in">ALL-IN</button>
            <button class="action-btn" id="place-bet">ĐẶT CƯỢC</button>
            <button class="action-btn" id="cancel">HỦY</button>
        </div>
        <ion-icon name="close-outline" class="close-btn" id="close-btn"></ion-icon>
    </div>

    <script>
        const jojoBtn = document.getElementById('jojo-btn');
        const dioBtn = document.getElementById('dio-btn');
        const timerDisplay = document.getElementById('timer');
        const resultDisplay = document.getElementById('result');
        const playerPointsDisplay = document.getElementById('player-points');
        const jojoBetDisplay = document.getElementById('jojo-bet');
        const dioBetDisplay = document.getElementById('dio-bet');
        const jojoBetAmountDisplay = document.getElementById('jojo-bet-amount');
        const dioBetAmountDisplay = document.getElementById('dio-bet-amount');
        const jojoLabel = document.getElementById('jojo-label');
        const dioLabel = document.getElementById('dio-label');
        const allInBtn = document.getElementById('all-in');
        const placeBetBtn = document.getElementById('place-bet');
        const cancelBtn = document.getElementById('cancel');
        const customBetAmountInput = document.getElementById('custom-bet-amount');
        const fightScene = document.getElementById('fight-scene');
        const jojoHealth = document.getElementById('jojo-health');
        const dioHealth = document.getElementById('dio-health');
        const jojoIcon = document.getElementById('jojo-icon');
        const dioIcon = document.getElementById('dio-icon');
        const closeBtn = document.getElementById('close-btn');

        let playerPoints = <?php echo $soDu; ?>;
        let selectedBetAmount = 0;
        let selectedOption = null;
        let timeLeft = 30;
        let phase = 'betting';
        let canBet = true;
        let roundId = <?php echo $roundId; ?>;
        let jojoTotalBet = <?php echo $betAmounts['JoJo']; ?>;
        let dioTotalBet = <?php echo $betAmounts['Dio']; ?>;
        let playerJojoBet = <?php echo $playerBets['JoJo']; ?>;
        let playerDioBet = <?php echo $playerBets['Dio']; ?>;
        let jojoHP = 100;
        let dioHP = 100;

        function formatCurrency(amount) {
            return amount.toLocaleString('en-US');
        }

        function clearBetSelection() {
            selectedBetAmount = 0;
            selectedOption = null;
            jojoBtn.style.backgroundColor = 'transparent';
            dioBtn.style.backgroundColor = 'transparent';
            customBetAmountInput.value = '';
        }

        customBetAmountInput.addEventListener('input', () => {
            selectedBetAmount = parseFloat(customBetAmountInput.value) || 0;
            if (selectedBetAmount > playerPoints) {
                customBetAmountInput.value = playerPoints;
                selectedBetAmount = playerPoints;
            }
        });

        jojoBtn.addEventListener('click', () => {
            if (canBet && playerPoints > 0) {
                selectedOption = 'JoJo';
                jojoBtn.style.backgroundColor = '#ffa500';
                dioBtn.style.backgroundColor = 'transparent';
            }
        });

        dioBtn.addEventListener('click', () => {
            if (canBet && playerPoints > 0) {
                selectedOption = 'Dio';
                dioBtn.style.backgroundColor = '#ffa500';
                jojoBtn.style.backgroundColor = 'transparent';
            }
        });

        async function fetchWithErrorHandling(url, options) {
            try {
                const response = await fetch(url, options);
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return await response.json();
            } catch (error) {
                console.error('Fetch error:', error);
                resultDisplay.textContent = '❌ Lỗi kết nối! Vui lòng thử lại.';
                return { success: false, message: error.message };
            }
        }

        allInBtn.addEventListener('click', async () => {
            if (!canBet || !selectedOption || playerPoints <= 0) {
                resultDisplay.textContent = !selectedOption ? 'Vui lòng chọn nhân vật!' : '❌ Hết tiền!';
                return;
            }
            selectedBetAmount = playerPoints;
            customBetAmountInput.value = playerPoints;

            const data = await fetchWithErrorHandling('/a/vq.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=place_bet&chon=${encodeURIComponent(selectedOption)}&cuoc=${selectedBetAmount}&round_id=${roundId}`
            });

            if (data.success) {
                playerPoints = data.playerPoints;
                playerPointsDisplay.textContent = `${formatCurrency(playerPoints)} VNĐ`;
                jojoBetDisplay.textContent = `${formatCurrency(data.betAmounts.JoJo)} VNĐ`;
                dioBetDisplay.textContent = `${formatCurrency(data.betAmounts.Dio)} VNĐ`;
                jojoBetAmountDisplay.textContent = `${formatCurrency(data.playerBets.JoJo)} VNĐ`;
                dioBetAmountDisplay.textContent = `${formatCurrency(data.playerBets.Dio)} VNĐ`;
                jojoTotalBet = data.betAmounts.JoJo;
                dioTotalBet = data.betAmounts.Dio;
                playerJojoBet = data.playerBets.JoJo;
                playerDioBet = data.playerBets.Dio;
                clearBetSelection();
                resultDisplay.textContent = data.message;
            } else {
                resultDisplay.textContent = data.message;
            }
        });

        placeBetBtn.addEventListener('click', async () => {
            if (!canBet || !selectedOption || selectedBetAmount <= 0 || playerPoints < selectedBetAmount) {
                resultDisplay.textContent = !selectedOption ? 'Vui lòng chọn nhân vật!' : '❌ Số tiền cược không hợp lệ!';
                return;
            }

            const data = await fetchWithErrorHandling('/a/vq.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=place_bet&chon=${encodeURIComponent(selectedOption)}&cuoc=${selectedBetAmount}&round_id=${roundId}`
            });

            if (data.success) {
                playerPoints = data.playerPoints;
                playerPointsDisplay.textContent = `${formatCurrency(playerPoints)} VNĐ`;
                jojoBetDisplay.textContent = `${formatCurrency(data.betAmounts.JoJo)} VNĐ`;
                dioBetDisplay.textContent = `${formatCurrency(data.betAmounts.Dio)} VNĐ`;
                jojoBetAmountDisplay.textContent = `${formatCurrency(data.playerBets.JoJo)} VNĐ`;
                dioBetAmountDisplay.textContent = `${formatCurrency(data.playerBets.Dio)} VNĐ`;
                jojoTotalBet = data.betAmounts.JoJo;
                dioTotalBet = data.betAmounts.Dio;
                playerJojoBet = data.playerBets.JoJo;
                playerDioBet = data.playerBets.Dio;
                clearBetSelection();
                resultDisplay.textContent = data.message;
            } else {
                resultDisplay.textContent = data.message;
            }
        });

        cancelBtn.addEventListener('click', async () => {
            if (!canBet) return;

            const data = await fetchWithErrorHandling('/a/vq.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=cancel_bet&round_id=${roundId}`
            });

            if (data.success) {
                playerPoints = data.playerPoints;
                playerPointsDisplay.textContent = `${formatCurrency(playerPoints)} VNĐ`;
                jojoBetDisplay.textContent = `${formatCurrency(data.betAmounts.JoJo)} VNĐ`;
                dioBetDisplay.textContent = `${formatCurrency(data.betAmounts.Dio)} VNĐ`;
                jojoBetAmountDisplay.textContent = `${formatCurrency(data.playerBets.JoJo)} VNĐ`;
                dioBetAmountDisplay.textContent = `${formatCurrency(data.playerBets.Dio)} VNĐ`;
                jojoTotalBet = data.betAmounts.JoJo;
                dioTotalBet = data.betAmounts.Dio;
                playerJojoBet = data.playerBets.JoJo;
                playerDioBet = data.playerBets.Dio;
                clearBetSelection();
                jojoLabel.classList.remove('winner', 'loser');
                dioLabel.classList.remove('winner', 'loser');
                resultDisplay.textContent = data.message;
            } else {
                resultDisplay.textContent = data.message;
            }
        });

        closeBtn.addEventListener('click', () => {
            window.location.href = 'index.php';
        });

        function simulateOtherBets() {
            if (phase !== 'betting') return;
            const betOptions = [1000, 10000, 50000, 100000, 500000, 5000000, 10000000, 50000000];
            const randomBet = betOptions[Math.floor(Math.random() * betOptions.length)];
            const character = Math.random() < 0.5 ? 'JoJo' : 'Dio';

            jojoTotalBet += character === 'JoJo' ? randomBet : 0;
            dioTotalBet += character === 'Dio' ? randomBet : 0;
            jojoBetDisplay.textContent = `${formatCurrency(jojoTotalBet)} VNĐ`;
            dioBetDisplay.textContent = `${formatCurrency(dioTotalBet)} VNĐ`;
        }

        function scheduleNextBet() {
            if (phase === 'betting') {
                const delay = Math.random() * (1500 - 500) + 500;
                setTimeout(() => {
                    simulateOtherBets();
                    scheduleNextBet();
                }, delay);
            }
        }

        function gameLoop() {
            timeLeft--;

            if (phase === 'betting') {
                timerDisplay.textContent = `Thời gian đặt cược: ${timeLeft}`;
                animateFight();
                if (timeLeft <= 0) {
                    phase = 'prepare';
                    timeLeft = 5;
                    canBet = false;
                    jojoBtn.disabled = true;
                    dioBtn.disabled = true;
                    allInBtn.disabled = true;
                    placeBetBtn.disabled = true;
                    cancelBtn.disabled = true;
                    resetFight();
                }
            } else if (phase === 'prepare') {
                timerDisplay.textContent = `Chuẩn bị chiến đấu: ${timeLeft}`;
                if (timeLeft <= 0) {
                    phase = 'betting';
                    timeLeft = 30;
                    canBet = true;
                    jojoBtn.disabled = false;
                    dioBtn.disabled = false;
                    allInBtn.disabled = false;
                    placeBetBtn.disabled = false;
                    cancelBtn.disabled = false;
                    jojoTotalBet = 0;
                    dioTotalBet = 0;
                    playerJojoBet = 0;
                    playerDioBet = 0;
                    jojoBetDisplay.textContent = `${formatCurrency(0)} VNĐ`;
                    dioBetDisplay.textContent = `${formatCurrency(0)} VNĐ`;
                    jojoBetAmountDisplay.textContent = `${formatCurrency(0)} VNĐ`;
                    dioBetAmountDisplay.textContent = `${formatCurrency(0)} VNĐ`;
                    resetFight();
                    clearBetSelection();
                    jojoBtn.style.backgroundColor = 'transparent';
                    dioBtn.style.backgroundColor = 'transparent';
                    jojoLabel.classList.remove('winner', 'loser');
                    dioLabel.classList.remove('winner', 'loser');
                    roundId = Date.now();
                    scheduleNextBet();
                    revealResult();
                }
            }

            setTimeout(gameLoop, 1000);
        }

        function animateFight() {
            if (phase !== 'betting') return;
            jojoHP = Math.max(0, jojoHP - (Math.random() * 5));
            dioHP = Math.max(0, dioHP - (Math.random() * 5));
            jojoHealth.style.width = `${jojoHP}%`;
            dioHealth.style.width = `${dioHP}%`;
        }

        function resetFight() {
            jojoHP = 100;
            dioHP = 100;
            jojoHealth.style.width = '100%';
            dioHealth.style.width = '100%';
            fightScene.classList.remove('fight-animation');
            jojoIcon.style.display = 'none';
            dioIcon.style.display = 'none';
            fightScene.querySelector('.jojo').classList.remove('loser', 'winner');
            fightScene.querySelector('.dio').classList.remove('loser', 'winner');
            resultDisplay.textContent = '';
            resultDisplay.classList.remove('popup');
        }

        async function revealResult() {
            const data = await fetchWithErrorHandling('/a/vq.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_result&round_id=${roundId}`
            });

            if (data.success) {
                const winner = data.winner;
                fightScene.classList.add('fight-animation');
                let fightDuration = 5000;
                let fightInterval = setInterval(() => {
                    fightDuration -= 200;
                    if (fightDuration <= 2000) {
                        if (winner === 'JoJo') {
                            dioHP = Math.max(0, dioHP - (Math.random() * 15 + 10));
                            jojoHP = Math.max(10, jojoHP - (Math.random() * 5));
                        } else {
                            jojoHP = Math.max(0, jojoHP - (Math.random() * 15 + 10));
                            dioHP = Math.max(10, dioHP - (Math.random() * 5));
                        }
                    } else {
                        jojoHP = Math.max(0, jojoHP - (Math.random() * 10));
                        dioHP = Math.max(0, dioHP - (Math.random() * 10));
                    }
                    jojoHealth.style.width = `${jojoHP}%`;
                    dioHealth.style.width = `${dioHP}%`;

                    if ((jojoHP <= 0 && winner === 'Dio') || (dioHP <= 0 && winner === 'JoJo') || fightDuration <= 0) {
                        clearInterval(fightInterval);
                        fightScene.classList.remove('fight-animation');
                        if (winner === 'JoJo') {
                            jojoHP = Math.random() * 10 + 10;
                            dioHP = 0;
                            resultDisplay.textContent = `Kết quả: JoJo thắng!`;
                            jojoIcon.style.display = 'block';
                            dioIcon.style.display = 'none';
                            fightScene.querySelector('.jojo').classList.add('winner');
                            fightScene.querySelector('.dio').classList.add('loser');
                            jojoLabel.classList.add('winner');
                            dioLabel.classList.add('loser');
                        } else {
                            dioHP = Math.random() * 10 + 10;
                            jojoHP = 0;
                            resultDisplay.textContent = `Kết quả: Dio thắng!`;
                            dioIcon.style.display = 'block';
                            jojoIcon.style.display = 'none';
                            fightScene.querySelector('.dio').classList.add('winner');
                            fightScene.querySelector('.jojo').classList.add('loser');
                            dioLabel.classList.add('winner');
                            jojoLabel.classList.add('loser');
                        }
                        jojoHealth.style.width = `${jojoHP}%`;
                        dioHealth.style.width = `${dioHP}%`;
                        resultDisplay.classList.add('popup');
                        playerPoints = data.playerPoints;
                        playerPointsDisplay.textContent = `${formatCurrency(playerPoints)} VNĐ`;
                        customBetAmountInput.max = playerPoints;
                        showFireworks();
                    }
                }, 200);
            } else {
                resultDisplay.textContent = data.message;
            }
        }

        function showFireworks() {
            const canvas = document.createElement('canvas');
            canvas.id = 'fireworks-canvas';
            document.body.appendChild(canvas);
            const ctx = canvas.getContext('2d');
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            let particles = [];

            function createFirework() {
                const x = Math.random() * canvas.width;
                const y = Math.random() * canvas.height / 2;
                const colors = ['#ffd700', '#ff6b6b', '#4ecdc4', '#45b7d1', '#ffa500'];
                const particleCount = 40; // Giảm số lượng để tối ưu hiệu năng
                for (let i = 0; i < particleCount; i++) {
                    const angle = (Math.PI * 2 * i) / particleCount;
                    const speed = 2 + Math.random() * 2;
                    particles.push({
                        x: x,
                        y: y,
                        dx: Math.cos(angle) * speed,
                        dy: Math.sin(angle) * speed,
                        life: 60,
                        maxLife: 60,
                        color: colors[Math.floor(Math.random() * colors.length)]
                    });
                }
            }

            function update() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                for (let i = particles.length - 1; i >= 0; i--) {
                    const p = particles[i];
                    p.x += p.dx;
                    p.y += p.dy;
                    p.dy += 0.1; // Gravity
                    p.life--;
                    const alpha = p.life / p.maxLife;
                    ctx.globalAlpha = alpha;
                    ctx.fillStyle = p.color;
                    ctx.fillRect(p.x, p.y, 3, 3);
                    if (p.life <= 0) particles.splice(i, 1);
                }
                ctx.globalAlpha = 1;
            }

            // Tạo 2 pháo hoa
            for (let i = 0; i < 2; i++) {
                setTimeout(() => createFirework(), i * 500);
            }
            const updateInterval = setInterval(update, 50);
            setTimeout(() => {
                clearInterval(updateInterval);
                canvas.remove();
            }, 3000);
        }

        // Đảm bảo cursor luôn hoạt động
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.cursor = "url('chuot.png'), url('../chuot.png'), auto";
            
            const interactiveElements = document.querySelectorAll('button, a, input, label, select');
            interactiveElements.forEach(el => {
                el.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
            });
        });
        
        playerPointsDisplay.textContent = `${formatCurrency(playerPoints)} VNĐ`;
        jojoBetAmountDisplay.textContent = `${formatCurrency(playerJojoBet)} VNĐ`;
        dioBetAmountDisplay.textContent = `${formatCurrency(playerDioBet)} VNĐ`;
        jojoBetDisplay.textContent = `${formatCurrency(jojoTotalBet)} VNĐ`;
        dioBetDisplay.textContent = `${formatCurrency(dioTotalBet)} VNĐ`;
        gameLoop();
        scheduleNextBet();
    </script>
    
    <script>
        // Initialize Three.js Background
        (function() {
            window.themeConfig = {
                particleCount: <?= $particleCount ?>,
                particleSize: <?= $particleSize ?>,
                particleColor: '<?= $particleColor ?>',
                particleOpacity: <?= $particleOpacity ?>,
                shapeCount: <?= $shapeCount ?>,
                shapeColors: <?= json_encode($shapeColors) ?>,
                shapeOpacity: <?= $shapeOpacity ?>,
                bgGradient: <?= json_encode($bgGradient) ?>
            };
            const script = document.createElement('script');
            script.src = 'threejs-background.js';
            script.onload = function() { console.log('Three.js background loaded'); };
            document.head.appendChild(script);
        })();
    </script>

    <script src="assets/js/game-effects.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script src="assets/js/game-effects-auto.js"></script>

        <script src="assets/js/game-enhancements.js"></script>
<script>
    // Auto initialize game effects
    if (typeof GameEffectsAuto !== 'undefined') {
        GameEffectsAuto.init();
    }
</script>
</body>
</html>
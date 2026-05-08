<?php
/**
 * 🏥 Bot Health System v1.0
 * Checks for session validity, state corruption, and recent errors.
 */
require_once __DIR__ . '/../db_connect.php';
$config = require __DIR__ . '/config.php';

function getBotHealthSummary(mysqli $conn, array $config) {
    $sessionDir = __DIR__ . '/sessions/';
    $logFile = __DIR__ . '/logs/' . date('Y-m-d') . '.log';
    
    $botEmails = $config['bot_emails'];
    $health = [
        'total' => count($botEmails),
        'healthy' => 0,
        'warning' => 0,
        'critical' => 0,
        'details' => []
    ];

    // Load recent errors from log
    $recentErrors = [];
    if (file_exists($logFile)) {
        $logContent = file_get_contents($logFile);
        // Lấy các lỗi trong 1 tiếng qua (giả định)
        preg_match_all('/\[(.*?)\] \[ERROR\] \[(.*?)\] (.*)/', $logContent, $matches);
        if (!empty($matches[2])) {
            foreach ($matches[2] as $index => $email) {
                $recentErrors[$email] = $matches[3][$index];
            }
        }
    }

    foreach ($botEmails as $email) {
        $botMd5 = md5($email);
        $stateFile = $sessionDir . $botMd5 . ".state.json";
        $cookieFile = $sessionDir . $botMd5 . ".txt";
        
        $status = 'healthy';
        $issues = [];

        // 1. Check State File
        if (!file_exists($stateFile)) {
            $status = 'warning';
            $issues[] = "Chưa có file trạng thái";
        } else {
            $state = json_decode(file_get_contents($stateFile), true);
            if ($state === null) {
                $status = 'critical';
                $issues[] = "File trạng thái bị hỏng (Invalid JSON)";
            }
        }

        // 2. Check Cookie/Session
        if (!file_exists($cookieFile)) {
            if ($status !== 'critical') $status = 'warning';
            $issues[] = "Chưa có session (Cookie)";
        } else {
            $age = time() - filemtime($cookieFile);
            if ($age > 86400 * 2) { // Hơn 2 ngày không hoạt động
                if ($status !== 'critical') $status = 'warning';
                $issues[] = "Session quá cũ (>48h)";
            }
        }

        // 3. Check Recent Errors
        if (isset($recentErrors[$email])) {
            $status = 'critical';
            $issues[] = "Lỗi gần đây: " . $recentErrors[$email];
        }

        $health[$status]++;
        if ($status !== 'healthy') {
            $health['details'][] = [
                'email' => $email,
                'status' => $status,
                'issues' => $issues
            ];
        }
    }

    return $health;
}

// Nếu gọi trực tiếp
if (basename($_SERVER['PHP_SELF']) == 'bot_health.php') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'clear_logs') {
        $logFile = __DIR__ . '/logs/' . date('Y-m-d') . '.log';
        if (file_exists($logFile)) {
            file_put_contents($logFile, ""); // Truncate file
            echo json_encode(['status' => 'success', 'message' => 'Logs cleared']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No log file to clear']);
        }
        exit;
    }

    if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && php_sapi_name() !== 'cli') die('Forbidden');
    header('Content-Type: application/json');
    echo json_encode(getBotHealthSummary($conn, $config));
}

<?php
/**
 * 🤖 Admin Tester Bot v1.0
 * Tự động kiểm tra các trang web để tìm lỗi và rủi ro bảo mật.
 * Báo cáo kết quả vào chat3.php thông qua bảng chat_messages.
 */

require_once __DIR__ . '/../db_connect.php';

// Cấu hình tự động nhận diện URL nếu chạy từ web
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? "127.0.0.1";
if ($host === "localhost") $host = "127.0.0.1";

// Lấy đường dẫn thư mục gốc tương đối với domain
if (isset($_SERVER['SCRIPT_NAME'])) {
    // Nếu chạy từ web: /1/bot/tester_bot.php -> /1/
    $baseDir = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'])));
} else {
    // Nếu chạy từ CLI: giả định thư mục hiện tại là bot/
    $baseDir = "/1"; // Giá định mặc định cho XAMPP local
}

$baseUrl = "$protocol://$host" . rtrim($baseDir, '/') . '/';

$botName = "Admin Tester Bot";
$botAvatar = "https://cdn-icons-png.flaticon.com/512/2583/2583150.png";
postToChat($conn, $botName, "[TESTER] 🛠️ Base URL detected: $baseUrl", $botAvatar);

// Danh sách các file cần loại bỏ khỏi việc quét trực tiếp (file config, include, v.v.)
$blacklist = [
    'db_connect.php',
    'db_connect_backup_local.php',
    'config.php',
    'ensure_db_connect.php',
    'load_theme.php',
    'include_css.php',
    'include_game_ui.php',
    'error_handler.php',
    'api_logout.php', // Tránh bị logout khi quét
];

function scanPhpFiles(string $dir) {
    $files = [];
    $it = new RecursiveDirectoryIterator($dir);
    foreach (new RecursiveIteratorIterator($it) as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    return $files;
}

function postToChat(mysqli $conn, string $botName, string $message, string $avatar) {
    $stmt = $conn->prepare("INSERT INTO chat_messages (user_id, username, message, avatar) VALUES (0, ?, ?, ?)");
    $stmt->bind_param("sss", $botName, $message, $avatar);
    $stmt->execute();
    $stmt->close();
}

function loginToSystem(string $baseUrl, string $username, string $password) {
    $cookieFile = __DIR__ . '/cookies.txt';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . 'api_login.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['username' => $username, 'password' => $password]));
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    
    if (PHP_VERSION_ID < 80500) {
        curl_close($ch);
    }
}

// Nếu bạn muốn quét các trang yêu cầu đăng nhập, hãy bỏ comment dòng dưới và nhập info
loginToSystem($baseUrl, 'cumanhpt@gmail.com', '1');

function checkUrl(string $url) {
    $cookieFile = __DIR__ . '/cookies.txt';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Đã đổi: Không tự động follow để phát hiện redirect 302
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, "AdminTesterBot/1.0");
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $error = curl_error($ch);
    
    if (PHP_VERSION_ID < 80500) {
        curl_close($ch);
    }

    if ($error) {
        return ["status" => "error", "message" => "CURL Error: $error"];
    }

    $results = [
        "code" => $httpCode,
        "issues" => []
    ];

    // Phát hiện redirect về login.php (Dấu hiệu thiếu session)
    if ($httpCode == 302 || $httpCode == 301) {
        $results["issues"][] = "Phát hiện chuyển hướng (Có thể là yêu cầu đăng nhập)";
    }

    if ($httpCode >= 400) {
        $results["issues"][] = "HTTP Code $httpCode";
    }

    // Kiểm tra các lỗi PHP phổ biến
    $phpErrors = [
        "Fatal error",
        "Parse error",
        "Warning: ",
        "Notice: ",
        "Uncaught Error",
        "Uncaught mysqli_sql_exception",
        "Stack trace:",
        "Call to undefined function"
    ];

    foreach ($phpErrors as $err) {
        if (stripos($response, $err) !== false) {
            // Cố gắng lấy thêm 1 dòng thông tin sau lỗi
            preg_match('/' . preg_quote($err, '/') . '(.*?)(?:\n|<br>|$)/si', $response, $matches);
            $details = isset($matches[1]) ? trim(strip_tags($matches[1])) : "";
            $results["issues"][] = "Phát hiện lỗi PHP: $err " . (strlen($details) > 2 ? "($details)" : "");
        }
    }

    // Kiểm tra rủi ro bảo mật đơn giản
    $securityRisks = [
        "mysql_fetch_array" => "Sử dụng hàm mysql_ cũ (SQL Injection risk)",
        "SELECT * FROM users" => "Có thể lộ thông tin SQL query",
        "DB_PASSWORD" => "Lộ hằng số mật khẩu database",
        "config.php" => "Lộ file cấu hình trong output",
        "phpinfo()" => "Lộ thông tin cấu hình server",
        "var_dump(" => "Còn sót lại hàm debug",
        "print_r(" => "Còn sót lại hàm debug"
    ];

    foreach ($securityRisks as $pattern => $desc) {
        if (stripos($response, $pattern) !== false) {
            $results["issues"][] = "Rủi ro bảo mật: $desc";
        }
    }

    return $results;
}

// Bắt đầu quét
$rootPath = realpath(__DIR__ . '/../');
$allFiles = scanPhpFiles($rootPath);
$targetFiles = [];

foreach ($allFiles as $file) {
    $relative = str_replace($rootPath . DIRECTORY_SEPARATOR, '', $file);
    $relative = str_replace('\\', '/', $relative);
    
    $basename = basename($relative);
    if (in_array($basename, $blacklist)) continue;
    if (strpos($relative, 'node_modules') !== false) continue;
    if (strpos($relative, 'vendor') !== false) continue;
    
    $targetFiles[] = $relative;
}

postToChat($conn, $botName, "[TESTER] 🚀 Bắt đầu quét toàn bộ hệ thống (" . count($targetFiles) . " file).", $botAvatar);

$findings = [];
$stopFlag = __DIR__ . '/scan_stop.flag';

foreach ($targetFiles as $index => $file) {
    // Kiểm tra tín hiệu dừng
    if (file_exists($stopFlag)) {
        $stopMsg = "[TESTER] ⏹️ Quá trình quét đã bị dừng bởi Admin.";
        postToChat($conn, $botName, $stopMsg, $botAvatar);
        @unlink($stopFlag);
        echo "DỪNG";
        exit;
    }

    $url = $baseUrl . $file;
    // CLI feedback
    if (php_sapi_name() === 'cli') echo "Checking [" . ($index + 1) . "/" . count($targetFiles) . "]: $file ... ";
    
    $res = checkUrl($url);
    
    // Thêm độ trễ nhỏ để tránh nghẽn socket (Only one usage of each socket address)
    usleep(50000); 
    
    if (!empty($res['issues'])) {
        if (php_sapi_name() === 'cli') echo "⚠️ ISSUES FOUND\n";
        $msg = "[TESTER] ⚠️ Vấn đề tại file: $file\n- " . implode("\n- ", $res['issues']);
        postToChat($conn, $botName, $msg, $botAvatar);
        $findings[] = $file;
    } else {
        if (php_sapi_name() === 'cli') echo "OK\n";
    }
}

$summary = "[TESTER] ✅ Quét hoàn tất. Tổng số file: " . count($targetFiles) . ". Số file có vấn đề: " . count($findings);
if (count($findings) > 0) {
    $summary .= "\nCần kiểm tra ngay các file: " . implode(", ", $findings);
} else {
    $summary .= "\nHệ thống có vẻ ổn định!";
}

postToChat($conn, $botName, $summary, $botAvatar);

echo "Quét hoàn tất. Xem báo cáo tại chat3.php";

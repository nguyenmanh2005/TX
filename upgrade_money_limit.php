<?php
/**
 * Script nâng giới hạn số dư tiền từ BIGINT UNSIGNED sang DECIMAL(30,2)
 * 
 * CẢNH BÁO: Chỉ chạy script này một lần và đảm bảo đã backup database trước!
 */

session_start();
require 'db_connect.php';

// Chỉ admin mới được chạy script này
if (!isset($_SESSION['Iduser'])) {
    die("Vui lòng đăng nhập!");
}

// Kiểm tra quyền admin
$userId = $_SESSION['Iduser'];
$checkAdmin = $conn->prepare("SELECT Role FROM users WHERE Iduser = ?");
$checkAdmin->bind_param("i", $userId);
$checkAdmin->execute();
$result = $checkAdmin->get_result();
$user = $result->fetch_assoc();
$checkAdmin->close();

if (!$user || $user['Role'] !== 'admin') {
    die("⚠️ Chỉ admin mới được chạy script này!");
}

// Kiểm tra xem đã nâng cấp chưa
$checkColumn = $conn->query("SHOW COLUMNS FROM users WHERE Field = 'Money'");
$columnInfo = $checkColumn->fetch_assoc();

$currentType = $columnInfo['Type'] ?? '';
$isUpgraded = strpos(strtolower($currentType), 'decimal') !== false;

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nâng Giới Hạn Số Dư Tiền</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        .info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #2196F3;
        }
        .warning {
            background: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #ffc107;
        }
        .success {
            background: #d4edda;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #28a745;
        }
        .error {
            background: #f8d7da;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #dc3545;
        }
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            margin-top: 20px;
        }
        button:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>💰 Nâng Giới Hạn Số Dư Tiền</h1>
        
        <?php if ($isUpgraded): ?>
            <div class="success">
                <strong>✅ Đã nâng cấp!</strong><br>
                Kiểu dữ liệu hiện tại: <code><?= htmlspecialchars($currentType) ?></code><br>
                Giới hạn mới: Lên đến 999,999,999,999,999,999,999,999,999,999.99 VNĐ
            </div>
        <?php else: ?>
            <div class="info">
                <strong>📊 Thông tin hiện tại:</strong><br>
                Kiểu dữ liệu: <code><?= htmlspecialchars($currentType) ?></code><br>
                Giới hạn hiện tại: 18.446.744.073.709.418.496 VNĐ (BIGINT UNSIGNED)
            </div>
            
            <div class="warning">
                <strong>⚠️ CẢNH BÁO:</strong>
                <ul>
                    <li>Hãy backup database trước khi chạy script này!</li>
                    <li>Quá trình này có thể mất vài giây tùy vào số lượng dữ liệu</li>
                    <li>Chỉ chạy một lần duy nhất!</li>
                </ul>
            </div>
            
            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_upgrade'])) {
                try {
                    // Bắt đầu transaction
                    $conn->begin_transaction();
                    
                    // Thay đổi kiểu dữ liệu
                    $sql = "ALTER TABLE users MODIFY COLUMN Money DECIMAL(30,2) UNSIGNED NOT NULL DEFAULT 0";
                    $conn->query($sql);
                    
                    // Commit transaction
                    $conn->commit();
                    
                    echo '<div class="success">';
                    echo '<strong>✅ Nâng cấp thành công!</strong><br>';
                    echo 'Cột Money đã được chuyển sang DECIMAL(30,2)<br>';
                    echo 'Giới hạn mới: Lên đến 999,999,999,999,999,999,999,999,999,999.99 VNĐ';
                    echo '</div>';
                    
                    // Reload để hiển thị trạng thái mới
                    echo '<script>setTimeout(function(){ location.reload(); }, 2000);</script>';
                } catch (Exception $e) {
                    $conn->rollback();
                    echo '<div class="error">';
                    echo '<strong>❌ Lỗi khi nâng cấp:</strong><br>';
                    echo htmlspecialchars($e->getMessage());
                    echo '</div>';
                }
            } else {
            ?>
                <form method="POST">
                    <div class="info">
                        <strong>📈 Sau khi nâng cấp:</strong><br>
                        Kiểu dữ liệu mới: <code>DECIMAL(30,2)</code><br>
                        Giới hạn mới: Lên đến 999,999,999,999,999,999,999,999,999,999.99 VNĐ
                    </div>
                    
                    <button type="submit" name="confirm_upgrade" onclick="return confirm('Bạn đã backup database chưa? Bạn chắc chắn muốn tiếp tục?')">
                        🚀 Nâng Cấp Ngay
                    </button>
                </form>
            <?php } ?>
        <?php endif; ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <a href="index.php" style="color: #667eea; text-decoration: none;">← Về Trang Chủ</a>
        </div>
    </div>
</body>
</html>


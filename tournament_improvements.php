<?php
/**
 * Tournament System Improvements
 * - Auto-create daily tournaments
 * - Auto-join feature
 * - Better rewards system
 * - Tournament reminders
 */

session_start();
require 'db_connect.php';

// Chỉ chạy từ cron job hoặc admin
$isAdmin = isset($_SESSION['Iduser']) && isset($_SESSION['Role']) && $_SESSION['Role'] === 'admin';
$isCron = isset($_GET['cron']) && $_GET['cron'] === 'true';

if (!$isAdmin && !$isCron) {
    die("Access denied");
}

// Kiểm tra bảng tồn tại
$checkTable = $conn->query("SHOW TABLES LIKE 'tournaments'");
if (!$checkTable || $checkTable->num_rows == 0) {
    die("Tournament tables not found!");
}

/**
 * Tạo tournament tự động hàng ngày
 */
function createDailyTournament($conn) {
    $today = date('Y-m-d');
    
    // Kiểm tra đã có tournament hôm nay chưa
    $sql = "SELECT id FROM tournaments WHERE DATE(start_time) = ? AND tournament_type = 'daily'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $stmt->close();
        return false; // Đã có tournament hôm nay
    }
    $stmt->close();
    
    // Tạo tournament mới
    $startTime = $today . ' 00:00:00';
    $endTime = $today . ' 23:59:59';
    $registrationEnd = $today . ' 23:00:00';
    
    $rewardStructure = json_encode([
        '1' => 1000000,
        '2' => 500000,
        '3' => 300000,
        '4-10' => 100000,
        '11-50' => 50000
    ]);
    
    $sql = "INSERT INTO tournaments 
            (name, description, tournament_type, start_time, end_time, registration_end_time, 
             max_participants, reward_structure, status, created_at)
            VALUES (?, ?, 'daily', ?, ?, ?, 1000, ?, 'registration', NOW())";
    $stmt = $conn->prepare($sql);
    $name = "Giải Đấu Hàng Ngày - " . date('d/m/Y');
    $description = "Giải đấu hàng ngày với phần thưởng hấp dẫn! Chơi game để tích điểm và giành vị trí cao nhất!";
    $stmt->bind_param("ssssss", $name, $description, $startTime, $endTime, $registrationEnd, $rewardStructure);
    $result = $stmt->execute();
    $tournamentId = $conn->insert_id;
    $stmt->close();
    
    return $result ? $tournamentId : false;
}

/**
 * Tự động đăng ký user vào daily tournament
 */
function autoJoinDailyTournament($conn, $userId) {
    $today = date('Y-m-d');
    
    // Tìm tournament hôm nay
    $sql = "SELECT id FROM tournaments 
            WHERE DATE(start_time) = ? 
            AND tournament_type = 'daily' 
            AND status IN ('registration', 'active')
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $tournament = $result->fetch_assoc();
    $stmt->close();
    
    if (!$tournament) {
        return false;
    }
    
    $tournamentId = $tournament['id'];
    
    // Kiểm tra đã đăng ký chưa
    $sql = "SELECT id FROM tournament_participants 
            WHERE tournament_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $tournamentId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $stmt->close();
        return false; // Đã đăng ký rồi
    }
    $stmt->close();
    
    // Đăng ký tự động
    $sql = "INSERT INTO tournament_participants 
            (tournament_id, user_id, score, total_games, total_wins, total_win_amount, rank, registered_at)
            VALUES (?, ?, 0, 0, 0, 0, 0, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $tournamentId, $userId);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Cập nhật trạng thái tournament
 */
function updateTournamentStatus($conn) {
    $now = date('Y-m-d H:i:s');
    
    // Cập nhật registration -> active
    $sql = "UPDATE tournaments 
            SET status = 'active' 
            WHERE status = 'registration' 
            AND start_time <= ? 
            AND end_time > ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $now, $now);
    $stmt->execute();
    $stmt->close();
    
    // Cập nhật active -> ended
    $sql = "UPDATE tournaments 
            SET status = 'ended' 
            WHERE status = 'active' 
            AND end_time <= ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $now);
    $stmt->execute();
    $stmt->close();
}

// Chạy các hàm cải thiện
if ($isCron || $isAdmin) {
    // Tạo tournament hàng ngày
    $tournamentId = createDailyTournament($conn);
    if ($tournamentId) {
        echo "Created daily tournament: $tournamentId\n";
    }
    
    // Cập nhật trạng thái
    updateTournamentStatus($conn);
    echo "Updated tournament statuses\n";
    
    // Auto-join cho tất cả users đang hoạt động (nếu cần)
    if (isset($_GET['auto_join']) && $_GET['auto_join'] === 'true') {
        $sql = "SELECT Iduser FROM users WHERE 1=1 LIMIT 100";
        $result = $conn->query($sql);
        $count = 0;
        while ($row = $result->fetch_assoc()) {
            if (autoJoinDailyTournament($conn, $row['Iduser'])) {
                $count++;
            }
        }
        echo "Auto-joined $count users to daily tournament\n";
    }
}

echo "Tournament improvements completed!";
?>


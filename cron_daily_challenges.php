<?php
// cron_daily_challenges.php - Tự động seed thử thách hàng ngày cho ngày hôm nay và ngày mai
require_once 'db_connect.php';

// Đảm bảo bảng daily_challenges tồn tại
$checkTable = $conn->query("SHOW TABLES LIKE 'daily_challenges'");
$tableExists = $checkTable && $checkTable->num_rows > 0;

if (!$tableExists) {
    echo "Lỗi: Bảng daily_challenges chưa được thiết lập. Hãy truy cập daily_challenges.php trước.";
    exit;
}

$daysToSeed = [date('Y-m-d'), date('Y-m-d', strtotime('+1 day'))];

$autoChallenges = [
    [
        'type' => 'play_games',
        'name' => 'Chơi 5 Game',
        'description' => 'Chơi tổng cộng 5 game bất kỳ',
        'requirement' => 5,
        'reward_money' => 50000,
        'reward_xp' => 50
    ],
    [
        'type' => 'win_games',
        'name' => 'Thắng 3 Game',
        'description' => 'Thắng tổng cộng 3 game',
        'requirement' => 3,
        'reward_money' => 100000,
        'reward_xp' => 100
    ],
    [
        'type' => 'earn_money',
        'name' => 'Kiếm 500,000 gtlm',
        'description' => 'Kiếm được tổng cộng 500,000 gtlm từ các game',
        'requirement' => 500000,
        'reward_money' => 200000,
        'reward_xp' => 150
    ]
];

$seedCount = 0;
// Lấy tất cả user ID hoạt động trong 30 ngày gần đây
$stmtUsers = $conn->prepare("SELECT Iduser FROM users WHERE last_active >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stmtUsers->execute();
$userList = $stmtUsers->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtUsers->close();

foreach ($daysToSeed as $day) {
    foreach ($autoChallenges as $challenge) {
        $sql = "INSERT INTO daily_challenges (challenge_date, challenge_type, challenge_name, description, requirement_value, reward_money, reward_xp)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE challenge_name = VALUES(challenge_name)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssssiii",
            $day,
            $challenge['type'],
            $challenge['name'],
            $challenge['description'],
            $challenge['requirement'],
            $challenge['reward_money'],
            $challenge['reward_xp']
        );
        $stmt->execute();
        
        // Lấy ID thử thách vừa thêm/cập nhật
        $challengeId = 0;
        if ($stmt->insert_id > 0) {
            $challengeId = $stmt->insert_id;
        } else {
            // Nếu đã tồn tại, truy vấn ngược lại để lấy ID
            $stmtFind = $conn->prepare("SELECT id FROM daily_challenges WHERE challenge_date = ? AND challenge_type = ?");
            $stmtFind->bind_param("ss", $day, $challenge['type']);
            $stmtFind->execute();
            $findRes = $stmtFind->get_result()->fetch_assoc();
            $challengeId = $findRes ? (int)$findRes['id'] : 0;
            $stmtFind->close();
        }
        $stmt->close();

        if ($challengeId > 0 && !empty($userList)) {
            $stmtProg = $conn->prepare("INSERT INTO daily_challenge_progress (user_id, challenge_id, progress)
                    VALUES (?, ?, 0)
                    ON DUPLICATE KEY UPDATE progress = progress");
            foreach ($userList as $u) {
                $uId = (int)$u['Iduser'];
                $stmtProg->bind_param("ii", $uId, $challengeId);
                $stmtProg->execute();
            }
            $stmtProg->close();
        }
        $seedCount++;
    }
}

echo "Successfully seeded " . $seedCount . " daily challenges for today and tomorrow.\n";
?>

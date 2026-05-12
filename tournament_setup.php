<?php
require 'db_connect.php';

echo "<h2>Khởi tạo hệ thống Tournament (Giải đấu)</h2>";

// 1. Tạo bảng Giải đấu (Tournaments)
$conn->query("CREATE TABLE IF NOT EXISTS tournaments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    game_type VARCHAR(50) DEFAULT 'Dice',
    buy_in DECIMAL(15,2) NOT NULL,
    house_fee_percent INT DEFAULT 10,
    prize_pool DECIMAL(15,2) DEFAULT 0,
    max_players INT DEFAULT 50,
    current_players INT DEFAULT 0,
    min_players INT DEFAULT 2,
    status ENUM('Pending', 'Ongoing', 'Finished', 'Cancelled') DEFAULT 'Pending',
    start_time DATETIME NOT NULL,
    end_time DATETIME NULL,
    winner_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
echo "✅ Đã tạo bảng 'tournaments'<br>";

// 2. Tạo bảng Tham gia giải đấu (Participants)
$conn->query("CREATE TABLE IF NOT EXISTS tournament_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    user_id INT NOT NULL,
    score DECIMAL(15,2) DEFAULT 0,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(Iduser),
    UNIQUE KEY unique_user_tournament (tournament_id, user_id)
)");
echo "✅ Đã tạo bảng 'tournament_participants'<br>";

// 2.1 Tạo bảng Điểm số giải đấu (Scores)
$conn->query("CREATE TABLE IF NOT EXISTS tournament_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    user_id INT NOT NULL,
    score DECIMAL(20,2) DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(Iduser),
    UNIQUE KEY unique_user_score (tournament_id, user_id)
)");
echo "✅ Đã tạo bảng 'tournament_scores'<br>";

// 3. Thêm dữ liệu mẫu (Các giải đấu sắp tới)
$checkTournaments = $conn->query("SELECT COUNT(*) as total FROM tournaments");
if ($checkTournaments->fetch_assoc()['total'] == 0) {
    $now = new DateTime();
    $tomorrow = (clone $now)->modify('+1 day')->format('Y-m-d H:i:s');
    $nextHour = (clone $now)->modify('+1 hour')->format('Y-m-d H:i:s');
    
    $conn->query("INSERT INTO tournaments (name, game_type, buy_in, house_fee_percent, prize_pool, max_players, start_time, status) VALUES 
    ('Giải Đấu Xúc Xắc Siêu Cấp', 'Dice', 100000, 10, 0, 20, '$nextHour', 'Pending'),
    ('Đại Chiến Bầu Cua Hàng Tuần', 'Baucua', 500000, 15, 0, 50, '$tomorrow', 'Pending')");
    echo "✅ Đã thêm các giải đấu mẫu<br>";
}

echo "<br><a href='tournaments.php'>Đến trang Giải Đấu -></a>";
?>

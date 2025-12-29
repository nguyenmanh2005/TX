-- ============================================
-- Tạo Database Tables cho Daily Missions System
-- ============================================
-- Hệ thống nhiệm vụ hàng ngày với phần thưởng hấp dẫn

-- Bảng lưu các nhiệm vụ hàng ngày (templates)
CREATE TABLE IF NOT EXISTS daily_mission_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mission_type VARCHAR(50) NOT NULL, -- 'play_games', 'win_games', 'earn_money', 'bet_amount', 'streak', 'login', 'gift', 'friend', etc.
    title VARCHAR(200) NOT NULL,
    description TEXT,
    requirement_value INT NOT NULL DEFAULT 1, -- Số lượng cần đạt
    reward_type VARCHAR(50) NOT NULL DEFAULT 'money', -- 'money', 'points', 'item', 'vip_points'
    reward_value DECIMAL(30,2) DEFAULT 0,
    reward_item_id INT NULL, -- Nếu reward_type = 'item'
    difficulty ENUM('easy', 'medium', 'hard', 'expert') DEFAULT 'easy',
    is_active TINYINT(1) DEFAULT 1,
    priority INT DEFAULT 0, -- Độ ưu tiên hiển thị
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (mission_type),
    INDEX idx_active (is_active, priority DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bảng lưu nhiệm vụ hàng ngày của từng user
CREATE TABLE IF NOT EXISTS user_daily_missions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    mission_template_id INT NOT NULL,
    mission_date DATE NOT NULL,
    progress INT DEFAULT 0,
    requirement_value INT NOT NULL,
    is_completed TINYINT(1) DEFAULT 0,
    is_claimed TINYINT(1) DEFAULT 0,
    completed_at TIMESTAMP NULL,
    claimed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(Iduser) ON DELETE CASCADE,
    FOREIGN KEY (mission_template_id) REFERENCES daily_mission_templates(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_mission_date (user_id, mission_template_id, mission_date),
    INDEX idx_user_date (user_id, mission_date),
    INDEX idx_completed (is_completed, is_claimed),
    INDEX idx_date (mission_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bảng lưu lịch sử hoàn thành nhiệm vụ
CREATE TABLE IF NOT EXISTS daily_mission_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    mission_template_id INT NOT NULL,
    mission_date DATE NOT NULL,
    reward_type VARCHAR(50) NOT NULL,
    reward_value DECIMAL(30,2) DEFAULT 0,
    reward_item_id INT NULL,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(Iduser) ON DELETE CASCADE,
    FOREIGN KEY (mission_template_id) REFERENCES daily_mission_templates(id) ON DELETE CASCADE,
    INDEX idx_user (user_id, completed_at DESC),
    INDEX idx_date (mission_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bảng thống kê nhiệm vụ của user
CREATE TABLE IF NOT EXISTS user_mission_stats (
    user_id INT PRIMARY KEY,
    total_missions_completed INT DEFAULT 0,
    total_rewards_earned DECIMAL(30,2) DEFAULT 0,
    current_streak INT DEFAULT 0,
    longest_streak INT DEFAULT 0,
    last_mission_date DATE NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(Iduser) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert một số nhiệm vụ mẫu
INSERT INTO daily_mission_templates (mission_type, title, description, requirement_value, reward_type, reward_value, difficulty, priority) VALUES
('login', 'Đăng Nhập Hàng Ngày', 'Đăng nhập vào game để nhận phần thưởng', 1, 'money', 10000, 'easy', 10),
('play_games', 'Chơi 5 Game', 'Chơi 5 game bất kỳ để nhận phần thưởng', 5, 'money', 50000, 'easy', 9),
('win_games', 'Thắng 3 Game', 'Thắng 3 game để nhận phần thưởng', 3, 'money', 100000, 'medium', 8),
('earn_money', 'Kiếm 500,000 VNĐ', 'Kiếm được 500,000 VNĐ từ các game', 500000, 'money', 200000, 'medium', 7),
('bet_amount', 'Cược Tổng 1,000,000 VNĐ', 'Cược tổng cộng 1,000,000 VNĐ', 1000000, 'money', 150000, 'hard', 6),
('streak', 'Duy Trì Streak 3 Ngày', 'Chơi game liên tiếp 3 ngày', 3, 'money', 300000, 'hard', 5),
('gift', 'Tặng Quà Cho Bạn Bè', 'Tặng quà cho 1 người bạn', 1, 'money', 50000, 'easy', 4),
('friend', 'Kết Bạn Mới', 'Kết bạn với 1 người chơi mới', 1, 'money', 75000, 'easy', 3),
('guild', 'Tham Gia Guild', 'Tham gia hoặc tạo guild', 1, 'money', 100000, 'medium', 2),
('big_win', 'Thắng Lớn', 'Thắng ít nhất 1,000,000 VNĐ trong 1 game', 1000000, 'money', 500000, 'expert', 1)
ON DUPLICATE KEY UPDATE title = VALUES(title);


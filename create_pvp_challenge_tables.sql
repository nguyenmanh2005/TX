-- ============================================
-- Tạo Database Tables cho PvP Challenge System
-- ============================================
-- Hệ thống đấu 1-1 giữa 2 người chơi

-- Bảng lưu các challenge đang chờ
CREATE TABLE IF NOT EXISTS pvp_challenges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    challenger_id INT NOT NULL,
    opponent_id INT NOT NULL,
    game_type VARCHAR(50) NOT NULL DEFAULT 'coinflip', -- coinflip, dice, rps, number
    bet_amount DECIMAL(15, 2) NOT NULL DEFAULT 0,
    status ENUM('pending', 'accepted', 'completed', 'cancelled', 'expired') DEFAULT 'pending',
    challenger_choice VARCHAR(50) DEFAULT NULL, -- 'heads', 'tails', dice number, etc.
    opponent_choice VARCHAR(50) DEFAULT NULL,
    result VARCHAR(50) DEFAULT NULL, -- 'challenger_win', 'opponent_win', 'draw'
    winner_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    accepted_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (challenger_id) REFERENCES users(Iduser) ON DELETE CASCADE,
    FOREIGN KEY (opponent_id) REFERENCES users(Iduser) ON DELETE CASCADE,
    INDEX idx_challenger (challenger_id),
    INDEX idx_opponent (opponent_id),
    INDEX idx_status (status),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bảng lưu lịch sử các trận đấu
CREATE TABLE IF NOT EXISTS pvp_match_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    challenge_id INT NOT NULL,
    challenger_id INT NOT NULL,
    opponent_id INT NOT NULL,
    game_type VARCHAR(50) NOT NULL,
    bet_amount DECIMAL(15, 2) NOT NULL,
    challenger_choice VARCHAR(50),
    opponent_choice VARCHAR(50),
    result VARCHAR(50),
    winner_id INT,
    played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (challenge_id) REFERENCES pvp_challenges(id) ON DELETE CASCADE,
    FOREIGN KEY (challenger_id) REFERENCES users(Iduser) ON DELETE CASCADE,
    FOREIGN KEY (opponent_id) REFERENCES users(Iduser) ON DELETE CASCADE,
    INDEX idx_challenger (challenger_id),
    INDEX idx_opponent (opponent_id),
    INDEX idx_winner (winner_id),
    INDEX idx_played_at (played_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bảng thống kê PvP của mỗi user
CREATE TABLE IF NOT EXISTS pvp_stats (
    user_id INT PRIMARY KEY,
    total_matches INT DEFAULT 0,
    wins INT DEFAULT 0,
    losses INT DEFAULT 0,
    draws INT DEFAULT 0,
    total_winnings DECIMAL(15, 2) DEFAULT 0,
    total_losses DECIMAL(15, 2) DEFAULT 0,
    win_streak INT DEFAULT 0,
    best_win_streak INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(Iduser) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================
-- Tạo Database Tables cho Guild System
-- ============================================
-- Hệ thống Guild/Clan cho phép người chơi tạo và tham gia các hội nhóm

-- Bảng lưu thông tin các guild
CREATE TABLE IF NOT EXISTS guilds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    tag VARCHAR(10) NOT NULL UNIQUE,
    description TEXT,
    leader_id INT NOT NULL,
    level INT DEFAULT 1,
    experience BIGINT UNSIGNED DEFAULT 0,
    max_members INT DEFAULT 20,
    total_contribution BIGINT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (leader_id) REFERENCES users(Iduser) ON DELETE CASCADE,
    INDEX idx_leader (leader_id),
    INDEX idx_level (level DESC),
    INDEX idx_experience (experience DESC),
    INDEX idx_tag (tag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bảng lưu thành viên của các guild
CREATE TABLE IF NOT EXISTS guild_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    guild_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('leader', 'officer', 'member') DEFAULT 'member',
    contribution BIGINT UNSIGNED DEFAULT 0,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (guild_id) REFERENCES guilds(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(Iduser) ON DELETE CASCADE,
    UNIQUE KEY unique_guild_user (guild_id, user_id),
    INDEX idx_guild (guild_id),
    INDEX idx_user (user_id),
    INDEX idx_role (role),
    INDEX idx_contribution (contribution DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bảng lưu tin nhắn trong guild chat
CREATE TABLE IF NOT EXISTS guild_chat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    guild_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (guild_id) REFERENCES guilds(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(Iduser) ON DELETE CASCADE,
    INDEX idx_guild (guild_id, created_at DESC),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bảng lưu đơn xin vào guild
CREATE TABLE IF NOT EXISTS guild_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    guild_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT NULL,
    FOREIGN KEY (guild_id) REFERENCES guilds(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(Iduser) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(Iduser) ON DELETE SET NULL,
    INDEX idx_guild (guild_id, status),
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bảng lưu hoạt động của guild (để tính contribution và experience)
CREATE TABLE IF NOT EXISTS guild_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    guild_id INT NOT NULL,
    user_id INT NOT NULL,
    activity_type VARCHAR(50) NOT NULL, -- 'game_win', 'game_play', 'daily_login', etc.
    contribution INT DEFAULT 0,
    experience INT DEFAULT 0,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (guild_id) REFERENCES guilds(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(Iduser) ON DELETE CASCADE,
    INDEX idx_guild (guild_id, created_at DESC),
    INDEX idx_user (user_id),
    INDEX idx_type (activity_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bảng lưu thống kê guild (tổng hợp)
CREATE TABLE IF NOT EXISTS guild_stats (
    guild_id INT PRIMARY KEY,
    total_games_played INT DEFAULT 0,
    total_games_won INT DEFAULT 0,
    total_money_won DECIMAL(30,2) DEFAULT 0,
    total_members_joined INT DEFAULT 0,
    last_activity_at TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (guild_id) REFERENCES guilds(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


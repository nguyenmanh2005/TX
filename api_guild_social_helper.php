<?php
require_once 'db_connect.php';

class GuildSocialHelper {
    
    /**
     * Lấy danh sách toàn bộ bản đồ lãnh thổ
     */
    public static function getTerritoryMap(mysqli $conn) {
        $sql = "SELECT t.*, g.Name as guild_name, g.ImageURL as guild_logo 
                FROM guild_territories t 
                LEFT JOIN guilds g ON t.guild_id = g.id 
                ORDER BY t.y ASC, t.x ASC";
        return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Chiếm đóng lãnh thổ (Thường gọi sau khi thắng Guild War)
     */
    public static function captureTerritory(mysqli $conn, int $guildId, int $x, int $y) {
        $stmt = $conn->prepare("UPDATE guild_territories SET guild_id = ?, captured_at = NOW() WHERE x = ? AND y = ?");
        $stmt->bind_param("iii", $guildId, $x, $y);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    /**
     * Lấy tổng passive bonus của một Guild từ lãnh thổ
     */
    public static function getGuildPassiveBonuses(mysqli $conn, int $guildId) {
        $sql = "SELECT bonus_type, SUM(bonus_value - 1) as total_bonus 
                FROM guild_territories 
                WHERE guild_id = ? 
                GROUP BY bonus_type";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $guildId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $bonuses = ['coin' => 0, 'exp' => 0, 'drop_rate' => 0];
        foreach ($res as $row) {
            $bonuses[$row['bonus_type']] = $row['total_bonus'];
        }
        return $bonuses;
    }

    /**
     * Khởi tạo Boss Raid mới cho Guild (Nếu chưa có Boss active)
     */
    public static function spawnRaidBoss(mysqli $conn, int $guildId) {
        $check = $conn->query("SELECT id FROM guild_raid_bosses WHERE guild_id = $guildId AND status = 'active'")->fetch_assoc();
        if ($check) return false;

        $bossNames = ['Ma Vương Azazel', 'Rồng Xương Frostbane', 'Khổng Lồ Thạch Anh', 'Phượng Hoàng Lửa'];
        $name = $bossNames[array_rand($bossNames)];
        $hp = 10000000; // 10 triệu HP mặc định
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $stmt = $conn->prepare("INSERT INTO guild_raid_bosses (guild_id, boss_name, current_hp, max_hp, expires_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isiis", $guildId, $name, $hp, $hp, $expires);
        $stmt->execute();
        return $stmt->insert_id;
    }

    /**
     * Tấn công Boss Raid
     */
    public static function attackRaidBoss(mysqli $conn, int $guildId, int $userId, int $damage) {
        $boss = $conn->query("SELECT * FROM guild_raid_bosses WHERE guild_id = $guildId AND status = 'active' AND expires_at > NOW()")->fetch_assoc();
        if (!$boss) return ['success' => false, 'message' => 'Không có Boss nào đang hoạt động!'];

        $raidId = $boss['id'];
        $newHp = max(0, $boss['current_hp'] - $damage);
        $status = ($newHp <= 0) ? 'defeated' : 'active';

        $conn->begin_transaction();
        try {
            // Cập nhật HP Boss
            $conn->query("UPDATE guild_raid_bosses SET current_hp = $newHp, status = '$status' WHERE id = $raidId");
            
            // Ghi nhận đóng góp
            $conn->query("INSERT INTO guild_raid_participation (raid_id, user_id, damage_dealt) 
                          VALUES ($raidId, $userId, $damage) 
                          ON DUPLICATE KEY UPDATE damage_dealt = damage_dealt + $damage");
            
            $conn->commit();
            return ['success' => true, 'new_hp' => $newHp, 'status' => $status];
        } catch (Exception $e) {
            $conn->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

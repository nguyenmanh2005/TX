<?php
/**
 * 🧠 World Boss Backend API
 * Xử lý tấn công, phân vai chiến đấu, kỹ năng Boss theo Phase,
 * hồi sinh theo lịch trình cố định và xếp hạng phần thưởng theo phần trăm đóng góp.
 */
require_once 'db_connect.php';
require_once 'reward_helper.php';
require_once 'notification_helper.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['Iduser'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$bossId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

// ✅ Cho phép get_status không cần id (bot announcer tương thích)
if ($bossId <= 0 && $action === 'get_status') {
    $bossId = 1;
}

if ($bossId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid boss ID']);
    exit;
}

// ⚡ TỰ ĐỘNG BẢO TRÌ CƠ SỞ DỮ LIỆU (SELF-HEALING SCHEMA)
$conn->query("ALTER TABLE world_boss_damage ADD COLUMN role VARCHAR(20) DEFAULT 'dps'");

// ⚡ LỊCH TRÌNH HỒI SINH CỐ ĐỊNH & KIỂM TRA TRẠNG THÁI
$spawnInfo = checkAndSpawnBoss($conn);
$boss = $spawnInfo['boss'];

// 1. SET_ROLE: Chọn/thay đổi vai trò của người chơi
if ($action === 'set_role') {
    $role = $_POST['role'] ?? 'dps';
    if (!in_array($role, ['dps', 'tank', 'healer'])) {
        echo json_encode(['success' => false, 'message' => 'Vai trò không hợp lệ!']);
        exit;
    }

    $stmtRole = $conn->prepare("
        INSERT INTO world_boss_damage (boss_id, user_id, damage, role)
        VALUES (?, ?, 0, ?)
        ON DUPLICATE KEY UPDATE role = ?
    ");
    $stmtRole->bind_param("iiss", $bossId, $userId, $role, $role);
    $stmtRole->execute();
    $stmtRole->close();
    
    echo json_encode(['success' => true, 'message' => 'Thay đổi vai trò thành công!', 'role' => $role]);
    exit;
}

// 2. SYNC: Lấy trạng thái chiến trường
if ($action === 'sync') {
    // Top 5 sát thương
    $stmtLb = $conn->prepare("
        SELECT d.damage, d.role, u.Name 
        FROM world_boss_damage d
        JOIN users u ON d.user_id = u.Iduser
        WHERE d.boss_id = ?
        ORDER BY d.damage DESC LIMIT 5
    ");
    $stmtLb->bind_param("i", $bossId);
    $stmtLb->execute();
    $leaderboard = $stmtLb->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtLb->close();

    // Sát thương cá nhân và vai trò hiện tại
    $stmtMyDmg = $conn->prepare("SELECT damage, role FROM world_boss_damage WHERE boss_id = ? AND user_id = ?");
    $stmtMyDmg->bind_param("ii", $bossId, $userId);
    $stmtMyDmg->execute();
    $myDamageRow = $stmtMyDmg->get_result()->fetch_assoc();
    $stmtMyDmg->close();
    $myDamage = (int)($myDamageRow['damage'] ?? 0);
    $myRole = $myDamageRow['role'] ?? 'dps';
    
    // Các đòn đánh gần đây (lấy từ arena_memory)
    $recent = $conn->query("
        SELECT target_name as Name, value 
        FROM arena_memory 
        WHERE event_type = 'boss_attack' 
        ORDER BY created_at DESC LIMIT 5
    ")->fetch_all(MYSQLI_ASSOC);
    
    $recentAttacks = array_map(function($r) {
        $val = json_decode($r['value'], true);
        return ['Name' => $r['Name'], 'val' => $val['damage'] ?? 0];
    }, $recent);

    // Tính toán Phase của Boss dựa trên tỷ lệ máu
    $hpPct = ($boss['max_hp'] > 0) ? ($boss['hp'] / $boss['max_hp']) * 100 : 0;
    $phase = 1;
    if ($hpPct <= 33.33) {
        $phase = 3;
    } elseif ($hpPct <= 66.66) {
        $phase = 2;
    }
    
    echo json_encode([
        'success' => true,
        'hp' => (int)$boss['hp'],
        'max_hp' => (int)$boss['max_hp'],
        'status' => $boss['status'],
        'phase' => $phase,
        'leaderboard' => $leaderboard,
        'my_damage' => $myDamage,
        'my_role' => $myRole,
        'recent_attacks' => $recentAttacks,
        'next_spawn' => $spawnInfo['next_spawn_time'],
        'next_spawn_epoch' => $spawnInfo['next_spawn_epoch']
    ]);
    exit;
}

// ✅ BOT COMPATIBILITY: get_status (legacy format for announcer bot)
if ($action === 'get_status') {
    $hpPct = ($boss['max_hp'] > 0) ? ($boss['hp'] / $boss['max_hp']) * 100 : 0;
    echo json_encode([
        'success' => true,
        'boss' => [
            'status' => ($boss['status'] === 'active') ? 'alive' : $boss['status'],
            'hp' => (int)$boss['hp'],
            'max_hp' => (int)$boss['max_hp'],
            'name' => $boss['name'] ?? 'Ma Thần',
        ]
    ]);
    exit;
}

// 3. ATTACK: Tung chiêu tấn công Boss
if ($action === 'attack') {
    $now = microtime(true) * 1000;
    if (isset($_SESSION['last_boss_attack'])) {
        $diff = $now - $_SESSION['last_boss_attack'];
        if ($diff < 1000) {
            echo json_encode(['success' => false, 'message' => 'Thao tác quá nhanh! Vui lòng đợi 1 giây.']);
            exit;
        }
    }
    $_SESSION['last_boss_attack'] = $now;

    $conn->begin_transaction();
    try {
        // Lấy thông tin vai trò của người chơi
        $myDamageRow = $conn->query("SELECT role FROM world_boss_damage WHERE boss_id = $bossId AND user_id = $userId FOR UPDATE")->fetch_assoc();
        $role = $myDamageRow['role'] ?? 'dps';

        // 🛡️ Hệ thống vai trò: Tank được giảm 30% chi phí tấn công
        $cost = ($role === 'tank') ? 3500 : 5000;

        // Kiểm tra tiền
        $user = $conn->query("SELECT Money, level, Name FROM users WHERE Iduser = $userId FOR UPDATE")->fetch_assoc();
        if ($user['Money'] < $cost) {
            throw new Exception("Không đủ GTLM để tung chiêu! Bạn cần " . number_format($cost) . " GTLM.");
        }

        // Kiểm tra Boss còn sống không
        $boss = $conn->query("SELECT hp, max_hp, name, status FROM world_boss WHERE id = $bossId FOR UPDATE")->fetch_assoc();
        if (!$boss || $boss['status'] !== 'active' || $boss['hp'] <= 0) {
            throw new Exception("Ma Thần đã bị tiêu diệt hoặc chưa xuất hiện! Hãy chờ lịch hồi sinh kế tiếp.");
        }

        // Tính toán Phase trước đòn đánh
        $hpPct = ($boss['max_hp'] > 0) ? ($boss['hp'] / $boss['max_hp']) * 100 : 0;
        $phase = 1;
        if ($hpPct <= 33.33) {
            $phase = 3;
        } elseif ($hpPct <= 66.66) {
            $phase = 2;
        }

        // Kiểm tra cơ chế đặc thù cho Phase 3: chỉ được đánh vào ban đêm (sau 20h tối)
        if ($phase === 3) {
            $currentHour = (int)date('G');
            if ($currentHour < 20) {
                throw new Exception("🔒 CẤM ẤN: Ma Thần đang ở trạng thái Nộ Long (Dưới 33% HP)! Phong ấn chỉ suy yếu và cho phép tấn công vào buổi tối muộn (sau 20:00)!");
            }
        }

        // Sát thương cơ bản dựa trên cấp độ
        $baseDamage = $user['level'] * rand(500, 1500);

        // ⚔️ Hệ thống vai trò: DPS tăng 50% sát thương cơ bản
        if ($role === 'dps') {
            $baseDamage *= 1.5;
        }

        $damage = $baseDamage;
        $mechanicMsg = "";
        $dmgReflected = 0;

        // 🌋 Kỹ năng đặc biệt của Boss theo từng Phase
        if ($phase === 1) {
            $mechanicMsg = "⚔️ [Phase 1: Thức Tỉnh] Đòn đánh vật lý chuẩn xác!";
        } elseif ($phase === 2) {
            // Phase 2: Phong Độc Trận (Boss né tránh và giảm 30% sát thương của người chơi)
            $damage = $baseDamage * 0.7;
            $mechanicMsg = "❄️ [Phase 2: Băng Hỏa Kiếp] Boss phủ giáp gai hấp thụ 30% sát thương của bạn!";
        } elseif ($phase === 3) {
            // Phase 3: Phản Phục Trận (Boss phản 20% đòn đánh tương đương 1,000 GTLM trị liệu)
            if ($role === 'tank') {
                // Tanker miễn nhiễm phản đòn và không bị giảm sát thương
                $mechanicMsg = "🛡️ [Phase 3: Phản Phục Trận] Tanker kiên cường chắn sóng! Bạn miễn nhiễm Phản đòn và giữ nguyên 100% sát thương!";
            } else {
                // DPS và Healer bị giảm 50% damage và phản đòn hao tổn tiền trị liệu
                $damage = $baseDamage * 0.5;
                $dmgReflected = 1000;
                $mechanicMsg = "⚡ [Phase 3: Phản Phục Trận] Boss phản kích dữ dội! Bạn bị giảm 50% sát thương và chịu phản đòn mất thêm 1,000 GTLM trị liệu!";
            }
        }

        $actualDamage = min($boss['hp'], $damage);

        // Khấu trừ tiền cơ bản
        $stmtDeduct = $conn->prepare("UPDATE users SET Money = Money - ? WHERE Iduser = ?");
        $stmtDeduct->bind_param("di", $cost, $userId);
        $stmtDeduct->execute();
        $stmtDeduct->close();

        // Khấu trừ tiền phản đòn (nếu có)
        if ($dmgReflected > 0) {
            if ($user['Money'] - $cost < $dmgReflected) {
                $stmtZero = $conn->prepare("UPDATE users SET Money = 0 WHERE Iduser = ?");
                $stmtZero->bind_param("i", $userId);
                $stmtZero->execute();
                $stmtZero->close();
            } else {
                $stmtReflect = $conn->prepare("UPDATE users SET Money = Money - ? WHERE Iduser = ?");
                $stmtReflect->bind_param("di", $dmgReflected, $userId);
                $stmtReflect->execute();
                $stmtReflect->close();
            }
        }

        // 💚 Hệ thống vai trò: Healer hồi máu nhận lại 1,250 GTLM vàng mỗi lượt đánh
        if ($role === 'healer') {
            $healBonus = 1250;
            $stmtHeal = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
            $stmtHeal->bind_param("di", $healBonus, $userId);
            $stmtHeal->execute();
            $stmtHeal->close();
            $mechanicMsg .= " 💚 [Thần Y] Nhận lại 1,250 GTLM vàng hồi sức!";
        }

        // Cập nhật máu Boss
        $stmtBossHp = $conn->prepare("UPDATE world_boss SET hp = hp - ? WHERE id = ?");
        $stmtBossHp->bind_param("di", $actualDamage, $bossId);
        $stmtBossHp->execute();
        $stmtBossHp->close();

        // Cập nhật bảng sát thương và bảo đảm role đồng bộ
        $stmtDmgLog = $conn->prepare("
            INSERT INTO world_boss_damage (boss_id, user_id, damage, role) 
            VALUES (?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE damage = damage + ?, role = ?
        ");
        $stmtDmgLog->bind_param("iidsis", $bossId, $userId, $actualDamage, $role, $actualDamage, $role);
        $stmtDmgLog->execute();
        $stmtDmgLog->close();

        // Ghi vào arena_memory cho Live Feed
        $val = json_encode(['damage' => $actualDamage]);
        $uName = $user['Name'];
        
        $stmtMemory = $conn->prepare("INSERT INTO arena_memory (event_type, target_name, value) VALUES ('boss_attack', ?, ?)");
        $stmtMemory->bind_param("ss", $uName, $val);
        $stmtMemory->execute();
        $stmtMemory->close();

        // Kiểm tra nếu Boss bị tiêu diệt hoàn toàn
        if ($boss['hp'] - $actualDamage <= 0) {
            $conn->query("UPDATE world_boss SET status = 'defeated' WHERE id = $bossId");
            // Ghi log vinh danh
            $msg = "🔥 MA THẦN ĐÃ BỊ TIÊU DIỆT! Người kết liễu: $uName. Phần thưởng vinh quang đang được phát!";
            $stmtChat = $conn->prepare("INSERT INTO chat (username, message, color) VALUES ('Hệ Thống', ?, '#ef4444')");
            $stmtChat->bind_param("s", $msg);
            $stmtChat->execute();
            $stmtChat->close();
            
            // 🖋️ GHI VÀO SỬ KÝ
            require_once 'lore_helper.php';
            $bossName = $boss['name'] ?? 'Ma Thần';
            $loreDesc = generateBossKillLore($uName, $bossName);
            recordServerLore($conn, 'boss', "Ma Thần {$bossName} Gục Ngã", $loreDesc, 2);

            // 🏆 PHÁT THƯỞNG THEO PERCENT TIER ĐÓNG GÓP
            distributeBossRewards($bossId, $conn);
        }

        $conn->commit();
        echo json_encode([
            'success' => true, 
            'damage' => $actualDamage, 
            'message' => $mechanicMsg
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

/**
 * 📅 HÀM KIỂM TRA VÀ HỒI SINH BOSS THEO LỊCH TRÌNH CỐ ĐỊNH (9:00, 15:00, 21:00)
 */
function checkAndSpawnBoss(mysqli $conn): array {
    $boss = $conn->query("SELECT * FROM world_boss WHERE id = 1")->fetch_assoc();
    if (!$boss) {
        return ['boss' => null, 'next_spawn_time' => '--', 'next_spawn_epoch' => 0];
    }
    
    $scheduleHours = [9, 15, 21];
    $now = time();
    $currentDate = date('Y-m-d');
    
    $todaySpawns = [];
    foreach ($scheduleHours as $hour) {
        $todaySpawns[] = strtotime("$currentDate $hour:00:00");
    }
    sort($todaySpawns);
    
    $lastApplicableSpawn = 0;
    $nextUpcomingSpawn = 0;
    
    foreach ($todaySpawns as $time) {
        if ($now >= $time) {
            $lastApplicableSpawn = $time;
        } else {
            if ($nextUpcomingSpawn === 0) {
                $nextUpcomingSpawn = $time;
            }
        }
    }
    
    if ($nextUpcomingSpawn === 0) {
        $tomorrowDate = date('Y-m-d', strtotime('+1 day'));
        $nextUpcomingSpawn = strtotime("$tomorrowDate 09:00:00");
    }
    
    if ($lastApplicableSpawn === 0) {
        $yesterdayDate = date('Y-m-d', strtotime('-1 day'));
        $lastApplicableSpawn = strtotime("$yesterdayDate 21:00:00");
    }
    
    $lastSpawnTs = strtotime($boss['last_spawn'] ?? '2000-01-01 00:00:00');
    if ($boss['status'] === 'defeated' && $lastSpawnTs < $lastApplicableSpawn) {
        // Hồi sinh Ma Thần với đầy 100% HP
        $maxHp = 500000000;
        $conn->query("
            UPDATE world_boss 
            SET hp = $maxHp, 
                max_hp = $maxHp, 
                status = 'active', 
                last_spawn = NOW() 
            WHERE id = 1
        ");
        
        // Reset bảng sát thương sự kiện cũ
        $conn->query("DELETE FROM world_boss_damage WHERE boss_id = 1");
        
        // Gửi thông báo hệ thống
        $conn->query("INSERT INTO chat (username, message, color) VALUES ('Hệ Thống', '🌋 MA THẦN HỦY DIỆT đã hồi sinh! Hãy tiến vào chiến trường tranh đoạt S-Tier!', '#ef4444')");
        
        // Truy vấn lại thông tin mới
        $boss = $conn->query("SELECT * FROM world_boss WHERE id = 1")->fetch_assoc();
    }
    
    return [
        'boss' => $boss,
        'next_spawn_time' => date('Y-m-d H:i:s', $nextUpcomingSpawn),
        'next_spawn_epoch' => $nextUpcomingSpawn
    ];
}

/**
 * 🏆 PHÂN PHỐI PHẦN THƯỞNG WORLD BOSS THEO TỶ LỆ PHẦN TRĂM SÁT THƯƠNG ĐÓNG GÓP
 * Top 10% nhận S-tier, Top 30% nhận A-tier, còn lại nhận B-tier.
 */
function distributeBossRewards(int $bossId, mysqli $conn): void {
    // Lấy danh sách người tham chiến sắp xếp theo damage giảm dần
    $participants = $conn->query("
        SELECT d.user_id, d.damage, u.Name
        FROM world_boss_damage d
        JOIN users u ON d.user_id = u.Iduser
        WHERE d.boss_id = $bossId
        ORDER BY d.damage DESC
    ")->fetch_all(MYSQLI_ASSOC);

    $totalCount = count($participants);
    if ($totalCount === 0) return;

    foreach ($participants as $rank => $p) {
        $rankNumber = $rank + 1;
        $pct = ($rankNumber / $totalCount) * 100;
        $uid = (int)$p['user_id'];

        if ($pct <= 10) {
            // 🥇 S-tier (Top 10%)
            deliverReward($uid, ['reward_type' => 'money', 'reward_value' => 5000000], $conn);
            deliverReward($uid, ['reward_type' => 'item',  'reward_value' => 'theme:1'],  $conn);
            createNotification(
                $conn, $uid, 'event_update',
                "🏆 World Boss — Hạng S-Tier (Top 10%!)",
                "Chúc mừng! Sát thương đóng góp của bạn thuộc Top 10% chiến trường! Nhận 5M GTLM và Chủ đề Mùa giải đặc biệt!",
                '🔥', 'world_boss.php', $bossId, true
            );
        } elseif ($pct <= 30) {
            // 🥈 A-tier (Top 30%)
            deliverReward($uid, ['reward_type' => 'money', 'reward_value' => 2500000], $conn);
            deliverReward($uid, ['reward_type' => 'item',  'reward_value' => 'cursor:1'],  $conn);
            createNotification(
                $conn, $uid, 'event_update',
                "🏆 World Boss — Hạng A-Tier (Top 30%!)",
                "Chúc mừng! Sát thương đóng góp của bạn thuộc Top 30% chiến trường! Nhận 2.5M GTLM và Trỏ chuột Ma thuật!",
                '🌟', 'world_boss.php', $bossId, true
            );
        } else {
            // 🥉 B-tier (Còn lại)
            deliverReward($uid, ['reward_type' => 'money', 'reward_value' => 500000], $conn);
            createNotification(
                $conn, $uid, 'event_update',
                "🏆 World Boss — Hạng B-Tier (Tham chiến!)",
                "Cảm ơn bạn đã cống hiến sức lực tiêu diệt Ma Thần! Nhận 500,000 GTLM thưởng vinh danh!",
                '🛡️', 'world_boss.php', $bossId, true
            );
        }
    }

    // Thông báo toàn server
    $top1Name = $participants[0]['Name'] ?? 'Vô Danh';
    $top1Dmg  = number_format($participants[0]['damage'] ?? 0);
    $conn->query("INSERT INTO chat (username, message, color)
        VALUES ('Hệ Thống',
                '🏆 PHẦN THƯỜNG WORLD BOSS ĐÃ ĐƯỢC PHÁT! Top 1: $top1Name ($top1Dmg sát thương). Xin chúc mừng toàn bộ dũng sĩ!',
                '#f59e0b')");
}

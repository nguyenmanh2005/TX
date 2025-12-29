<?php
/**
 * API xử lý Profile System
 * 
 * Actions:
 * - get_profile: Lấy thông tin profile của user
 * - update_profile: Cập nhật profile
 * - get_visits: Lấy lịch sử ghé thăm
 * - record_visit: Ghi lại lượt ghé thăm
 * - get_recent_visitors: Lấy danh sách người ghé thăm gần đây
 */

session_start();
require 'db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập!']);
    exit;
}

$userId = $_SESSION['Iduser'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Kiểm tra bảng users tồn tại (quan trọng nhất)
$checkUsersTable = $conn->query("SHOW TABLES LIKE 'users'");
if (!$checkUsersTable || $checkUsersTable->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Bảng users không tồn tại! Vui lòng chạy file RESTORE_USERS_TABLE.sql hoặc ALL_DATABASE_TABLES.sql trước.']);
    exit;
}

// Các bảng khác có thể chưa tồn tại, sẽ xử lý trong từng action

switch ($action) {
    case 'get_balance':
        // Lấy số dư hiện tại
        try {
            $balanceSql = "SELECT Money FROM users WHERE Iduser = ?";
            $balanceStmt = $conn->prepare($balanceSql);
            if (!$balanceStmt) {
                throw new Exception("Lỗi prepare statement: " . $conn->error);
            }
            $balanceStmt->bind_param("i", $userId);
            $balanceStmt->execute();
            $balanceResult = $balanceStmt->get_result();
            
            if ($balanceResult && $balanceResult->num_rows > 0) {
                $balanceRow = $balanceResult->fetch_assoc();
                echo json_encode([
                    'success' => true,
                    'balance' => (float)$balanceRow['Money']
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Không tìm thấy thông tin người dùng'
                ]);
            }
            $balanceStmt->close();
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        break;
        
    case 'get_profile':
        // Lấy thông tin profile
        $targetUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $userId;
        
        try {
            // Lấy thông tin user cơ bản
            $userSql = "SELECT u.Iduser, u.Name, u.Money, u.ImageURL, u.active_title_id,
                        a.icon as title_icon, a.name as title_name
                        FROM users u
                        LEFT JOIN achievements a ON u.active_title_id = a.id
                        WHERE u.Iduser = ?";
            $userStmt = $conn->prepare($userSql);
            if (!$userStmt) {
                throw new Exception("Lỗi prepare statement: " . $conn->error);
            }
            $userStmt->bind_param("i", $targetUserId);
            $userStmt->execute();
            $user = $userStmt->get_result()->fetch_assoc();
            $userStmt->close();
            
            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'User không tồn tại!']);
                exit;
            }
            
            // Lấy thông tin profile mở rộng (nếu bảng tồn tại)
            $profile = [];
            $checkProfileTable = $conn->query("SHOW TABLES LIKE 'user_profiles'");
            if ($checkProfileTable && $checkProfileTable->num_rows > 0) {
                $profileSql = "SELECT * FROM user_profiles WHERE user_id = ?";
                $profileStmt = $conn->prepare($profileSql);
                if ($profileStmt) {
                    $profileStmt->bind_param("i", $targetUserId);
                    $profileStmt->execute();
                    $profile = $profileStmt->get_result()->fetch_assoc() ?: [];
                    $profileStmt->close();
                }
            }
            
            // Lấy thống kê (nếu bảng tồn tại)
            $stats = [];
            $checkStatsTable = $conn->query("SHOW TABLES LIKE 'user_statistics'");
            if ($checkStatsTable && $checkStatsTable->num_rows > 0) {
                $statsSql = "SELECT * FROM user_statistics WHERE user_id = ?";
                $statsStmt = $conn->prepare($statsSql);
                if ($statsStmt) {
                    $statsStmt->bind_param("i", $targetUserId);
                    $statsStmt->execute();
                    $stats = $statsStmt->get_result()->fetch_assoc() ?: [];
                    $statsStmt->close();
                }
            }
            
            // Lấy số lượt ghé thăm (nếu bảng tồn tại)
            $visitsCount = 0;
            $checkVisitsTable = $conn->query("SHOW TABLES LIKE 'user_visits'");
            if ($checkVisitsTable && $checkVisitsTable->num_rows > 0) {
                $visitsSql = "SELECT COUNT(*) as total FROM user_visits WHERE profile_user_id = ?";
                $visitsStmt = $conn->prepare($visitsSql);
                if ($visitsStmt) {
                    $visitsStmt->bind_param("i", $targetUserId);
                    $visitsStmt->execute();
                    $visitsResult = $visitsStmt->get_result()->fetch_assoc();
                    $visitsCount = $visitsResult ? (int)$visitsResult['total'] : 0;
                    $visitsStmt->close();
                }
                
                // Ghi lại lượt ghé thăm (nếu không phải chính mình)
                if ($targetUserId != $userId) {
                    $recordVisitSql = "INSERT INTO user_visits (profile_user_id, visitor_user_id) VALUES (?, ?)";
                    $recordVisitStmt = $conn->prepare($recordVisitSql);
                    if ($recordVisitStmt) {
                        $recordVisitStmt->bind_param("ii", $targetUserId, $userId);
                        $recordVisitStmt->execute();
                        $recordVisitStmt->close();
                    }
                }
            }
            
            // Thành tựu (nếu bảng tồn tại)
            $achievements = [];
            $checkUserAchievements = $conn->query("SHOW TABLES LIKE 'user_achievements'");
            if ($checkUserAchievements && $checkUserAchievements->num_rows > 0) {
                $achievementsSql = "SELECT ua.unlocked_at, a.name, a.icon, a.description 
                                    FROM user_achievements ua
                                    INNER JOIN achievements a ON ua.achievement_id = a.id
                                    WHERE ua.user_id = ?
                                    ORDER BY ua.unlocked_at DESC
                                    LIMIT 8";
                $achievementsStmt = $conn->prepare($achievementsSql);
                if ($achievementsStmt) {
                    $achievementsStmt->bind_param("i", $targetUserId);
                    $achievementsStmt->execute();
                    $achievementsResult = $achievementsStmt->get_result();
                    while ($row = $achievementsResult->fetch_assoc()) {
                        $achievements[] = $row;
                    }
                    $achievementsStmt->close();
                }
            }
            
            // Game nổi bật (nếu bảng tồn tại)
            $gameHighlights = [];
            $checkGameHistory = $conn->query("SHOW TABLES LIKE 'game_history'");
            if ($checkGameHistory && $checkGameHistory->num_rows > 0) {
                $gamesSql = "SELECT game_name,
                                    COUNT(*) AS plays,
                                    SUM(is_win) AS wins,
                                    SUM(CASE WHEN is_win = 1 THEN win_amount ELSE 0 END) AS total_win_amount,
                                    SUM(bet_amount) AS total_bet_amount,
                                    SUM(win_amount - bet_amount) AS net_profit,
                                    MAX(played_at) AS last_played
                             FROM game_history
                             WHERE user_id = ?
                             GROUP BY game_name
                             ORDER BY plays DESC
                             LIMIT 5";
                $gamesStmt = $conn->prepare($gamesSql);
                if ($gamesStmt) {
                    $gamesStmt->bind_param("i", $targetUserId);
                    $gamesStmt->execute();
                    $gamesResult = $gamesStmt->get_result();
                    while ($row = $gamesResult->fetch_assoc()) {
                        $row['plays'] = (int)$row['plays'];
                        $row['wins'] = (int)$row['wins'];
                        $row['total_win_amount'] = (float)$row['total_win_amount'];
                        $row['total_bet_amount'] = (float)$row['total_bet_amount'];
                        $row['net_profit'] = (float)$row['net_profit'];
                        $gameHighlights[] = $row;
                    }
                    $gamesStmt->close();
                }
            }
            
            // Thông tin Guild (nếu bảng tồn tại)
            $guild = null;
            $checkGuilds = $conn->query("SHOW TABLES LIKE 'guilds'");
            if ($checkGuilds && $checkGuilds->num_rows > 0) {
                $guildSql = "SELECT g.*, gm.role as user_role,
                            (SELECT COUNT(*) FROM guild_members WHERE guild_id = g.id) as member_count
                            FROM guilds g
                            JOIN guild_members gm ON g.id = gm.guild_id
                            WHERE gm.user_id = ?";
                $guildStmt = $conn->prepare($guildSql);
                if ($guildStmt) {
                    $guildStmt->bind_param("i", $targetUserId);
                    $guildStmt->execute();
                    $guildResult = $guildStmt->get_result();
                    if ($guildResult->num_rows > 0) {
                        $guild = $guildResult->fetch_assoc();
                    }
                    $guildStmt->close();
                }
            }
            
            echo json_encode([
                'success' => true,
                'user' => $user,
                'profile' => $profile,
                'statistics' => $stats,
                'visits_count' => $visitsCount,
                'achievements' => $achievements,
                'games' => $gameHighlights,
                'guild' => $guild
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ]);
        }
        break;
        
    case 'update_profile':
        // Cập nhật profile
        $bio = trim($_POST['bio'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $socialFacebook = trim($_POST['social_facebook'] ?? '');
        $socialTwitter = trim($_POST['social_twitter'] ?? '');
        $socialDiscord = trim($_POST['social_discord'] ?? '');
        $favoriteColor = trim($_POST['favorite_color'] ?? '');
        $showEmail = isset($_POST['show_email']) ? 1 : 0;
        $showStatistics = isset($_POST['show_statistics']) ? 1 : 0;
        $profileTheme = trim($_POST['profile_theme'] ?? '');
        $customCss = trim($_POST['custom_css'] ?? '');
        
        // Kiểm tra đã có profile chưa
        $checkSql = "SELECT user_id FROM user_profiles WHERE user_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("i", $userId);
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->num_rows > 0;
        $checkStmt->close();
        
        if ($exists) {
            $updateSql = "UPDATE user_profiles SET 
                         bio = ?, location = ?, website = ?, 
                         social_facebook = ?, social_twitter = ?, social_discord = ?,
                         favorite_color = ?, show_email = ?, show_statistics = ?,
                         profile_theme = ?, custom_css = ?
                         WHERE user_id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("sssssssiissi", $bio, $location, $website,
                                   $socialFacebook, $socialTwitter, $socialDiscord,
                                   $favoriteColor, $showEmail, $showStatistics,
                                   $profileTheme, $customCss, $userId);
        } else {
            $insertSql = "INSERT INTO user_profiles 
                         (user_id, bio, location, website, 
                          social_facebook, social_twitter, social_discord,
                          favorite_color, show_email, show_statistics,
                          profile_theme, custom_css) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->bind_param("isssssssiiss", $userId, $bio, $location, $website,
                                   $socialFacebook, $socialTwitter, $socialDiscord,
                                   $favoriteColor, $showEmail, $showStatistics,
                                   $profileTheme, $customCss);
        }
        
        $stmt = $exists ? $updateStmt : $insertStmt;
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Cập nhật profile thành công!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật profile!']);
        }
        $stmt->close();
        break;
        
    case 'get_recent_visitors':
        // Lấy danh sách người ghé thăm gần đây
        $limit = (int)($_GET['limit'] ?? 20);
        
        $sql = "SELECT DISTINCT uv.visitor_user_id, u.Name, u.ImageURL, u.active_title_id,
                a.icon as title_icon, a.name as title_name,
                MAX(uv.visited_at) as last_visit
                FROM user_visits uv
                INNER JOIN users u ON uv.visitor_user_id = u.Iduser
                LEFT JOIN achievements a ON u.active_title_id = a.id
                WHERE uv.profile_user_id = ?
                GROUP BY uv.visitor_user_id, u.Name, u.ImageURL, u.active_title_id, a.icon, a.name
                ORDER BY last_visit DESC
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $visitors = [];
        while ($row = $result->fetch_assoc()) {
            $visitors[] = $row;
        }
        $stmt->close();
        
        echo json_encode(['success' => true, 'visitors' => $visitors]);
        break;
        
    case 'search':
        // Tìm kiếm user theo tên
        $query = isset($_GET['q']) ? trim($_GET['q']) : '';
        
        if (strlen($query) < 2) {
            echo json_encode(['success' => false, 'message' => 'Từ khóa tìm kiếm phải có ít nhất 2 ký tự!']);
            exit;
        }
        
        $searchSql = "SELECT Iduser as id, Name as name, ImageURL as avatar, Money 
                     FROM users 
                     WHERE Name LIKE ? AND Iduser != ?
                     ORDER BY Name ASC 
                     LIMIT 10";
        $searchStmt = $conn->prepare($searchSql);
        $searchPattern = '%' . $query . '%';
        $searchStmt->bind_param("si", $searchPattern, $userId);
        $searchStmt->execute();
        $searchResult = $searchStmt->get_result();
        
        $users = [];
        while ($row = $searchResult->fetch_assoc()) {
            $users[] = $row;
        }
        $searchStmt->close();
        
        echo json_encode(['success' => true, 'users' => $users]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Action không hợp lệ!']);
        break;
}

$conn->close();


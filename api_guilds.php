<?php
/**
 * API xử lý các thao tác với Guild
 * 
 * Actions:
 * - create: Tạo guild mới
 * - join: Tham gia guild (gửi đơn hoặc accept)
 * - leave: Rời guild
 * - invite: Mời người chơi vào guild
 * - kick: Kick thành viên (chỉ leader/officer)
 * - promote: Thăng chức thành viên
 * - demote: Giáng chức thành viên
 * - transfer: Chuyển quyền leader
 * - chat: Gửi tin nhắn trong guild chat
 * - get_chat: Lấy tin nhắn guild chat
 * - get_members: Lấy danh sách thành viên
 * - get_info: Lấy thông tin guild
 * - search: Tìm kiếm guild
 * - apply: Gửi đơn xin vào guild
 * - accept_application: Chấp nhận đơn xin vào
 * - reject_application: Từ chối đơn xin vào
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

// Kiểm tra bảng tồn tại
$checkGuilds = $conn->query("SHOW TABLES LIKE 'guilds'");
if (!$checkGuilds || $checkGuilds->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Hệ thống Guild chưa được kích hoạt! Vui lòng chạy file create_guild_tables.sql trước.']);
    exit;
}

/**
 * Kiểm tra user có trong guild không
 */
function isUserInGuild($conn, $userId, $guildId) {
    $sql = "SELECT role FROM guild_members WHERE guild_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $guildId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $member = $result->fetch_assoc();
    $stmt->close();
    return $member ? $member['role'] : false;
}

/**
 * Kiểm tra user có phải leader hoặc officer không
 */
function isLeaderOrOfficer($conn, $userId, $guildId) {
    $role = isUserInGuild($conn, $userId, $guildId);
    return $role === 'leader' || $role === 'officer';
}

/**
 * Lấy số thành viên hiện tại của guild
 */
function getGuildMemberCount($conn, $guildId) {
    $sql = "SELECT COUNT(*) as count FROM guild_members WHERE guild_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $guildId);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    return (int)$data['count'];
}

// ============================================
// XỬ LÝ CÁC ACTION
// ============================================

switch ($action) {
    case 'create':
        // Tạo guild mới
        $name = trim($_POST['name'] ?? '');
        $tag = strtoupper(trim($_POST['tag'] ?? ''));
        $description = trim($_POST['description'] ?? '');
        
        if (empty($name) || empty($tag)) {
            echo json_encode(['success' => false, 'message' => 'Tên và tag guild không được để trống!']);
            exit;
        }
        
        if (strlen($tag) < 2 || strlen($tag) > 10) {
            echo json_encode(['success' => false, 'message' => 'Tag phải từ 2-10 ký tự!']);
            exit;
        }
        
        // Kiểm tra user đã có guild chưa
        $checkMember = $conn->query("SELECT guild_id FROM guild_members WHERE user_id = $userId");
        if ($checkMember && $checkMember->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Bạn đã có guild rồi!']);
            exit;
        }
        
        // Kiểm tra tên và tag đã tồn tại chưa
        $checkName = $conn->prepare("SELECT id FROM guilds WHERE name = ? OR tag = ?");
        $checkName->bind_param("ss", $name, $tag);
        $checkName->execute();
        $result = $checkName->get_result();
        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Tên hoặc tag guild đã tồn tại!']);
            exit;
        }
        $checkName->close();
        
        // Tạo guild
        $conn->begin_transaction();
        try {
            $sql = "INSERT INTO guilds (name, tag, description, leader_id) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $name, $tag, $description, $userId);
            $stmt->execute();
            $guildId = $conn->insert_id;
            $stmt->close();
            
            // Thêm leader vào guild_members
            $sql2 = "INSERT INTO guild_members (guild_id, user_id, role) VALUES (?, ?, 'leader')";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bind_param("ii", $guildId, $userId);
            $stmt2->execute();
            $stmt2->close();
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Tạo guild thành công!', 'guild_id' => $guildId]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        break;
        
    case 'join':
        // Tham gia guild (accept invite hoặc join trực tiếp nếu public)
        $guildId = (int)($_POST['guild_id'] ?? 0);
        
        if (!$guildId) {
            echo json_encode(['success' => false, 'message' => 'Guild ID không hợp lệ!']);
            exit;
        }
        
        // Kiểm tra user đã có guild chưa
        $checkMember = $conn->query("SELECT guild_id FROM guild_members WHERE user_id = $userId");
        if ($checkMember && $checkMember->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Bạn đã có guild rồi!']);
            exit;
        }
        
        // Kiểm tra guild tồn tại
        $checkGuild = $conn->prepare("SELECT id, max_members FROM guilds WHERE id = ?");
        $checkGuild->bind_param("i", $guildId);
        $checkGuild->execute();
        $guild = $checkGuild->get_result()->fetch_assoc();
        $checkGuild->close();
        
        if (!$guild) {
            echo json_encode(['success' => false, 'message' => 'Guild không tồn tại!']);
            exit;
        }
        
        // Kiểm tra số thành viên
        $memberCount = getGuildMemberCount($conn, $guildId);
        if ($memberCount >= $guild['max_members']) {
            echo json_encode(['success' => false, 'message' => 'Guild đã đầy!']);
            exit;
        }
        
        // Thêm thành viên
        $sql = "INSERT INTO guild_members (guild_id, user_id, role) VALUES (?, ?, 'member')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $guildId, $userId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Tham gia guild thành công!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi tham gia guild!']);
        }
        $stmt->close();
        break;
        
    case 'leave':
        // Rời guild
        $guildId = (int)($_POST['guild_id'] ?? 0);
        
        if (!$guildId) {
            echo json_encode(['success' => false, 'message' => 'Guild ID không hợp lệ!']);
            exit;
        }
        
        $role = isUserInGuild($conn, $userId, $guildId);
        if (!$role) {
            echo json_encode(['success' => false, 'message' => 'Bạn không phải thành viên của guild này!']);
            exit;
        }
        
        // Nếu là leader, không cho rời (phải transfer trước)
        if ($role === 'leader') {
            echo json_encode(['success' => false, 'message' => 'Leader không thể rời guild! Hãy chuyển quyền leader trước.']);
            exit;
        }
        
        // Xóa thành viên
        $sql = "DELETE FROM guild_members WHERE guild_id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $guildId, $userId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Rời guild thành công!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi rời guild!']);
        }
        $stmt->close();
        break;
        
    case 'chat':
        // Gửi tin nhắn trong guild chat
        $guildId = (int)($_POST['guild_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        
        if (!$guildId || empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ!']);
            exit;
        }
        
        // Kiểm tra user có trong guild không
        if (!isUserInGuild($conn, $userId, $guildId)) {
            echo json_encode(['success' => false, 'message' => 'Bạn không phải thành viên của guild này!']);
            exit;
        }
        
        // Lưu tin nhắn
        $sql = "INSERT INTO guild_chat (guild_id, user_id, message) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $guildId, $userId, $message);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Gửi tin nhắn thành công!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi gửi tin nhắn!']);
        }
        $stmt->close();
        break;
        
    case 'get_chat':
        // Lấy tin nhắn guild chat
        $guildId = (int)($_GET['guild_id'] ?? 0);
        $limit = (int)($_GET['limit'] ?? 50);
        
        if (!$guildId) {
            echo json_encode(['success' => false, 'message' => 'Guild ID không hợp lệ!']);
            exit;
        }
        
        // Kiểm tra user có trong guild không
        if (!isUserInGuild($conn, $userId, $guildId)) {
            echo json_encode(['success' => false, 'message' => 'Bạn không phải thành viên của guild này!']);
            exit;
        }
        
        // Lấy tin nhắn
        $sql = "SELECT gc.*, u.Name, u.ImageURL 
                FROM guild_chat gc
                JOIN users u ON gc.user_id = u.Iduser
                WHERE gc.guild_id = ?
                ORDER BY gc.created_at DESC
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $guildId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        $stmt->close();
        
        echo json_encode(['success' => true, 'messages' => array_reverse($messages)]);
        break;
        
    case 'get_members':
        // Lấy danh sách thành viên
        $guildId = (int)($_GET['guild_id'] ?? 0);
        
        if (!$guildId) {
            echo json_encode(['success' => false, 'message' => 'Guild ID không hợp lệ!']);
            exit;
        }
        
        // Kiểm tra user có trong guild không
        if (!isUserInGuild($conn, $userId, $guildId)) {
            echo json_encode(['success' => false, 'message' => 'Bạn không phải thành viên của guild này!']);
            exit;
        }
        
        // Lấy danh sách thành viên
        $sql = "SELECT gm.*, u.Name, u.ImageURL, u.Money, u.active_title_id,
                a.icon as title_icon, a.name as title_name
                FROM guild_members gm
                JOIN users u ON gm.user_id = u.Iduser
                LEFT JOIN achievements a ON u.active_title_id = a.id
                WHERE gm.guild_id = ?
                ORDER BY 
                    CASE gm.role
                        WHEN 'leader' THEN 1
                        WHEN 'officer' THEN 2
                        WHEN 'member' THEN 3
                    END,
                    gm.contribution DESC,
                    gm.joined_at ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $guildId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $members = [];
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
        $stmt->close();
        
        echo json_encode(['success' => true, 'members' => $members]);
        break;
        
    case 'get_info':
        // Lấy thông tin guild
        $guildId = (int)($_GET['guild_id'] ?? 0);
        
        if (!$guildId) {
            echo json_encode(['success' => false, 'message' => 'Guild ID không hợp lệ!']);
            exit;
        }
        
        // Lấy thông tin guild
        $sql = "SELECT g.*, u.Name as leader_name, u.ImageURL as leader_avatar,
                (SELECT COUNT(*) FROM guild_members WHERE guild_id = g.id) as member_count
                FROM guilds g
                JOIN users u ON g.leader_id = u.Iduser
                WHERE g.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $guildId);
        $stmt->execute();
        $guild = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$guild) {
            echo json_encode(['success' => false, 'message' => 'Guild không tồn tại!']);
            exit;
        }
        
        // Kiểm tra user có trong guild không
        $userRole = isUserInGuild($conn, $userId, $guildId);
        $guild['user_role'] = $userRole;
        $guild['is_member'] = $userRole !== false;
        
        echo json_encode(['success' => true, 'guild' => $guild]);
        break;
        
    case 'search':
        // Tìm kiếm guild
        $keyword = trim($_GET['keyword'] ?? '');
        $limit = (int)($_GET['limit'] ?? 20);
        
        if (empty($keyword)) {
            echo json_encode(['success' => false, 'message' => 'Nhập từ khóa tìm kiếm!']);
            exit;
        }
        
        $searchTerm = "%$keyword%";
        $sql = "SELECT g.*, u.Name as leader_name,
                (SELECT COUNT(*) FROM guild_members WHERE guild_id = g.id) as member_count
                FROM guilds g
                JOIN users u ON g.leader_id = u.Iduser
                WHERE g.name LIKE ? OR g.tag LIKE ?
                ORDER BY g.level DESC, g.experience DESC
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $searchTerm, $searchTerm, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $guilds = [];
        while ($row = $result->fetch_assoc()) {
            $guilds[] = $row;
        }
        $stmt->close();
        
        echo json_encode(['success' => true, 'guilds' => $guilds]);
        break;
        
    case 'apply':
        // Gửi đơn xin vào guild
        $guildId = (int)($_POST['guild_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        
        if (!$guildId) {
            echo json_encode(['success' => false, 'message' => 'Guild ID không hợp lệ!']);
            exit;
        }
        
        // Kiểm tra user đã có guild chưa
        $checkMember = $conn->query("SELECT guild_id FROM guild_members WHERE user_id = $userId");
        if ($checkMember && $checkMember->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Bạn đã có guild rồi!']);
            exit;
        }
        
        // Kiểm tra đã gửi đơn chưa
        $checkApp = $conn->prepare("SELECT id FROM guild_applications WHERE guild_id = ? AND user_id = ? AND status = 'pending'");
        $checkApp->bind_param("ii", $guildId, $userId);
        $checkApp->execute();
        if ($checkApp->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Bạn đã gửi đơn xin vào guild này rồi!']);
            exit;
        }
        $checkApp->close();
        
        // Tạo đơn xin
        $sql = "INSERT INTO guild_applications (guild_id, user_id, message) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $guildId, $userId, $message);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Gửi đơn xin vào guild thành công!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi gửi đơn!']);
        }
        $stmt->close();
        break;
        
    case 'accept_application':
        // Chấp nhận đơn xin vào
        $applicationId = (int)($_POST['application_id'] ?? 0);
        
        if (!$applicationId) {
            echo json_encode(['success' => false, 'message' => 'Application ID không hợp lệ!']);
            exit;
        }
        
        // Kiểm tra quyền
        $appSql = "SELECT ga.*, g.max_members FROM guild_applications ga
                   JOIN guilds g ON ga.guild_id = g.id
                   WHERE ga.id = ? AND ga.status = 'pending'";
        $stmt = $conn->prepare($appSql);
        $stmt->bind_param("i", $applicationId);
        $stmt->execute();
        $application = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$application) {
            echo json_encode(['success' => false, 'message' => 'Đơn xin không tồn tại hoặc đã được xử lý!']);
            exit;
        }
        
        // Kiểm tra quyền duyệt đơn
        if (!isLeaderOrOfficer($conn, $userId, $application['guild_id'])) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền duyệt đơn!']);
            exit;
        }
        
        // Kiểm tra số thành viên
        $memberCount = getGuildMemberCount($conn, $application['guild_id']);
        if ($memberCount >= $application['max_members']) {
            echo json_encode(['success' => false, 'message' => 'Guild đã đầy!']);
            exit;
        }
        
        // Chấp nhận đơn
        $conn->begin_transaction();
        try {
            // Thêm thành viên
            $sql1 = "INSERT INTO guild_members (guild_id, user_id, role) VALUES (?, ?, 'member')";
            $stmt1 = $conn->prepare($sql1);
            $stmt1->bind_param("ii", $application['guild_id'], $application['user_id']);
            $stmt1->execute();
            $stmt1->close();
            
            // Cập nhật trạng thái đơn
            $sql2 = "UPDATE guild_applications SET status = 'accepted', reviewed_at = NOW(), reviewed_by = ? WHERE id = ?";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bind_param("ii", $userId, $applicationId);
            $stmt2->execute();
            $stmt2->close();
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Chấp nhận đơn thành công!']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        break;
        
    case 'reject_application':
        // Từ chối đơn xin vào
        $applicationId = (int)($_POST['application_id'] ?? 0);
        
        if (!$applicationId) {
            echo json_encode(['success' => false, 'message' => 'Application ID không hợp lệ!']);
            exit;
        }
        
        // Kiểm tra quyền
        $appSql = "SELECT guild_id FROM guild_applications WHERE id = ? AND status = 'pending'";
        $stmt = $conn->prepare($appSql);
        $stmt->bind_param("i", $applicationId);
        $stmt->execute();
        $application = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$application) {
            echo json_encode(['success' => false, 'message' => 'Đơn xin không tồn tại hoặc đã được xử lý!']);
            exit;
        }
        
        // Kiểm tra quyền duyệt đơn
        if (!isLeaderOrOfficer($conn, $userId, $application['guild_id'])) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền duyệt đơn!']);
            exit;
        }
        
        // Từ chối đơn
        $sql = "UPDATE guild_applications SET status = 'rejected', reviewed_at = NOW(), reviewed_by = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $userId, $applicationId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Từ chối đơn thành công!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi từ chối đơn!']);
        }
        $stmt->close();
        break;
        
    case 'kick':
        // Kick thành viên
        $guildId = (int)($_POST['guild_id'] ?? 0);
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        
        if (!$guildId || !$targetUserId) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ!']);
            exit;
        }
        
        // Kiểm tra quyền
        if (!isLeaderOrOfficer($conn, $userId, $guildId)) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền kick thành viên!']);
            exit;
        }
        
        // Không cho kick leader
        $targetRole = isUserInGuild($conn, $targetUserId, $guildId);
        if ($targetRole === 'leader') {
            echo json_encode(['success' => false, 'message' => 'Không thể kick leader!']);
            exit;
        }
        
        // Không cho kick chính mình
        if ($targetUserId == $userId) {
            echo json_encode(['success' => false, 'message' => 'Bạn không thể kick chính mình!']);
            exit;
        }
        
        // Kick thành viên
        $sql = "DELETE FROM guild_members WHERE guild_id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $guildId, $targetUserId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Kick thành viên thành công!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi kick thành viên!']);
        }
        $stmt->close();
        break;
        
    case 'promote':
        // Thăng chức thành viên (member -> officer)
        $guildId = (int)($_POST['guild_id'] ?? 0);
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        
        if (!$guildId || !$targetUserId) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ!']);
            exit;
        }
        
        // Chỉ leader mới được promote
        $userRole = isUserInGuild($conn, $userId, $guildId);
        if ($userRole !== 'leader') {
            echo json_encode(['success' => false, 'message' => 'Chỉ leader mới được thăng chức thành viên!']);
            exit;
        }
        
        // Không cho promote chính mình
        if ($targetUserId == $userId) {
            echo json_encode(['success' => false, 'message' => 'Bạn không thể thăng chức chính mình!']);
            exit;
        }
        
        // Thăng chức
        $sql = "UPDATE guild_members SET role = 'officer' WHERE guild_id = ? AND user_id = ? AND role = 'member'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $guildId, $targetUserId);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Thăng chức thành công!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi thăng chức!']);
        }
        $stmt->close();
        break;
        
    case 'demote':
        // Giáng chức thành viên (officer -> member)
        $guildId = (int)($_POST['guild_id'] ?? 0);
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        
        if (!$guildId || !$targetUserId) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ!']);
            exit;
        }
        
        // Chỉ leader mới được demote
        $userRole = isUserInGuild($conn, $userId, $guildId);
        if ($userRole !== 'leader') {
            echo json_encode(['success' => false, 'message' => 'Chỉ leader mới được giáng chức thành viên!']);
            exit;
        }
        
        // Không cho demote chính mình
        if ($targetUserId == $userId) {
            echo json_encode(['success' => false, 'message' => 'Bạn không thể giáng chức chính mình!']);
            exit;
        }
        
        // Giáng chức
        $sql = "UPDATE guild_members SET role = 'member' WHERE guild_id = ? AND user_id = ? AND role = 'officer'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $guildId, $targetUserId);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Giáng chức thành công!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi giáng chức!']);
        }
        $stmt->close();
        break;
        
    case 'transfer':
        // Chuyển quyền leader
        $guildId = (int)($_POST['guild_id'] ?? 0);
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        
        if (!$guildId || !$targetUserId) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ!']);
            exit;
        }
        
        // Chỉ leader hiện tại mới được transfer
        $userRole = isUserInGuild($conn, $userId, $guildId);
        if ($userRole !== 'leader') {
            echo json_encode(['success' => false, 'message' => 'Chỉ leader mới được chuyển quyền!']);
            exit;
        }
        
        // Không cho transfer cho chính mình
        if ($targetUserId == $userId) {
            echo json_encode(['success' => false, 'message' => 'Bạn không thể chuyển quyền cho chính mình!']);
            exit;
        }
        
        // Kiểm tra target user có trong guild không
        $targetRole = isUserInGuild($conn, $targetUserId, $guildId);
        if (!$targetRole) {
            echo json_encode(['success' => false, 'message' => 'Người này không phải thành viên của guild!']);
            exit;
        }
        
        // Chuyển quyền
        $conn->begin_transaction();
        try {
            // Cập nhật leader trong guilds
            $sql1 = "UPDATE guilds SET leader_id = ? WHERE id = ?";
            $stmt1 = $conn->prepare($sql1);
            $stmt1->bind_param("ii", $targetUserId, $guildId);
            $stmt1->execute();
            $stmt1->close();
            
            // Cập nhật role của leader cũ thành officer
            $sql2 = "UPDATE guild_members SET role = 'officer' WHERE guild_id = ? AND user_id = ?";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bind_param("ii", $guildId, $userId);
            $stmt2->execute();
            $stmt2->close();
            
            // Cập nhật role của leader mới thành leader
            $sql3 = "UPDATE guild_members SET role = 'leader' WHERE guild_id = ? AND user_id = ?";
            $stmt3 = $conn->prepare($sql3);
            $stmt3->bind_param("ii", $guildId, $targetUserId);
            $stmt3->execute();
            $stmt3->close();
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Chuyển quyền leader thành công!']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        break;
        
    case 'get_leaderboard':
        // Lấy bảng xếp hạng guild
        $type = $_GET['type'] ?? 'overall';
        $limit = (int)($_GET['limit'] ?? 50);
        
        $sql = "SELECT g.*, 
                u.Name as leader_name, u.ImageURL as leader_avatar,
                (SELECT COUNT(*) FROM guild_members WHERE guild_id = g.id) as member_count,
                (SELECT SUM(contribution) FROM guild_members WHERE guild_id = g.id) as total_contribution,
                (SELECT SUM(total_money_won) FROM guild_stats WHERE guild_id = g.id) as total_money_won
                FROM guilds g
                JOIN users u ON g.leader_id = u.Iduser";
        
        if ($type === 'level') {
            $sql .= " ORDER BY g.level DESC, g.experience DESC";
        } else if ($type === 'members') {
            $sql .= " ORDER BY member_count DESC";
        } else if ($type === 'contribution') {
            $sql .= " ORDER BY total_contribution DESC";
        } else {
            // overall - theo experience
            $sql .= " ORDER BY g.experience DESC, g.level DESC";
        }
        
        $sql .= " LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $guilds = [];
        $rank = 1;
        while ($row = $result->fetch_assoc()) {
            $row['rank'] = $rank++;
            $guilds[] = $row;
        }
        $stmt->close();
        
        echo json_encode(['success' => true, 'guilds' => $guilds, 'type' => $type]);
        break;
        
    case 'get_applications':
        // Lấy danh sách đơn xin vào guild
        $guildId = (int)($_GET['guild_id'] ?? 0);
        
        if (!$guildId) {
            echo json_encode(['success' => false, 'message' => 'Guild ID không hợp lệ!']);
            exit;
        }
        
        // Kiểm tra quyền (chỉ leader/officer mới xem được)
        if (!isLeaderOrOfficer($conn, $userId, $guildId)) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền xem đơn xin vào guild!']);
            exit;
        }
        
        // Lấy danh sách đơn xin đang pending
        $sql = "SELECT ga.*, u.Name, u.ImageURL, u.Money, u.active_title_id,
                a.icon as title_icon, a.name as title_name
                FROM guild_applications ga
                JOIN users u ON ga.user_id = u.Iduser
                LEFT JOIN achievements a ON u.active_title_id = a.id
                WHERE ga.guild_id = ? AND ga.status = 'pending'
                ORDER BY ga.created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $guildId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $applications = [];
        while ($row = $result->fetch_assoc()) {
            $applications[] = $row;
        }
        $stmt->close();
        
        echo json_encode(['success' => true, 'applications' => $applications]);
        break;
        
    case 'get_guild_rank':
        // Lấy vị trí xếp hạng của guild
        $guildId = (int)($_GET['guild_id'] ?? 0);
        
        if (!$guildId) {
            echo json_encode(['success' => false, 'message' => 'Guild ID không hợp lệ!']);
            exit;
        }
        
        // Lấy thông tin guild
        $guildSql = "SELECT g.*, 
                     (SELECT COUNT(*) FROM guild_members WHERE guild_id = g.id) as member_count
                     FROM guilds g WHERE g.id = ?";
        $guildStmt = $conn->prepare($guildSql);
        $guildStmt->bind_param("i", $guildId);
        $guildStmt->execute();
        $guild = $guildStmt->get_result()->fetch_assoc();
        $guildStmt->close();
        
        if (!$guild) {
            echo json_encode(['success' => false, 'message' => 'Guild không tồn tại!']);
            exit;
        }
        
        // Tính rank
        $rankSql = "SELECT COUNT(*) + 1 as rank FROM guilds 
                    WHERE (experience > ?) OR (experience = ? AND level > ?) OR (experience = ? AND level = ? AND id < ?)";
        $rankStmt = $conn->prepare($rankSql);
        $rankStmt->bind_param("iiiiii", 
            $guild['experience'], $guild['experience'], $guild['level'],
            $guild['experience'], $guild['level'], $guildId);
        $rankStmt->execute();
        $rankResult = $rankStmt->get_result();
        $rankData = $rankResult->fetch_assoc();
        $rankStmt->close();
        
        // Tổng số guild
        $totalSql = "SELECT COUNT(*) as total FROM guilds";
        $totalResult = $conn->query($totalSql);
        $totalData = $totalResult->fetch_assoc();
        
        echo json_encode([
            'success' => true, 
            'rank' => (int)$rankData['rank'],
            'total' => (int)$totalData['total'],
            'guild' => $guild
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Action không hợp lệ!']);
        break;
}

$conn->close();


<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

// Load theme
require_once 'load_theme.php';

$userId = $_SESSION['Iduser'];

// Handle AJAX reward claim POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'claim_reward') {
    header('Content-Type: application/json');
    $relationshipId = (int)$_POST['relationship_id'];

    $conn->begin_transaction();
    try {
        // Fetch relationship details and verify ownership
        $stmt = $conn->prepare("
            SELECT um.*, u.Name as mentee_name 
            FROM user_mentor_relationships um
            JOIN users u ON um.mentee_id = u.Iduser
            WHERE um.id = ? AND um.mentor_id = ?
        ");
        $stmt->bind_param("ii", $relationshipId, $userId);
        $stmt->execute();
        $relation = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$relation) {
            throw new Exception("Mối quan hệ sư đồ không hợp lệ!");
        }

        if ($relation['active_days_count'] < 7) {
            throw new Exception("Đệ tử chưa tích lũy đủ 7 ngày hoạt động tích cực!");
        }

        if ($relation['reward_claimed'] == 1) {
            throw new Exception("Bạn đã nhận phần thưởng cho đệ tử này rồi!");
        }

        // Grant 50,000 GTLM to Mentor
        $rewardAmount = 50000;
        $stmt = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
        $stmt->bind_param("ii", $rewardAmount, $userId);
        $stmt->execute();
        $stmt->close();

        // Mark as claimed
        $stmt = $conn->prepare("UPDATE user_mentor_relationships SET reward_claimed = 1 WHERE id = ?");
        $stmt->bind_param("i", $relationshipId);
        $stmt->execute();
        $stmt->close();

        // Log transaction
        $logMsg = "Nhận thưởng hướng dẫn tân thủ [{$relation['mentee_name']}]";
        $stmt = $conn->prepare("INSERT INTO bot_transactions (user_id, amount, type, description, created_at) VALUES (?, ?, 'bonus', ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("iis", $userId, $rewardAmount, $logMsg);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => "Húp trọn 50.000 GTLM thưởng sư đồ thành công! 🎁"]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// 1. Fetch user level & money
$stmt = $conn->prepare("SELECT u.Name, u.Money, up.level FROM users u JOIN user_progress up ON u.Iduser = up.user_id WHERE u.Iduser = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 2. Fetch my Mentor (if any)
$myMentor = null;
$stmt = $conn->prepare("
    SELECT um.*, u.Name as mentor_name, up.level as mentor_level, u.ImageURL as mentor_avatar
    FROM user_mentor_relationships um
    JOIN users u ON um.mentor_id = u.Iduser
    JOIN user_progress up ON u.Iduser = up.user_id
    WHERE um.mentee_id = ?
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$myMentor = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 3. Fetch my Mentees (if any)
$myMentees = [];
$stmt = $conn->prepare("
    SELECT um.*, u.Name as mentee_name, up.level as mentee_level, u.ImageURL as mentee_avatar
    FROM user_mentor_relationships um
    JOIN users u ON um.mentee_id = u.Iduser
    JOIN user_progress up ON u.Iduser = up.user_id
    WHERE um.mentor_id = ?
    ORDER BY um.assigned_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $myMentees[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trung Tâm Sư Đồ - Vegas Royale</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #1e293b;
        }
        
        * { cursor: inherit; }
        button, a { cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important; }

        .mentor-container {
            max-width: 1100px;
            margin: 0 auto;
        }

        .mentor-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 35px;
            border-radius: 24px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header-left h1 {
            font-size: 38px;
            font-weight: 800;
            background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0 0 10px;
        }

        .header-left p {
            color: #64748b;
            font-size: 16px;
            margin: 0;
        }

        .header-stats {
            display: flex;
            gap: 15px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(0, 0, 0, 0.05);
            padding: 15px 25px;
            border-radius: 16px;
            text-align: center;
            min-width: 140px;
        }

        .stat-val {
            font-size: 20px;
            font-weight: 800;
            color: #6366f1;
        }

        .stat-lbl {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            margin-top: 4px;
        }

        .grid-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        @media (max-width: 900px) {
            .grid-layout {
                grid-template-columns: 1fr;
            }
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            padding: 30px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }

        .card-title {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 20px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid rgba(0, 0, 0, 0.05);
            padding-bottom: 15px;
        }

        .mentor-profile-card {
            display: flex;
            align-items: center;
            gap: 20px;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(168, 85, 247, 0.05) 100%);
            border: 1px dashed rgba(99, 102, 241, 0.2);
            padding: 25px;
            border-radius: 20px;
        }

        .mentor-avatar-img {
            width: 75px;
            height: 75px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #6366f1;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }

        .mentor-info-box h3 {
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 5px;
            color: #1e293b;
        }

        .mentor-info-box .badge {
            background: #6366f1;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
        }

        .progress-container {
            margin-top: 25px;
        }

        .progress-lbl {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            font-weight: 700;
            color: #475569;
            margin-bottom: 8px;
        }

        .progress-bar-bg {
            background: #e2e8f0;
            height: 12px;
            border-radius: 6px;
            overflow: hidden;
        }

        .progress-bar-fill {
            background: linear-gradient(90deg, #6366f1, #a855f7);
            height: 100%;
            border-radius: 6px;
            transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .mentee-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .mentee-card {
            background: rgba(0, 0, 0, 0.02);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 20px;
            padding: 20px;
            transition: all 0.3s;
        }

        .mentee-card:hover {
            transform: translateY(-3px);
            background: rgba(255, 255, 255, 0.6);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.05);
        }

        .mentee-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .mentee-avatar-img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #a855f7;
        }

        .mentee-name {
            font-weight: 700;
            font-size: 16px;
            color: #1e293b;
        }

        .claim-btn {
            background: linear-gradient(135deg, #eab308 0%, #ca8a04 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(234, 179, 8, 0.3);
            transition: all 0.2s;
            margin-left: auto;
        }

        .claim-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(234, 179, 8, 0.4);
        }

        .claimed-badge {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, 0.3);
            padding: 6px 12px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
            margin-left: auto;
        }

        .empty-mentees {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
        }

        .empty-mentees i {
            font-size: 50px;
            color: #cbd5e1;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="mentor-container">
        <!-- Header Section -->
        <div class="mentor-header">
            <div class="header-left">
                <h1>🤝 Trung Tâm Sư Đồ</h1>
                <p>Khai phá Trận Địa, cùng đệ tử xây dựng huyền thoại!</p>
            </div>
            
            <div class="header-stats">
                <div class="stat-card">
                    <div class="stat-val"><?= htmlspecialchars($me['level']) ?></div>
                    <div class="stat-lbl">Cấp Độ Hiện Tại</div>
                </div>
                <div class="stat-card">
                    <div class="stat-val"><?= number_format($me['Money'], 0, ',', '.') ?></div>
                    <div class="stat-lbl">Số Dư GTLM</div>
                </div>
            </div>
        </div>

        <!-- Main Workspace -->
        <div class="grid-layout">
            <!-- Left Side: My Master -->
            <div class="glass-card">
                <h2 class="card-title"><i class="fas fa-graduation-cap" style="color:#6366f1;"></i> Sư Phụ Của Tôi</h2>
                
                <?php if ($myMentor): 
                    $percent = min(100, round(($myMentor['active_days_count'] / 7) * 100));
                ?>
                    <div class="mentor-profile-card">
                        <img src="<?= htmlspecialchars($myMentor['mentor_avatar'] ?: 'img/default-avatar.png') ?>" 
                             class="mentor-avatar-img" alt="Mentor Avatar">
                        <div class="mentor-info-box">
                            <h3><?= htmlspecialchars($myMentor['mentor_name']) ?></h3>
                            <span class="badge">Level <?= $myMentor['mentor_level'] ?></span>
                        </div>
                    </div>

                    <div class="progress-container">
                        <div class="progress-lbl">
                            <span>Thời gian đồng hành tích cực</span>
                            <span><?= $myMentor['active_days_count'] ?> / 7 Ngày</span>
                        </div>
                        <div class="progress-bar-bg">
                            <div class="progress-bar-fill" style="width: <?= $percent ?>%;"></div>
                        </div>
                        <p style="font-size: 13px; color: #64748b; margin-top: 15px; line-height: 1.6; font-style: italic;">
                            *Mỗi ngày bạn đăng nhập và tham gia cược game, thanh tiến trình của Sư phụ sẽ tăng lên! Khi đạt mốc 7 ngày, Sư phụ sẽ nhận phần quà khích lệ cực lớn từ Ban Quản Trị!
                        </p>
                    </div>
                <?php else: ?>
                    <div class="empty-mentees" style="padding: 60px 20px;">
                        <i class="fas fa-scroll"></i>
                        <p style="font-size: 18px; font-weight: 700; color: #334155; margin-bottom: 8px;">Bạn là Cao Thủ Độc Lập!</p>
                        <p style="font-size: 14px; line-height: 1.6;">
                            Bạn không có sư phụ vì đã vượt cấp Tân thủ (Level >= 5). Hãy tập trung chiêu mộ đệ tử của riêng mình ở khung bên phải để truyền dạy võ nghệ và húp trọn phần thưởng sư đồ 50.000 GTLM!
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right Side: My Trainees (Mentees) -->
            <div class="glass-card">
                <h2 class="card-title"><i class="fas fa-users" style="color:#a855f7;"></i> Đệ Tử Hướng Dẫn</h2>

                <?php if (empty($myMentees)): ?>
                    <div class="empty-mentees">
                        <i class="fas fa-user-plus"></i>
                        <p style="font-size: 16px; font-weight: 700; color: #334155; margin-bottom: 5px;">Chưa có đệ tử kết nối</p>
                        <p style="font-size: 13px; line-height: 1.5;">
                            Khi có người chơi mới đăng ký dưới Level 5, hệ thống sẽ tự động ghép đệ tử ngẫu nhiên cho bạn! Hãy giữ online để đệ tử có cơ hội nhận bùa may mắn và bắt đầu luyện tập!
                        </p>
                    </div>
                <?php else: ?>
                    <div class="mentee-list">
                        <?php foreach ($myMentees as $mentee): 
                            $percent = min(100, round(($mentee['active_days_count'] / 7) * 100));
                        ?>
                            <div class="mentee-card">
                                <div class="mentee-profile">
                                    <img src="<?= htmlspecialchars($mentee['mentee_avatar'] ?: 'img/default-avatar.png') ?>" 
                                         class="mentee-avatar-img" alt="Trainee Avatar">
                                    <div>
                                        <div class="mentee-name"><?= htmlspecialchars($mentee['mentee_name']) ?></div>
                                        <div style="font-size: 12px; color: #64748b; font-weight: 600; margin-top: 3px;">
                                            Cấp độ: Level <?= $mentee['mentee_level'] ?>
                                        </div>
                                    </div>

                                    <?php if ($mentee['reward_claimed'] == 1): ?>
                                        <span class="claimed-badge"><i class="fas fa-check-circle"></i> ĐÃ NHẬN 50K</span>
                                    <?php elseif ($mentee['active_days_count'] >= 7): ?>
                                        <button class="claim-btn" onclick="claimReward(<?= $mentee['id'] ?>)">
                                            <i class="fas fa-gift"></i> NHẬN THƯỞNG 50K
                                        </button>
                                    <?php else: ?>
                                        <span style="font-size: 12px; font-weight: 700; color: #64748b; margin-left: auto; text-align: right;">
                                            ⏱️ Chờ tích lũy<br>
                                            <span style="font-size: 11px; font-weight: 600; color: #94a3b8;"><?= $mentee['active_days_count'] ?> / 7 ngày</span>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="progress-container" style="margin-top: 15px;">
                                    <div class="progress-bar-bg" style="height: 6px;">
                                        <div class="progress-bar-fill" style="width: <?= $percent ?>%; height: 100%;"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Back Button -->
        <p style="text-align: center; margin-top: 40px;">
            <a href="index.php" style="color: white; text-decoration: none; font-weight: 700; font-size: 16px; display: inline-flex; align-items: center; gap: 8px; padding: 12px 30px; background: linear-gradient(135deg, #6366f1, #a855f7); border-radius: 50px; box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);">
                <i class="fas fa-home"></i> Quay Lại Trang Chủ
            </a>
        </p>
    </div>

    <script>
        function claimReward(relationshipId) {
            Swal.fire({
                title: '🎁 NHẬN THƯỞNG SƯ ĐỒ?',
                text: "Bạn chắc chắn muốn nhận 50.000 GTLM thưởng đồng hành cùng tân thủ?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#eab308',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Đúng vậy, nhận ngay!',
                cancelButtonText: 'Để sau'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'mentor_center.php',
                        method: 'POST',
                        data: {
                            action: 'claim_reward',
                            relationship_id: relationshipId
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                Swal.fire({
                                    title: '🎉 THÀNH CÔNG RỰC RỠ!',
                                    text: response.message,
                                    icon: 'success',
                                    confirmButtonColor: '#6366f1'
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    title: 'Lỗi!',
                                    text: response.message,
                                    icon: 'error',
                                    confirmButtonColor: '#ef4444'
                                });
                            }
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>

<?php
/**
 * Load Theme Configuration
 * File này load theme config từ database và trả về các biến PHP
 */

// Đảm bảo đã có session và db_connect
if (!isset($conn)) {
    require_once __DIR__ . '/db_connect.php';
}

$themeConfig = null;
$bgGradient = ['#667eea', '#764ba2', '#4facfe']; // Default gradient
$bgGradientCSS = 'linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%)';
$particleCount = 100;
$particleSize = 0.05;
$particleColor = '#ffffff';
$particleOpacity = 0.6;
$shapeCount = 10;
$shapeColors = ['#667eea', '#764ba2', '#4facfe', '#00f2fe'];
$shapeOpacity = 0.3;
$themeName = 'Default';

// Nếu có user session, load theme của user
if (isset($_SESSION['Iduser'])) {
    $userId = $_SESSION['Iduser'];
    
    // Kiểm tra bảng users tồn tại trước
    $checkUsersTable = $conn->query("SHOW TABLES LIKE 'users'");
    if ($checkUsersTable && $checkUsersTable->num_rows > 0) {
    // Kiểm tra xem bảng themes có tồn tại không
    $checkThemesTable = $conn->query("SHOW TABLES LIKE 'themes'");
    if ($checkThemesTable && $checkThemesTable->num_rows > 0) {
        // Lấy current_theme_id của user
        $userSql = "SELECT current_theme_id FROM users WHERE Iduser = ?";
        $userStmt = $conn->prepare($userSql);
        if ($userStmt) {
            $userStmt->bind_param("i", $userId);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
                if ($userResult && ($userRow = $userResult->fetch_assoc())) {
                $currentThemeId = $userRow['current_theme_id'] ?? null;
                
                // Load theme config
                if (!empty($currentThemeId)) {
                    $themeSql = "SELECT * FROM themes WHERE id = ?";
                    $themeStmt = $conn->prepare($themeSql);
                    if ($themeStmt) {
                        $themeStmt->bind_param("i", $currentThemeId);
                        $themeStmt->execute();
                        $themeResult = $themeStmt->get_result();
                        if ($themeResult) {
                            $themeConfig = $themeResult->fetch_assoc();
                        }
                        $themeStmt->close();
                    }
                }
                
                // Nếu không có theme, lấy theme mặc định (id = 1)
                if (!$themeConfig) {
                    $defaultThemeSql = "SELECT * FROM themes WHERE id = 1";
                    $defaultThemeResult = $conn->query($defaultThemeSql);
                    if ($defaultThemeResult && $defaultThemeResult->num_rows > 0) {
                        $themeConfig = $defaultThemeResult->fetch_assoc();
                    }
                }
            }
            $userStmt->close();
            }
        }
    }
}

// Parse theme config (với giá trị mặc định nếu không có theme)
if ($themeConfig && !empty($themeConfig['background_gradient'])) {
    $bgGradient = json_decode($themeConfig['background_gradient'], true);
    if (!is_array($bgGradient) || count($bgGradient) < 2) {
        $bgGradient = ['#667eea', '#764ba2', '#4facfe'];
    }
}

// Đảm bảo có đủ 3 màu
if (count($bgGradient) < 3) {
    $bgGradient[] = $bgGradient[count($bgGradient) - 1];
}

// Tạo gradient string cho CSS
$bgGradientCSS = 'linear-gradient(135deg, ' . 
    htmlspecialchars($bgGradient[0]) . ' 0%, ' . 
    htmlspecialchars($bgGradient[1]) . ' 50%, ' . 
    htmlspecialchars($bgGradient[2] ?? $bgGradient[1]) . ' 100%)';

// Parse Three.js config (với giá trị mặc định nếu không có theme)
// Giới hạn để tránh lag: particles tối đa 800, shapes tối đa 10
$particleCount = min($themeConfig['particle_count'] ?? 1000, 800);
$particleSize = $themeConfig['particle_size'] ?? 0.05;
$particleColor = $themeConfig['particle_color'] ?? '#ffffff';
$particleOpacity = $themeConfig['particle_opacity'] ?? 0.6;
$shapeCount = min($themeConfig['shape_count'] ?? 15, 10);
$shapeColors = !empty($themeConfig['shape_colors']) ? json_decode($themeConfig['shape_colors'], true) : ['#667eea', '#764ba2', '#4facfe', '#00f2fe'];
$shapeOpacity = $themeConfig['shape_opacity'] ?? 0.3;
$themeName = $themeConfig['name'] ?? '';

// Đảm bảo $bgGradient được định nghĩa (đã có ở trên, nhưng đảm bảo với giá trị mặc định)
if (!isset($bgGradient) || empty($bgGradient)) {
    $bgGradient = ['#667eea', '#764ba2', '#4facfe'];
}

// --- Centralized Session-based Gamification Toast Dispatcher ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hook Mentor Assignment and Activity Tracking globally on lobby page loads
if (isset($_SESSION['Iduser'])) {
    require_once __DIR__ . '/mentor_helper.php';
    MentorHelper::ensureMentor($conn, (int)$_SESSION['Iduser']);
    MentorHelper::trackMenteeActivity($conn, (int)$_SESSION['Iduser']);
}

// 1. Secret Lucky Hours & General pending alerts
if (isset($_SESSION['pending_notifications']) && !empty($_SESSION['pending_notifications'])) {
    echo "<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof Swal !== 'undefined') {
            const notifs = " . json_encode($_SESSION['pending_notifications']) . ";
            notifs.forEach(notif => {
                Swal.fire({
                    title: notif.title,
                    text: notif.message,
                    icon: notif.type,
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 5000,
                    timerProgressBar: true
                });
            });
        }
    });
    </script>";
    unset($_SESSION['pending_notifications']);
}

// 2. Completed Daily Challenges
if (isset($_SESSION['completed_challenges']) && !empty($_SESSION['completed_challenges'])) {
    echo "<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof Swal !== 'undefined') {
            const challenges = " . json_encode($_SESSION['completed_challenges']) . ";
            challenges.forEach(ch => {
                Swal.fire({
                    title: '🎯 THỬ THÁCH HOÀN THÀNH!',
                    html: `Chúc mừng bạn đã hoàn thành thử thách của game <b>\${ch.game_key.toUpperCase()}</b>:<br><br><i>\${ch.text}</i><br><br>🎁 <b>Nhận ngay: +\${new Intl.NumberFormat().format(ch.reward)} GTLM!</b>`,
                    icon: 'success',
                    confirmButtonColor: '#fbbf24',
                    background: '#0f172a',
                    color: '#f8fafc'
                });
            });
        }
    });
    </script>";
    unset($_SESSION['completed_challenges']);
}

// 3. New Mentor Assigned Modal
if (isset($_SESSION['new_mentor_assigned']) && !empty($_SESSION['new_mentor_assigned'])) {
    $mentorData = $_SESSION['new_mentor_assigned'];
    echo "<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: '🤝 ĐÃ KẾT NỐI SƯ PHỤ!',
                html: `Chào mừng bạn gia nhập Vegas Royale! Bạn đã được tự động kết nối với Sư phụ <b>" . htmlspecialchars($mentorData['mentor_name']) . "</b>.<br><br>Sư phụ sẽ hướng dẫn bạn hành tẩu Trận Địa. Khi bạn hoạt động tích cực đủ 7 ngày, Sư phụ sẽ nhận được phần thưởng <b>50.000 GTLM</b> khích lệ!`,
                icon: 'info',
                confirmButtonColor: '#6366f1',
                confirmButtonText: 'Tuyệt vời, cảm ơn Sư phụ!'
            });
        }
    });
    </script>";
    unset($_SESSION['new_mentor_assigned']);
}

// 4. Server-Sent Events cho Sự kiện mới
echo "<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof EventSource !== 'undefined' && typeof Swal !== 'undefined') {
        const evtSource = new EventSource('api_sse_events.php');
        evtSource.addEventListener('new_event', (e) => {
            try {
                const data = JSON.parse(e.data);
                Swal.fire({
                    title: '🎉 SỰ KIỆN MÙA GIẢI MỚI!',
                    html: `Sự kiện <b>\${data.emoji} \${data.name}</b> vừa chính thức bắt đầu!<br><br>Hãy vào Đại Sảnh Sự Kiện để tham gia và nhận thưởng!`,
                    icon: 'info',
                    confirmButtonText: 'Tham Gia Ngay',
                    confirmButtonColor: '#38bdf8',
                    showCancelButton: true,
                    cancelButtonText: 'Đóng',
                    background: '#0f172a',
                    color: '#f8fafc'
                }).then((res) => {
                    if (res.isConfirmed) {
                        window.location.href = 'event_center.php';
                    }
                });
            } catch (err) {}
        });

        evtSource.addEventListener('new_random_event', (e) => {
            try {
                const data = JSON.parse(e.data);
                Swal.fire({
                    title: '🚨 SỰ KIỆN ĐỘT XUẤT!',
                    html: `<b>\${data.name}</b> đang diễn ra!<br><br>Nhanh chân tham gia trước khi hết thời gian!`,
                    icon: 'warning',
                    confirmButtonText: 'Đã Hiểu',
                    confirmButtonColor: '#f43f5e',
                    background: '#0f172a',
                    color: '#f8fafc'
                });
            } catch (err) {}
        });
    }
});
</script>";
?>


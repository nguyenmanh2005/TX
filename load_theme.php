<?php
/**
 * Load Theme Configuration
 * File này load theme config từ database và trả về các biến PHP
 */

// Đảm bảo đã có session và db_connect
if (!isset($conn)) {
    require_once 'db_connect.php';
}

$themeConfig = null;
$bgGradient = ['#667eea', '#764ba2', '#4facfe']; // Default gradient

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

?>


<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require_once 'load_theme.php';
$userId = $_SESSION['Iduser'];

// Lấy danh sách công thức
$recipes = $conn->query("SELECT * FROM crafting_recipes")->fetch_all(MYSQLI_ASSOC);

// Lấy số lượng items user đang sở hữu để hiển thị trong workshop
function getUserItemCounts($conn, $userId) {
    $counts = [];
    
    // Themes
    $res = $conn->query("SELECT COUNT(*) as total FROM user_themes WHERE user_id = $userId AND is_active = 0");
    $counts['theme'] = $res->fetch_assoc()['total'];
    
    // Cursors
    $res = $conn->query("SELECT COUNT(*) as total FROM user_cursors WHERE user_id = $userId AND is_active = 0");
    $counts['cursor'] = $res->fetch_assoc()['total'];
    
    // Frames
    $res = $conn->query("SELECT COUNT(*) as total FROM user_avatar_frames WHERE user_id = $userId");
    $counts['avatar_frame'] = $res->fetch_assoc()['total'];
    
    return $counts;
}

$userCounts = getUserItemCounts($conn, $userId);
$userMoney = $conn->query("SELECT Money FROM users WHERE Iduser = $userId")->fetch_assoc()['Money'];

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xưởng Chế Tác - Crafting Workshop</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            min-height: 100vh;
            color: white;
            font-family: 'Orbitron', sans-serif;
            padding: 20px;
        }

        .workshop-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .forge-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 40px;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(20px);
            border-radius: 30px;
            border: 2px solid #ff4500;
            box-shadow: 0 0 30px rgba(255, 69, 0, 0.3);
        }

        .forge-header h1 {
            font-size: 3.5rem;
            text-transform: uppercase;
            letter-spacing: 5px;
            margin-bottom: 10px;
            background: linear-gradient(180deg, #fff 0%, #ff4500 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stats-bar {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px 25px;
            border-radius: 15px;
            border: 1px solid rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .recipe-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
        }

        .recipe-card {
            background: rgba(0, 0, 0, 0.4);
            border-radius: 20px;
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: 0.3s;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .recipe-card:hover {
            border-color: #ff4500;
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(255, 69, 0, 0.2);
        }

        .recipe-title {
            font-size: 20px;
            margin-bottom: 20px;
            color: #ff8c00;
        }

        .success-rate {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #28a745;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 800;
        }

        .requirement-box {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            flex-grow: 1;
        }

        .req-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .req-item.met { color: #4ade80; }
        .req-item.not-met { color: #ff6b6b; }

        .craft-btn {
            width: 100%;
            padding: 15px;
            border-radius: 12px;
            border: none;
            background: linear-gradient(135deg, #ff4500 0%, #ff8c00 100%);
            color: white;
            font-weight: 800;
            cursor: pointer;
            transition: 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .craft-btn:hover:not(:disabled) {
            filter: brightness(1.2);
            box-shadow: 0 0 20px rgba(255, 69, 0, 0.5);
        }

        .craft-btn:disabled {
            background: #444;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .forge-animation {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }

        .hammer {
            font-size: 100px;
            animation: hammerStrike 0.5s infinite;
        }

        @keyframes hammerStrike {
            0% { transform: rotate(0deg); }
            50% { transform: rotate(-45deg); }
            100% { transform: rotate(0deg); }
        }

        .sparks {
            position: absolute;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="forge-animation" id="forgeAnim">
        <div class="hammer">🔨</div>
        <h2 id="forgeStatus">ĐANG RÈN...</h2>
    </div>

    <div class="workshop-container">
        <div class="forge-header">
            <h1>🔥 WORKSHOP</h1>
            <p>Nâng cấp trang bị - Rèn giũa huyền thoại</p>
        </div>

        <div class="stats-bar">
            <div class="stat-card">
                <i class="fas fa-wallet" style="color: #ffd700;"></i>
                <span><?= number_format($userMoney) ?> GTLM</span>
            </div>
            <div class="stat-card">
                <i class="fas fa-palette" style="color: #667eea;"></i>
                <span>Themes: <?= $userCounts['theme'] ?></span>
            </div>
            <div class="stat-card">
                <i class="fas fa-user-circle" style="color: #4ade80;"></i>
                <span>Frames: <?= $userCounts['avatar_frame'] ?></span>
            </div>
        </div>

        <div class="recipe-grid">
            <?php foreach ($recipes as $recipe): 
                $reqs = json_decode($recipe['input_requirements'], true);
                $canCraft = ($userMoney >= $recipe['gtlm_cost']);
                foreach ($reqs as $type => $amt) {
                    if (($userCounts[$type] ?? 0) < $amt) $canCraft = false;
                }
            ?>
            <div class="recipe-card">
                <div class="success-rate">Tỉ lệ: <?= $recipe['success_rate'] ?>%</div>
                <h3 class="recipe-title"><?= $recipe['name'] ?></h3>
                <p style="font-size: 13px; opacity: 0.7; margin-bottom: 15px;"><?= $recipe['description'] ?></p>
                
                <div class="requirement-box">
                    <div style="font-size: 12px; opacity: 0.5; margin-bottom: 10px;">NGUYÊN LIỆU CẦN:</div>
                    <?php foreach ($reqs as $type => $amt): 
                        $has = $userCounts[$type] ?? 0;
                        $met = ($has >= $amt);
                    ?>
                        <div class="req-item <?= $met ? 'met' : 'not-met' ?>">
                            <span><?= ucfirst(str_replace('_', ' ', $type)) ?></span>
                            <span><?= $has ?> / <?= $amt ?></span>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="req-item <?= $userMoney >= $recipe['gtlm_cost'] ? 'met' : 'not-met' ?>">
                        <span>Chi phí GTLM</span>
                        <span><?= number_format($recipe['gtlm_cost']) ?></span>
                    </div>
                </div>

                <button class="craft-btn" onclick="startCraft(<?= $recipe['id'] ?>, '<?= $recipe['name'] ?>')" <?= $canCraft ? '' : 'disabled' ?>>
                    BẮT ĐẦU CHẾ TÁC
                </button>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="text-align: center; margin-top: 50px;">
            <a href="inventory.php" style="color: rgba(255,255,255,0.5); text-decoration: none;"><i class="fas fa-arrow-left"></i> Quay lại Kho Đồ</a>
        </div>
    </div>

    <script>
        function startCraft(recipeId, name) {
            Swal.fire({
                title: 'Bắt đầu chế tác?',
                text: `Bạn có muốn rèn ${name}? Nguyên liệu và tiền sẽ bị tiêu hao.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ff4500',
                confirmButtonText: 'RÈN NGAY!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#forgeAnim').css('display', 'flex');
                    
                    $.post('api_crafting.php', { action: 'craft', recipe_id: recipeId }, function(res) {
                        setTimeout(() => {
                            $('#forgeAnim').hide();
                            if (res.status === 'success') {
                                Swal.fire({
                                    title: 'THÀNH CÔNG!',
                                    text: res.message,
                                    icon: 'success',
                                    background: '#1a1a1a',
                                    color: '#fff'
                                }).then(() => location.reload());
                            } else if (res.status === 'failure') {
                                Swal.fire({
                                    title: 'THẤT BẠI!',
                                    text: res.message,
                                    icon: 'error',
                                    background: '#1a1a1a',
                                    color: '#fff'
                                }).then(() => location.reload());
                            } else {
                                Swal.fire('Lỗi!', res.message, 'error');
                            }
                        }, 2500);
                    }, 'json');
                }
            });
        }
    </script>
</body>
</html>

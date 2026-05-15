<?php
require_once 'db_connect.php';

$res = $conn->query("SELECT * FROM community_challenges WHERE status = 'active' LIMIT 1");
$challenge = $res ? $res->fetch_assoc() : null;

if ($challenge):
    $percent = round(($challenge['current_count'] / $challenge['target_count']) * 100);
    $percent = min(100, $percent);
?>
<div class="community-challenge" style="
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(240, 247, 255, 0.95) 100%);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    border: 2px solid var(--secondary-color);
    position: relative;
    overflow: hidden;
">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h3 style="margin: 0; color: var(--primary-color); font-size: 18px;">🌍 Nhiệm vụ Cộng đồng</h3>
        <span style="background: var(--secondary-color); color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700;">ĐANG DIỄN RA</span>
    </div>
    
    <p style="margin: 0 0 10px 0; font-weight: 600; color: var(--text-dark);"><?= htmlspecialchars($challenge['name']) ?></p>
    <p style="margin: 0 0 15px 0; font-size: 13px; color: var(--text-light);"><?= htmlspecialchars($challenge['description'] ?? 'Cùng nhau đạt mục tiêu để nhận thưởng lớn!') ?></p>
    
    <div style="background: rgba(0,0,0,0.05); height: 12px; border-radius: 10px; overflow: hidden; margin-bottom: 8px;">
        <div style="width: <?= $percent ?>%; height: 100%; background: linear-gradient(90deg, var(--secondary-color), var(--success-color)); transition: width 1s ease;"></div>
    </div>
    
    <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 700;">
        <span style="color: var(--secondary-color);"><?= number_format($challenge['current_count']) ?> / <?= number_format($challenge['target_count']) ?></span>
        <span style="color: var(--success-color);"><?= $percent ?>%</span>
    </div>
    
    <div style="margin-top: 15px; padding-top: 10px; border-top: 1px dashed #ddd; display: flex; justify-content: space-between; align-items: center;">
        <span style="font-size: 13px; color: var(--text-dark);">Phần thưởng: <strong style="color: var(--warning-color);"><?= number_format($challenge['reward']) ?> GTLM</strong> (Chia đều)</span>
        <i class="fas fa-users" style="color: var(--secondary-color); font-size: 20px; opacity: 0.5;"></i>
    </div>
</div>
<?php endif; ?>

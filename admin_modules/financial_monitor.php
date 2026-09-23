<?php
/**
 * 🛡️ Module Giám Sát Tài Chính & Phát Hiện Gian Lận (Financial Anomaly & Fraud Monitor)
 * Quản trị rủi ro, phát hiện nick bất thường, cheat winrate, tràn số GTLM
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../admin_helper.php';

$currentUserId = (int)($_SESSION['Iduser'] ?? 0);
if (!isAdmin($conn, $currentUserId)) {
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
        exit();
    }
    header("Location: ../Shared/403/403.php");
    exit();
}

// Xử lý AJAX Actions
if (isset($_GET['ajax']) && $_GET['ajax'] === 'user_transactions') {
    header('Content-Type: application/json; charset=utf-8');
    $uid = (int)($_GET['user_id'] ?? 0);
    $logs = [];
    if ($uid > 0) {
        $stmt = $conn->prepare("SELECT id, transaction_type, currency_type, amount, balance_after, reason, created_at FROM economy_transaction_logs WHERE user_id = ? ORDER BY id DESC LIMIT 50");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $logs[] = [
                'id' => $r['id'],
                'type' => $r['transaction_type'],
                'amount' => (float)$r['amount'],
                'balance_after' => (float)$r['balance_after'],
                'reason' => $r['reason'] ?: 'Không rõ',
                'time' => $r['created_at']
            ];
        }
        $stmt->close();
    }
    echo json_encode(['success' => true, 'logs' => $logs]);
    exit();
}

// Xử lý Quick Action: Khóa nick / Mở khóa
$alertMessage = '';
$alertType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $act = $_POST['action'];
    $targetUid = (int)($_POST['target_user_id'] ?? 0);

    if ($targetUid > 0 && $targetUid !== $currentUserId) {
        if ($act === 'lock_user') {
            $stmt = $conn->prepare("UPDATE users SET Role = -1 WHERE Iduser = ?");
            $stmt->bind_param("i", $targetUid);
            $stmt->execute();
            $stmt->close();
            $alertMessage = "Đã khóa (ban) thành công tài khoản ID #$targetUid!";
            $alertType = "success";
        } elseif ($act === 'unlock_user') {
            $stmt = $conn->prepare("UPDATE users SET Role = 0 WHERE Iduser = ?");
            $stmt->bind_param("i", $targetUid);
            $stmt->execute();
            $stmt->close();
            $alertMessage = "Đã mở khóa tài khoản ID #$targetUid trở lại bình thường!";
            $alertType = "success";
        } elseif ($act === 'freeze_money') {
            // Ghi log trước khi reset
            $curMoney = (float)($conn->query("SELECT Money FROM users WHERE Iduser = $targetUid")->fetch_row()[0] ?? 0);
            $conn->query("UPDATE users SET Money = 0 WHERE Iduser = $targetUid");
            $logStmt = $conn->prepare("INSERT INTO economy_transaction_logs (user_id, transaction_type, currency_type, amount, balance_after, reason, created_at) VALUES (?, 'ADMIN_RESET', 'GOLD', ?, 0, 'Admin đóng băng số dư bất thường', NOW())");
            $diff = -$curMoney;
            $logStmt->bind_param("id", $targetUid, $diff);
            $logStmt->execute();
            $logStmt->close();
            $alertMessage = "Đã đóng băng và thu hồi toàn bộ số dư của ID #$targetUid về 0 GTLM!";
            $alertType = "warning";
        }
    }
}

// 1. Quét Tỉ lệ thắng Dị Thường (> 80% trong 15+ ván, 7 ngày gần nhất)
$sqlWinrate = "SELECT gh.user_id, u.Name, u.Email, u.Role, u.Money,
                      COUNT(*) as total_plays,
                      SUM(CASE WHEN gh.is_win = 1 THEN 1 ELSE 0 END) as wins,
                      ROUND(SUM(CASE WHEN gh.is_win = 1 THEN 1 ELSE 0 END) / COUNT(*) * 100, 1) as win_rate,
                      SUM(gh.bet_amount) as total_bet,
                      SUM(gh.win_amount) as total_win,
                      SUM(gh.win_amount - gh.bet_amount) as net_profit
               FROM game_history gh
               JOIN users u ON gh.user_id = u.Iduser
               WHERE gh.played_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
               GROUP BY gh.user_id
               HAVING total_plays >= 15 AND win_rate >= 80
               ORDER BY win_rate DESC, net_profit DESC
               LIMIT 15";
$anomalousWinrates = [];
$resWinrate = $conn->query($sqlWinrate);
if ($resWinrate) {
    while ($r = $resWinrate->fetch_assoc()) $anomalousWinrates[] = $r;
}

// 2. Quét Tài khoản Siêu Tài Sản Bất Thường (> 1,000,000,000,000 GTLM)
$chkActive = $conn->query("SHOW COLUMNS FROM users LIKE 'last_active'");
if (!$chkActive || $chkActive->num_rows == 0) {
    @$conn->query("ALTER TABLE users ADD COLUMN last_active DATETIME NULL");
    $hasLastActive = false;
} else {
    $hasLastActive = true;
}
$lastActiveCol = $hasLastActive ? "last_active" : "NULL as last_active";
$sqlWealth = "SELECT Iduser, Name, Email, Role, Money, created_at, $lastActiveCol
              FROM users
              WHERE Money >= 1000000000000
              ORDER BY Money DESC
              LIMIT 15";
$wealthAnomalies = [];
$resWealth = $conn->query($sqlWealth);
if ($resWealth) {
    while ($r = $resWealth->fetch_assoc()) $wealthAnomalies[] = $r;
}

// 3. Quét Tăng Vọt Lợi Nhuận Trong 24h (> 100,000,000 GTLM)
$sqlSpikes = "SELECT gh.user_id, u.Name, u.Email, u.Role, u.Money,
                     COUNT(*) as plays_24h,
                     SUM(gh.win_amount - gh.bet_amount) as profit_24h
              FROM game_history gh
              JOIN users u ON gh.user_id = u.Iduser
              WHERE gh.played_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
              GROUP BY gh.user_id
              HAVING profit_24h >= 100000000
              ORDER BY profit_24h DESC
              LIMIT 15";
$profitSpikes = [];
$resSpikes = $conn->query($sqlSpikes);
if ($resSpikes) {
    while ($r = $resSpikes->fetch_assoc()) $profitSpikes[] = $r;
}

// 4. Tổng hợp số lượng cảnh báo
$totalAlertsCount = count($anomalousWinrates) + count($wealthAnomalies) + count($profitSpikes);
?>

<div class="financial-monitor-container">
    <?php if ($alertMessage): ?>
        <div class="alert-box <?= $alertType === 'success' ? 'alert-success' : 'alert-warning' ?>">
            <i class="fa fa-<?= $alertType === 'success' ? 'circle-check' : 'triangle-exclamation' ?>"></i>
            <?= htmlspecialchars($alertMessage) ?>
        </div>
    <?php endif; ?>

    <!-- Header & Summary Cards -->
    <div class="monitor-header">
        <div class="monitor-title">
            <h2><i class="fa fa-shield-virus" style="color: #fb7185;"></i> Trung Tâm Giám Sát Tài Chính & Chống Gian Lận</h2>
            <p>Hệ thống tự động quét 125k+ ván game và 197k+ giao dịch để cảnh báo các hành vi bất thường, cheat winrate và tràn số GTLM.</p>
        </div>
        <div class="alert-count-pill <?= $totalAlertsCount > 0 ? 'pill-danger' : 'pill-safe' ?>">
            <i class="fa fa-bell"></i>
            <span><?= $totalAlertsCount ?> CẢNH BÁO ĐANG THEO DÕI</span>
        </div>
    </div>

    <div class="monitor-metrics-grid">
        <div class="monitor-stat-card border-red">
            <div class="stat-icon-wrap icon-red"><i class="fa fa-crosshairs"></i></div>
            <div class="stat-info">
                <div class="stat-label">Cheat Winrate (>80%)</div>
                <div class="stat-val"><?= count($anomalousWinrates) ?> <small>tài khoản</small></div>
            </div>
        </div>
        <div class="monitor-stat-card border-amber">
            <div class="stat-icon-wrap icon-amber"><i class="fa fa-vault"></i></div>
            <div class="stat-info">
                <div class="stat-label">Siêu Phú Hào (>1.000 Tỷ)</div>
                <div class="stat-val"><?= count($wealthAnomalies) ?> <small>tài khoản</small></div>
            </div>
        </div>
        <div class="monitor-stat-card border-purple">
            <div class="stat-icon-wrap icon-purple"><i class="fa fa-bolt"></i></div>
            <div class="stat-info">
                <div class="stat-label">Tăng Vọt Lãi 24h (>100M)</div>
                <div class="stat-val"><?= count($profitSpikes) ?> <small>tài khoản</small></div>
            </div>
        </div>
    </div>

    <!-- Section 1: Winrate Anomaly Table -->
    <div class="monitor-section">
        <div class="section-top-bar">
            <h3><i class="fa fa-chart-line-up" style="color: #fb7185;"></i> 1. Danh Sách Tỉ Lệ Húp GTLM Dị Thường (WinRate > 80%)</h3>
            <span class="badge-hint">Tiêu chuẩn: Tối thiểu 15 ván trong 7 ngày gần nhất</span>
        </div>
        <?php if (empty($anomalousWinrates)): ?>
            <div class="empty-state-card"><i class="fa fa-circle-check"></i> Trận địa trong sạch, không phát hiện tài khoản nào có tỉ lệ thắng dị thường.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="monitor-table">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Tên Người Chơi</th>
                            <th>Phân Loại</th>
                            <th style="text-align:center;">Số Ván</th>
                            <th style="text-align:center;">Thắng / Thua</th>
                            <th style="text-align:center;">Tỉ Lệ Húp</th>
                            <th style="text-align:right;">Lợi Nhuận Ròng</th>
                            <th style="text-align:center;">Thao Tác Admin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($anomalousWinrates as $u): 
                            $isBot = (strpos($u['Email'], 'bot') !== false || strpos($u['Email'], '@example.com') !== false);
                            $isBanned = ((int)$u['Role'] === -1);
                        ?>
                            <tr class="<?= $isBanned ? 'row-banned' : '' ?>">
                                <td><span class="id-badge">#<?= $u['user_id'] ?></span></td>
                                <td>
                                    <div class="user-meta-name">
                                        <b><?= htmlspecialchars($u['Name']) ?></b>
                                        <small><?= htmlspecialchars($u['Email']) ?></small>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($isBot): ?>
                                        <span class="type-badge badge-bot"><i class="fa fa-robot"></i> Bot Streamer</span>
                                    <?php else: ?>
                                        <span class="type-badge badge-human"><i class="fa fa-user"></i> Người Thật</span>
                                    <?php endif; ?>
                                    <?php if ($isBanned): ?>
                                        <span class="type-badge badge-banned"><i class="fa fa-lock"></i> Đã Khóa</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center; font-family:'Space Mono',monospace; font-weight:700;"><?= $u['total_plays'] ?></td>
                                <td style="text-align:center; font-size:12px;">
                                    <span style="color:#34d399;"><?= $u['wins'] ?> thắng</span> / 
                                    <span style="color:#fb7185;"><?= $u['total_plays'] - $u['wins'] ?> thua</span>
                                </td>
                                <td style="text-align:center;">
                                    <span class="winrate-pill <?= $u['win_rate'] >= 90 ? 'winrate-extreme' : 'winrate-high' ?>">
                                        <?= $u['win_rate'] ?>%
                                    </span>
                                </td>
                                <td style="text-align:right; font-family:'Space Mono',monospace; font-weight:700; color:<?= $u['net_profit'] >= 0 ? '#34d399' : '#fb7185' ?>;">
                                    <?= ($u['net_profit'] > 0 ? '+' : '') . number_format($u['net_profit']) ?> GTLM
                                </td>
                                <td style="text-align:center;">
                                    <div class="action-buttons-wrap">
                                        <button class="btn-sm btn-inspect" onclick="openInspector(<?= $u['user_id'] ?>, '<?= htmlspecialchars(addslashes($u['Name'])) ?>')">
                                            <i class="fa fa-list-check"></i> Soi
                                        </button>
                                        <?php if ($isBanned): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Mở khóa nick này?');">
                                                <input type="hidden" name="action" value="unlock_user">
                                                <input type="hidden" name="target_user_id" value="<?= $u['user_id'] ?>">
                                                <button type="submit" class="btn-sm btn-unlock"><i class="fa fa-lock-open"></i> Mở</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Khóa tài khoản nghi vấn này?');">
                                                <input type="hidden" name="action" value="lock_user">
                                                <input type="hidden" name="target_user_id" value="<?= $u['user_id'] ?>">
                                                <button type="submit" class="btn-sm btn-lock"><i class="fa fa-ban"></i> Khóa</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Section 2: Extreme Wealth / Trillionaires Table -->
    <div class="monitor-section">
        <div class="section-top-bar">
            <h3><i class="fa fa-money-bill-trend-up" style="color: #fbbf24;"></i> 2. Bảng Xếp Hạng Siêu Tài Sản & Tràn Số (> 1.000 Tỷ GTLM)</h3>
            <span class="badge-hint">Giám sát lạm phát tài sản & phát hiện dấu hiệu hack Money</span>
        </div>
        <div class="table-responsive">
            <table class="monitor-table">
                <thead>
                    <tr>
                        <th>Hạng</th>
                        <th>User ID</th>
                        <th>Tên Người Chơi</th>
                        <th>Phân Loại</th>
                        <th style="text-align:right;">Số Dư Hiện Tại</th>
                        <th style="text-align:center;">Hoạt Động Gần Nhất</th>
                        <th style="text-align:center;">Hành Động Khẩn</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($wealthAnomalies as $idx => $w): 
                        $isBot = (strpos($w['Email'], 'bot') !== false || strpos($w['Email'], '@example.com') !== false);
                        $isBanned = ((int)$w['Role'] === -1);
                        $isExtremeOverflow = ($w['Money'] > 1000000000000000);
                    ?>
                        <tr class="<?= $isExtremeOverflow ? 'row-overflow' : '' ?> <?= $isBanned ? 'row-banned' : '' ?>">
                            <td><b style="color:#fbbf24;">#<?= $idx + 1 ?></b></td>
                            <td><span class="id-badge">#<?= $w['Iduser'] ?></span></td>
                            <td>
                                <div class="user-meta-name">
                                    <b><?= htmlspecialchars($w['Name']) ?></b>
                                    <small><?= htmlspecialchars($w['Email']) ?></small>
                                </div>
                            </td>
                            <td>
                                <?php if ($isBot): ?>
                                    <span class="type-badge badge-bot"><i class="fa fa-robot"></i> Bot</span>
                                <?php else: ?>
                                    <span class="type-badge badge-human"><i class="fa fa-user"></i> Người Thật</span>
                                <?php endif; ?>
                                <?php if ($isExtremeOverflow): ?>
                                    <span class="type-badge badge-danger"><i class="fa fa-triangle-exclamation"></i> Tràn Số</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right; font-family:'Space Mono',monospace; font-size:15px; font-weight:700; color:#ffd700;">
                                <?= number_format($w['Money']) ?> <small style="font-size:10px; opacity:0.6;">GTLM</small>
                            </td>
                            <td style="text-align:center; font-size:12px; color:var(--muted);"><?= $w['last_active'] ?: 'Chưa ghi' ?></td>
                            <td style="text-align:center;">
                                <div class="action-buttons-wrap">
                                    <button class="btn-sm btn-inspect" onclick="openInspector(<?= $w['Iduser'] ?>, '<?= htmlspecialchars(addslashes($w['Name'])) ?>')">
                                        <i class="fa fa-receipt"></i> Lịch Sử
                                    </button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('CẢNH BÁO: Bạn chắc chắn muốn ĐÓNG BĂNG VÀ THU HỒI toàn bộ số dư của tài khoản này về 0 GTLM?');">
                                        <input type="hidden" name="action" value="freeze_money">
                                        <input type="hidden" name="target_user_id" value="<?= $w['Iduser'] ?>">
                                        <button type="submit" class="btn-sm btn-freeze"><i class="fa fa-snowflake"></i> Đóng Băng</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Section 3: 24h Profit Spikes -->
    <div class="monitor-section">
        <div class="section-top-bar">
            <h3><i class="fa fa-arrow-trend-up" style="color: #a78bfa;"></i> 3. Biến Động Thắng Lớn Đột Biến Trong 24 Giờ (> 100M GTLM)</h3>
            <span class="badge-hint">Theo dõi biến động dòng tiền thắng cược theo thời gian thực</span>
        </div>
        <?php if (empty($profitSpikes)): ?>
            <div class="empty-state-card"><i class="fa fa-check"></i> Không có tài khoản nào húp đột biến vượt ngưỡng trong 24 giờ qua.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="monitor-table">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Tên Người Chơi</th>
                            <th style="text-align:center;">Số Ván 24h</th>
                            <th style="text-align:right;">Lợi Nhuận Húp 24h</th>
                            <th style="text-align:right;">Số Dư Hiện Tại</th>
                            <th style="text-align:center;">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($profitSpikes as $ps): ?>
                            <tr>
                                <td><span class="id-badge">#<?= $ps['user_id'] ?></span></td>
                                <td><b><?= htmlspecialchars($ps['Name']) ?></b></td>
                                <td style="text-align:center; font-family:'Space Mono',monospace;"><?= $ps['plays_24h'] ?></td>
                                <td style="text-align:right; font-family:'Space Mono',monospace; font-weight:700; color:#34d399;">
                                    +<?= number_format($ps['profit_24h']) ?> GTLM
                                </td>
                                <td style="text-align:right; font-family:'Space Mono',monospace; color:#ffd700;">
                                    <?= number_format($ps['Money']) ?> GTLM
                                </td>
                                <td style="text-align:center;">
                                    <button class="btn-sm btn-inspect" onclick="openInspector(<?= $ps['user_id'] ?>, '<?= htmlspecialchars(addslashes($ps['Name'])) ?>')">
                                        <i class="fa fa-magnifying-glass-chart"></i> Soi Kèo
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Soi Nhật Ký Giao Dịch -->
<div id="inspectorModal" class="inspector-modal" style="display:none;">
    <div class="modal-overlay" onclick="closeInspector()"></div>
    <div class="modal-body-card">
        <div class="modal-header">
            <h4><i class="fa fa-receipt" style="color:#4f8dff;"></i> Soi Nhật Ký Giao Dịch: <span id="modalUserName" style="color:#22d3ee;"></span></h4>
            <button class="modal-close-btn" onclick="closeInspector()">&times;</button>
        </div>
        <div class="modal-content-scroll">
            <div id="modalLoading" style="text-align:center; padding:30px; color:var(--muted);"><i class="fa fa-spinner fa-spin"></i> Đang trích xuất dữ liệu giao dịch...</div>
            <table class="monitor-table" id="modalTable" style="display:none;">
                <thead>
                    <tr>
                        <th>Mã GD</th>
                        <th>Loại GD</th>
                        <th style="text-align:right;">Biến Động</th>
                        <th style="text-align:right;">Số Dư Sau Đó</th>
                        <th>Lý Do / Chi Tiết</th>
                        <th>Thời Gian</th>
                    </tr>
                </thead>
                <tbody id="modalTableBody"></tbody>
            </table>
        </div>
    </div>
</div>

<style>
.financial-monitor-container { margin-top: 10px; }
.monitor-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px; }
.monitor-title h2 { margin: 0 0 6px 0; font-size: 20px; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 10px; }
.monitor-title p { margin: 0; font-size: 13px; color: var(--muted, #636b80); max-width: 700px; }
.alert-count-pill { display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 30px; font-weight: 700; font-size: 12px; letter-spacing: 0.5px; }
.pill-danger { background: rgba(251, 113, 133, 0.15); color: #fb7185; border: 1px solid rgba(251, 113, 133, 0.3); animation: pulse-border 2s infinite; }
.pill-safe { background: rgba(52, 211, 153, 0.15); color: #34d399; border: 1px solid rgba(52, 211, 153, 0.3); }
@keyframes pulse-border { 0%, 100% { box-shadow: 0 0 0 rgba(251, 113, 133, 0); } 50% { box-shadow: 0 0 15px rgba(251, 113, 133, 0.3); } }

.monitor-metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; margin-bottom: 30px; }
.monitor-stat-card { background: var(--surface, #0e111a); border: 1px solid var(--border, rgba(255,255,255,0.07)); border-radius: 16px; padding: 20px; display: flex; align-items: center; gap: 16px; position: relative; overflow: hidden; }
.monitor-stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; }
.border-red::before { background: #fb7185; }
.border-amber::before { background: #fbbf24; }
.border-purple::before { background: #a78bfa; }
.stat-icon-wrap { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
.icon-red { background: rgba(251, 113, 133, 0.12); color: #fb7185; }
.icon-amber { background: rgba(251, 191, 36, 0.12); color: #fbbf24; }
.icon-purple { background: rgba(167, 139, 250, 0.12); color: #a78bfa; }
.stat-label { font-size: 11px; text-transform: uppercase; color: var(--muted, #636b80); font-weight: 600; letter-spacing: 0.5px; }
.stat-val { font-size: 24px; font-weight: 800; font-family: 'Space Mono', monospace; color: #fff; }
.stat-val small { font-size: 12px; font-weight: 400; opacity: 0.7; }

.monitor-section { background: var(--surface, #0e111a); border: 1px solid var(--border, rgba(255,255,255,0.07)); border-radius: 18px; padding: 24px; margin-bottom: 24px; }
.section-top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 10px; }
.section-top-bar h3 { margin: 0; font-size: 15px; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 10px; }
.badge-hint { font-size: 11px; background: rgba(255,255,255,0.05); padding: 4px 10px; border-radius: 6px; color: var(--muted, #636b80); }

.monitor-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.monitor-table th { text-align: left; padding: 12px 14px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--muted, #636b80); border-bottom: 1px solid var(--border, rgba(255,255,255,0.07)); }
.monitor-table td { padding: 12px 14px; border-bottom: 1px solid rgba(255,255,255,0.03); }
.monitor-table tr:hover td { background: rgba(255,255,255,0.02); }
.row-banned td { opacity: 0.5; background: rgba(251, 113, 133, 0.03); }
.row-overflow td { background: rgba(251, 191, 36, 0.04); }

.id-badge { font-family: 'Space Mono', monospace; font-size: 11px; background: rgba(255,255,255,0.06); padding: 2px 6px; border-radius: 4px; color: #a2a2bd; }
.user-meta-name { display: flex; flex-direction: column; }
.user-meta-name small { font-size: 11px; color: var(--muted, #636b80); }
.type-badge { font-size: 10px; font-weight: 600; padding: 3px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px; }
.badge-bot { background: rgba(167, 139, 250, 0.15); color: #a78bfa; }
.badge-human { background: rgba(79, 141, 255, 0.15); color: #4f8dff; }
.badge-banned { background: rgba(251, 113, 133, 0.15); color: #fb7185; }
.badge-danger { background: rgba(239, 68, 68, 0.2); color: #f87171; }

.winrate-pill { display: inline-block; padding: 3px 10px; border-radius: 20px; font-weight: 800; font-family: 'Space Mono', monospace; font-size: 12px; }
.winrate-high { background: rgba(251, 191, 36, 0.2); color: #fbbf24; }
.winrate-extreme { background: rgba(251, 113, 133, 0.25); color: #fb7185; animation: pulse-border 1.5s infinite; }

.action-buttons-wrap { display: flex; gap: 6px; justify-content: center; }
.btn-sm { padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; border: none; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 4px; }
.btn-inspect { background: rgba(79, 141, 255, 0.15); color: #4f8dff; }
.btn-inspect:hover { background: #4f8dff; color: #fff; }
.btn-lock { background: rgba(251, 113, 133, 0.15); color: #fb7185; }
.btn-lock:hover { background: #fb7185; color: #fff; }
.btn-unlock { background: rgba(52, 211, 153, 0.15); color: #34d399; }
.btn-unlock:hover { background: #34d399; color: #000; }
.btn-freeze { background: rgba(251, 191, 36, 0.15); color: #fbbf24; }
.btn-freeze:hover { background: #fbbf24; color: #000; }

.alert-box { padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 13px; font-weight: 600; }
.alert-success { background: rgba(52, 211, 153, 0.15); color: #34d399; border: 1px solid rgba(52, 211, 153, 0.3); }
.alert-warning { background: rgba(251, 191, 36, 0.15); color: #fbbf24; border: 1px solid rgba(251, 191, 36, 0.3); }
.empty-state-card { text-align: center; padding: 30px; color: var(--muted, #636b80); font-size: 13px; border: 1px dashed rgba(255,255,255,0.08); border-radius: 12px; }

/* Modal */
.inspector-modal { position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; }
.modal-overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(5px); }
.modal-body-card { position: relative; z-index: 10; background: #0e111a; border: 1px solid rgba(255,255,255,0.12); border-radius: 20px; width: 90%; max-width: 850px; max-height: 85vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 25px 60px rgba(0,0,0,0.7); }
.modal-header { padding: 18px 24px; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; justify-content: space-between; align-items: center; }
.modal-header h4 { margin: 0; font-size: 16px; color: #fff; }
.modal-close-btn { background: none; border: none; font-size: 24px; color: var(--muted, #636b80); cursor: pointer; }
.modal-content-scroll { padding: 20px 24px; overflow-y: auto; flex: 1; }
</style>

<script>
function openInspector(userId, userName) {
    document.getElementById('modalUserName').innerText = userName + ' (ID #' + userId + ')';
    document.getElementById('inspectorModal').style.display = 'flex';
    document.getElementById('modalLoading').style.display = 'block';
    document.getElementById('modalTable').style.display = 'none';

    fetch('admin_modules/financial_monitor.php?ajax=user_transactions&user_id=' + userId)
        .then(res => res.json())
        .then(data => {
            document.getElementById('modalLoading').style.display = 'none';
            const tbody = document.getElementById('modalTableBody');
            tbody.innerHTML = '';
            if (data.success && data.logs && data.logs.length > 0) {
                data.logs.forEach(l => {
                    const tr = document.createElement('tr');
                    const amtColor = l.amount >= 0 ? '#34d399' : '#fb7185';
                    tr.innerHTML = `
                        <td style="font-family:'Space Mono'; font-size:11px; opacity:0.6;">#${l.id}</td>
                        <td><span class="type-badge badge-human">${l.type}</span></td>
                        <td style="text-align:right; font-family:'Space Mono'; font-weight:700; color:${amtColor};">
                            ${l.amount >= 0 ? '+' : ''}${Number(l.amount).toLocaleString('vi-VN')}
                        </td>
                        <td style="text-align:right; font-family:'Space Mono'; font-weight:700; color:#ffd700;">
                            ${Number(l.balance_after).toLocaleString('vi-VN')}
                        </td>
                        <td style="font-size:12px; max-width:250px;">${l.reason}</td>
                        <td style="font-size:11px; opacity:0.6;">${l.time}</td>
                    `;
                    tbody.appendChild(tr);
                });
                document.getElementById('modalTable').style.display = 'table';
            } else {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:20px; color:#636b80;">Chưa có bản ghi giao dịch trong bảng economy_transaction_logs.</td></tr>';
                document.getElementById('modalTable').style.display = 'table';
            }
        })
        .catch(err => {
            document.getElementById('modalLoading').innerHTML = '<span style="color:#fb7185;">Lỗi khi tải dữ liệu!</span>';
        });
}

function closeInspector() {
    document.getElementById('inspectorModal').style.display = 'none';
}
</script>

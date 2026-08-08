<?php
session_start();
require_once 'db_connect.php';
require_once 'notification_helper.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}
$userId = $_SESSION['Iduser'];

// ── WARN Fix 5: Xử lý đổi xu dư sang GTLM ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convert_currency'])) {
    header('Content-Type: application/json; charset=utf-8');
    $eventId = (int)$_POST['event_id'];

    $event = $conn->query("SELECT * FROM seasonal_events WHERE id = $eventId AND (status='inactive' OR ends_at < NOW())")->fetch_assoc();
    $userData = $conn->query("SELECT event_currency FROM user_event_data WHERE user_id = $userId AND event_id = $eventId")->fetch_assoc();

    if (!$event || !$userData || (int)$userData['event_currency'] <= 0) {
        echo json_encode(['success' => false, 'message' => 'Không có xu để đổi!']);
        exit;
    }

    // Tỉ lệ giảm dần theo số ngày kể từ khi sự kiện kết thúc (hỗ trợ cấu hình động qua theme_config)
    $daysSinceEnd = (int)ceil((time() - strtotime($event['ends_at'])) / 86400);
    $theme = json_decode($event['theme_config'] ?? '{}', true);
    $cRates = $theme['convert_rates'] ?? [];
    
    $highDays = isset($cRates['high_days']) ? (int)$cRates['high_days'] : 7;
    $midDays  = isset($cRates['mid_days'])  ? (int)$cRates['mid_days']  : 30;
    
    $highRate = isset($cRates['high_rate']) ? (float)$cRates['high_rate'] : 0.50;
    $midRate  = isset($cRates['mid_rate'])  ? (float)$cRates['mid_rate']  : 0.25;
    $lowRate  = isset($cRates['low_rate'])  ? (float)$cRates['low_rate']  : 0.10;

    if ($daysSinceEnd <= $highDays)       $rate = $highRate;
    elseif ($daysSinceEnd <= $midDays)    $rate = $midRate;
    else                                  $rate = $lowRate;

    $xuDu    = (int)$userData['event_currency'];
    $gtlmEarned = (int)floor($xuDu * $rate);

    if ($gtlmEarned < 1) {
        echo json_encode(['success' => false, 'message' => 'Số xu quá nhỏ, không đủ để quy đổi.']);
        exit;
    }

    $conn->begin_transaction();
    try {
        // Trừ xu sự kiện về 0
        $conn->query("UPDATE user_event_data SET event_currency = 0 WHERE user_id = $userId AND event_id = $eventId");
        // Cộng GTLM
        $conn->query("UPDATE users SET money = money + $gtlmEarned WHERE Iduser = $userId");
        // Thông báo
        createNotification($conn, $userId, 'reward',
            '💱 Quy Đổi Xu Sự Kiện Thành Công!',
            "Bạn đã đổi $xuDu Xu Sự Kiện ({$event['name']}) lấy " . number_format($gtlmEarned) . " GTLM (tỉ lệ " . ($rate * 100) . "%).",
            '💰', 'event_archive.php', $eventId, true
        );
        $conn->commit();
        echo json_encode(['success' => true, 'gtlm_earned' => $gtlmEarned, 'rate' => $rate]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ── WARN Fix 2: Lấy dữ liệu thống kê đầy đủ cho mỗi sự kiện đã qua ─────────
$pastEvents = $conn->query("
    SELECT e.*,
           ud.points          as my_points,
           ud.event_currency  as my_currency,
           (SELECT COUNT(DISTINCT user_id) FROM user_event_data WHERE event_id = e.id AND points > 0) as total_participants,
           (SELECT COUNT(*) + 1 FROM user_event_data WHERE event_id = e.id AND points > IFNULL(ud.points, 0)) as my_rank,
           (SELECT COUNT(*) FROM user_achievements ua JOIN event_exchange_shop s ON ua.achievement_id = s.item_id WHERE s.event_id = e.id AND s.item_type = 'title' AND ua.user_id = $userId) as titles_won
    FROM seasonal_events e
    LEFT JOIN user_event_data ud ON e.id = ud.event_id AND ud.user_id = $userId
    WHERE e.status = 'inactive' OR e.ends_at < NOW()
    ORDER BY e.ends_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Top 3 mỗi sự kiện
$topThreeByEvent = [];
foreach ($pastEvents as $ev) {
    $eid = (int)$ev['id'];
    $topThreeByEvent[$eid] = $conn->query("
        SELECT u.Name as username, u.Avatar as avatar, d.points
        FROM user_event_data d
        JOIN users u ON d.user_id = u.Iduser
        WHERE d.event_id = $eid AND d.points > 0
        ORDER BY d.points DESC
        LIMIT 3
    ")->fetch_all(MYSQLI_ASSOC);
}

// Lấy lịch sử bình chọn của người chơi
$myVotesHistory = [];
$votesHistoryRes = $conn->query("
    SELECT uev.event_id, vo.title as option_title, vo.icon as option_icon
    FROM user_event_votes uev
    JOIN event_voting_options vo ON uev.option_id = vo.id
    WHERE uev.user_id = $userId
");
if ($votesHistoryRes) {
    while ($row = $votesHistoryRes->fetch_assoc()) {
        $myVotesHistory[(int)$row['event_id']] = $row;
    }
}

// Tỉ lệ quy đổi hiện tại cho từng sự kiện (hỗ trợ cấu hình động qua theme_config)
function getConvertRate(string $endsAt, ?array $theme = null): array {
    $days = (int)ceil((time() - strtotime($endsAt)) / 86400);
    
    $cRates = $theme['convert_rates'] ?? [];
    $highDays = isset($cRates['high_days']) ? (int)$cRates['high_days'] : 7;
    $midDays  = isset($cRates['mid_days'])  ? (int)$cRates['mid_days']  : 30;
    
    $highRate = isset($cRates['high_rate']) ? (float)$cRates['high_rate'] : 0.50;
    $midRate  = isset($cRates['mid_rate'])  ? (float)$cRates['mid_rate']  : 0.25;
    $lowRate  = isset($cRates['low_rate'])  ? (float)$cRates['low_rate']  : 0.10;

    if ($days <= $highDays) {
        $r = $highRate;
        $cls = 'rate-high';
    } elseif ($days <= $midDays) {
        $r = $midRate;
        $cls = 'rate-mid';
    } else {
        $r = $lowRate;
        $cls = 'rate-low';
    }
    
    $percent = $r * 100;
    return ['rate' => $percent, 'label' => $percent . '%', 'class' => $cls];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biên Niên Sử Kiện – Lịch Sử Vinh Quang</title>
    <meta name="description" content="Lịch sử các mùa sự kiện đã qua: thứ hạng, thành tích và xu dư của bạn.">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0f172a;
            --card: rgba(30, 41, 59, 0.75);
            --border: rgba(255,255,255,0.07);
            --text: #f8fafc;
            --muted: #94a3b8;
            --gold: #f59e0b;
            --silver: #94a3b8;
            --bronze: #cd7f32;
            --success: #22c55e;
            --danger: #ef4444;
            --info: #38bdf8;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
            background-image:
                radial-gradient(at 20% 0%,  rgba(56,189,248,0.08) 0, transparent 50%),
                radial-gradient(at 80% 100%, rgba(99,102,241,0.08) 0, transparent 50%);
        }

        .btn-back {
            position: fixed; top: 20px; left: 20px; z-index: 100;
            background: rgba(255,255,255,0.08); color: white;
            padding: 10px 20px; border-radius: 50px; text-decoration: none;
            backdrop-filter: blur(10px); font-weight: 600;
            border: 1px solid rgba(255,255,255,0.1);
            transition: background 0.2s;
        }
        .btn-back:hover { background: rgba(255,255,255,0.15); }

        .container { max-width: 1050px; margin: 0 auto; padding: 50px 20px; }

        .header { text-align: center; margin-bottom: 50px; }
        .header h1 {
            font-size: 2.8rem; font-weight: 900;
            background: linear-gradient(to right, #38bdf8, #818cf8, #f472b6);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .header p { color: var(--muted); margin-top: 10px; font-size: 1.05rem; }

        /* ── Archive Card ── */
        .archive-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 30px;
            margin-bottom: 28px;
            backdrop-filter: blur(12px);
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative; overflow: hidden;
        }
        .archive-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 40px rgba(0,0,0,0.4);
        }
        .archive-card::before {
            content: '';
            position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.02), transparent);
            pointer-events: none;
        }

        .card-header {
            display: flex; align-items: center; gap: 20px; margin-bottom: 24px;
        }
        .ac-icon {
            font-size: 52px;
            background: rgba(255,255,255,0.05);
            width: 90px; height: 90px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 20px; flex-shrink: 0;
        }
        .ac-meta { flex: 1; }
        .ac-title { font-size: 1.5rem; font-weight: 800; color: #e2e8f0; margin-bottom: 4px; }
        .ac-date  { color: var(--muted); font-size: 0.9rem; }

        /* ── My Stats Row ── */
        .my-stats {
            display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 20px;
        }
        .stat-pill {
            background: rgba(15,23,42,0.5);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 50px;
            padding: 8px 16px;
            display: flex; align-items: center; gap: 8px;
            font-weight: 600; font-size: 0.9rem;
        }
        .stat-pill.rank-1 { border-color: rgba(245,158,11,0.4); color: var(--gold); }
        .stat-pill.rank-2 { border-color: rgba(148,163,184,0.4); color: var(--silver); }
        .stat-pill.rank-3 { border-color: rgba(205,127,50,0.4);  color: var(--bronze); }
        .stat-pill .icon { font-size: 1.1em; }

        /* ── Divider ── */
        .section-divider {
            border: none; border-top: 1px solid var(--border); margin: 18px 0;
        }

        /* ── Two-col layout: top3 + convert ── */
        .card-bottom {
            display: grid; grid-template-columns: 1fr 1fr; gap: 20px;
        }
        @media (max-width: 640px) { .card-bottom { grid-template-columns: 1fr; } }

        /* ── Top 3 Podium ── */
        .top3-title { font-size: 0.8rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; }
        .top3-list { display: flex; flex-direction: column; gap: 8px; }
        .top3-item {
            display: flex; align-items: center; gap: 10px;
            background: rgba(255,255,255,0.03);
            border-radius: 12px; padding: 8px 12px;
        }
        .top3-rank {
            font-size: 1.2em; font-weight: 900; width: 28px; text-align: center;
        }
        .rank-gold   { color: var(--gold); }
        .rank-silver { color: var(--silver); }
        .rank-bronze { color: var(--bronze); }
        .top3-name  { flex: 1; font-weight: 600; font-size: 0.95rem; }
        .top3-pts   { color: var(--muted); font-size: 0.85rem; }

        /* ── Currency Convert Box ── */
        .convert-box {
            background: rgba(15,23,42,0.4);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 16px; padding: 18px;
        }
        .convert-box .cb-title { font-size: 0.8rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px; }
        .convert-amount { font-size: 1.4rem; font-weight: 900; color: #ef4444; margin-bottom: 6px; }
        .convert-rate { font-size: 0.85rem; color: var(--muted); margin-bottom: 12px; }
        .rate-high { color: #22c55e; font-weight: 700; }
        .rate-mid  { color: #f59e0b; font-weight: 700; }
        .rate-low  { color: #ef4444; font-weight: 700; }
        .btn-convert {
            width: 100%; padding: 11px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border: none; border-radius: 12px; color: #1a1a1a;
            font-weight: 800; font-size: 0.95rem; cursor: pointer;
            font-family: inherit; transition: all 0.2s;
        }
        .btn-convert:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(245,158,11,0.3); }
        .btn-convert:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        .convert-success { color: var(--success); font-weight: 700; margin-top: 8px; font-size: 0.9rem; display: none; }

        /* ── No Participation Badge ── */
        .no-part { color: var(--muted); font-size: 0.9rem; font-style: italic; }

        .empty-state {
            text-align: center; padding: 70px 20px; color: var(--muted);
            background: rgba(255,255,255,0.02); border-radius: 20px;
        }
        .empty-state i { font-size: 50px; margin-bottom: 15px; display: block; }
    </style>
</head>
<body>

    <a href="events.php" class="btn-back"><i class="fa fa-arrow-left"></i> Đại Sảnh Sự Kiện</a>

    <div class="container">
        <div class="header">
            <h1><i class="fa fa-book-journal-whills"></i> Biên Niên Sử Kiện</h1>
            <p>Thành tích, thứ hạng và vinh quang của bạn trong các mùa giải đã qua.</p>
        </div>

        <?php if (empty($pastEvents)): ?>
            <div class="empty-state">
                <i class="fa fa-box-open"></i>
                <p>Chưa có sự kiện nào được lưu trữ.</p>
            </div>
        <?php else: ?>
            <?php foreach ($pastEvents as $ev):
                $eid    = (int)$ev['id'];
                $theme  = json_decode($ev['theme_config'] ?? '{}', true);
                $primary = $theme['primary'] ?? '#38bdf8';
                $emoji  = $ev['theme_emoji'] ?: '🏆';
                $myPts  = (int)($ev['my_points'] ?? 0);
                $myXu   = (int)($ev['my_currency'] ?? 0);
                $myRank = (int)($ev['my_rank'] ?? 0);
                $total  = (int)($ev['total_participants'] ?? 0);
                $top3   = $topThreeByEvent[$eid] ?? [];
                $rateInfo = getConvertRate($ev['ends_at'], $theme);
                $participated = $myPts > 0 || $myXu > 0;
            ?>
            <div class="archive-card" style="border-left: 4px solid <?= $primary ?>;">
                <div class="card-header">
                    <div class="ac-icon"><?= $emoji ?></div>
                    <div class="ac-meta">
                        <div class="ac-title"><?= htmlspecialchars($ev['name']) ?></div>
                        <div class="ac-date">
                            <i class="fa fa-calendar-alt"></i>
                            <?= date('d/m/Y', strtotime($ev['starts_at'])) ?> → <?= date('d/m/Y', strtotime($ev['ends_at'])) ?>
                            &nbsp;·&nbsp; <i class="fa fa-users"></i> <?= number_format($total) ?> người tham gia
                        </div>
                    </div>
                </div>

                <?php if ($participated): ?>
                <!-- ── Thống kê cá nhân ── -->
                <div class="my-stats">
                    <div class="stat-pill <?= ($myPts > 0 && $myRank === 1) ? 'rank-1' : (($myPts > 0 && $myRank === 2) ? 'rank-2' : (($myPts > 0 && $myRank === 3) ? 'rank-3' : '')) ?>">
                        <span class="icon"><i class="fa fa-ranking-star"></i></span>
                        Hạng #<?= ($total > 0 && $myPts > 0) ? number_format($myRank) : '—' ?>
                        <?php if ($total > 0 && $myPts > 0): ?> / <?= number_format($total) ?><?php endif; ?>
                    </div>
                    <div class="stat-pill">
                        <span class="icon" style="color: var(--gold)"><i class="fa fa-trophy"></i></span>
                        <?= number_format($myPts) ?> Điểm
                    </div>
                    <?php if ($myXu > 0): ?>
                    <div class="stat-pill">
                        <span class="icon" style="color:#ef4444"><i class="fa fa-coins"></i></span>
                        <?= number_format($myXu) ?> Xu Dư
                    </div>
                    <?php endif; ?>
                    <?php if ($ev['titles_won'] > 0): ?>
                    <div class="stat-pill" style="color: var(--success);">
                        <span class="icon"><i class="fa fa-medal"></i></span>
                        Đã nhận danh hiệu
                    </div>
                    <?php endif; ?>
                    <?php if (isset($myVotesHistory[$eid])): ?>
                    <div class="stat-pill" style="border-color: rgba(56, 189, 248, 0.4); color: var(--info);">
                        <span class="icon"><i class="fa fa-vote-yea"></i></span>
                        Đã vote: <?= htmlspecialchars($myVotesHistory[$eid]['option_icon']) ?> <?= htmlspecialchars($myVotesHistory[$eid]['option_title']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <p class="no-part" style="margin-bottom: 18px;"><i class="fa fa-circle-info"></i> Bạn không tham gia sự kiện này.</p>
                <?php endif; ?>

                <hr class="section-divider">

                <div class="card-bottom">
                    <!-- ── Top 3 Server ── -->
                    <div>
                        <div class="top3-title"><i class="fa fa-crown"></i> Top 3 Server</div>
                        <?php if (empty($top3)): ?>
                            <p class="no-part">Không có dữ liệu.</p>
                        <?php else: ?>
                        <div class="top3-list">
                            <?php
                            $medals = ['🥇', '🥈', '🥉'];
                            $rankClasses = ['rank-gold', 'rank-silver', 'rank-bronze'];
                            foreach ($top3 as $i => $p):
                            ?>
                            <div class="top3-item">
                                <span class="top3-rank <?= $rankClasses[$i] ?>"><?= $medals[$i] ?></span>
                                <span class="top3-name"><?= htmlspecialchars($p['username']) ?></span>
                                <span class="top3-pts"><?= number_format($p['points']) ?> đ</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- ── Đổi Xu Dư ── -->
                    <div>
                        <div class="convert-box" id="convert-box-<?= $eid ?>">
                            <div class="cb-title"><i class="fa fa-arrows-rotate"></i> Đổi Xu Dư → GTLM</div>
                            <?php if ($myXu > 0): ?>
                                <div class="convert-amount"><?= number_format($myXu) ?> Xu Dư</div>
                                <div class="convert-rate">
                                    Tỉ lệ hiện tại: <span class="<?= $rateInfo['class'] ?>"><?= $rateInfo['label'] ?></span>
                                    ≈ <strong><?= number_format((int)floor($myXu * $rateInfo['rate'] / 100)) ?> GTLM</strong>
                                </div>
                                <button class="btn-convert" id="btn-convert-<?= $eid ?>"
                                        onclick="convertCurrency(<?= $eid ?>)">
                                    <i class="fa fa-exchange-alt"></i> Quy Đổi Ngay
                                </button>
                                <div class="convert-success" id="conv-ok-<?= $eid ?>"></div>
                            <?php else: ?>
                                <p class="no-part" style="margin-top:8px;">
                                    <?= $participated ? 'Không còn xu dư.' : 'Không có xu để đổi.' ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
    async function convertCurrency(eventId) {
        const btn = document.getElementById('btn-convert-' + eventId);
        const ok  = document.getElementById('conv-ok-'    + eventId);
        if (!confirm('Xác nhận quy đổi toàn bộ xu sự kiện này sang GTLM?')) return;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Đang xử lý...';

        const res  = await fetch('event_archive.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `convert_currency=1&event_id=${eventId}`
        });
        const data = await res.json();

        if (data.success) {
            ok.style.display = 'block';
            ok.innerHTML = `✅ Đổi thành công! +${data.gtlm_earned.toLocaleString('vi-VN')} GTLM`;
            btn.style.display = 'none';
            document.querySelectorAll(`#convert-box-${eventId} .convert-amount, #convert-box-${eventId} .convert-rate`).forEach(el => el.style.display = 'none');
            // Cập nhật badge xu dư
            document.querySelectorAll('.my-stats .stat-pill').forEach(p => {
                if (p.textContent.includes('Xu Dư')) p.style.display = 'none';
            });
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-exchange-alt"></i> Quy Đổi Ngay';
            if (typeof Swal !== 'undefined') { Swal.fire('Thông báo', String('⚠️ ' + data.message), 'warning'); } else { alert('⚠️ ' + data.message); };
        }
    }
    </script>
</body>
</html>

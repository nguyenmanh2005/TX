<?php
require_once 'db_connect.php';
session_start();
if (!isset($_SESSION['Iduser'])) { header('Location: login.php'); exit; }
$userId = (int)$_SESSION['Iduser'];
$user = $conn->query("SELECT Name, avatar FROM users WHERE Iduser=$userId")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>🔮 Lời Tiên Tri — Sự Kiện Hàng Tuần</title>
<meta name="description" content="Lão Tiên Tri công bố 3 lời tiên tri mỗi đầu tuần. Nếu cả 3 ứng nghiệm — server nhận Phúc Lành cộng đồng!">
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  :root {
    --gold: #f0c060;
    --gold2: #ffd97a;
    --purple: #7c3aed;
    --purple2: #a78bfa;
    --deep: #0a0612;
    --card: rgba(255,255,255,0.04);
    --border: rgba(160,120,255,0.18);
    --glow: 0 0 40px rgba(124,58,237,0.4);
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    background: var(--deep);
    color: #e2d9f3;
    font-family: 'Inter', sans-serif;
    min-height: 100vh;
    overflow-x: hidden;
  }

  /* ── Starfield ── */
  #stars-canvas { position: fixed; inset: 0; z-index: 0; pointer-events: none; }

  /* ── Layout ── */
  .page-wrap { position: relative; z-index: 1; max-width: 900px; margin: 0 auto; padding: 32px 16px 80px; }

  /* ── Header ── */
  .oracle-header {
    text-align: center; margin-bottom: 40px;
    animation: fadeDown .7s ease both;
  }
  .oracle-eye {
    font-size: 72px; line-height: 1;
    filter: drop-shadow(0 0 30px #a78bfa);
    animation: pulse-eye 3s ease-in-out infinite;
  }
  @keyframes pulse-eye { 0%,100%{filter:drop-shadow(0 0 20px #a78bfa);} 50%{filter:drop-shadow(0 0 50px #f0c060);} }
  .oracle-title {
    font-family: 'Cinzel', serif;
    font-size: clamp(24px, 6vw, 42px);
    font-weight: 900;
    background: linear-gradient(135deg, #f0c060, #a78bfa, #f0c060);
    background-size: 200%;
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    animation: shimmer 4s linear infinite;
    margin: 12px 0 8px;
  }
  @keyframes shimmer { 0%{background-position:0%} 100%{background-position:200%} }
  .oracle-subtitle { color: #9d8ec0; font-size: 14px; letter-spacing: .5px; }

  /* ── Week Badge ── */
  .week-badge {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(124,58,237,.2); border: 1px solid var(--border);
    border-radius: 99px; padding: 6px 18px; font-size: 13px; color: var(--purple2);
    margin-top: 16px;
  }
  .days-left { color: var(--gold); font-weight: 700; }

  /* ── Cards ── */
  .glass-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 20px;
    backdrop-filter: blur(12px);
    padding: 28px;
    margin-bottom: 24px;
    animation: fadeUp .6s ease both;
  }
  @keyframes fadeUp { from{opacity:0;transform:translateY(24px)} to{opacity:1;transform:none} }
  @keyframes fadeDown { from{opacity:0;transform:translateY(-20px)} to{opacity:1;transform:none} }

  /* ── Prophecy Cards ── */
  .prophecy-grid { display: grid; gap: 16px; }
  .prophecy-card {
    background: rgba(124,58,237,.08);
    border: 1px solid rgba(167,139,250,.2);
    border-radius: 16px;
    padding: 22px 24px;
    display: flex; align-items: flex-start; gap: 16px;
    transition: transform .2s, box-shadow .2s;
    position: relative; overflow: hidden;
  }
  .prophecy-card:hover { transform: translateY(-2px); box-shadow: var(--glow); }
  .prophecy-card.correct  { border-color: #22c55e55; background: rgba(34,197,94,.06); }
  .prophecy-card.wrong    { border-color: #ef444455; background: rgba(239,68,68,.06); }
  .prophecy-card.pending  { border-color: rgba(167,139,250,.2); }
  .prophecy-num {
    width: 36px; height: 36px; border-radius: 50%;
    background: linear-gradient(135deg, var(--purple), #4c1d95);
    display: flex; align-items: center; justify-content: center;
    font-family: 'Cinzel', serif; font-weight: 700; font-size: 16px;
    color: var(--gold2); flex-shrink: 0;
    box-shadow: 0 0 16px rgba(124,58,237,.5);
  }
  .prophecy-body { flex: 1; }
  .prophecy-text {
    font-family: 'Cinzel', serif;
    font-size: 15px; line-height: 1.7;
    color: #e8d8ff;
    font-style: italic;
  }
  .prophecy-meta { margin-top: 10px; font-size: 12px; color: #7c6a9a; display: flex; align-items: center; gap: 8px; }
  .prophecy-result-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 10px; border-radius: 99px; font-size: 11px; font-weight: 600;
  }
  .badge-correct { background: rgba(34,197,94,.2); color: #4ade80; }
  .badge-wrong   { background: rgba(239,68,68,.2);  color: #f87171; }
  .badge-pending { background: rgba(167,139,250,.15); color: #a78bfa; }

  /* ── Score Bar ── */
  .score-section { text-align: center; }
  .score-orbs { display: flex; gap: 20px; justify-content: center; margin: 20px 0; }
  .orb {
    width: 70px; height: 70px; border-radius: 50%;
    border: 2px solid rgba(167,139,250,.3);
    display: flex; align-items: center; justify-content: center;
    font-size: 28px; transition: all .4s;
    position: relative;
  }
  .orb.lit {
    border-color: var(--gold);
    box-shadow: 0 0 30px rgba(240,192,96,.5), inset 0 0 20px rgba(240,192,96,.1);
    animation: orb-glow 2s ease-in-out infinite;
  }
  @keyframes orb-glow { 0%,100%{box-shadow:0 0 20px rgba(240,192,96,.4)} 50%{box-shadow:0 0 45px rgba(240,192,96,.8)} }
  .orb.wrong-orb { border-color: #ef4444; box-shadow: 0 0 20px rgba(239,68,68,.3); }
  .score-label { font-family: 'Cinzel', serif; font-size: 13px; color: #9d8ec0; letter-spacing: 1px; }

  /* ── Buff Banner ── */
  .buff-banner {
    background: linear-gradient(135deg, rgba(240,192,96,.15), rgba(124,58,237,.15));
    border: 1px solid rgba(240,192,96,.4);
    border-radius: 16px; padding: 20px 24px;
    display: flex; align-items: center; gap: 16px;
    animation: buff-pulse 3s ease infinite;
  }
  @keyframes buff-pulse { 0%,100%{border-color:rgba(240,192,96,.4)} 50%{border-color:rgba(240,192,96,.9)} }
  .buff-icon { font-size: 40px; }
  .buff-title { font-family: 'Cinzel', serif; font-size: 16px; color: var(--gold); font-weight: 700; }
  .buff-desc  { font-size: 13px; color: #c4b3e0; margin-top: 4px; }
  .buff-timer { font-size: 12px; color: var(--purple2); margin-top: 6px; }

  /* ── Witness Button ── */
  .witness-section { text-align: center; margin-top: 8px; }
  .btn-witness {
    background: linear-gradient(135deg, var(--purple), #4c1d95);
    border: none; border-radius: 99px;
    padding: 14px 36px; font-size: 15px; font-weight: 600;
    color: #fff; cursor: pointer; letter-spacing: .5px;
    box-shadow: 0 4px 20px rgba(124,58,237,.4);
    transition: all .25s; font-family: 'Inter', sans-serif;
    display: inline-flex; align-items: center; gap: 8px;
  }
  .btn-witness:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 8px 32px rgba(124,58,237,.6); }
  .btn-witness:disabled { opacity: .5; cursor: default; }
  .witness-count { font-size: 13px; color: #7c6a9a; margin-top: 10px; }

  /* ── No Week State ── */
  .no-week { text-align: center; padding: 60px 20px; }
  .no-week-icon { font-size: 64px; opacity: .5; }
  .no-week h3 { font-family: 'Cinzel', serif; color: #6b5894; margin: 16px 0 8px; }
  .no-week p  { color: #5a4d72; font-size: 14px; }

  /* ── History ── */
  .history-item {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 0; border-bottom: 1px solid rgba(255,255,255,.05);
  }
  .history-item:last-child { border-bottom: none; }
  .history-week { font-size: 13px; color: #9d8ec0; min-width: 130px; }
  .history-score { font-family: 'Cinzel', serif; font-weight: 700; font-size: 18px; }
  .history-score.s3 { color: var(--gold); }
  .history-score.s2 { color: #60a5fa; }
  .history-score.s1 { color: #a78bfa; }
  .history-score.s0 { color: #6b7280; }
  .history-buff { font-size: 12px; }

  /* ── Section Title ── */
  .section-title {
    font-family: 'Cinzel', serif; font-size: 15px; font-weight: 700;
    color: var(--gold2); letter-spacing: 1px;
    margin-bottom: 20px; display: flex; align-items: center; gap: 10px;
  }
  .section-title::after { content:''; flex:1; height:1px; background:linear-gradient(90deg,rgba(240,192,96,.3),transparent); }

  /* ── Toast ── */
  #toast {
    position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%) translateY(100px);
    background: rgba(20,10,40,.95); border: 1px solid var(--border);
    border-radius: 12px; padding: 14px 24px;
    font-size: 14px; color: #e2d9f3; z-index: 9999;
    transition: transform .4s cubic-bezier(.34,1.56,.64,1);
    backdrop-filter: blur(16px); max-width: 340px; text-align: center;
  }
  #toast.show { transform: translateX(-50%) translateY(0); }
  #toast.success { border-color: #22c55e55; }
  #toast.error   { border-color: #ef444455; }

  .section-stagger-1 { animation-delay: .1s; }
  .section-stagger-2 { animation-delay: .2s; }
  .section-stagger-3 { animation-delay: .3s; }
</style>
</head>
<body>
<canvas id="stars-canvas"></canvas>

<div class="page-wrap">

  <!-- Header -->
  <div class="oracle-header">
    <div class="oracle-eye">🔮</div>
    <div class="oracle-title">Lời Tiên Tri</div>
    <div class="oracle-subtitle">Lão Tiên Tri công bố 3 lời tiên tri mỗi đầu tuần. Cả 3 ứng nghiệm — server nhận Phúc Lành!</div>
    <div id="week-badge" class="week-badge" style="display:none">
      <span>📅</span>
      <span id="week-range"></span>
      &bull;
      <span class="days-left" id="days-left"></span>
    </div>
  </div>

  <!-- Community Buff (if active) -->
  <div id="buff-section" style="display:none" class="glass-card section-stagger-1">
    <div class="buff-banner" id="buff-banner">
      <div class="buff-icon">✨</div>
      <div>
        <div class="buff-title" id="buff-title">Phúc Lành Tiên Tri Đang Hiệu Lực!</div>
        <div class="buff-desc"  id="buff-desc"></div>
        <div class="buff-timer" id="buff-timer"></div>
      </div>
    </div>
  </div>

  <!-- Score Orbs -->
  <div class="glass-card score-section section-stagger-1" id="score-card" style="display:none">
    <div class="section-title">⚡ Tiến Độ Tuần Này</div>
    <div class="score-orbs" id="score-orbs">
      <div class="orb" id="orb1">🔮</div>
      <div class="orb" id="orb2">🔮</div>
      <div class="orb" id="orb3">🔮</div>
    </div>
    <div class="score-label" id="score-label">Đang theo dõi...</div>
  </div>

  <!-- Prophecies -->
  <div class="glass-card section-stagger-2" id="prophecy-card">
    <div class="section-title">🔮 Lời Tiên Tri Tuần Này</div>
    <div class="prophecy-grid" id="prophecy-grid">
      <div class="no-week">
        <div class="no-week-icon">🌙</div>
        <h3>Chưa có lời tiên tri</h3>
        <p>Lão Tiên Tri đang chiêm tinh... hãy quay lại vào đầu tuần.</p>
      </div>
    </div>
  </div>

  <!-- Witness CTA -->
  <div class="glass-card witness-section section-stagger-2" id="witness-card" style="display:none">
    <div class="section-title">👁️ Chứng Nhân</div>
    <p style="color:#9d8ec0;font-size:14px;margin-bottom:18px">Hãy "chứng kiến" lời tiên tri để ghi danh vào sử sách. Nếu cả 3 lời đúng, ngươi sẽ được Lão nhớ mãi.</p>
    <button class="btn-witness" id="witness-btn" onclick="doWitness()">
      <span>👁️</span> Tôi Chứng Kiến
    </button>
    <div class="witness-count" id="witness-count"></div>
  </div>

  <!-- History -->
  <div class="glass-card section-stagger-3" id="history-card" style="display:none">
    <div class="section-title">📜 Lịch Sử Tiên Tri</div>
    <div id="history-list"></div>
  </div>

</div><!-- /page-wrap -->

<div id="toast"></div>

<script>
// ── Stars ──────────────────────────────────────────────────────────────────
(function(){
  const c = document.getElementById('stars-canvas');
  const ctx = c.getContext('2d');
  let stars = [];
  function resize() { c.width=innerWidth; c.height=innerHeight; }
  resize(); addEventListener('resize', resize);
  for(let i=0;i<200;i++) stars.push({x:Math.random()*9999,y:Math.random()*9999,r:Math.random()*1.4+.2,a:Math.random(),da:Math.random()*.006-.003});
  (function draw(){
    ctx.clearRect(0,0,c.width,c.height);
    stars.forEach(s=>{
      s.a=Math.max(0.05,Math.min(1,s.a+s.da));
      if(s.a<=0.05||s.a>=1) s.da*=-1;
      const sx=s.x%c.width, sy=s.y%c.height;
      ctx.beginPath(); ctx.arc(sx,sy,s.r,0,Math.PI*2);
      ctx.fillStyle=`rgba(200,170,255,${s.a})`; ctx.fill();
    });
    requestAnimationFrame(draw);
  })();
})();

// ── Data ───────────────────────────────────────────────────────────────────
let weekData = null;
let hasWitnessed = false;

async function loadData() {
  try {
    const r = await fetch('api_oracle_prophecy.php?action=get_current');
    const d = await r.json();
    if (!d.success) return;

    weekData = d;
    hasWitnessed = d.has_witnessed;

    // Week badge
    if (d.week) {
      const ws = d.week.week_start, we = d.week.week_end;
      document.getElementById('week-range').textContent = `${formatDate(ws)} → ${formatDate(we)}`;
      document.getElementById('days-left').textContent = d.days_left + ' ngày còn lại';
      document.getElementById('week-badge').style.display = 'inline-flex';
    }

    // Buff banner
    if (d.buff) {
      document.getElementById('buff-section').style.display = '';
      document.getElementById('buff-title').textContent = d.buff.description;
      document.getElementById('buff-desc').textContent = '+' + Math.round((parseFloat(d.buff.multiplier)-1)*100) + '% GTLM cho toàn bộ chiến thắng!';
      const exp = new Date(d.buff.expires_at);
      document.getElementById('buff-timer').textContent = '⏳ Hết hạn: ' + exp.toLocaleDateString('vi-VN') + ' lúc ' + exp.toLocaleTimeString('vi-VN');
    }

    // Prophecies
    if (d.prophecies && d.prophecies.length > 0) {
      renderProphecies(d.prophecies);
      renderScore(d.prophecies);
      document.getElementById('score-card').style.display = '';
      // Witness section only if week active
      if (d.week && d.week.status === 'active') {
        document.getElementById('witness-card').style.display = '';
        updateWitnessUI(d.has_witnessed, d.witness_count);
      }
    }

    // History
    await loadHistory();

  } catch(e) { console.error(e); }
}

function renderProphecies(prophecies) {
  const grid = document.getElementById('prophecy-grid');
  grid.innerHTML = '';
  prophecies.forEach(p => {
    const result = p.result || 'pending';
    const resultBadge = result === 'correct'
      ? '<span class="prophecy-result-badge badge-correct">✅ Ứng nghiệm</span>'
      : result === 'wrong'
      ? '<span class="prophecy-result-badge badge-wrong">❌ Không ứng nghiệm</span>'
      : '<span class="prophecy-result-badge badge-pending">🔮 Đang theo dõi</span>';

    const actualInfo = result !== 'pending' && p.actual_value !== undefined
      ? `<span>Thực tế: <b>${p.actual_value.toLocaleString()}</b></span>` : '';

    grid.innerHTML += `
      <div class="prophecy-card ${result}">
        <div class="prophecy-num">${p.prophecy_index}</div>
        <div class="prophecy-body">
          <div class="prophecy-text">"${p.prophecy_text}"</div>
          <div class="prophecy-meta">
            ${resultBadge}
            ${actualInfo}
          </div>
        </div>
      </div>`;
  });
}

function renderScore(prophecies) {
  const scores = [null, null, null];
  let correct = 0;
  prophecies.forEach((p, i) => {
    scores[i] = p.result;
    if (p.result === 'correct') correct++;
  });

  for (let i = 0; i < 3; i++) {
    const orb = document.getElementById('orb' + (i+1));
    orb.className = 'orb';
    if (scores[i] === 'correct') { orb.className = 'orb lit'; orb.textContent = '⭐'; }
    else if (scores[i] === 'wrong') { orb.className = 'orb wrong-orb'; orb.textContent = '✖'; }
    else { orb.textContent = '🔮'; }
  }

  const allPending = prophecies.every(p => p.result === 'pending');
  if (allPending) {
    document.getElementById('score-label').textContent = 'Lão đang quan sát... Cuối tuần sẽ có phán quyết.';
  } else {
    document.getElementById('score-label').textContent =
      correct === 3 ? '🎉 CẢ 3 LỜI ỨNG NGHIỆM — PHÚC LÀNH BAN XUỐNG!' :
      correct === 2 ? '✨ 2/3 lời đúng — Gần rồi!' :
      correct === 1 ? '🔮 1/3 lời đúng' : '❌ 0/3 — Thiên cơ vẫn còn bí ẩn';
  }
}

function updateWitnessUI(witnessed, count) {
  const btn = document.getElementById('witness-btn');
  const cnt = document.getElementById('witness-count');
  if (witnessed) {
    btn.disabled = true;
    btn.innerHTML = '<span>✅</span> Đã Chứng Kiến';
  }
  cnt.textContent = `👥 ${count || 0} chiến binh đã chứng kiến lời tiên tri này`;
}

async function doWitness() {
  if (hasWitnessed) return;
  const btn = document.getElementById('witness-btn');
  btn.disabled = true;
  try {
    const fd = new FormData();
    fd.append('action', 'witness');
    const r = await fetch('api_oracle_prophecy.php', {method:'POST', body:fd});
    const d = await r.json();
    showToast(d.message, d.success ? 'success' : 'error');
    if (d.success) {
      hasWitnessed = true;
      btn.innerHTML = '<span>✅</span> Đã Chứng Kiến';
      document.getElementById('witness-count').textContent = `👥 ${d.witness_count} chiến binh đã chứng kiến lời tiên tri này`;
    } else { btn.disabled = false; }
  } catch(e) { btn.disabled = false; showToast('Lỗi kết nối', 'error'); }
}

async function loadHistory() {
  // Fetch completed weeks
  try {
    const r = await fetch('api_oracle_prophecy.php?action=get_history');
    const d = await r.json();
    if (!d.success || !d.history || !d.history.length) return;
    const list = document.getElementById('history-list');
    list.innerHTML = '';
    d.history.forEach(w => {
      const cc = parseInt(w.correct_count);
      const scoreClass = cc===3?'s3':cc===2?'s2':cc===1?'s1':'s0';
      const buffIcon = w.buff_type === 'oracle_blessing' ? '✨ Buff cộng đồng' : '—';
      list.innerHTML += `
        <div class="history-item">
          <div class="history-week">${formatDate(w.week_start)} → ${formatDate(w.week_end)}</div>
          <div class="history-score ${scoreClass}">${cc}/3</div>
          <div class="history-buff" style="color:${cc===3?'#f0c060':'#6b7280'}">${buffIcon}</div>
        </div>`;
    });
    document.getElementById('history-card').style.display = '';
  } catch(e) {}
}

function formatDate(d) {
  const dt = new Date(d + 'T00:00:00');
  return dt.toLocaleDateString('vi-VN', {day:'2-digit', month:'2-digit'});
}

function showToast(msg, type='') {
  const t = document.getElementById('toast');
  t.textContent = msg; t.className = 'show ' + type;
  clearTimeout(t._tid);
  t._tid = setTimeout(() => t.className = type, 3200);
}

loadData();
</script>
</body>
</html>

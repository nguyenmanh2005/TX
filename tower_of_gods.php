<?php
/**
 * Tháp Thần Bài — Vận Mệnh Chi Lộ (V5)
 * Giao diện và Gameplay hoàn toàn mới theo Kịch Bản Chi Tiết!
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db_connect.php';
$userId = isset($_SESSION['Iduser']) ? (int)$_SESSION['Iduser'] : 1;
require_once __DIR__ . '/load_theme.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>🗼 Tháp Thần Bài — Vận Mệnh Chi Lộ V5</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@700;900&family=Outfit:wght@400;600;800;900&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;overflow:hidden;font-family:'Outfit',sans-serif;background:#030611;color:#e2e8f0;cursor:url('img/chuot.png'),auto}

/* ── BG ── */
#bg{position:fixed;inset:0;z-index:0;
  background:radial-gradient(ellipse 80% 50% at 50% -10%,rgba(168,85,247,0.35) 0%,transparent 70%),
    radial-gradient(ellipse 50% 50% at 10% 90%,rgba(59,130,246,0.18) 0%,transparent 60%),
    radial-gradient(ellipse 40% 40% at 90% 90%,rgba(239,68,68,0.12) 0%,transparent 50%),#030611}

/* Stars */
.star{position:fixed;border-radius:50%;background:#fff;animation:twinkle var(--d,3s) ease-in-out infinite;opacity:0;pointer-events:none;z-index:0}
@keyframes twinkle{0%,100%{opacity:0;transform:scale(1)}50%{opacity:var(--o,.6);transform:scale(1.4)}}

/* ── LAYOUT ── */
#app{position:relative;z-index:1;display:grid;grid-template-columns:300px 1fr 320px;height:100vh;overflow:hidden}

/* ── PANELS ── */
.panel{background:rgba(8,12,30,0.85);border:1px solid rgba(251,191,36,0.12);backdrop-filter:blur(20px);display:flex;flex-direction:column;overflow:hidden}
.panel-head{padding:18px 16px;border-bottom:1px solid rgba(251,191,36,0.12);text-align:center;flex-shrink:0}
.panel-title{font-family:'Cinzel Decorative',serif;font-size:12.5px;letter-spacing:2px;font-weight:900;
  background:linear-gradient(to right,#fbbf24,#fff,#fbbf24);background-size:200%;
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;animation:shimmerText 3s linear infinite}
@keyframes shimmerText{0%{background-position:0%}100%{background-position:200%}}

/* ── LEFT PANEL: PROGRESS & STATS ── */
.floor-track{flex:1;overflow-y:auto;padding:12px;display:flex;flex-direction:column;gap:5px}
.floor-track::-webkit-scrollbar{width:4px}
.floor-track::-webkit-scrollbar-thumb{background:rgba(251,191,36,0.25);border-radius:2px}
.fp-row{display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:10px;font-size:12.5px;font-weight:700;border:1px solid transparent;transition:all 0.2s}
.fp-row.done{color:#34d399;background:rgba(52,211,153,0.08)}
.fp-row.current{color:#fbbf24;background:rgba(251,191,36,0.15);border-color:rgba(251,191,36,0.4);box-shadow:0 0 14px rgba(251,191,36,0.18)}
.fp-row.upcoming{color:#334155}
.fp-row.boss-f{color:#fca5a5!important;background:rgba(239,68,68,0.05);border-color:rgba(239,68,68,0.2)}
.fp-row.boss-f.current{color:#f87171!important;background:rgba(239,68,68,0.2);border-color:rgba(239,68,68,0.5)}
.fp-icon{font-size:14px;width:20px;text-align:center;flex-shrink:0}
.fp-name{flex:1}
.fp-tag{font-size:9px;padding:2px 6px;border-radius:5px;background:rgba(239,68,68,0.25);color:#f87171;font-weight:900}

.stats-panel{padding:16px;border-top:1px solid rgba(251,191,36,0.12);background:rgba(4,8,20,0.5);flex-shrink:0}
.stat-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:13px}
.stat-row:last-child{border:none}
.stat-label{color:#475569;text-transform:uppercase;letter-spacing:1px;font-weight:600}
.stat-value{font-weight:800;color:#f1f5f9}

/* ── CENTER: ARENA ── */
#center{display:flex;flex-direction:column;align-items:center;justify-content:space-between;padding:24px 20px;position:relative;overflow:hidden}

.arena-header{display:flex;justify-content:space-between;align-items:center;width:100%;max-width:550px;flex-shrink:0;z-index:5}
.floor-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.3);color:#fca5a5;padding:6px 16px;border-radius:20px;font-size:12px;font-weight:900;letter-spacing:2px;text-transform:uppercase}
.floor-number-text{font-family:'Cinzel Decorative',serif;font-size:clamp(40px,7vw,65px);font-weight:900;line-height:1;background:linear-gradient(180deg,#fff 0%,#fbbf24 60%,#d97706 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;filter:drop-shadow(0 0 20px rgba(251,191,36,0.45));animation:glowPulse 2.5s ease-in-out infinite}
@keyframes glowPulse{0%,100%{filter:drop-shadow(0 0 15px rgba(251,191,36,0.35))}50%{filter:drop-shadow(0 0 35px rgba(251,191,36,0.75))}}
.reward-display-pill{background:rgba(251,191,36,0.12);border:1px solid rgba(251,191,36,0.35);color:#fbbf24;padding:6px 16px;border-radius:20px;font-size:14px;font-weight:900}

/* Canvas Area */
.wheel-container{position:relative;display:flex;align-items:center;justify-content:center;flex:1;width:100%;z-index:2}
#wheelCanvas{border-radius:50%;max-width:min(60vw, 420px);max-height:min(60vh, 420px);box-shadow:0 0 50px rgba(0,0,0,0.6);transition:filter 0.3s}
#wheelCanvas:hover{filter:drop-shadow(0 0 30px rgba(251,191,36,0.2))}

/* Live Speed Bar */
.speed-bar-wrap{width:100%;max-width:440px;flex-shrink:0;z-index:5;margin-bottom:12px}
.speed-lbl-row{display:flex;justify-content:space-between;font-size:11px;color:#475569;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px}
.speed-track{height:6px;background:rgba(255,255,255,0.05);border-radius:3px;overflow:hidden;border:1px solid rgba(255,255,255,0.03)}
.speed-bar-fill{height:100%;width:100%;background:linear-gradient(to right,#22c55e,#fbbf24,#ef4444);border-radius:3px;transition:width 0.1s}

/* Controls */
.control-zone{width:100%;display:flex;flex-direction:column;align-items:center;gap:12px;z-index:5}
.prompt-text{font-size:14.5px;color:#64748b;text-align:center;min-height:42px;line-height:1.5}
.btn-stop{
  padding:18px 60px;border-radius:18px;font-weight:900;font-size:24px;
  font-family:'Outfit',sans-serif;border:2px solid #fef08a;
  background:linear-gradient(135deg,#f59e0b,#d97706);color:#000;
  cursor:url('img/tay.png'),pointer!important;
  transition:all 0.25s;box-shadow:0 8px 30px rgba(245,158,11,0.4);position:relative;overflow:hidden;
}
.btn-stop::before{content:'';position:absolute;top:-50%;left:-75%;width:50%;height:200%;background:rgba(255,255,255,0.3);transform:skewX(-20deg);animation:btnHighlight 2.5s ease-in-out infinite}
@keyframes btnHighlight{0%,100%{left:-75%;opacity:0}40%{opacity:1}60%{left:125%;opacity:0}}
.btn-stop:hover{transform:translateY(-4px);box-shadow:0 14px 45px rgba(245,158,11,0.6)}
.btn-stop:active{transform:translateY(1px) scale(0.97)}
.btn-stop:disabled{opacity:0.35;cursor:default!important;transform:none!important;box-shadow:none!important;animation:none!important}
.btn-stop:disabled::before{display:none}

/* Post-Game Buttons */
.post-action-btn{padding:14px 40px;border-radius:14px;font-weight:800;font-size:16px;font-family:'Outfit',sans-serif;border:1px solid;cursor:url('img/tay.png'),pointer!important;transition:all 0.2s}
.btn-advance-floor{background:linear-gradient(135deg,#22c55e,#15803d);border-color:#4ade80;color:#fff;box-shadow:0 6px 20px rgba(34,197,94,0.3)}
.btn-advance-floor:hover{transform:translateY(-3px);box-shadow:0 12px 30px rgba(34,197,94,0.5)}
.btn-retry-floor{background:rgba(239,68,68,0.15);border-color:rgba(239,68,68,0.45);color:#f87171}
.btn-retry-floor:hover{background:rgba(239,68,68,0.25);transform:translateY(-3px)}

/* Overlay Result */
.overlay-result-stage{
  display:none;position:absolute;inset:0;background:rgba(3,6,17,0.94);
  backdrop-filter:blur(8px);z-index:20;
  flex-direction:column;align-items:center;justify-content:center;gap:20px;
  animation:fadeStage .35s ease-out;
}
@keyframes fadeStage{from{opacity:0}to{opacity:1}}
.overlay-result-stage.active{display:flex}
.ov-icon-anim{font-size:76px;animation:bounceIcon .6s cubic-bezier(0.175, 0.885, 0.32, 1.275)}
@keyframes bounceIcon{from{transform:scale(0) rotate(-30deg);opacity:0}to{transform:scale(1) rotate(0);opacity:1}}
.ov-title-text{font-family:'Cinzel Decorative',serif;font-size:26px;font-weight:900;text-align:center}
.ov-subtitle-text{font-size:14.5px;color:#94a3b8;text-align:center;max-width:380px;line-height:1.6;padding:0 12px}
.ov-reward-capsule{display:inline-flex;align-items:center;gap:8px;padding:10px 24px;border-radius:99px;font-size:19px;font-weight:900;background:rgba(251,191,36,0.15);border:1px solid rgba(251,191,36,0.4);color:#fbbf24}

/* ── RIGHT PANEL: CHARACTERS & SKILLS ── */
.rp-body{padding:16px;flex:1;overflow-y:auto;display:flex;flex-direction:column;gap:18px}
.rp-body::-webkit-scrollbar{width:3px}
.rp-body::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.08);border-radius:2px}

/* Character Card */
.char-card{background:linear-gradient(135deg,rgba(15,23,42,0.8),rgba(30,41,59,0.8));border:1px solid rgba(251,191,36,0.25);border-radius:18px;padding:16px;position:relative}
.char-info-row{display:flex;align-items:center;gap:14px}
.char-avatar{width:64px;height:64px;border-radius:50%;border:2px solid #fbbf24;box-shadow:0 0 15px rgba(251,191,36,0.3);flex-shrink:0;object-fit:cover;background:#0d1527}
.char-meta{flex:1}
.char-class-tag{font-size:11px;color:#fbbf24;font-weight:900;text-transform:uppercase;letter-spacing:1px}
.char-name-text{font-size:18px;font-weight:900;color:#f1f5f9;margin-top:2px}
.char-desc-block{margin-top:14px;border-top:1px dashed rgba(255,255,255,0.08);padding-top:12px;font-size:13px;color:#94a3b8;line-height:1.5}
.char-passive-badge{display:inline-block;background:rgba(59,130,246,0.15);border:1px solid rgba(59,130,246,0.3);color:#60a5fa;font-size:11px;font-weight:800;padding:2px 8px;border-radius:6px;margin-bottom:6px}

/* Skills Zone */
.skill-control-box{background:rgba(10,15,35,0.6);border:1px solid rgba(255,255,255,0.04);border-radius:16px;padding:14px;text-align:center}
.btn-skill-cast{
  width:100%;padding:14px;border-radius:12px;font-family:'Outfit',sans-serif;font-weight:900;font-size:15px;
  border:1px solid;cursor:url('img/tay.png'),pointer!important;transition:all 0.2s;text-transform:uppercase;letter-spacing:1px;
}
.btn-skill-cast.ready{background:linear-gradient(135deg,#a855f7,#6b21a8);border-color:#d8b4fe;color:#fff;box-shadow:0 4px 15px rgba(168,85,247,0.35)}
.btn-skill-cast.ready:hover{transform:translateY(-3px);box-shadow:0 8px 22px rgba(168,85,247,0.5)}
.btn-skill-cast.active-buff{background:linear-gradient(135deg,#22c55e,#15803d);border-color:#4ade80;color:#fff;box-shadow:0 0 15px rgba(34,197,94,0.4);animation:buffPulse 1.5s ease-in-out infinite alternate}
@keyframes buffPulse{from{filter:brightness(1)}to{filter:brightness(1.2)}}
.btn-skill-cast.cooldown{background:rgba(15,23,42,0.6);border-color:rgba(255,255,255,0.08);color:#475569;cursor:default!important}

/* Character Selector Bar */
.char-selector-bar{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:10px}
.char-select-btn{
  background:rgba(15,23,42,0.6);border:1px solid rgba(255,255,255,0.06);border-radius:10px;
  padding:8px 4px;font-size:11px;font-weight:800;color:#64748b;font-family:'Outfit',sans-serif;
  cursor:url('img/tay.png'),pointer!important;transition:all 0.2s;text-align:center;
}
.char-select-btn.active{background:rgba(251,191,36,0.12);border-color:rgba(251,191,36,0.4);color:#fbbf24}
.char-select-btn:hover:not(.active){background:rgba(255,255,255,0.03);color:#e2e8f0}

/* Particle Canvas */
#pc{position:fixed;inset:0;pointer-events:none;z-index:999}
</style>
</head>
<body>
<div id="bg"></div>
<canvas id="pc"></canvas>

<div id="app">

  <!-- ================= LEFT PANEL ================= -->
  <div class="panel" id="left-panel">
    <div class="panel-head">
      <div class="panel-title">🗼 LỘ TRÌNH THÁP</div>
      <div style="font-size:10.5px;color:#475569;margin-top:4px;letter-spacing:1px;font-weight:700">100 TẦNG VẬN MỆNH CHI LỘ</div>
    </div>
    <div class="floor-track" id="floorTrack">
      <div style="color:#334155;font-size:13px;text-align:center;padding:20px;">Đang tải lộ trình...</div>
    </div>
    <div class="stats-panel">
      <div class="stat-row"><span class="stat-label">Kỷ Lục Cao Nhất</span><span class="stat-value" style="color:#fbbf24">Tầng <span id="sBest">-</span></span></div>
      <div class="stat-row"><span class="stat-label">Tổng Trận Thắng</span><span class="stat-value" style="color:#34d399"><span id="sWins">-</span> trận</span></div>
      <div class="stat-row"><span class="stat-label">Tổng Phúc Lộc GTLM</span><span class="stat-value" style="color:#c084fc;font-size:12.5px;" id="sGtlm">-</span></div>
    </div>
  </div>

  <!-- ================= CENTER ARENA ================= -->
  <div id="center">
    <!-- Header top -->
    <div class="arena-header">
      <div class="floor-pill" id="floorPill">🔥 TẦNG <span id="floorNum">-</span></div>
      <div class="floor-number-text" id="floorBig">-</div>
      <div class="reward-display-pill" id="rewardPill">💰 <span id="rewardAmt">-</span></div>
    </div>

    <!-- Canvas Wheel -->
    <div class="wheel-container">
      <div class="mult-flash" id="multFlash" style="display:none;position:absolute;font-family:'Cinzel Decorative',serif;font-size:72px;font-weight:900;z-index:10;pointer-events:none;"></div>
      <canvas id="wheelCanvas" width="420" height="420"></canvas>
    </div>

    <!-- Controls -->
    <div class="control-zone">
      <div class="speed-bar-wrap">
        <div class="speed-lbl-row"><span>⚡ Tốc Độ Trục Kim</span><span id="speedText">Đang thiết lập...</span></div>
        <div class="speed-track"><div class="speed-bar-fill" id="speedFill"></div></div>
      </div>
      <div class="prompt-text" id="prompt">Kim đang chuyển động xoay — bấm nút bên dưới đúng thời khắc để hãm kim dừng vào ô mong muốn!</div>
      <div id="actionBtns">
        <button class="btn-stop" id="btnStop" onclick="Game.stop()">🛑 DỪNG KIM!</button>
      </div>
    </div>

    <!-- Overlay Result Stage -->
    <div class="overlay-result-stage" id="ovResult">
      <div class="ov-icon-anim" id="ovIcon">🎉</div>
      <div class="ov-title-text" id="ovTitle">-</div>
      <div class="ov-subtitle-text" id="ovSub">-</div>
      <div class="ov-reward-capsule" id="ovReward" style="display:none">💰 <span id="ovAmt">-</span></div>
      <div style="display:flex;gap:12px;justify-content:center;margin-top:10px;" id="ovActions"></div>
    </div>
  </div>

  <!-- ================= RIGHT PANEL ================= -->
  <div class="panel" id="right-panel">
    <div class="panel-head">
      <div class="panel-title">🎴 CHIẾN BINH & TUYỆT KỸ</div>
    </div>
    <div class="rp-body">
      
      <!-- Character Display Card -->
      <div class="char-card" id="charCard">
        <div class="char-info-row">
          <img class="char-avatar" id="charAvatar" src="https://api.dicebear.com/7.x/avataaars/svg?seed=fighter" alt="Avatar">
          <div class="char-meta">
            <div class="char-class-tag" id="charClassTag">LỚP NHÂN VẬT</div>
            <div class="char-name-text" id="charName">...</div>
          </div>
        </div>
        <div class="char-desc-block">
          <div class="char-passive-badge">Nội Tại Bị Động</div>
          <p id="charPassiveDesc" style="color:#cbd5e1;font-size:12.5px;margin-bottom:8px">...</p>
          <div id="charShieldRow" style="display:none;font-size:12px;color:#fca5a5;font-weight:800;margin-top:6px">
            🛡️ Khiên Hộ Mệnh còn lại: <span id="charShieldCount">0</span>
          </div>
        </div>
      </div>

      <!-- Skill Button -->
      <div class="skill-control-box">
        <button class="btn-skill-cast ready" id="btnSkill" onclick="Game.castSkill()">KÍCH HOẠT TUYỆT KỸ</button>
        <div id="skillDescText" style="font-size:11.5px;color:#475569;margin-top:8px;line-height:1.4">Mô tả tuyệt kỹ...</div>
      </div>

      <!-- Selectors -->
      <div>
        <div style="font-size:11px;color:#334155;letter-spacing:1px;font-weight:800;margin-bottom:6px">🔄 LỰA CHỌN CHIẾN BINH</div>
        <div class="char-selector-bar">
          <button class="char-select-btn" id="btnChar_kiem_thanh" onclick="Game.switchCharacter('kiem_thanh')">🥷 Kiếm Thánh</button>
          <button class="char-select-btn" id="btnChar_phap_su" onclick="Game.switchCharacter('phap_su')">🧙‍♂️ Pháp Sư</button>
          <button class="char-select-btn" id="btnChar_cuong_chien_si" onclick="Game.switchCharacter('cuong_chien_si')">🛡️ Cuồng Sĩ</button>
        </div>
      </div>

      <!-- Top Leaderboard -->
      <div style="border-top:1px solid rgba(251,191,36,0.12);padding-top:12px">
        <div style="font-size:10px;color:#334155;letter-spacing:1.5px;text-transform:uppercase;font-weight:900;margin-bottom:8px">🏆 BẢNG VÀNG LEO THÁP</div>
        <div id="lbBox" style="display:flex;flex-direction:column;gap:5px"></div>
      </div>

      <!-- Breakdown info -->
      <div style="border-top:1px solid rgba(251,191,36,0.12);padding-top:12px">
        <div style="font-size:10px;color:#334155;letter-spacing:1.5px;text-transform:uppercase;font-weight:900;margin-bottom:8px">💰 PHÚC LỘC TẦNG NÀY</div>
        <div id="rewardBreakdown" style="font-size:12px;color:#475569;line-height:2"></div>
      </div>

    </div>
  </div>

</div>

<script>
// ===== STAR BACKGROUND =====
for(let i=0;i<50;i++){
  const star = document.createElement('div');
  star.className = 'star';
  const sz = Math.random()*2 + 0.8;
  star.style.cssText = `width:${sz}px;height:${sz}px;top:${Math.random()*100}%;left:${Math.random()*100}%;--d:${2+Math.random()*4}s;--o:${0.2+Math.random()*0.6};animation-delay:${Math.random()*4}s`;
  document.body.appendChild(star);
}

// ===== PARTICLE BURSTS =====
const pc = document.getElementById('pc'), pctx = pc.getContext('2d');
pc.width = window.innerWidth; pc.height = window.innerHeight;
window.addEventListener('resize', () => { pc.width = window.innerWidth; pc.height = window.innerHeight });
let pts = [];
function spawnBurst(x, y, color, count=40) {
  for(let i=0;i<count;i++) {
    pts.push({
      x, y, vx: (Math.random()-0.5)*10, vy: (Math.random()-0.5)*10-4,
      life: 1, decay: 0.015 + Math.random()*0.015,
      r: 3 + Math.random()*5, color,
      chars: ['★','✦','◆','●','✸','✨'][Math.floor(Math.random()*6)]
    });
  }
}
function animateParticles() {
  pctx.clearRect(0,0,pc.width,pc.height);
  pts = pts.filter(p => p.life > 0);
  pts.forEach(p => {
    p.x += p.vx; p.y += p.vy; p.vy += 0.16; p.life -= p.decay;
    pctx.globalAlpha = p.life; pctx.fillStyle = p.color;
    pctx.font = `${p.r*3.5}px serif`; pctx.fillText(p.chars, p.x, p.y);
  });
  pctx.globalAlpha = 1;
  requestAnimationFrame(animateParticles);
}
animateParticles();

// ===== CHARACTER DATABASE =====
const CHARACTER_DB = {
  kiem_thanh: {
    name: 'Kiếm Thánh',
    tag: 'Tốc độ / Phản xạ',
    passive: 'Nội tại: Tốc độ kim quay được giảm sẵn 15% giúp bạn canh hãm kim dễ dàng hơn.',
    activeName: 'Tuyệt kỹ: Ngưng Đọng',
    activeDesc: 'Hãm kim chậm lại ngay 70% trong 1.5 giây để ra chiêu chuẩn xác nhất! (Hồi chiêu 10s)',
    avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=samurai'
  },
  phap_su: {
    name: 'Pháp Sư Tối Thượng',
    tag: 'May rủi / Lợi nhuận',
    passive: 'Nội tại: Ô Thần Bài (Jackpot x5) trên vòng quay vận mệnh được kéo giãn thêm 5% diện tích.',
    activeName: 'Tuyệt kỹ: Chuyển Mệnh',
    activeDesc: 'Nếu dừng vào ô Tử Thần, kỹ năng tự động kích hoạt chuyển hóa thành Thử Lại! (Hồi chiêu 15s)',
    avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=wizard'
  },
  cuong_chien_si: {
    name: 'Cuồng Chiến Sĩ',
    tag: 'Chống chịu / Bất tử',
    passive: 'Nội tại: Khởi đầu mỗi trận leo tháp nhận sẵn 1 Khiên Hộ Mệnh chống kẹt khi trúng ô Tử Thần.',
    activeName: 'Tuyệt kỹ: Hét Thấu Trời',
    activeDesc: 'Nhân đôi toàn bộ thạch thưởng GTLM của tầng này nếu dừng trúng ô Chiến Thắng trở lên! (Hồi chiêu 20s)',
    avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=barbarian'
  }
};

const ALL_SEGS = [
  { key:'jackpot', label:'THẦN BÀI', icon:'👑', color:'#f59e0b', dark:'#92400e', mult:5, desc:'Jackpot: ×5 thạch thưởng tầng!' },
  { key:'win3',    label:'ĐẠI THẮNG', icon:'🔥', color:'#22c55e', dark:'#052e16', mult:3, desc:'Đại thắng: ×3 thạch thưởng tầng' },
  { key:'win',     label:'CHIẾN THẮNG', icon:'⚔️', color:'#3b82f6', dark:'#1e3a5f', mult:1, desc:'Chiến thắng: ×1 thạch thưởng tầng' },
  { key:'half',    label:'NỬA THẮNG', icon:'💫', color:'#8b5cf6', dark:'#2e1065', mult:0.5, desc:'Nửa thắng: ×0.5 thạch thưởng tầng' },
  { key:'retry',   label:'THỬ LẠI', icon:'🛡️', color:'#64748b', dark:'#1e293b', mult:0, desc:'Giao lưu hòa: Quay lại miễn phí' },
  { key:'death',   label:'TỬ THẦN', icon:'💀', color:'#ef4444', dark:'#3b0000', mult:-1, desc:'Bay màu: Bị kẹt lại tầng hiện tại' }
];

// Phân bổ tỷ lệ theo tầng (vùng góc)
function getSegs(floor, char) {
  const boss = floor % 10 === 0;
  
  // Buff nội tại của Pháp Sư Tối Thượng: Tăng 5% ô Thần Bài (Jackpot)
  const jackpotBonus = (char === 'phap_su') ? 0.05 : 0.0;

  let dist = [];
  if (boss) {
    // Tầng Boss: Tỷ lệ tử thần rộng hơn
    dist = [
      { key: 'jackpot', p: 0.05 + jackpotBonus },
      { key: 'win3',    p: 0.15 },
      { key: 'win',     p: 0.20 },
      { key: 'half',    p: 0.12 },
      { key: 'retry',   p: 0.10 },
      { key: 'death',   p: 0.38 - jackpotBonus }
    ];
  } else if (floor <= 10) {
    dist = [
      { key: 'jackpot', p: 0.10 + jackpotBonus },
      { key: 'win3',    p: 0.20 },
      { key: 'win',     p: 0.30 },
      { key: 'half',    p: 0.15 },
      { key: 'retry',   p: 0.15 },
      { key: 'death',   p: 0.10 - jackpotBonus }
    ];
  } else if (floor <= 25) {
    dist = [
      { key: 'jackpot', p: 0.08 + jackpotBonus },
      { key: 'win3',    p: 0.15 },
      { key: 'win',     p: 0.25 },
      { key: 'half',    p: 0.18 },
      { key: 'retry',   p: 0.14 },
      { key: 'death',   p: 0.20 - jackpotBonus }
    ];
  } else if (floor <= 50) {
    dist = [
      { key: 'jackpot', p: 0.06 + jackpotBonus },
      { key: 'win3',    p: 0.12 },
      { key: 'win',     p: 0.22 },
      { key: 'half',    p: 0.18 },
      { key: 'retry',   p: 0.14 },
      { key: 'death',   p: 0.28 - jackpotBonus }
    ];
  } else if (floor <= 75) {
    dist = [
      { key: 'jackpot', p: 0.05 + jackpotBonus },
      { key: 'win3',    p: 0.10 },
      { key: 'win',     p: 0.18 },
      { key: 'half',    p: 0.17 },
      { key: 'retry',   p: 0.10 },
      { key: 'death',   p: 0.40 - jackpotBonus }
    ];
  } else {
    // Tầng 76-100
    dist = [
      { key: 'jackpot', p: 0.04 + jackpotBonus },
      { key: 'win3',    p: 0.08 },
      { key: 'win',     p: 0.14 },
      { key: 'half',    p: 0.14 },
      { key: 'retry',   p: 0.10 },
      { key: 'death',   p: 0.50 - jackpotBonus }
    ];
  }

  // Map ngược lại segs hoàn chỉnh
  return dist.map(d => {
    const s = ALL_SEGS.find(item => item.key === d.key);
    return { ...s, portion: d.p };
  });
}

// ===== WHEEL CANVAS =====
const wc = document.getElementById('wheelCanvas'), wctx = wc.getContext('2d');
const W = wc.width, H = wc.height, CX = W/2, CY = H/2, R = W/2 - 12;

const Game = (() => {
  let floor = 1, reward = 0, segs = [], segAngles = [];
  let angle = 0, speed = 0, baseSpeed = 0, spinning = false, canStop = false, cooldownTimer = null;
  let activeBuff = null, selectedChar = 'kiem_thanh';
  let isHaming = false, rafId = null;

  function buildAngles(s) {
    segAngles = [];
    let cur = 0;
    s.forEach(seg => {
      const arc = seg.portion * Math.PI*2;
      segAngles.push({ seg, start: cur, end: cur+arc });
      cur += arc;
    });
  }

  function drawWheel() {
    wctx.clearRect(0,0,W,H);

    // Neon outer glow ring
    const grad = wctx.createRadialGradient(CX,CY,R-24,CX,CY,R+6);
    grad.addColorStop(0, 'transparent');
    grad.addColorStop(0.9, 'rgba(251,191,36,0.06)');
    grad.addColorStop(1, 'rgba(251,191,36,0.3)');
    wctx.beginPath(); wctx.arc(CX,CY,R+6,0,Math.PI*2); wctx.fillStyle = grad; wctx.fill();

    // Segments
    segAngles.forEach(({seg, start, end}) => {
      const s = start + angle, e = end + angle;
      wctx.beginPath();
      wctx.moveTo(CX,CY);
      wctx.arc(CX,CY,R,s,e);
      wctx.closePath();

      // Radial slice gradient
      const mx = CX + Math.cos((s+e)/2)*R*0.65, my = CY + Math.sin((s+e)/2)*R*0.65;
      const sg = wctx.createRadialGradient(mx,my,0,CX,CY,R);
      sg.addColorStop(0, seg.color);
      sg.addColorStop(1, seg.dark || seg.color);
      wctx.fillStyle = sg;
      wctx.fill();

      // Thin outline
      wctx.strokeStyle = 'rgba(3,6,17,0.45)';
      wctx.lineWidth = 1.5;
      wctx.stroke();

      // Render icons
      const arcMid = (s+e)/2;
      const tx = CX + Math.cos(arcMid)*R*0.65, ty = CY + Math.sin(arcMid)*R*0.65;
      wctx.save();
      wctx.translate(tx,ty); wctx.rotate(arcMid + Math.PI/2);
      wctx.textAlign = 'center'; wctx.textBaseline = 'middle';
      
      const arcLen = end - start;
      if (arcLen > 0.28) {
        wctx.font = 'bold 20px serif'; wctx.fillStyle = '#fff';
        wctx.shadowColor = 'rgba(0,0,0,0.6)'; wctx.shadowBlur = 4;
        wctx.fillText(seg.icon, 0, 0);
        if (arcLen > 0.45) {
          wctx.font = '900 10.5px Outfit';
          wctx.fillText(seg.mult > 0 ? `×${seg.mult}` : seg.mult === 0 ? '🔄' : '💀', 0, 16);
        }
      } else {
        wctx.font = '16px serif'; wctx.fillText(seg.icon, 0, 0);
      }
      wctx.restore();
    });

    // Center pivot cap
    const cc = wctx.createRadialGradient(CX,CY,0,CX,CY,26);
    cc.addColorStop(0, '#10172a'); cc.addColorStop(1, '#020617');
    wctx.beginPath(); wctx.arc(CX,CY,26,0,Math.PI*2); wctx.fillStyle = cc; wctx.fill();
    wctx.strokeStyle = 'rgba(251,191,36,0.6)'; wctx.lineWidth = 2.5; wctx.stroke();
    wctx.fillStyle = '#fbbf24'; wctx.font = 'bold 15px serif'; wctx.textAlign = 'center'; wctx.textBaseline = 'middle';
    wctx.fillText('🗼', CX, CY);

    // Indicator Needle pointing down from the very top (12 o'clock, 270 degrees or -PI/2)
    drawNeedle();
  }

  function drawNeedle() {
    const nx = CX, ny = 12;
    wctx.save();
    wctx.translate(nx,ny);
    wctx.beginPath();
    wctx.moveTo(-11,0); wctx.lineTo(11,0); wctx.lineTo(0,46);
    wctx.closePath();
    const ng = wctx.createLinearGradient(0,0,0,46);
    ng.addColorStop(0, '#ef4444'); ng.addColorStop(1, '#7f1d1d');
    wctx.fillStyle = ng; wctx.fill();
    wctx.strokeStyle = 'rgba(0,0,0,0.5)'; wctx.lineWidth = 1.5; wctx.stroke();

    // Circle pivot
    wctx.beginPath(); wctx.arc(0,0,10,0,Math.PI*2);
    wctx.fillStyle = '#0f172a'; wctx.fill();
    wctx.strokeStyle = '#ef4444'; wctx.lineWidth = 2.5; wctx.stroke();
    wctx.restore();
  }

  function getSegmentAtNeedle() {
    const needle = -Math.PI/2;
    // Map angle to range [0, 2PI)
    let relative = ((needle - angle) % (Math.PI*2) + Math.PI*2) % (Math.PI*2);
    for(const sa of segAngles) {
      if (relative >= sa.start && relative < sa.end) return sa.seg;
    }
    return segAngles[0].seg;
  }

  function runLoop() {
    if (!spinning) return;
    angle += speed;
    if (angle > Math.PI*2) angle -= Math.PI*2;

    drawWheel();

    // Speed bar calculation
    const pct = Math.min(100, (speed / baseSpeed) * 100);
    const fill = document.getElementById('speedFill');
    if (fill) fill.style.width = pct + '%';
    
    const textEl = document.getElementById('speedText');
    if (textEl) {
      if (speed > baseSpeed * 0.75) {
        textEl.innerHTML = '<span style="color:#ef4444">🔥 CỰC NHANH</span>';
      } else if (speed > baseSpeed * 0.4) {
        textEl.innerHTML = '<span style="color:#fbbf24">⚡ KHÁ NHANH</span>';
      } else if (speed > baseSpeed * 0.15) {
        textEl.innerHTML = '<span style="color:#60a5fa">🐢 ĐANG HÃM TỐC...</span>';
      } else {
        textEl.innerHTML = '<span style="color:#22c55e">🐢 SẮP DỪNG!</span>';
      }
    }

    if (speed > 0.001) {
      rafId = requestAnimationFrame(runLoop);
    } else {
      spinning = false;
      speed = 0;
      drawWheel();
      const finalSeg = getSegmentAtNeedle();
      postResult(finalSeg);
    }
  }

  async function postResult(seg) {
    canStop = false;
    document.getElementById('btnStop').disabled = true;

    // Canvas centre coords for burst effects
    const rect = wc.getBoundingClientRect();
    const cx = rect.left + rect.width/2, cy = rect.top + rect.height/2;

    // Flash segment text
    const flash = document.getElementById('multFlash');
    flash.textContent = seg.mult > 0 ? `×${seg.mult}` : seg.key === 'retry' ? '🔄' : '💀';
    flash.style.color = seg.color;
    flash.style.textShadow = `0 0 35px ${seg.color}`;
    flash.style.display = 'block';
    flash.classList.add('show');
    setTimeout(() => { flash.style.display = 'none'; flash.classList.remove('show'); }, 1800);

    // Dynamic color particle bursts
    spawnBurst(cx, cy, seg.color, seg.key === 'death' ? 65 : 40);
    if (seg.key === 'jackpot') {
      setTimeout(() => spawnBurst(cx-80, cy-80, '#fbbf24', 30), 200);
      setTimeout(() => spawnBurst(cx+80, cy-80, '#a855f7', 30), 400);
      setTimeout(() => spawnBurst(cx, cy+80, '#22c55e', 30), 600);
    }

    await new Promise(r => setTimeout(r, 650));

    // Send payload to backend
    const fd = new FormData();
    fd.append('card_result', seg.key);
    try {
      const res = await fetch('api_tower_gods.php?action=card_result', { method:'POST', body:fd });
      const data = await res.json();

      if (!data.success) {
        Swal.fire({ title:'Lỗi', text:data.message, icon:'error', background:'#080c1e', color:'#e2e8f0' });
        startSpin();
        return;
      }

      // Sync data
      if (data.progress) updateUIStats(data.progress);
      if (data.cooldowns) syncCooldowns(data.cooldowns);
      
      const realResult = data.card_result; // Kết quả cuối cùng sau khi áp dụng Tuyệt kỹ
      const finalReward = data.reward_gtlm || 0;
      const outcome = ALL_SEGS.find(item => item.key === realResult);

      const ov = document.getElementById('ovResult');
      document.getElementById('ovIcon').textContent = outcome.icon;
      document.getElementById('ovTitle').textContent = outcome.label + '!';
      document.getElementById('ovTitle').style.color = outcome.color;
      document.getElementById('ovSub').innerHTML = data.message;

      const rewEl = document.getElementById('ovReward');
      if (finalReward > 0) {
        rewEl.style.display = 'inline-flex';
        document.getElementById('ovAmt').textContent = new Intl.NumberFormat().format(finalReward) + ' GTLM';
      } else {
        rewEl.style.display = 'none';
      }

      const actRow = document.getElementById('ovActions');
      if (data.advanced) {
        actRow.innerHTML = `<button class="post-action-btn btn-advance-floor" onclick="Game.nextFloor()">🚀 Lên Tầng ${floor+1}!</button>`;
      } else {
        actRow.innerHTML = `<button class="post-action-btn btn-retry-floor" onclick="Game.closeOverlay()">🔄 Quay Tiếp</button>`;
      }
      
      ov.classList.add('active');
    } catch(e) {
      console.error(e);
      startSpin();
    }
  }

  function startSpin() {
    isHaming = false;
    document.getElementById('ovResult').classList.remove('active');
    
    spinning = true; canStop = true;
    document.getElementById('btnStop').disabled = false;
    document.getElementById('prompt').innerHTML = 'Kim đang chuyển động xoay — bấm nút bên dưới đúng thời khắc để hãm kim dừng!';
    document.getElementById('actionBtns').innerHTML = '<button class="btn-stop" id="btnStop" onclick="Game.stop()">🛑 DỪNG KIM!</button>';

    // Base speed increases with floor height
    baseSpeed = 0.045 + Math.min(floor, 100) * 0.0022;

    // Kiếm Thánh passive: Reduce base speed by 15%
    if (selectedChar === 'kiem_thanh') {
      baseSpeed = baseSpeed * 0.85;
    }

    speed = baseSpeed;
    cancelAnimationFrame(rafId);
    rafId = requestAnimationFrame(runLoop);
  }

  function stop() {
    if (!canStop || !spinning || isHaming) return;
    isHaming = true;
    
    // Slow down gradually over time (negative acceleration)
    const decel = setInterval(() => {
      speed *= 0.945;
      if (speed < 0.001) {
        speed = 0;
        clearInterval(decel);
      }
    }, 28);
  }

  function castSkill() {
    fetch('api_tower_gods.php?action=use_skill', { method:'POST' })
      .then(res => res.json())
      .then(data => {
        if (!data.success) {
          Swal.fire({ title:'Tuyệt Kỹ Chưa Sẵn Sàng', text:data.message, icon:'warning', background:'#080c1e', color:'#e2e8f0', confirmButtonColor:'#fbbf24' });
          return;
        }

        activeBuff = data.active_buff;
        
        // Active visual state
        const btn = document.getElementById('btnSkill');
        btn.className = 'btn-skill-cast active-buff';
        btn.textContent = 'TUYỆT KỸ ĐANG KÍCH HOẠT!';
        
        // Kiếm Thánh Active Skill: Hãm phanh 70% tốc độ trong 1.5 giây
        if (selectedChar === 'kiem_thanh') {
          const originalSpeed = speed;
          speed = speed * 0.3; // Chậm đi 70%
          setTimeout(() => {
            if (spinning && !isHaming) {
              speed = originalSpeed; // Hết 1.5 giây phục hồi tốc độ cũ
              btn.className = 'btn-skill-cast cooldown';
              btn.disabled = true;
            }
          }, 1500);
        }

        // Đếm ngược CD ngay lập tức
        if (data.cooldown_left) {
          startCdCountdown(selectedChar, data.cooldown_left);
        }

        if (typeof SoundFXHub !== 'undefined') SoundFXHub.playBossRoar?.();
      });
  }

  function startCdCountdown(char, duration) {
    const btn = document.getElementById('btnSkill');
    if (char !== selectedChar) return;

    btn.className = 'btn-skill-cast cooldown';
    btn.disabled = true;

    let left = duration;
    clearInterval(cooldownTimer);
    cooldownTimer = setInterval(() => {
      left--;
      if (left <= 0) {
        clearInterval(cooldownTimer);
        btn.className = 'btn-skill-cast ready';
        btn.disabled = false;
        btn.textContent = 'KÍCH HOẠT TUYỆT KỸ';
      } else {
        btn.textContent = `HỒI CHIÊU CHỜ (${left}s)`;
      }
    }, 1000);
  }

  function switchCharacter(char) {
    if (spinning && !isHaming) {
      Swal.fire({ title:'Đang giao lưu!', text:'Không thể thay đổi chiến binh khi kim đang xoay!', icon:'warning', background:'#080c1e', color:'#e2e8f0' });
      return;
    }

    const fd = new FormData();
    fd.append('character', char);
    fetch('api_tower_gods.php?action=select_character', { method:'POST', body:fd })
      .then(res => res.json())
      .then(data => {
        if (!data.success) return;
        
        selectedChar = char;
        activeBuff = null;
        clearInterval(cooldownTimer);
        
        // Sync UI character selection
        updateUIStats(data.progress);
        loadFloor(floor);
      });
  }

  function closeOverlay() {
    document.getElementById('ovResult').classList.remove('active');
    startSpin();
  }

  function nextFloor() {
    document.getElementById('ovResult').classList.remove('active');
    floor++;
    loadFloor(floor);
  }

  function updateUIStats(p) {
    floor = parseInt(p.current_floor) || 1;
    document.getElementById('sBest').textContent = p.highest_floor || '-';
    document.getElementById('sWins').textContent = p.total_wins || '0';
    document.getElementById('sGtlm').textContent = new Intl.NumberFormat().format(p.total_gtlm_won || 0) + ' GTLM';
    
    // Character setup
    selectedChar = p.selected_character || 'kiem_thanh';
    const c = CHARACTER_DB[selectedChar];
    
    document.getElementById('charAvatar').src = c.avatar;
    document.getElementById('charClassTag').textContent = c.tag;
    document.getElementById('charName').textContent = c.name;
    document.getElementById('charPassiveDesc').textContent = c.passive;
    document.getElementById('skillDescText').textContent = c.activeDesc;

    // Show shield row only for Berserker
    const shieldRow = document.getElementById('charShieldRow');
    if (selectedChar === 'cuong_chien_si') {
      shieldRow.style.display = 'block';
      document.getElementById('charShieldCount').textContent = p.shield_count || 0;
    } else {
      shieldRow.style.display = 'none';
    }

    // Toggle active selector class
    document.querySelectorAll('.char-select-btn').forEach(btn => btn.classList.remove('active'));
    const btnActive = document.getElementById(`btnChar_${selectedChar}`);
    if (btnActive) btnActive.classList.add('active');
  }

  function syncCooldowns(cd) {
    const activeCooldown = cd[selectedChar] || 0;
    const btn = document.getElementById('btnSkill');
    if (activeCooldown > 0) {
      startCdCountdown(selectedChar, activeCooldown);
    } else {
      btn.className = 'btn-skill-cast ready';
      btn.disabled = false;
      btn.textContent = 'KÍCH HOẠT TUYỆT KỸ';
    }
  }

  async function loadFloor(f) {
    try {
      const res = await fetch('api_tower_gods.php?action=info');
      const data = await res.json();
      if (!data.success) return;

      const p = data.progress;
      floor = parseInt(p.current_floor) || f;
      reward = data.floor_reward || floor * 10000;
      
      // Build segment distributions
      segs = getSegs(floor, selectedChar);
      buildAngles(segs);

      // Render UI
      document.getElementById('floorNum').textContent = floor;
      document.getElementById('floorBig').textContent = floor;
      document.getElementById('rewardAmt').textContent = '+' + new Intl.NumberFormat().format(reward) + ' GTLM';
      
      const isBoss = floor % 10 === 0;
      document.getElementById('floorPill').innerHTML = isBoss ? '👑 BOSS TẦNG <span id="floorNum">'+floor+'</span>' : '🔥 TẦNG <span id="floorNum">'+floor+'</span>';

      updateUIStats(p);
      syncCooldowns(data.cooldowns);
      renderFloorTrack(floor);
      renderLegend(segs);

      // Rewards info column
      const multTxt = isBoss ? 'Tầng Boss: Hệ số phúc lộc cực lớn!' : floor % 5 === 0 ? 'Tầng mốc: Phần thưởng được nhân đôi!' : 'Thường';
      document.getElementById('rewardBreakdown').innerHTML = 
        `Mức GTLM gốc: <b style="color:#fbbf24">+${new Intl.NumberFormat().format(reward)} GTLM</b><br>`+
        `<span style="color:#94a3b8">Thể thức: ${multTxt}</span>` +
        (data.floor_trophy ? `<br>🏆 Báu vật: <b style="color:#c084fc">${data.floor_trophy.name}</b>` : '');

      // Load leaderboard
      const lb = document.getElementById('lbBox');
      const medals = ['🥇','🥈','🥉','4️⃣','5️⃣'];
      lb.innerHTML = (data.leaderboard || []).map((u, i) => `
        <div class="lb-item">
          <span class="lb-rank">${medals[i] || '🔹'}</span>
          <span class="lb-name">${u.username}</span>
          <span class="lb-floor">Tầng ${u.highest_floor}</span>
        </div>`).join('') || '<div style="color:#334155;font-size:12px;text-align:center;">Chưa có ai leo tháp</div>';

      // Start spinning!
      startSpin();
    } catch(e) {
      console.error('Lỗi tải tầng:', e);
    }
  }

  function renderFloorTrack(cur) {
    const el = document.getElementById('floorTrack');
    let html = '';
    for(let f=100;f>=1;f--){
      const isBoss = f % 10 === 0, isMile = f % 5 === 0 && !isBoss;
      const cls = f < cur ? 'done' : f === cur ? 'current' : 'upcoming';
      const ec = isBoss ? ' boss-f' : '';
      const ico = f < cur ? '✅' : isBoss ? '👑' : isMile ? '⭐' : '◻';
      html += `<div class="fp-row ${cls}${ec}">
        <span class="fp-icon">${ico}</span>
        <span class="fp-name">${isBoss ? `<b>Tầng ${f} — BOSS</b>` : `Tầng ${f}`}</span>
        ${isBoss ? '<span class="fp-tag">BOSS</span>' : ''}
      </div>`;
    }
    el.innerHTML = html;
    
    // Auto scroll track to current floor
    setTimeout(() => {
      const c = el.querySelector('.current');
      if (c) c.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }, 100);
  }

  function renderLegend(s) {
    document.getElementById('segLegend').innerHTML = s.map(seg => `
      <div class="seg-legend">
        <div class="seg-dot" style="background:${seg.color}"></div>
        <div class="seg-info">
          <div class="name" style="color:${seg.color}">${seg.icon} ${seg.label} <span style="font-size:10px;opacity:0.65">(${Math.round(seg.portion*100)}%)</span></div>
          <div class="desc" style="color:#64748b">${seg.desc}</div>
        </div>
      </div>`).join('');
  }

  // Bind Space and Enter keys for action stopping
  document.addEventListener('keydown', e => {
    if (e.code === 'Space' || e.code === 'Enter') {
      e.preventDefault();
      const b = document.getElementById('btnStop');
      if (b && !b.disabled) b.click();
    }
  });

  return { stop, nextFloor, castSkill, switchCharacter, closeOverlay, startSpin };
})();

document.addEventListener('DOMContentLoaded', () => Game.loadFloor(1));
</script>
</body>
</html>

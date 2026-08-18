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

/* Character Selector Bar - Premium Upgrade */
.char-selector-bar{display:grid;grid-template-columns:repeat(4, 1fr);gap:8px;margin-top:10px;}
.char-select-btn{
  background:rgba(15,23,42,0.7);border:1px solid rgba(255,255,255,0.05);border-radius:12px;
  padding:10px 4px;font-size:11.5px;font-weight:800;color:#94a3b8;font-family:'Outfit',sans-serif;
  cursor:url('img/tay.png'),pointer!important;transition:all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  text-align:center;position:relative;overflow:hidden;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);
  width:100%;
}
.char-select-btn::before{
  content:'';position:absolute;top:0;left:-100%;width:50%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,0.08),transparent);
  transform:skewX(-20deg);transition:all 0.5s;
}
.char-select-btn:hover:not(.active)::before{left:150%}
.char-select-btn.active{
  background:linear-gradient(135deg,rgba(251,191,36,0.15),rgba(217,119,6,0.15));
  border-color:rgba(251,191,36,0.5);color:#fbbf24;
  box-shadow:0 0 20px rgba(251,191,36,0.15),inset 0 0 10px rgba(251,191,36,0.05);
  transform:translateY(-3px);
}
.char-select-btn.active::after{
  content:'✓';position:absolute;top:6px;right:8px;font-size:12px;color:#fbbf24;font-weight:bold;
  text-shadow:0 0 8px rgba(251,191,36,0.8);
}
.char-select-btn:hover:not(.active){
  background:rgba(30,41,59,0.9);color:#f1f5f9;border-color:rgba(255,255,255,0.15);transform:translateY(-2px);
}
.char-select-icon{
  display:block;font-size:26px;margin-bottom:8px;filter:drop-shadow(0 2px 4px rgba(0,0,0,0.5));
  transition:transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.char-select-btn.active .char-select-icon{transform:scale(1.15)}
.char-select-name{display:block;letter-spacing:0.5px}

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
      <div class="stat-row"><span class="stat-label">Số Dư Tài Khoản</span><span class="stat-value" style="color:#60a5fa;font-size:12.5px;font-weight:900" id="sBalance">-</span></div>
      <div class="stat-row"><span class="stat-label">Kỷ Lục Cao Nhất</span><span class="stat-value" style="color:#fbbf24">Tầng <span id="sBest">-</span></span></div>
      <div class="stat-row"><span class="stat-label">Tổng Trận Thắng</span><span class="stat-value" style="color:#34d399"><span id="sWins">-</span> trận</span></div>
      <div class="stat-row"><span class="stat-label">Tổng Phúc Lộc GTLM</span><span class="stat-value" style="color:#c084fc;font-size:12.5px;" id="sGtlm">-</span></div>
    </div>
  </div>

  <!-- ================= CENTER ARENA ================= -->
  <div id="center">
    <!-- Header top -->
    <div class="arena-header" style="justify-content:center;">
      <div class="floor-pill" id="floorPill">🔥 TẦNG <span id="floorNum">-</span></div>
    </div>

    <!-- Combat Arena -->
    <div class="combat-container" style="display:flex; flex-direction:column; gap:20px; padding:20px;">
        <div style="text-align:center;"><div class="floor-pill" id="weatherPill" style="background:rgba(124, 58, 237, 0.2); color:#c4b5fd; border-color:rgba(139, 92, 246, 0.4); display:none;">☀️ Bình Thường</div></div>
        
        <div class="combat-stage" style="display:flex; justify-content:space-between; align-items:stretch; background:rgba(0,0,0,0.4); border-radius:16px; padding:20px; border:1px solid rgba(255,255,255,0.1);">
            
            <!-- Phe Ta (3 slots) -->
            <div id="playerTeamArea" style="display:flex; flex-direction:column; gap:10px; width:42%; justify-content:center;">
                <!-- Template for a player entity, injected via JS -->
            </div>
            
            <div style="display:flex; align-items:center; font-size:30px; font-weight:900; color:#ef4444; font-style:italic;">VS</div>

            <!-- Phe Địch (3 slots) -->
            <div id="monsterTeamArea" style="display:flex; flex-direction:column; gap:10px; width:42%; justify-content:center;">
                <!-- Template for a monster entity, injected via JS -->
            </div>
            
        </div>

        <div class="combat-log" id="combatLog" style="background:#0f172a; border-radius:12px; padding:15px; height:220px; overflow-y:auto; border:1px solid #1e293b; font-family:'Courier New', monospace; font-size:13px; color:#94a3b8; display:flex; flex-direction:column; gap:8px; scroll-behavior: smooth;">
            <div style="text-align:center; color:#64748b; font-style:italic;">Sẵn sàng chiến đấu...</div>
        </div>
    </div>

    <!-- Controls -->
    <div class="control-zone" style="margin-top:0;">
      <div id="actionBtns">
        <button class="btn-stop" style="background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%); border-bottom: 4px solid #7f1d1d;" id="btnStart" onclick="Game.startBattle()">⚔️ TIẾN VÀO CHIẾN ĐẬU</button>
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
      <div style="margin-top:4px">
        <div style="font-size:12px;color:#94a3b8;letter-spacing:2px;text-transform:uppercase;font-weight:900;margin-bottom:12px;display:flex;align-items:center;gap:10px;">
          <span style="flex:1;height:1px;background:linear-gradient(90deg,transparent,rgba(255,255,255,0.08))"></span>
          🔄 LỰA CHỌN CHIẾN BINH
          <span style="flex:1;height:1px;background:linear-gradient(270deg,transparent,rgba(255,255,255,0.08))"></span>
        </div>
        <div class="char-selector-bar" id="charSelectorBar">
          <!-- JS sẽ generate tự động 20 buttons ở đây -->
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
    if (pts.length === 0) return;
    pctx.clearRect(0,0,pc.width,pc.height);
    pts = pts.filter(p => p.life > 0);
    if (pts.length === 0) return; 

    pts.forEach(p => {
      p.x += p.vx; p.y += p.vy; p.vy += 0.16; p.life -= p.decay;
      pctx.globalAlpha = p.life; pctx.fillStyle = p.color;
      pctx.font = `${p.r*3.5}px serif`; pctx.fillText(p.chars, p.x, p.y);
    });
    pctx.globalAlpha = 1;
}

  function particleLoop() {
    animateParticles();
    requestAnimationFrame(particleLoop);
  }
  particleLoop();

// ===== CHARACTER DATABASE =====
const CHARACTER_DB = {
  kiem_thanh: { tag: 'Kiểm Soát', name: 'Kiếm Thánh', icon: '🥷', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=kiemthanh', passive: 'Tăng mạnh ATK (+50%), tỷ lệ bạo kích 30%.', activeDesc: 'Nhất Kiếm Đoạt Mệnh: Đòn chém đầu tiên x3 Sát thương. (CD: 3 tầng)' },
  cung_thu: { tag: 'Kiểm Soát', name: 'Cung Thủ', icon: '🏹', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=cungthu', passive: 'Tỷ lệ bạo kích 50%, nhưng HP rất thấp (-20%).', activeDesc: 'Phong Thần Tiễn: Tỷ lệ bạo kích lượt đầu là 100%. (CD: 3 tầng)' },
  ninja: { tag: 'Kiểm Soát', name: 'Ninja', icon: '🗡️', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=ninja', passive: '30% né tránh sát thương. ATK khá.', activeDesc: 'Phân Thân: Miễn nhiễm sát thương trong 2 hiệp đầu. (CD: 4 tầng)' },
  tien_tri: { tag: 'Kiểm Soát', name: 'Tiên Tri', icon: '🔮', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=tientri', passive: 'Giảm 20% sức mạnh tấn công của quái vật.', activeDesc: 'Thấu Thị: Trực tiếp kết liễu quái vật nếu HP quái < 50%. (CD: 4 tầng)' },
  ma_kiem_si: { tag: 'Kiểm Soát', name: 'Ma Kiếm Sĩ', icon: '⚔️', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=makiemsi', passive: 'Hút máu cơ bản 10%.', activeDesc: 'Song Long Kích: Chém liên tiếp 2 lần trong hiệp đấu này. (CD: 3 tầng)' },
  
  phap_su: { tag: 'Sinh Tồn', name: 'Pháp Sư', icon: '🧙‍♂️', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=phapsu', passive: 'Sát thương phép lực lớn (ATK x2), máu giấy.', activeDesc: 'Nghịch Chuyển: Tự động hồi phục 100% HP khi máu dưới 20%. (CD: 5 tầng)' },
  cuong_chien_si: { tag: 'Sinh Tồn', name: 'Cuồng Sĩ', icon: '🛡️', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=cuongsj', passive: 'Trâu bò (HP x2).', activeDesc: 'Thịnh Nộ: Hy sinh 30% HP để tăng x2 ATK toàn trận. (CD: 4 tầng)' },
  hac_am: { tag: 'Sinh Tồn', name: 'Hắc Ám', icon: '🧛‍♂️', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=hacam', passive: 'Hút máu cực mạnh (50% ST gây ra).', activeDesc: 'Bóng Tối: Vô hiệu hóa đòn đánh của quái trong 3 hiệp. (CD: 6 tầng)' },
  muc_su: { tag: 'Sinh Tồn', name: 'Mục Sư', icon: '⛪', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=mucsu', passive: 'HP dồi dào, hút máu 20%.', activeDesc: 'Thánh Ca: Hồi phục toàn bộ máu và nhân đôi HP tối đa. (CD: 5 tầng)' },
  trieu_hoi: { tag: 'Sinh Tồn', name: 'Triệu Hồi Sư', icon: '🐉', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=trieuhoi', passive: 'Chỉ số đồng đều, mạnh về HP.', activeDesc: 'Triệu Hồi: Trực tiếp rút đi 50% máu tối đa của quái vật ngay đầu trận. (CD: 5 tầng)' },
  
  dao_tac: { tag: 'Kiếm Tiền', name: 'Đạo Tặc', icon: '🦹', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=daotac', passive: '+15% tiền thưởng cơ bản.', activeDesc: 'Tráo Phụng: x2 tiền thưởng của tầng này nếu chiến thắng. (CD: 4 tầng)' },
  than_tai: { tag: 'Kiếm Tiền', name: 'Thần Tài', icon: '🤑', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=thantai', passive: 'Nhận x4 GTLM ở các tầng chia hết cho 5.', activeDesc: 'Hào Quang: Nếu thắng tầng này, nhận tiền thưởng tương đương tầng Boss. (CD: 4 tầng)' },
  thuong_nhan: { tag: 'Kiếm Tiền', name: 'Thương Nhân', icon: '💰', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=thuongnhan', passive: 'Mỗi tầng tự sinh ra 5% lãi dựa trên tổng thưởng.', activeDesc: 'Hối Lộ: Bỏ qua việc đánh quái (thắng tự động), nhưng bị trừ 20% tổng tiền tích lũy. (CD: 4 tầng)' },
  tho_san: { tag: 'Kiếm Tiền', name: 'Thợ Săn', icon: '🤠', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=thosan', passive: 'Tiền thưởng từ đánh Boss (Tầng 10, 20...) luôn x2.', activeDesc: 'Đóng Dấu: X10 thưởng nếu thắng, nhưng ATK của quái cũng x2. (CD: 4 tầng)' },
  nhac_si: { tag: 'Kiếm Tiền', name: 'Nhạc Sĩ', icon: '🪕', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=nhacsi', passive: 'HP dày.', activeDesc: 'Ru Ngủ: Quái vật bỏ qua 2 hiệp đánh đầu tiên. (CD: 5 tầng)' },
  
  gia_kim: { tag: 'Đột Biến', name: 'Giả Kim', icon: '🧪', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=giakim', passive: 'Chuyển hóa 10% sát thương gây ra thành GTLM thưởng.', activeDesc: 'Chế Thuốc Nổ: Đặt bom nổ chết quái ở hiệp 3 (nếu sống sót đến hiệp 3). (CD: 3 tầng)' },
  cuong_tin: { tag: 'Đột Biến', name: 'Cuồng Tín', icon: '🩸', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=cuongtin', passive: 'HP cực thấp, ATK x2.5.', activeDesc: 'Hy Sinh: Trực tiếp tự sát để tiêu diệt quái, bảo toàn tiền thưởng và tiến lên tầng mới. (CD: 4 tầng)' },
  xuyen_khong: { tag: 'Đột Biến', name: 'Xuyên Không', icon: '⏳', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=xuyenkhong', passive: 'Trâu, sát thương vừa. Nếu bị quái giết, không rớt về tầng 1 mà chỉ lùi 1 tầng.', activeDesc: 'Bẻ Cong: Bỏ qua hoàn toàn tầng này nhảy lên tầng kế (Không nhận thưởng). (CD: 4 tầng)' },
  do_te: { tag: 'Đột Biến', name: 'Đồ Tể', icon: '🪓', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=dote', passive: 'Hút máu 40%. Trâu bò.', activeDesc: 'Chặt Chém: One-hit tiêu diệt quái thường (Không tác dụng với Boss). (CD: 3 tầng)' },
  vua_tro_choi: { tag: 'Đột Biến', name: 'Gambler', icon: '🎲', avatar: 'https://api.dicebear.com/7.x/avataaars/svg?seed=gambler', passive: 'HP và ATK đều x2, nhưng có 20% đột tử mỗi hiệp.', activeDesc: 'Lật Kèo: Trò chơi tử thần 50/50: 50% quái chết ngay lập tức, 50% bạn tự sát. (CD: 8 tầng)' }
};

const ALL_SEGS = [
  { key:'jackpot', label:'THẦN BÀI', icon:'👑', color:'#f59e0b', dark:'#92400e', mult:5, desc:'Jackpot: ×5 thạch thưởng tầng!' },
  { key:'win3',    label:'ĐẠI THẮNG', icon:'🔥', color:'#22c55e', dark:'#052e16', mult:3, desc:'Đại thắng: ×3 thạch thưởng tầng' },
  { key:'win',     label:'CHIẾN THẮNG', icon:'⚔️', color:'#3b82f6', dark:'#1e3a5f', mult:1, desc:'Chiến thắng: ×1 thạch thưởng tầng' },
  { key:'half',    label:'NỬA THẮNG', icon:'💫', color:'#8b5cf6', dark:'#2e1065', mult:0.5, desc:'Nửa thắng: ×0.5 thạch thưởng tầng' },
  { key:'retry',   label:'THỬ LẠI', icon:'🛡️', color:'#64748b', dark:'#1e293b', mult:0, desc:'Giao lưu hòa: Quay lại miễn phí' },
  { key:'death',   label:'TỬ THẦN', icon:'💀', color:'#ef4444', dark:'#3b0000', mult:-1, desc:'Bay màu: Bị kẹt lại tầng hiện tại' }
];

// Tạo DOM cho 20 nhân vật
const charBar = document.getElementById('charSelectorBar');
Object.keys(CHARACTER_DB).forEach(k => {
  const c = CHARACTER_DB[k];
  const btn = document.createElement('button');
  btn.className = 'char-select-btn';
  btn.id = `btnChar_${k}`;
  btn.onclick = () => Game.switchCharacter(k);
  btn.innerHTML = `<span class="char-select-icon">${c.icon}</span><span class="char-select-name">${c.name}</span>`;
  charBar.appendChild(btn);
});

// Phân bổ tỷ lệ theo tầng (vùng góc)
function getSegs(floor, char, activeBuff = null) {
  const boss = floor % 10 === 0;
  
  let pJackpot = boss ? 0.05 : (floor <= 10 ? 0.10 : (floor <= 25 ? 0.08 : (floor <= 50 ? 0.06 : (floor <= 75 ? 0.05 : 0.04))));
  let pWin3    = boss ? 0.15 : (floor <= 10 ? 0.20 : (floor <= 25 ? 0.15 : (floor <= 50 ? 0.12 : (floor <= 75 ? 0.10 : 0.08))));
  let pWin     = boss ? 0.20 : (floor <= 10 ? 0.30 : (floor <= 25 ? 0.25 : (floor <= 50 ? 0.22 : (floor <= 75 ? 0.18 : 0.14))));
  let pHalf    = boss ? 0.12 : (floor <= 10 ? 0.15 : (floor <= 25 ? 0.18 : (floor <= 50 ? 0.18 : (floor <= 75 ? 0.17 : 0.14))));
  let pRetry   = boss ? 0.10 : (floor <= 10 ? 0.15 : (floor <= 25 ? 0.14 : (floor <= 50 ? 0.14 : (floor <= 75 ? 0.10 : 0.10))));
  let pDeath   = boss ? 0.38 : (floor <= 10 ? 0.10 : (floor <= 25 ? 0.20 : (floor <= 50 ? 0.28 : (floor <= 75 ? 0.40 : 0.50))));

  // 1. NỘI TẠI (PASSIVE)
  if (char === 'phap_su') { pJackpot += 0.05; pDeath -= 0.05; }
  if (char === 'cung_thu') { pWin3 += 0.05; pWin += 0.05; pDeath -= 0.10; }
  if (char === 'ninja') { pDeath -= 0.05; pWin += 0.05; }
  if (char === 'ma_kiem_si') { pDeath += 0.05; pWin -= 0.05; }
  if (char === 'gia_kim') { pWin3 += pHalf; pHalf = 0; }
  if (char === 'cuong_tin') { if ((window.shieldCount || 0) === 0) { pJackpot += 0.10; pDeath -= 0.10; } }
  if (char === 'do_te') { pDeath += pRetry; pRetry = 0; }
  if (char === 'vua_tro_choi') { pJackpot = 0.2; pDeath = 0.8; pWin3 = 0; pWin = 0; pHalf = 0; pRetry = 0; }

  // Chuẩn hóa lỡ < 0
  pDeath = Math.max(0, pDeath); pWin = Math.max(0, pWin);

  // 2. TUYỆT KỸ (ACTIVE BUFF)
  if (activeBuff === 'nhat_kiem') { let add = pJackpot; pJackpot += add; pDeath = Math.max(0, pDeath - add); }
  if (activeBuff === 'bong_toi') { pWin3 += pJackpot; pJackpot = 0; pRetry += pDeath; pDeath = 0; }
  if (activeBuff === 'hop_the') { pWin3 += pWin + pHalf; pWin = 0; pHalf = 0; }
  if (activeBuff === 'hao_quang') { pJackpot += pHalf; pHalf = 0; pDeath += 0.05; pWin = Math.max(0, pWin - 0.05); }
  if (activeBuff === 'ru_ngu') { pHalf += pRetry + pDeath; pRetry = 0; pDeath = 0; }
  if (activeBuff === 'che_thuoc') { pJackpot += pWin / 2; pDeath += pWin / 2; pWin = 0; }
  if (activeBuff === 'hy_sinh') {
    let shields = window.shieldCount || 0;
    if (shields > 0) {
      pJackpot += shields * 0.05;
      pDeath = Math.max(0, pDeath - (shields * 0.05));
    }
  }
  if (activeBuff === 'chat_chem') { pWin += pDeath / 2; pDeath /= 2; }
  if (activeBuff === 'lat_keo' && char === 'vua_tro_choi') { pJackpot = 0.8; pDeath = 0.2; }

  const resultDist = [
    { key: 'jackpot', p: pJackpot },
    { key: 'win3',    p: pWin3 },
    { key: 'win',     p: pWin },
    { key: 'half',    p: pHalf },
    { key: 'retry',   p: pRetry },
    { key: 'death',   p: pDeath }
  ];

  return resultDist.filter(d => d.p > 0).map(d => {
    const s = ALL_SEGS.find(item => item.key === d.key);
    return { ...s, portion: d.p };
  });
}

// ===== WHEEL MODULE =====
const qteBarEl = document.getElementById('qteBar');
const qteCursorEl = document.getElementById('qteCursor');

const Game = (() => {
  let floor = 1, reward = 0, cooldownTimer = null;
  let activeBuff = null, selectedChar = 'kiem_thanh';
  let teamChars = [];
  let isBattling = false;

  async function startBattle() {
    if (isBattling) return;
    isBattling = true;
    
    document.getElementById('ovResult').classList.remove('active');
    document.getElementById('btnStart').disabled = true;
    const logBox = document.getElementById('combatLog');
    logBox.innerHTML = '';
    
    try {
      const res = await fetch('api_tower_gods.php?action=auto_battle', { method:'POST' });
      const data = await res.json();

      if (!data.success) {
        Swal.fire({ title:'Lỗi', text:data.message, icon:'error', background:'#080c1e', color:'#e2e8f0' });
        readyPhase();
        return;
      }

      await playCombatAnimation(data);

    } catch(e) {
      console.error(e);
      readyPhase();
    }
  }

  function createEntityHTML(entity, isPlayer) {
      const hpPct = Math.max(0, (entity.hp / entity.max_hp) * 100);
      const color = isPlayer ? '#3b82f6' : '#ef4444';
      const hpColor = hpPct < 30 ? '#ef4444' : '#22c55e';
      const avatar = (isPlayer && CHARACTER_DB[entity.char]) ? CHARACTER_DB[entity.char].icon : entity.avatar;
      return `
      <div id="${entity.id}" class="combat-entity ${isPlayer?'player':'monster'}" style="text-align:center; padding:10px; background:rgba(255,255,255,0.02); border-radius:12px; transition:all 0.3s; flex:1;">
          <div id="${entity.id}_avatar" style="font-size:35px; filter:drop-shadow(0 0 10px ${color}); transition:transform 0.1s;">${avatar}</div>
          <div id="${entity.id}_name" style="font-weight:bold; color:#94a3b8; margin:5px 0 2px 0; font-size:11px;">${entity.name}</div>
          <div style="background:#1e293b; border-radius:6px; height:8px; overflow:hidden; border:1px solid #334155; margin:0 5px;">
              <div id="${entity.id}_hpBar" style="background:${hpColor}; height:100%; width:${hpPct}%; transition:width 0.3s;"></div>
          </div>
          <div id="${entity.id}_hpText" style="font-size:10px; margin-top:2px; color:#cbd5e1;">${entity.hp}/${entity.max_hp}</div>
      </div>`;
  }

  async function playCombatAnimation(data) {
    const logBox = document.getElementById('combatLog');
    const pArea = document.getElementById('playerTeamArea');
    const mArea = document.getElementById('monsterTeamArea');
    
    if (data.pTeam && data.mTeam) {
        pArea.innerHTML = data.pTeam.map(p => createEntityHTML(p, true)).join('');
        mArea.innerHTML = data.mTeam.map(m => createEntityHTML(m, false)).join('');
    }

    for (const log of data.combat_log) {
        if (log.turn_end) {
            for (const p of log.pState) {
                const elBar = document.getElementById(p.id + "_hpBar");
                const elText = document.getElementById(p.id + "_hpText");
                const elBox = document.getElementById(p.id);
                if (elBar) {
                    const pct = Math.max(0, (p.hp / p.max) * 100);
                    elBar.style.width = pct + '%';
                    elText.textContent = `${p.hp}/${p.max}`;
                    elBar.style.background = pct < 30 ? '#ef4444' : '#22c55e';
                    if (p.hp <= 0 && elBox) elBox.style.opacity = '0.3';
                }
            }
            for (const m of log.mState) {
                const elBar = document.getElementById(m.id + "_hpBar");
                const elText = document.getElementById(m.id + "_hpText");
                const elBox = document.getElementById(m.id);
                if (elBar) {
                    const pct = Math.max(0, (m.hp / m.max) * 100);
                    elBar.style.width = pct + '%';
                    elText.textContent = `${m.hp}/${m.max}`;
                    elBar.style.background = pct < 30 ? '#ef4444' : '#22c55e';
                    if (m.hp <= 0 && elBox) elBox.style.opacity = '0.3';
                }
            }
            await new Promise(r => setTimeout(r, 600));
            continue;
        }

        const d = document.createElement('div');
        d.style.padding = '4px 8px';
        d.style.borderRadius = '4px';
        d.style.background = log.speaker === 'system' ? 'rgba(255, 255, 255, 0.05)' : 
                            (log.speaker === 'player' ? 'rgba(59, 130, 246, 0.1)' : 'rgba(239, 68, 68, 0.1)');
        d.style.color = log.speaker === 'system' ? '#fbbf24' : 
                       (log.speaker === 'player' ? '#93c5fd' : '#fca5a5');
        d.innerHTML = log.msg;
        logBox.appendChild(d);
        logBox.scrollTop = logBox.scrollHeight;

        if (log.speaker === 'player' && logBox.children.length > 0) {
            pArea.style.transform = 'translateX(10px)';
            setTimeout(() => pArea.style.transform = 'none', 100);
        } else if (log.speaker === 'monster') {
            mArea.style.transform = 'translateX(-10px)';
            setTimeout(() => mArea.style.transform = 'none', 100);
        }
        
        await new Promise(r => setTimeout(r, 200));
    }

    await new Promise(r => setTimeout(r, 1000));
    
    if (data.progress) updateUIStats(data.progress, data.cooldowns, data.user_balance);
    
    const ov = document.getElementById('ovResult');
    if (data.is_win) {
        document.getElementById('ovIcon').textContent = '⚔️';
        document.getElementById('ovTitle').textContent = 'CHIẾN THẮNG!';
        document.getElementById('ovTitle').style.color = '#22c55e';
    } else {
        document.getElementById('ovIcon').textContent = '💀';
        document.getElementById('ovTitle').textContent = 'TỬ THẦN!';
        document.getElementById('ovTitle').style.color = '#ef4444';
    }
    
    document.getElementById('ovSub').innerHTML = data.message;
    const rewEl = document.getElementById('ovReward');
    if (data.reward_gtlm > 0) {
        rewEl.style.display = 'inline-flex';
        document.getElementById('ovAmt').textContent = new Intl.NumberFormat().format(data.reward_gtlm) + ' GTLM';
    } else {
        rewEl.style.display = 'none';
    }

    const actRow = document.getElementById('ovActions');
    if (data.is_win) {
        actRow.innerHTML = `<button class="post-action-btn btn-advance-floor" onclick="Game.nextFloor()">🚀 Lên Tầng ${floor+1}!</button>`;
    } else {
        actRow.innerHTML = `<button class="post-action-btn btn-retry-floor" onclick="Game.closeOverlay()">🔄 Quay Lại Tầng 1</button>`;
    }
    
    ov.classList.add('active');
  }

  function readyPhase() {
    isBattling = false;
    document.getElementById('btnStart').disabled = false;
    document.getElementById('combatLog').innerHTML = '<div style="text-align:center; color:#64748b; font-style:italic;">Sẵn sàng chiến đấu...</div>';
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
        
        // No UI rebuild needed in autobattler logic

        // Cập nhật CD UI ngay lập tức
        if (data.cooldown_left) {
          updateSkillBtnUI(data.cooldown_left);
        }

        if (typeof SoundFXHub !== 'undefined') SoundFXHub.playBossRoar?.();
      });
  }

  function updateSkillBtnUI(cdLeft) {
    const btn = document.getElementById('btnSkill');
    if (activeBuff) {
      btn.className = 'btn-skill-cast active-buff';
      btn.textContent = 'TUYỆT KỸ ĐANG KÍCH HOẠT!';
      btn.disabled = true;
      return;
    }
    
    if (cdLeft > 0) {
      btn.className = 'btn-skill-cast cooldown';
      btn.disabled = true;
      btn.textContent = `HỒI CHIÊU CHỜ (${cdLeft} TẦNG)`;
    } else {
      btn.className = 'btn-skill-cast ready';
      btn.disabled = false;
      btn.textContent = 'KÍCH HOẠT TUYỆT KỸ';
    }
  }

  function switchCharacter(char) {
    if (isBattling) {
      Swal.fire({ title:'Đang chiến đấu!', text:'Không thể thay đổi chiến binh lúc này!', icon:'warning', background:'#080c1e', color:'#e2e8f0' });
      return;
    }
    
    if (floor >= 50) {
        Swal.fire('Bị Khóa!', 'Đội hình đã bị khóa vĩnh viễn từ Tầng 50!', 'error');
        return;
    }

    if (teamChars.includes(char)) {
        if (teamChars.length === 1) {
            Swal.fire('Lỗi', 'Đội hình phải có ít nhất 1 nhân vật!', 'warning');
            return;
        }
        teamChars = teamChars.filter(c => c !== char);
    } else {
        if (teamChars.length >= 3) {
            Swal.fire('Lỗi', 'Đội hình tối đa 3 nhân vật!', 'warning');
            return;
        }
        teamChars.push(char);
    }

    const fd = new FormData();
    fd.append('team', JSON.stringify(teamChars));
    fetch('api_tower_gods.php?action=select_character', { method:'POST', body:fd })
      .then(res => res.json())
      .then(data => {
        if (!data.success) {
          Swal.fire('Lỗi', data.message, 'error');
          return;
        }
        
        activeBuff = null;
        updateUIStats(data.progress, data.cooldowns, data.user_balance);
        loadFloor(floor);
      });
  }

  function closeOverlay() {
    document.getElementById('ovResult').classList.remove('active');
    readyPhase();
  }

  function nextFloor() {
    document.getElementById('ovResult').classList.remove('active');
    floor++;
    loadFloor(floor);
  }

  function updateUIStats(p, cd = null, userBalance = null) {
    floor = parseInt(p.current_floor) || 1;
    window.shieldCount = parseInt(p.shield_count) || 0;
    
    document.getElementById('sBest').textContent = p.highest_floor || '-';
    document.getElementById('sWins').textContent = p.total_wins || '0';
    document.getElementById('sGtlm').textContent = new Intl.NumberFormat().format(p.total_gtlm_won || 0) + ' GTLM';
    if (userBalance !== null) {
      document.getElementById('sBalance').textContent = new Intl.NumberFormat().format(userBalance) + ' GTLM';
    }
    
    try {
        teamChars = JSON.parse(p.team_chars);
    } catch(e) {
        teamChars = [p.selected_character || 'kiem_thanh'];
    }
    
    selectedChar = teamChars[0];
    const c = CHARACTER_DB[selectedChar];
    
    document.getElementById('charAvatar').src = c.avatar;
    document.getElementById('charClassTag').textContent = "Leader: " + c.tag;
    document.getElementById('charName').textContent = c.name;
    
    let passives = teamChars.map(tc => `<b style="color:#fbbf24">${CHARACTER_DB[tc].name}</b>: ${CHARACTER_DB[tc].passive}`).join('<br>');
    document.getElementById('charPassiveDesc').innerHTML = passives;
    document.getElementById('skillDescText').innerHTML = `<b>Tuyệt Kỹ:</b> ${c.activeDesc}`;

    document.getElementById('charShieldRow').style.display = 'none';

    document.querySelectorAll('.char-select-btn').forEach(btn => {
        btn.classList.remove('active');
        const badge = btn.querySelector('.team-badge');
        if (badge) badge.remove();
    });
    
    teamChars.forEach((tc, idx) => {
        const btnActive = document.getElementById(`btnChar_${tc}`);
        if (btnActive) {
            btnActive.classList.add('active');
            const badge = document.createElement('div');
            badge.className = 'team-badge';
            badge.style = 'position:absolute; top:4px; right:4px; background:#fbbf24; color:#000; font-size:10px; padding:2px 5px; border-radius:4px; font-weight:bold; z-index:10;';
            badge.textContent = idx === 0 ? 'LDR' : (idx + 1);
            btnActive.appendChild(badge);
        }
    });
    
    if (cd) {
      updateSkillBtnUI(cd[selectedChar] || 0);
    }
  }

  async function loadFloor(f) {
    try {
      const res = await fetch('api_tower_gods.php?action=info');
      const data = await res.json();
      if (!data.success) {
        alert(data.message);
        if (data.message === 'Chưa đăng nhập!') {
          window.location.href = 'index.php';
        }
        return;
      }

      const p = data.progress;
      floor = parseInt(p.current_floor) || f;
      reward = data.floor_reward || floor * 10000;
      document.getElementById('playerTeamArea').innerHTML = '<div style="color:#64748b; font-style:italic; padding:20px; text-align:center;">Sẵn sàng chiến đấu...</div>';
      document.getElementById('monsterTeamArea').innerHTML = '<div style="color:#64748b; font-style:italic; padding:20px; text-align:center;">Đang tìm đối thủ...</div>';
      
      const isBoss = floor % 10 === 0;
      // Render UI
      document.getElementById('floorNum').textContent = floor;
      document.getElementById('floorPill').innerHTML = isBoss ? '👑 BOSS TẦNG <span id="floorNum">'+floor+'</span>' : '🔥 TẦNG <span id="floorNum">'+floor+'</span>';
      
      const wp = document.getElementById('weatherPill');
      if (floor > 1) {
          wp.style.display = 'inline-block';
          wp.textContent = "Dự báo thời tiết: ☁️ Đang xác định...";
      } else {
          wp.style.display = 'none';
      }

      updateUIStats(p, data.cooldowns, data.user_balance);
      renderFloorTrack(floor);

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

      // Chờ người chơi bấm Bắt đầu (ready phase)
      readyPhase();
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


  // Bind Space and Enter keys for action start
  document.addEventListener('keydown', e => {
    if (e.code === 'Space' || e.code === 'Enter') {
      e.preventDefault();
      const b = document.getElementById('btnStart');
      if (b && !b.disabled) b.click();
    }
  });

  return { startBattle, nextFloor, castSkill, switchCharacter, closeOverlay, loadFloor };
})();

document.addEventListener('DOMContentLoaded', () => Game.loadFloor(1));
</script>
</body>
</html>

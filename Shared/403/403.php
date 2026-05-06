<?php
session_start();
// Đảm bảo có tên người chơi nếu đã đăng nhập
$tenNguoiChoi = $_SESSION['Name'] ?? 'Khách Lạ';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>403 - Cút Ra Ngay!</title>
<link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Fredoka+One&family=Comic+Neue:wght@700&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
<style>
  :root {
    --red:    #ff2d55;
    --yellow: #ffd60a;
    --blue:   #0a84ff;
    --green:  #30d158;
    --purple: #bf5af2;
    --bg:     #0a0a0f;
  }

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    background: var(--bg);
    font-family: 'Fredoka One', cursive;
    color: #fff;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    cursor: url('chuot.png'), auto;
  }

  /* Three.js canvas nền */
  #bg-canvas {
    position: fixed;
    inset: 0;
    z-index: 0;
  }

  /* Lớp scanline overlay */
  body::after {
    content: '';
    position: fixed;
    inset: 0;
    background: repeating-linear-gradient(
      0deg,
      transparent,
      transparent 2px,
      rgba(0,0,0,0.08) 2px,
      rgba(0,0,0,0.08) 4px
    );
    pointer-events: none;
    z-index: 1;
  }

  .stage {
    position: relative;
    z-index: 2;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0;
    text-align: center;
    padding: 2rem;
  }

  /* Biển hiệu 403 */
  .sign {
    position: relative;
    margin-bottom: 2rem;
  }

  .sign-board {
    background: var(--red);
    border: 6px solid var(--yellow);
    border-radius: 20px;
    padding: 16px 48px;
    position: relative;
    box-shadow:
      0 0 0 3px var(--red),
      0 0 40px rgba(255,45,85,0.8),
      0 0 80px rgba(255,45,85,0.4),
      inset 0 0 30px rgba(0,0,0,0.3);
  }
  .sign-board::before,
  .sign-board::after {
    content: '⚠️';
    position: absolute;
    top: 50%; transform: translateY(-50%);
    font-size: 32px;
  }
  .sign-board::before { left: 10px; }
  .sign-board::after  { right: 10px; }

  .num-403 {
    font-family: 'Press Start 2P', monospace;
    font-size: clamp(48px, 10vw, 96px);
    color: var(--yellow);
    text-shadow:
      4px 4px 0 #b8860b,
      0 0 30px rgba(255,214,10,0.9),
      0 0 60px rgba(255,214,10,0.5);
    display: block;
    line-height: 1;
  }

  /* Nhân vật bảo vệ */
  .guard {
    font-size: 100px;
    display: block;
    margin: 1rem 0;
    filter: drop-shadow(0 0 20px rgba(255,45,85,0.6));
    transform-origin: center bottom;
  }

  /* Tiêu đề chính */
  .title-main {
    font-family: 'Press Start 2P', monospace;
    font-size: clamp(14px, 3vw, 22px);
    color: var(--yellow);
    text-shadow: 0 0 20px var(--yellow);
    line-height: 1.6;
    margin-bottom: 0.5rem;
  }

  .title-sub {
    font-size: clamp(18px, 4vw, 28px);
    color: #fff;
    opacity: 0.85;
    margin-bottom: 1.5rem;
  }

  /* Badge người dùng */
  .user-badge {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: rgba(255,255,255,0.08);
    border: 2px solid rgba(255,255,255,0.2);
    border-radius: 999px;
    padding: 8px 24px;
    font-size: 18px;
    margin-bottom: 2rem;
    backdrop-filter: blur(10px);
  }
  .user-badge .avatar { font-size: 28px; }
  .user-badge .uname  { color: var(--yellow); }

  /* Lý do từ chối — danh sách hài */
  .reason-box {
    background: rgba(255,255,255,0.05);
    border: 2px dashed rgba(255,214,10,0.4);
    border-radius: 16px;
    padding: 1.25rem 2rem;
    margin-bottom: 2rem;
    max-width: 560px;
    text-align: left;
  }
  .reason-box h3 {
    font-family: 'Press Start 2P', monospace;
    font-size: 11px;
    color: var(--red);
    margin-bottom: 12px;
    text-align: center;
    text-shadow: 0 0 10px var(--red);
  }
  .reason-list {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }
  .reason-list li {
    font-size: 16px;
    color: rgba(255,255,255,0.8);
    display: flex;
    align-items: flex-start;
    gap: 10px;
  }
  .reason-list li .ico { flex-shrink: 0; font-size: 20px; }

  /* Nút hành động */
  .btn-group {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    justify-content: center;
    margin-bottom: 2rem;
  }

  .btn {
    font-family: 'Press Start 2P', monospace;
    font-size: 11px;
    padding: 14px 28px;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: transform 0.1s;
    letter-spacing: 0.05em;
  }
  .btn:active { transform: scale(0.94); }

  .btn-home {
    background: var(--yellow);
    color: #000;
    box-shadow: 0 0 20px rgba(255,214,10,0.5), 4px 4px 0 #b8860b;
  }
  .btn-home:hover {
    box-shadow: 0 0 40px rgba(255,214,10,0.8), 4px 4px 0 #b8860b;
  }

  .btn-beg {
    background: var(--purple);
    color: #fff;
    box-shadow: 0 0 20px rgba(191,90,242,0.5), 4px 4px 0 #7b2d8b;
  }
  .btn-beg:hover {
    box-shadow: 0 0 40px rgba(191,90,242,0.8), 4px 4px 0 #7b2d8b;
  }

  /* Ticker bottom */
  .ticker {
    position: fixed;
    bottom: 0; left: 0; right: 0;
    background: var(--red);
    padding: 10px 0;
    overflow: hidden;
    z-index: 10;
    border-top: 3px solid var(--yellow);
  }
  .ticker-inner {
    display: inline-flex;
    gap: 80px;
    white-space: nowrap;
    font-family: 'Press Start 2P', monospace;
    font-size: 11px;
    color: var(--yellow);
    animation: scroll-left 18s linear infinite;
  }
  @keyframes scroll-left {
    from { transform: translateX(0); }
    to   { transform: translateX(-50%); }
  }

  /* Confetti particles từ CSS */
  .confetti-wrap {
    position: fixed;
    inset: 0;
    pointer-events: none;
    z-index: 3;
    overflow: hidden;
  }
  .confetti-piece {
    position: absolute;
    width: 10px; height: 10px;
    border-radius: 2px;
    opacity: 0;
    animation: fall linear infinite;
  }
  @keyframes fall {
    0%   { transform: translateY(-20px) rotate(0deg);   opacity: 1; }
    100% { transform: translateY(110vh) rotate(720deg); opacity: 0; }
  }

  /* Glitch effect cho 403 */
  @keyframes glitch {
    0%,100% { text-shadow: 4px 4px 0 #b8860b, 0 0 30px rgba(255,214,10,0.9); clip-path: none; }
    20%      { text-shadow: -4px 4px 0 var(--red), 4px -4px 0 var(--blue); clip-path: inset(10% 0 80% 0); transform: translate(-3px, 0); }
    40%      { text-shadow: 4px 4px 0 #b8860b; clip-path: inset(60% 0 20% 0); transform: translate(3px, 0); }
    60%      { text-shadow: -4px -4px 0 var(--green), 0 0 50px rgba(255,214,10,1); clip-path: none; transform: translate(0); }
  }
  .glitch { animation: glitch 3s steps(1) infinite; }

  /* Shake guard */
  @keyframes guard-bounce {
    0%,100% { transform: rotate(0deg) scale(1); }
    15%      { transform: rotate(-8deg) scale(1.1); }
    30%      { transform: rotate(8deg) scale(1.1); }
    45%      { transform: rotate(-5deg) scale(1.05); }
    60%      { transform: rotate(5deg) scale(1.05); }
    75%      { transform: rotate(-2deg) scale(1); }
  }
  .guard-anim { animation: guard-bounce 1.2s ease-in-out infinite; }

  /* Popup khi bấm "Năn nỉ" */
  .popup-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.7);
    z-index: 100;
    display: none;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(8px);
  }
  .popup-overlay.show { display: flex; }
  .popup-box {
    background: #1a1a2e;
    border: 3px solid var(--yellow);
    border-radius: 20px;
    padding: 2.5rem;
    max-width: 400px;
    text-align: center;
    box-shadow: 0 0 60px rgba(255,214,10,0.4);
  }
  .popup-box .big-emoji { font-size: 80px; display: block; margin-bottom: 1rem; }
  .popup-box h2 { font-family: 'Press Start 2P', monospace; font-size: 14px; color: var(--red); margin-bottom: 1rem; line-height: 1.6; }
  .popup-box p  { font-size: 18px; color: rgba(255,255,255,0.8); margin-bottom: 1.5rem; line-height: 1.5; }
  .popup-box .btn-close {
    background: var(--red);
    color: #fff;
    font-family: 'Press Start 2P', monospace;
    font-size: 10px;
    padding: 12px 24px;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    box-shadow: 4px 4px 0 #8b0000;
  }
</style>
</head>
<body>

<canvas id="bg-canvas"></canvas>

<!-- Confetti CSS -->
<div class="confetti-wrap" id="confetti-wrap"></div>

<div class="stage" id="stage">

  <div class="sign" id="sign">
    <div class="sign-board">
      <span class="num-403 glitch">403</span>
    </div>
  </div>

  <span class="guard guard-anim" id="guard">🚫</span>

  <h1 class="title-main" id="title-main">DỪNG LẠI! KHÔNG ĐƯỢC VÀO!</h1>
  <p  class="title-sub"  id="title-sub">Trang này chỉ dành cho Admin thôi bạn ơi 😂</p>

  <div class="user-badge" id="user-badge">
    <span class="avatar">🧑💻</span>
    <span>Xin chào, <span class="uname"><?= htmlspecialchars($tenNguoiChoi) ?></span> — bạn chưa đủ quyền!</span>
  </div>

  <div class="reason-box" id="reason-box">
    <h3>📋 LÝ DO TỪ CHỐI TRUY CẬP</h3>
    <ul class="reason-list">
      <li><span class="ico">❌</span> Bạn không phải Admin (và chắc chắn sẽ không bao giờ là)</li>
      <li><span class="ico">🔐</span> Cấp độ bảo mật: CAO — cấp độ bạn: ??? </li>
      <li><span class="ico">🧠</span> Bạn thông minh nhưng chưa đủ thông minh cho trang này</li>
      <li><span class="ico">💸</span> Chưa nộp đủ học phí Admin (phí: 999 triệu gtlm)</li>
      <li><span class="ico">🎯</span> Hệ thống phát hiện bạn chỉ muốn xem người khác thua bao nhiêu</li>
    </ul>
  </div>

  <div class="btn-group" id="btn-group">
    <a href="index.php" class="btn btn-home">🏠 VỀ NHÀ NGAY!</a>
    <button class="btn btn-beg" onclick="showBegging()">🙏 NĂN NỈ ADMIN</button>
  </div>

</div>

<!-- Ticker bottom -->
<div class="ticker">
  <div class="ticker-inner" id="ticker-inner">
    <span>⛔ KHU VỰC CẤM &nbsp;•&nbsp; BẠN KHÔNG CÓ QUYỀN &nbsp;•&nbsp; QUAY ĐI &nbsp;•&nbsp; ĐỌC ĐI ĐỌC LẠI CŨNG VẪN 403 &nbsp;•&nbsp; XIN CHÀO <?= strtoupper(htmlspecialchars($tenNguoiChoi)) ?> — THẤT BẠI RỒI &nbsp;•&nbsp; GỌI MẸ ĐI RỒI NHỜ MẸ XIN QUYỀN &nbsp;•&nbsp;</span>
    <span>⛔ KHU VỰC CẤM &nbsp;•&nbsp; BẠN KHÔNG CÓ QUYỀN &nbsp;•&nbsp; QUAY ĐI &nbsp;•&nbsp; ĐỌC ĐI ĐỌC LẠI CŨNG VẪN 403 &nbsp;•&nbsp; XIN CHÀO <?= strtoupper(htmlspecialchars($tenNguoiChoi)) ?> — THẤT BẠI RỒI &nbsp;•&nbsp; GỌI MẸ ĐI RỒI NHỜ MẸ XIN QUYỀN &nbsp;•&nbsp;</span>
  </div>
</div>

<!-- Popup năn nỉ -->
<div class="popup-overlay" id="popup-overlay">
  <div class="popup-box" id="popup-box">
    <span class="big-emoji" id="popup-emoji">🤣</span>
    <h2 id="popup-title">YÊU CẦU NĂN NỈ ĐÃ GỬI!</h2>
    <p id="popup-msg">Admin đã nhận được đơn của bạn và đã... xóa luôn rồi. Cảm ơn đã thử! 😂</p>
    <button class="btn-close" onclick="hideBegging()">ĐÓNG & KHÓC TIẾP</button>
  </div>
</div>

<script>
/* ─── THREE.JS BACKGROUND: Particles + Floating Shapes ─── */
(function() {
  const canvas = document.getElementById('bg-canvas');
  const renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: false });
  renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
  renderer.setSize(innerWidth, innerHeight);

  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(60, innerWidth / innerHeight, 0.1, 200);
  camera.position.z = 30;

  // Particle field
  const pCount = 600;
  const pGeo = new THREE.BufferGeometry();
  const pPos = new Float32Array(pCount * 3);
  const pCol = new Float32Array(pCount * 3);
  const colors = [
    [1, 0.18, 0.33],   // red
    [1, 0.84, 0.04],   // yellow
    [0.04, 0.52, 1],   // blue
    [0.75, 0.35, 0.95] // purple
  ];
  for (let i = 0; i < pCount; i++) {
    pPos[i*3]   = (Math.random() - 0.5) * 120;
    pPos[i*3+1] = (Math.random() - 0.5) * 80;
    pPos[i*3+2] = (Math.random() - 0.5) * 60;
    const c = colors[Math.floor(Math.random() * colors.length)];
    pCol[i*3] = c[0]; pCol[i*3+1] = c[1]; pCol[i*3+2] = c[2];
  }
  pGeo.setAttribute('position', new THREE.BufferAttribute(pPos, 3));
  pGeo.setAttribute('color',    new THREE.BufferAttribute(pCol, 3));
  const pMat = new THREE.PointsMaterial({ size: 0.35, vertexColors: true, transparent: true, opacity: 0.7 });
  scene.add(new THREE.Points(pGeo, pMat));

  // Floating emoji-like shapes (đa giác ngẫu nhiên)
  const shapeColors = [0xff2d55, 0xffd60a, 0x0a84ff, 0x30d158, 0xbf5af2];
  const shapes = [];
  for (let i = 0; i < 18; i++) {
    const geo = Math.random() > 0.5
      ? new THREE.OctahedronGeometry(0.8 + Math.random() * 1.2)
      : new THREE.TorusGeometry(0.7 + Math.random(), 0.25, 8, 16);
    const mat = new THREE.MeshBasicMaterial({
      color: shapeColors[Math.floor(Math.random() * shapeColors.length)],
      wireframe: true,
      transparent: true,
      opacity: 0.25
    });
    const mesh = new THREE.Mesh(geo, mat);
    mesh.position.set(
      (Math.random() - 0.5) * 60,
      (Math.random() - 0.5) * 40,
      (Math.random() - 0.5) * 20 - 10
    );
    mesh.userData = {
      rx: (Math.random() - 0.5) * 0.02,
      ry: (Math.random() - 0.5) * 0.015,
      fy: (Math.random() - 0.5) * 0.003,
      originY: mesh.position.y
    };
    scene.add(mesh);
    shapes.push(mesh);
  }

  let t = 0;
  function animate() {
    requestAnimationFrame(animate);
    t += 0.01;

    // Rotate particles slowly
    scene.rotation.y = t * 0.03;
    scene.rotation.x = Math.sin(t * 0.05) * 0.05;

    shapes.forEach(m => {
      m.rotation.x += m.userData.rx;
      m.rotation.y += m.userData.ry;
      m.position.y = m.userData.originY + Math.sin(t + m.position.x) * 2;
    });

    renderer.render(scene, camera);
  }
  animate();

  window.addEventListener('resize', () => {
    camera.aspect = innerWidth / innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(innerWidth, innerHeight);
  });
})();

/* ─── GSAP ENTRANCE ANIMATION ─── */
gsap.set(['#sign','#guard','#title-main','#title-sub','#user-badge','#reason-box','#btn-group'], { opacity: 0, y: 40 });

const tl = gsap.timeline({ defaults: { ease: 'back.out(1.4)' } });
tl.to('#sign',       { opacity: 1, y: 0, duration: 0.7, delay: 0.2 })
  .to('#guard',      { opacity: 1, y: 0, duration: 0.5 }, '-=0.3')
  .to('#title-main', { opacity: 1, y: 0, duration: 0.5 }, '-=0.2')
  .to('#title-sub',  { opacity: 1, y: 0, duration: 0.5 }, '-=0.3')
  .to('#user-badge', { opacity: 1, y: 0, duration: 0.5 }, '-=0.2')
  .to('#reason-box', { opacity: 1, y: 0, duration: 0.6 }, '-=0.2')
  .to('#btn-group',  { opacity: 1, y: 0, duration: 0.5 }, '-=0.3');

/* ─── GSAP: Biển hiệu lắc lư ─── */
gsap.to('#sign', {
  rotation: 3,
  yoyo: true,
  repeat: -1,
  duration: 1.8,
  ease: 'sine.inOut'
});

/* ─── GSAP: Nút home nhảy nhảy ─── */
gsap.to('.btn-home', {
  y: -6,
  yoyo: true,
  repeat: -1,
  duration: 0.9,
  ease: 'sine.inOut'
});

/* ─── CSS Confetti particles ─── */
(function() {
  const wrap = document.getElementById('confetti-wrap');
  const colors = ['#ff2d55','#ffd60a','#0a84ff','#30d158','#bf5af2','#ff9f0a'];
  for (let i = 0; i < 40; i++) {
    const el = document.createElement('div');
    el.className = 'confetti-piece';
    el.style.cssText = `
      left: ${Math.random()*100}%;
      top: ${-10 - Math.random()*20}px;
      background: ${colors[Math.floor(Math.random()*colors.length)]};
      width: ${6 + Math.random()*8}px;
      height: ${6 + Math.random()*8}px;
      border-radius: ${Math.random() > 0.5 ? '50%' : '2px'};
      animation-duration: ${4 + Math.random()*6}s;
      animation-delay: ${Math.random()*8}s;
    `;
    wrap.appendChild(el);
  }
})();

/* ─── Mouse parallax trên stage ─── */
document.addEventListener('mousemove', e => {
  const cx = (e.clientX / innerWidth  - 0.5) * 2;
  const cy = (e.clientY / innerHeight - 0.5) * 2;
  gsap.to('#stage', {
    x: cx * 10,
    y: cy * 6,
    duration: 1.2,
    ease: 'power2.out'
  });
});

/* ─── Popup năn nỉ ─── */
const responses = [
  { emoji: '🤣', title: 'HA HA HA!',              msg: 'Admin đọc đơn xong cười bò rồi xóa luôn. Thử lại sau 10 năm nha!' },
  { emoji: '🗑️', title: 'ĐƠN ĐÃ BỊ XÓA!',        msg: 'Hệ thống tự động ném đơn vào thùng rác. Không qua tay người.' },
  { emoji: '😴', title: 'ADMIN ĐANG NGỦ...',       msg: 'Bạn làm phiền Admin lúc trưa. Bị vào blacklist 30 ngày rồi đó.' },
  { emoji: '🤖', title: 'BOT TỪ CHỐI TỰ ĐỘNG',    msg: 'AI đã đọc đơn và kết luận: Không đủ điều kiện. IQ cần cao hơn 9000.' },
  { emoji: '📮', title: 'ĐÃ GỬI... VÀO SPAM!',    msg: 'Email năn nỉ tự động vào thư mục spam. Admin không bao giờ mở thư mục đó.' },
];
let begged = 0;

function showBegging() {
  const r = responses[begged % responses.length];
  begged++;
  document.getElementById('popup-emoji').textContent = r.emoji;
  document.getElementById('popup-title').textContent  = r.title;
  document.getElementById('popup-msg').textContent    = r.msg;

  const overlay = document.getElementById('popup-overlay');
  overlay.classList.add('show');
  gsap.fromTo('#popup-box',
    { scale: 0.5, rotation: -10, opacity: 0 },
    { scale: 1,   rotation: 0,   opacity: 1, duration: 0.5, ease: 'back.out(2)' }
  );
}
function hideBegging() {
  gsap.to('#popup-box', {
    scale: 0.5, opacity: 0, rotation: 10, duration: 0.3, ease: 'back.in(2)',
    onComplete: () => document.getElementById('popup-overlay').classList.remove('show')
  });
}
document.getElementById('popup-overlay').addEventListener('click', function(e) {
  if (e.target === this) hideBegging();
});
</script>
</body>
</html>

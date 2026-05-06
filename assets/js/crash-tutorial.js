/* ================================================================
   Crash Tutorial – GSAP Animated Walkthrough
   ================================================================ */
(function () {
  /* ---- inject CSS ---- */
  const css = `
#crTut{position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.93);backdrop-filter:blur(14px);
  display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1.1rem;padding:1rem;
  opacity:0;pointer-events:none;transition:opacity .4s;box-sizing:border-box}
#crTut.on{opacity:1;pointer-events:all}
#crTutSkip{position:absolute;top:1rem;right:1.5rem;background:rgba(255,255,255,.07);
  border:1px solid rgba(255,255,255,.12);color:rgba(255,255,255,.55);font-size:.72rem;font-weight:700;
  letter-spacing:1px;padding:.4rem .9rem;border-radius:2rem;cursor:pointer;text-transform:uppercase;transition:.25s}
#crTutSkip:hover{background:rgba(255,71,87,.2);border-color:#ff4757;color:#fff}
#crTutDots{display:flex;gap:6px;position:absolute;top:1.1rem;left:50%;transform:translateX(-50%)}
.crdot{width:7px;height:7px;border-radius:50%;background:rgba(255,255,255,.18);transition:.3s;cursor:pointer}
.crdot.on{background:#ff4757;width:22px;border-radius:4px}
#crTutWin{display:grid;grid-template-columns:185px 1fr;border-radius:1.5rem;overflow:hidden;
  border:1px solid rgba(255,255,255,.08);box-shadow:0 40px 100px rgba(0,0,0,.8);
  width:min(820px,95vw);height:min(360px,45vh);background:#05050f}
/* sidebar */
#crTS{background:rgba(8,4,20,.97);border-right:1px solid rgba(255,255,255,.05);
  padding:.85rem;display:flex;flex-direction:column;gap:.5rem}
.crtfl{background:rgba(0,0,0,.3);border:1px solid rgba(255,255,255,.05);border-radius:.7rem;
  padding:.4rem .7rem;transition:.35s}
.crtfl.hlr{border-color:#ff4757;background:rgba(255,71,87,.09);box-shadow:0 0 18px rgba(255,71,87,.4)}
.crtfl.hlg{border-color:#2ecc71;background:rgba(46,204,113,.09);box-shadow:0 0 18px rgba(46,204,113,.35)}
.crtfl-lbl{font-size:.5rem;text-transform:uppercase;color:rgba(255,255,255,.3);font-weight:700;letter-spacing:1px;margin-bottom:1px}
.crtfl-val{font-size:.9rem;font-weight:900;color:#fff;font-family:'Orbitron',sans-serif}
.crtfl-val.ac{color:#f1c40f}
#crTSBtn{background:linear-gradient(135deg,#ff4757,#ff6b81);border:none;color:#fff;
  font-weight:900;font-size:.75rem;padding:.55rem;border-radius:.7rem;cursor:pointer;
  text-transform:uppercase;font-family:'Orbitron';letter-spacing:1px;transition:.3s}
#crTSBtn.hlr{box-shadow:0 0 28px rgba(255,71,87,.85);transform:scale(1.05)}
#crTCBtn{background:linear-gradient(135deg,#2ecc71,#27ae60);border:none;color:#fff;
  font-weight:900;font-size:.75rem;padding:.55rem;border-radius:.7rem;cursor:pointer;
  text-transform:uppercase;font-family:'Orbitron';letter-spacing:1px;transition:.3s;display:none}
#crTCBtn.hlg{box-shadow:0 0 28px rgba(46,204,113,.85);transform:scale(1.07)}
#crTBal{margin-top:auto;display:flex;justify-content:space-between;font-size:.68rem;color:rgba(255,255,255,.35)}
#crTBal b{font-family:'Orbitron';font-size:.78rem;color:#f1c40f}
/* game area */
#crTGame{position:relative;background:#05050a;overflow:hidden;display:flex;align-items:center;justify-content:center}
#crTCvs{position:absolute;inset:0;width:100%;height:100%}
#crTMWrap{position:relative;z-index:5;text-align:center;pointer-events:none}
#crTMult{font-family:'Orbitron';font-size:min(4rem,7vw);font-weight:900;color:#fff;
  transition:color .3s,text-shadow .3s;position:relative;z-index:6}
#crTMGlow{position:absolute;inset:0;font-family:'Orbitron';font-size:min(4rem,7vw);font-weight:900;
  filter:blur(20px);opacity:.6;color:#2ecc71;pointer-events:none}
/* rocket */
#crTRocket{position:absolute;bottom:22px;left:22px;z-index:10;display:none}
.crtr-body{width:18px;height:40px;background:linear-gradient(180deg,#fff 0%,#ccc 60%,#aaa 100%);
  border-radius:40% 40% 15% 15%;margin:0 auto;position:relative;box-shadow:0 0 12px rgba(255,71,87,.5)}
.crtr-tip{width:0;height:0;border-left:9px solid transparent;border-right:9px solid transparent;
  border-bottom:14px solid #ff4757;position:absolute;top:-13px;left:50%;transform:translateX(-50%)}
.crtr-win{width:6px;height:6px;background:radial-gradient(circle,#12c2e9,#0077b6);border-radius:50%;
  position:absolute;top:10px;left:50%;transform:translateX(-50%);box-shadow:0 0 5px #12c2e9}
.crtr-fin{position:absolute;bottom:0;width:0;height:0}
.crtr-fin.l{border-right:5px solid #cc3333;border-top:10px solid transparent;left:-5px}
.crtr-fin.r{border-left:5px solid #cc3333;border-top:10px solid transparent;right:-5px}
.crtr-flames{position:absolute;bottom:-18px;left:50%;transform:translateX(-50%);
  display:flex;flex-direction:column;align-items:center;gap:1px}
.crtf{border-radius:50%/60% 60% 40% 40%;animation:flameFlicker .08s infinite alternate}
.crtf1{width:10px;height:18px;background:linear-gradient(to top,#ff4500,#ff8c00,#ffd700)}
.crtf2{width:7px;height:12px;background:linear-gradient(to top,#ff6b00,#ffa500,#fff);opacity:.8;animation-delay:.03s}
/* caption */
#crTutCap{background:rgba(8,4,20,.96);border:1px solid rgba(255,255,255,.07);border-radius:1.2rem;
  padding:.9rem 1.3rem;width:min(820px,95vw);box-sizing:border-box;display:flex;align-items:center;gap:1rem}
#crTutCapL{flex:1;min-width:0}
#crTutIcon{font-size:1.5rem;line-height:1}
#crTutTitle{font-family:'Orbitron';font-size:.88rem;font-weight:900;color:#fff;margin:.1rem 0 .2rem}
#crTutDesc{font-size:.76rem;color:rgba(255,255,255,.55);line-height:1.5}
#crTutCapR{display:flex;align-items:center;gap:.6rem;flex-shrink:0}
#crTutCnt{font-size:.68rem;color:rgba(255,255,255,.28);font-family:'Orbitron';white-space:nowrap}
.crtnav{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);color:rgba(255,255,255,.65);
  font-size:.73rem;font-weight:700;padding:.45rem .9rem;border-radius:.7rem;cursor:pointer;transition:.25s;white-space:nowrap}
.crtnav:hover{background:rgba(255,71,87,.2);border-color:#ff4757;color:#fff}
.crtnav.pri{background:linear-gradient(135deg,#ff4757,#ff6b81);border-color:transparent;color:#fff}
.crtnav.pri:hover{filter:brightness(1.1)}
/* cursor indicator */
#crTCursor{position:absolute;width:18px;height:18px;border-radius:50%;background:rgba(255,71,87,.7);
  border:2px solid #fff;pointer-events:none;z-index:20;display:none;
  box-shadow:0 0 10px rgba(255,71,87,.8);transition:none}
  `;
  const styleEl = document.createElement('style');
  styleEl.textContent = css;
  document.head.appendChild(styleEl);

  /* ---- inject HTML ---- */
  document.body.insertAdjacentHTML('beforeend', `
<div id="crTut">
  <button id="crTutSkip" onclick="crTutClose()">✕ Bỏ qua</button>
  <div id="crTutDots"></div>
  <div id="crTutWin">
    <div id="crTS">
      <div style="font-family:'Orbitron';font-size:1.15rem;font-weight:900;color:#ff4757;margin-bottom:.05rem">🚀 CRASH</div>
      <div class="crtfl" id="crtfl-bet"><div class="crtfl-lbl">Gtlm cược (gtlm)</div><div class="crtfl-val" id="crTBetV">10.000</div></div>
      <div class="crtfl" id="crtfl-auto"><div class="crtfl-lbl">Tự động rút (x)</div><div class="crtfl-val" id="crTAutoV">2.00</div></div>
      <div class="crtfl" id="crtfl-win"><div class="crtfl-lbl">Gtlm nhận dự kiến</div><div class="crtfl-val ac" id="crTWinV">20.000</div></div>
      <button id="crTSBtn">CẤT CÁNH</button>
      <button id="crTCBtn">RÚT Gtlm</button>
      <div id="crTBal"><span>Số Gtlm:</span><b id="crTBalV">100.000</b></div>
    </div>
    <div id="crTGame">
      <canvas id="crTCvs"></canvas>
      <div id="crTMWrap">
        <div id="crTMGlow">1.00x</div>
        <div id="crTMult">1.00x</div>
      </div>
      <div id="crTRocket">
        <div class="crtr-body">
          <div class="crtr-tip"></div>
          <div class="crtr-win"></div>
          <div class="crtr-fin l"></div>
          <div class="crtr-fin r"></div>
        </div>
        <div class="crtr-flames">
          <div class="crtf crtf1"></div>
          <div class="crtf crtf2"></div>
        </div>
      </div>
      <div id="crTCursor"></div>
    </div>
  </div>
  <div id="crTutCap">
    <div id="crTutCapL">
      <div id="crTutIcon">🎮</div>
      <div id="crTutTitle">Chào mừng!</div>
      <div id="crTutDesc">Nhấn Tiếp theo để bắt đầu hướng dẫn.</div>
    </div>
    <div id="crTutCapR">
      <span id="crTutCnt">1 / 7</span>
      <button class="crtnav" id="crTutPrev" onclick="crTutPrev()">← Trước</button>
      <button class="crtnav pri" id="crTutNext" onclick="crTutNext()">Tiếp theo →</button>
    </div>
  </div>
</div>`);

  /* ---- Steps definition ---- */
  const STEPS = [
    { icon: '🎮', title: 'Chào mừng đến Crash!', desc: 'Chúng ta sẽ chơi thử một ván mẫu ngay bây giờ. Nhấn <b style="color:#fff">Tiếp theo</b> để bắt đầu!' },
    { icon: '💰', title: 'Bước 1 – Đặt Gtlm cược', desc: 'Nhập số Gtlm muốn đặt cược. Ví dụ <b style="color:#fff">10.000 gtlm</b>. Gtlm bị trừ ngay khi ván bắt đầu.', fn: step_bet },
    { icon: '⚙️', title: 'Bước 2 – Tự động rút', desc: 'Nhập hệ số <b style="color:#fff">2.00</b> → hệ thống tự rút khi đạt ×2.00 dù bạn không kịp nhấn tay.', fn: step_auto },
    { icon: '🚀', title: 'Bước 3 – Nhấn CẤT CÁNH!', desc: 'Nhấn nút <b style="color:#ff4757">CẤT CÁNH</b>. Tên lửa bay lên, hệ số bắt đầu tăng từ 1.00x...', fn: step_launch },
    { icon: '📈', title: 'Bước 4 – Hệ số leo thang', desc: 'Hệ số tăng liên tục. <b>Gtlm thắng = Cược × Hệ số</b>. Hãy rút trước khi tên lửa phát nổ!', fn: step_fly },
    { icon: '💵', title: 'Bước 5 – Rút Gtlm thắng! 🎉', desc: 'Nhấn <b style="color:#2ecc71">RÚT Gtlm</b> bất cứ lúc nào. Rút tại ×2.00 → thắng <b style="color:#2ecc71">20.000 gtlm</b>!', fn: step_cashout },
    { icon: '💥', title: 'Cảnh báo – Phát nổ!', desc: 'Nếu không kịp rút, tên lửa phát nổ và bạn <b style="color:#ff4757">mất toàn bộ Gtlm cược</b>. Hãy rút đúng lúc!', fn: step_crash },
  ];

  /* ---- State ---- */
  let cur = 0, raf = null, running = false, tl = null, mult = 1.0, gpts = [];

  /* ---- Public API ---- */
  window.crTutOpen = function () {
    cur = 0;
    buildDots();
    reset();
    goStep(0);
    document.getElementById('crTut').classList.add('on');
  };
  window.crTutClose = function () {
    stopSim();
    if (tl) { tl.kill(); tl = null; }
    document.getElementById('crTut').classList.remove('on');
  };
  window.crTutPrev = function () { if (cur > 0) goStep(cur - 1); };
  window.crTutNext = function () {
    if (cur < STEPS.length - 1) goStep(cur + 1);
    else crTutClose();
  };

  /* ---- Helpers ---- */
  function buildDots() {
    document.getElementById('crTutDots').innerHTML =
      STEPS.map((_, i) => `<span class="crdot${i === 0 ? ' on' : ''}" onclick="crTutGo(${i})"></span>`).join('');
  }
  window.crTutGo = function (i) { goStep(i); };

  function goStep(i) {
    stopSim();
    if (tl) { tl.kill(); tl = null; }
    reset();
    cur = i;
    const s = STEPS[i];
    document.getElementById('crTutIcon').textContent = s.icon;
    document.getElementById('crTutTitle').textContent = s.title;
    document.getElementById('crTutDesc').innerHTML = s.desc;
    document.getElementById('crTutCnt').textContent = (i + 1) + ' / ' + STEPS.length;
    document.getElementById('crTutNext').textContent = i === STEPS.length - 1 ? 'Chơi ngay! 🚀' : 'Tiếp theo →';
    document.getElementById('crTutPrev').style.opacity = i === 0 ? '.35' : '1';
    document.querySelectorAll('.crdot').forEach((d, j) => d.classList.toggle('on', j === i));
    if (s.fn) s.fn();
  }

  function hl(id, cls) { document.getElementById(id)?.classList.add(cls || 'hlr'); }
  function unhl(id) { const el = document.getElementById(id); if (el) { el.classList.remove('hlr', 'hlg'); } }

  function reset() {
    stopSim();
    ['crtfl-bet', 'crtfl-auto', 'crtfl-win'].forEach(unhl);
    const sb = document.getElementById('crTSBtn'), cb = document.getElementById('crTCBtn');
    sb.classList.remove('hlr'); sb.style.display = '';
    cb.classList.remove('hlg'); cb.style.display = 'none';
    setMult(1.0);
    const rk = document.getElementById('crTRocket');
    rk.style.display = 'none';
    if (window.gsap) gsap.set('#crTRocket', { x: 0, y: 0, rotation: -10, scale: 1, opacity: 1, clearProps: '' });
    gpts = []; drawGraph();
    document.getElementById('crTBetV').textContent = '10.000';
    document.getElementById('crTAutoV').textContent = '2.00';
    document.getElementById('crTWinV').textContent = '20.000';
    document.getElementById('crTBalV').textContent = '100.000';
    hideCursor();
  }

  function setMult(v, hue) {
    const h = hue !== undefined ? hue : Math.max(0, 120 - (v - 1) * 30);
    const col = `hsl(${h},100%,65%)`;
    const txt = v.toFixed(2) + 'x';
    const m = document.getElementById('crTMult'), g = document.getElementById('crTMGlow');
    m.textContent = txt; m.style.color = col; m.style.textShadow = `0 0 30px hsl(${h},100%,50%)`;
    g.textContent = txt; g.style.color = `hsl(${h},100%,50%)`;
  }

  function drawGraph() {
    const cvs = document.getElementById('crTCvs');
    const game = document.getElementById('crTGame');
    cvs.width = game.offsetWidth; cvs.height = game.offsetHeight;
    const ctx = cvs.getContext('2d');
    ctx.clearRect(0, 0, cvs.width, cvs.height);
    if (gpts.length < 2) return;
    const W = cvs.width, H = cvs.height, maxM = Math.max(mult, 2);
    ctx.beginPath();
    gpts.forEach((p, i) => {
      const px = (p.t / gpts[gpts.length - 1].t) * (W * .82) + W * .05;
      const py = H - (((p.m - 1) / (maxM - 1)) * (H * .72) + H * .08);
      i === 0 ? ctx.moveTo(px, py) : ctx.lineTo(px, py);
    });
    const h = Math.max(0, 120 - (mult - 1) * 30);
    ctx.strokeStyle = `hsl(${h},100%,60%)`; ctx.lineWidth = 3;
    ctx.shadowBlur = 14; ctx.shadowColor = `hsl(${h},100%,60%)`; ctx.stroke(); ctx.shadowBlur = 0;
    const lx = (gpts[gpts.length - 1].t / gpts[gpts.length - 1].t) * (W * .82) + W * .05;
    ctx.lineTo(lx, H); ctx.lineTo(W * .05, H); ctx.closePath();
    ctx.fillStyle = `hsla(${h},100%,50%,.07)`; ctx.fill();
  }

  function stopSim() { running = false; if (raf) { cancelAnimationFrame(raf); raf = null; } if (window.gsap) gsap.killTweensOf('#crTRocket'); }

  function launchRocket(dur) {
    const rk = document.getElementById('crTRocket');
    rk.style.display = 'block';
    if (window.gsap) {
      gsap.set('#crTRocket', { x: 0, y: 0, rotation: -10, scale: 1, opacity: 1 });
      gsap.to('#crTRocket', { x: 280, y: -340, rotation: -45, duration: dur || 10, ease: 'power1.in' });
      gsap.to('#crTRocket', { y: '+=4', repeat: -1, yoyo: true, duration: .1, ease: 'none' });
    }
  }

  function showCursor(targetEl, cb) {
    const cur_el = document.getElementById('crTCursor');
    if (!targetEl || !cur_el) { if (cb) cb(); return; }
    const game = document.getElementById('crTGame');
    const gameRect = game.getBoundingClientRect();
    const rect = targetEl.getBoundingClientRect();
    const tx = rect.left + rect.width / 2 - gameRect.left;
    const ty = rect.top + rect.height / 2 - gameRect.top;
    cur_el.style.display = 'block';
    cur_el.style.left = '20px'; cur_el.style.top = '20px';
    if (window.gsap) {
      gsap.to(cur_el, { left: tx, top: ty, duration: .6, ease: 'power2.out', onComplete: cb });
    } else { if (cb) cb(); }
  }
  function hideCursor() {
    const c = document.getElementById('crTCursor'); if (c) c.style.display = 'none';
  }

  function simFly(stopAt, onDone) {
    mult = 1.0; gpts = []; running = true;
    const startT = Date.now();
    function tick() {
      if (!running) return;
      mult *= 1.007;
      gpts.push({ t: Date.now() - startT, m: mult });
      setMult(mult);
      drawGraph();
      document.getElementById('crTWinV').textContent = Math.round(10000 * mult).toLocaleString('vi-VN');
      if (mult >= stopAt) { running = false; if (onDone) onDone(); return; }
      raf = requestAnimationFrame(tick);
    }
    raf = requestAnimationFrame(tick);
  }

  /* ---- Step functions ---- */
  function step_bet() {
    tl = gsap.timeline();
    hl('crtfl-bet');
    // animate cursor onto bet field then type number
    const obj = { v: 0 };
    tl.to(obj, {
      v: 10000, duration: 1.4, ease: 'power2.out',
      onUpdate: () => { document.getElementById('crTBetV').textContent = Math.round(obj.v).toLocaleString('vi-VN'); }
    }, 0.3);
    tl.call(() => hl('crtfl-win'), null, 1.2);
  }

  function step_auto() {
    hl('crtfl-bet');
    tl = gsap.timeline();
    tl.call(() => hl('crtfl-auto'), null, 0);
    const obj = { v: 1 };
    tl.to(obj, {
      v: 2, duration: 1.1, ease: 'power1.out',
      onUpdate: () => {
        document.getElementById('crTAutoV').textContent = obj.v.toFixed(2);
        document.getElementById('crTWinV').textContent = (10000 * obj.v).toLocaleString('vi-VN');
      }
    }, 0.3);
  }

  function step_launch() {
    hl('crtfl-bet'); hl('crtfl-auto');
    tl = gsap.timeline();
    tl.call(() => {
      const sb = document.getElementById('crTSBtn');
      sb.classList.add('hlr');
    }, null, 0);
    // pulse then "click"
    tl.to('#crTSBtn', { scale: .92, duration: .12, yoyo: true, repeat: 3, ease: 'power2.inOut' }, 0.5);
    tl.call(() => {
      document.getElementById('crTSBtn').style.display = 'none';
      document.getElementById('crTCBtn').style.display = 'block';
      launchRocket();
    }, null, 1.3);
  }

  function step_fly() {
    document.getElementById('crTSBtn').style.display = 'none';
    document.getElementById('crTCBtn').style.display = 'block';
    launchRocket();
    simFly(3.0, () => { /* just keep showing */ });
  }

  function step_cashout() {
    document.getElementById('crTSBtn').style.display = 'none';
    const cb = document.getElementById('crTCBtn');
    cb.style.display = 'block';
    launchRocket(12);
    simFly(2.00, () => {
      // cashout moment
      setMult(2.00, 120);
      cb.classList.add('hlg');
      hl('crtfl-win', 'hlg'); hl('crtfl-bet', 'hlg');
      document.getElementById('crTWinV').textContent = '20.000';
      document.getElementById('crTBalV').textContent = '110.000';
      if (window.gsap) {
        gsap.killTweensOf('#crTRocket');
        gsap.to('#crTRocket', { y: '-=180', opacity: 0, duration: .5, ease: 'power2.in' });
        gsap.to('#crTutWin', { scale: 1.015, yoyo: true, repeat: 3, duration: .18, ease: 'none' });
      }
    });
  }

  function step_crash() {
    document.getElementById('crTSBtn').style.display = 'none';
    document.getElementById('crTCBtn').style.display = 'block';
    launchRocket(12);
    simFly(1.52, () => {
      setMult(1.52, 0);
      document.getElementById('crTMult').textContent = '💥 1.52x';
      document.getElementById('crTMGlow').textContent = '💥 1.52x';
      document.getElementById('crTBetV').style.color = '#ff4757';
      if (window.gsap) {
        gsap.killTweensOf('#crTRocket');
        gsap.to('#crTRocket', { scale: 3.5, opacity: 0, duration: .35, ease: 'expo.out' });
        gsap.to('#crTutWin', { x: -6, yoyo: true, repeat: 7, duration: .06, ease: 'none', clearProps: 'x' });
      }
      if (window.GameEffects) {
        const rk = document.getElementById('crTRocket');
        const rect = rk.getBoundingClientRect();
        GameEffects.crashExplosion(rect.left + rect.width / 2, rect.top + rect.height / 2);
      }
    });
  }

  /* ---- Keyboard ---- */
  document.addEventListener('keydown', e => { if (e.key === 'Escape') crTutClose(); });

})();

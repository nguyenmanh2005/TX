/**
 * Red Envelope Rain Animation & Manager (4B) — v2
 * [NEW FILE] - Hiệu ứng Mưa Lì Xì + Khay Lì Xì Đang Chờ (Persistent Tray)
 * Fix: Bao chưa giật hết sẽ hiển thị trong khay 30 phút, không mất sau khi mưa tắt
 */

const RedEnvelopeHub = (function() {
    let activeEnvelopes = [];
    let knownEnvelopeIds = new Set(); // Chỉ track để không trigger mưa 2 lần
    let trayVisible = false;

    // ───────────────────────────────────────────
    // RAIN CONTAINER
    // ───────────────────────────────────────────
    function createRainContainer() {
        if (document.getElementById('gtlm-red-rain-box')) return;
        const box = document.createElement('div');
        box.id = 'gtlm-red-rain-box';
        box.className = 'red-rain-container';
        document.body.appendChild(box);
    }

    // ───────────────────────────────────────────
    // CLAIM MODAL (popup giật 1 bao)
    // ───────────────────────────────────────────
    function createClaimModal() {
        if (document.getElementById('gtlm-claim-modal')) return;
        const modal = document.createElement('div');
        modal.id = 'gtlm-claim-modal';
        modal.className = 'red-claim-modal-overlay';
        modal.innerHTML = `
            <div class="red-claim-modal">
                <div class="modal-header">
                    <img id="claim-avatar" src="img/avatar_default.png" alt="Sender">
                    <h3 id="claim-sender">Người Phát Lộc</h3>
                </div>
                <div class="modal-body" id="claim-body">
                    <p id="claim-msg">"Phát lộc rực rỡ, chúc đạo hữu húp đậm GTLM!"</p>
                    <div class="claim-action" id="claim-btn-box">
                        <button class="btn-grab" id="btn-grab-envelope">🧧 GIẬT LỘC NGAY</button>
                    </div>
                </div>
                <button class="btn-close-modal" onclick="RedEnvelopeHub.closeModal()">✖</button>
            </div>
        `;
        document.body.appendChild(modal);
    }

    function closeModal() {
        const modal = document.getElementById('gtlm-claim-modal');
        if (modal) modal.classList.remove('show');
    }

    function openModal(env) {
        createClaimModal();
        const modal = document.getElementById('gtlm-claim-modal');
        document.getElementById('claim-avatar').src = env.sender_avatar || 'img/avatar_default.png';
        document.getElementById('claim-sender').textContent = env.sender_name || 'Đạo Hữu Phát Lộc';
        document.getElementById('claim-msg').textContent = `"${env.message || 'Chúc đạo hữu húp đậm GTLM!'}"`;

        const btnBox = document.getElementById('claim-btn-box');
        btnBox.innerHTML = `<button class="btn-grab" onclick="RedEnvelopeHub.claim(${env.id})">🧧 GIẬT LỘC NGAY (còn ${env.remaining_count}/${env.total_count} bao)</button>`;
        modal.classList.add('show');
        if (typeof SoundFXHub !== 'undefined') SoundFXHub.playPop();
    }

    // ───────────────────────────────────────────
    // PERSISTENT TRAY — Khay Lì Xì Đang Chờ
    // Hiển thị tất cả bao còn lại, tồn tại suốt 30 phút
    // ───────────────────────────────────────────
    function createTray() {
        if (document.getElementById('gtlm-envelope-tray')) return;
        const tray = document.createElement('div');
        tray.id = 'gtlm-envelope-tray';
        tray.innerHTML = `
            <div id="gtlm-tray-header" onclick="RedEnvelopeHub.toggleTray()" title="Ấn để mở/đóng khay">
                <span id="gtlm-tray-icon">🧧</span>
                <span id="gtlm-tray-label">Lì Xì Đang Chờ</span>
                <span id="gtlm-tray-badge">0</span>
                <span id="gtlm-tray-chevron">▲</span>
            </div>
            <div id="gtlm-tray-body"></div>
        `;
        document.body.appendChild(tray);
        // Thêm CSS inline để không phụ thuộc vào file ngoài
        if (!document.getElementById('gtlm-tray-style')) {
            const style = document.createElement('style');
            style.id = 'gtlm-tray-style';
            style.textContent = `
                #gtlm-envelope-tray {
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    width: 320px;
                    background: linear-gradient(145deg, #7f1d1d, #991b1b, #7f1d1d);
                    border: 2px solid #fbbf24;
                    border-radius: 18px;
                    box-shadow: 0 10px 35px rgba(0,0,0,0.7), 0 0 20px rgba(251,191,36,0.4);
                    z-index: 99980;
                    overflow: hidden;
                    font-family: 'Outfit', 'Segoe UI', sans-serif;
                    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
                    display: none;
                }
                #gtlm-tray-header {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    padding: 12px 16px;
                    cursor: pointer;
                    background: rgba(0,0,0,0.3);
                    user-select: none;
                    transition: background 0.2s;
                }
                #gtlm-tray-header:hover { background: rgba(0,0,0,0.5); }
                #gtlm-tray-icon { font-size: 22px; animation: trayBounce 1.5s ease-in-out infinite; }
                @keyframes trayBounce {
                    0%,100% { transform: rotate(-10deg); }
                    50% { transform: rotate(10deg); }
                }
                #gtlm-tray-label { color: #fef08a; font-weight: 800; font-size: 15px; flex: 1; }
                #gtlm-tray-badge {
                    background: #fbbf24; color: #451a03;
                    font-weight: 900; font-size: 13px;
                    padding: 2px 8px; border-radius: 20px;
                    min-width: 24px; text-align: center;
                }
                #gtlm-tray-chevron { color: #fbbf24; font-size: 12px; transition: transform 0.3s; }
                #gtlm-envelope-tray.collapsed #gtlm-tray-chevron { transform: rotate(180deg); }
                #gtlm-tray-body {
                    max-height: 340px;
                    overflow-y: auto;
                    padding: 0;
                    transition: max-height 0.35s ease;
                }
                #gtlm-envelope-tray.collapsed #gtlm-tray-body {
                    max-height: 0;
                }
                #gtlm-tray-body::-webkit-scrollbar { width: 4px; }
                #gtlm-tray-body::-webkit-scrollbar-thumb { background: #fbbf24; border-radius: 4px; }
                .tray-env-item {
                    padding: 12px 16px;
                    border-bottom: 1px solid rgba(255,255,255,0.1);
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    transition: background 0.2s;
                }
                .tray-env-item:last-child { border-bottom: none; }
                .tray-env-item:hover { background: rgba(255,255,255,0.08); }
                .tray-env-avatar {
                    width: 40px; height: 40px;
                    border-radius: 50%; border: 2px solid #fbbf24;
                    object-fit: cover; flex-shrink: 0;
                }
                .tray-env-info { flex: 1; min-width: 0; }
                .tray-env-sender { color: #fef08a; font-weight: 700; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
                .tray-env-msg { color: #fca5a5; font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px; }
                .tray-env-count { color: #86efac; font-size: 11px; font-weight: 700; margin-top: 3px; }
                .tray-env-timer { color: #94a3b8; font-size: 11px; margin-top: 2px; }
                .btn-tray-grab {
                    background: linear-gradient(135deg, #f59e0b, #d97706);
                    color: #451a03; font-weight: 800; font-size: 13px;
                    border: none; border-radius: 10px;
                    padding: 8px 12px; cursor: pointer;
                    white-space: nowrap; flex-shrink: 0;
                    transition: all 0.2s ease;
                    box-shadow: 0 3px 10px rgba(245,158,11,0.5);
                }
                .btn-tray-grab:hover {
                    transform: scale(1.05);
                    box-shadow: 0 5px 15px rgba(251,191,36,0.7);
                }
                .tray-empty {
                    text-align: center;
                    padding: 20px;
                    color: #fca5a5;
                    font-size: 13px;
                    font-style: italic;
                }
                #gtlm-tray-footer {
                    text-align: center;
                    padding: 8px;
                    font-size: 11px;
                    color: #94a3b8;
                    background: rgba(0,0,0,0.3);
                    border-top: 1px solid rgba(255,255,255,0.1);
                }
            `;
            document.head.appendChild(style);
        }
    }

    function toggleTray() {
        const tray = document.getElementById('gtlm-envelope-tray');
        if (!tray) return;
        tray.classList.toggle('collapsed');
    }

    function getTimeRemaining(createdAt) {
        // createdAt từ server là string dạng "2026-07-19 13:00:00"
        const created = new Date(createdAt.replace(' ', 'T'));
        const expires = new Date(created.getTime() + 30 * 60 * 1000); // +30 phút
        const now = new Date();
        const diffMs = expires - now;
        if (diffMs <= 0) return 'Hết hạn';
        const diffMin = Math.floor(diffMs / 60000);
        const diffSec = Math.floor((diffMs % 60000) / 1000);
        return `⏳ Còn ${diffMin}p${diffSec}s`;
    }

    function updateTray(envelopes) {
        const tray = document.getElementById('gtlm-envelope-tray');
        if (!tray) return;

        const activeList = envelopes.filter(e => e.remaining_count > 0);

        // Cập nhật badge
        const badge = document.getElementById('gtlm-tray-badge');
        if (badge) badge.textContent = activeList.length;

        // Ẩn/hiện tray
        tray.style.display = activeList.length > 0 ? 'block' : 'none';

        // Render danh sách
        const body = document.getElementById('gtlm-tray-body');
        if (!body) return;

        if (activeList.length === 0) {
            body.innerHTML = `<div class="tray-empty">✨ Không có lì xì nào đang chờ</div>`;
        } else {
            body.innerHTML = activeList.map(env => `
                <div class="tray-env-item">
                    <img class="tray-env-avatar" src="${env.sender_avatar || 'img/avatar_default.png'}" onerror="this.src='images.ico'" alt="">
                    <div class="tray-env-info">
                        <div class="tray-env-sender">🧧 ${env.sender_name}</div>
                        <div class="tray-env-msg">${env.message || 'Phát lộc rực rỡ!'}</div>
                        <div class="tray-env-count">Còn <b>${env.remaining_count}</b>/${env.total_count} bao · ${new Intl.NumberFormat().format(Math.round(env.remaining_amount))} GTLM</div>
                        <div class="tray-env-timer" id="tray-timer-${env.id}">${getTimeRemaining(env.created_at)}</div>
                    </div>
                    <button class="btn-tray-grab" onclick="RedEnvelopeHub.open(${JSON.stringify(env).replace(/"/g, '&quot;')})">
                        Giật!
                    </button>
                </div>
            `).join('');

            // Footer hint
            if (!document.getElementById('gtlm-tray-footer')) {
                const footer = document.createElement('div');
                footer.id = 'gtlm-tray-footer';
                footer.textContent = 'Bao chưa giật hết sẽ còn đây 30 phút';
                tray.appendChild(footer);
            }
        }
    }

    // Cập nhật countdown timer mỗi giây
    function startTimerUpdater() {
        setInterval(() => {
            activeEnvelopes.forEach(env => {
                const el = document.getElementById(`tray-timer-${env.id}`);
                if (el) el.textContent = getTimeRemaining(env.created_at);
            });
        }, 1000);
    }

    // ───────────────────────────────────────────
    // MƯARƠI ANIMATION (chỉ trigger lần đầu mỗi bao)
    // ───────────────────────────────────────────
    function startRainAnimation(env) {
        createRainContainer();
        const box = document.getElementById('gtlm-red-rain-box');
        if (!box) return;

        if (typeof SoundFXHub !== 'undefined') SoundFXHub.playLuckySpin();

        for (let i = 0; i < 18; i++) {
            setTimeout(() => {
                const item = document.createElement('div');
                item.className = 'falling-envelope';
                item.style.left = Math.floor(Math.random() * 88) + 5 + '%';
                item.style.animationDuration = (Math.random() * 2 + 2.5) + 's';
                item.innerHTML = '🧧';
                item.title = `Bấm để giật lì xì từ ${env.sender_name}! (Hoặc dùng khay góc phải nếu lỡ tay)`;

                item.addEventListener('click', () => {
                    item.remove();
                    openModal(env);
                });

                box.appendChild(item);
                setTimeout(() => { if (item && item.parentNode) item.remove(); }, 5000);
            }, i * 200);
        }

        // Sau khi mưa tắt — nhắc người dùng dùng khay
        setTimeout(() => {
            const stillActive = activeEnvelopes.find(e => e.id === env.id && e.remaining_count > 0);
            if (stillActive && typeof Swal !== 'undefined') {
                Swal.fire({
                    title: '🧧 Còn lì xì đang chờ!',
                    html: `Bao lì xì của <b>${env.sender_name}</b> vẫn còn <b>${stillActive.remaining_count} bao</b> chưa được giật.<br><small style="color:#94a3b8;">Xem khay 🧧 ở góc dưới phải màn hình!</small>`,
                    icon: 'info',
                    toast: true,
                    position: 'bottom-end',
                    timer: 6000,
                    showConfirmButton: false,
                    background: '#1e293b',
                    color: '#f8fafc'
                });
            }
        }, 6000);
    }

    // ───────────────────────────────────────────
    // CLAIM — Giật lì xì
    // ───────────────────────────────────────────
    async function claimEnvelope(envId) {
        const btnBox = document.getElementById('claim-btn-box');
        if (btnBox) btnBox.innerHTML = `<span class="loading-grab">⏳ Đang mở bao lộc...</span>`;

        try {
            const formData = new FormData();
            formData.append('envelope_id', envId);
            const res = await fetch('api_red_envelope.php?action=claim', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                if (typeof SoundFXHub !== 'undefined') SoundFXHub.playLotteryWin();
                if (btnBox) btnBox.innerHTML = `
                    <div class="grab-success">
                        <h4>🎉 HÚP LỘC RỰC RỠ! 🎉</h4>
                        <div class="grab-amount">+${new Intl.NumberFormat().format(data.amount)} <small>GTLM</small></div>
                        <p>${data.message}</p>
                    </div>
                `;
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: '🧧 GIẬT LỘC THÀNH CÔNG!',
                        text: `Bạn vừa húp thêm +${new Intl.NumberFormat().format(data.amount)} GTLM vào nick!`,
                        icon: 'success',
                        toast: true,
                        position: 'top-end',
                        timer: 4000,
                        showConfirmButton: false,
                        background: '#1e293b',
                        color: '#f8fafc'
                    });
                }
                // Refresh tray ngay sau khi giật
                setTimeout(pollActiveEnvelopes, 800);
            } else {
                if (typeof SoundFXHub !== 'undefined') SoundFXHub.playPop();
                if (btnBox) btnBox.innerHTML = `
                    <div class="grab-error">
                        <p>⚠️ ${data.message}</p>
                        <button class="btn-retry" onclick="RedEnvelopeHub.closeModal()">Đóng</button>
                    </div>
                `;
                // Cũng refresh tray để cập nhật số bao còn lại
                setTimeout(pollActiveEnvelopes, 800);
            }
        } catch (e) {
            if (btnBox) btnBox.innerHTML = `<p style="color:#ef4444">Lỗi kết nối: ${e.message}</p>`;
        }
    }

    // ───────────────────────────────────────────
    // POLLING — Rà soát mỗi 10 giây
    // ───────────────────────────────────────────
    function pollActiveEnvelopes() {
        fetch('api_red_envelope.php?action=list')
            .then(res => res.json())
            .then(data => {
                if (!data.success || !data.table_ready) return;
                activeEnvelopes = data.envelopes || [];

                // Cập nhật log bot nếu đang trên trang tester
                if (data.bot_action && typeof appendLog === 'function') {
                    appendLog(`🔥 ${data.bot_action}`);
                    if (typeof SoundFXHub !== 'undefined') SoundFXHub.playPop();
                }

                // Cập nhật tray với toàn bộ bao còn active (kể cả bao cũ chưa giật hết)
                updateTray(activeEnvelopes);

                // Trigger mưa CHỈ cho bao mới xuất hiện lần đầu
                activeEnvelopes.forEach(env => {
                    if (!knownEnvelopeIds.has(env.id)) {
                        knownEnvelopeIds.add(env.id);
                        startRainAnimation(env);
                    }
                });
            })
            .catch(() => {}); // Silent fail
    }

    // ───────────────────────────────────────────
    // INIT
    // ───────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        createRainContainer();
        createClaimModal();
        createTray();
        startTimerUpdater();
        pollActiveEnvelopes();
        setInterval(pollActiveEnvelopes, 10000);
    });

    return {
        open: openModal,
        closeModal: closeModal,
        claim: claimEnvelope,
        triggerRain: startRainAnimation,
        poll: pollActiveEnvelopes,
        toggleTray: toggleTray,
        updateTray: updateTray
    };
})();

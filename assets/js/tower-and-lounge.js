/**
 * Tower of Gods & Royal Virtual Lounge JS Engine
 * [NEW FILE] - Quản lý logic đấu bài Tháp Thần Bài & Phòng Trưng Bày Biệt Thự GTLM
 */

const TowerEngine = (() => {
    let currentCompanionId = 1;

    async function loadInfo() {
        try {
            const res = await fetch('api_tower_gods.php?action=info');
            const data = await res.json();
            if (!data.success) return;

            // Render tiến trình
            const prog = data.progress;
            const boss = data.current_boss;
            
            document.getElementById('towerFloorNum').textContent = prog.current_floor;
            document.getElementById('towerHighestFloor').textContent = prog.highest_floor;
            document.getElementById('towerTotalWins').textContent = prog.total_wins;
            document.getElementById('towerTotalGtlm').textContent = new Intl.NumberFormat().format(prog.total_gtlm_won) + ' GTLM';

            // Render Boss
            document.getElementById('bossName').textContent = boss.name;
            document.getElementById('bossReward').textContent = '+' + new Intl.NumberFormat().format(boss.reward) + ' GTLM';
            document.getElementById('bossAvatar').src = boss.avatar;
            if (boss.trophy) {
                document.getElementById('bossTrophyAlert').innerHTML = `🎁 <b>Thưởng Đặc Biệt Tầng Này:</b> ${boss.trophy.name}`;
                document.getElementById('bossTrophyAlert').style.display = 'block';
            } else {
                document.getElementById('bossTrophyAlert').style.display = 'none';
            }

            // Render Leaderboard
            const lbEl = document.getElementById('towerLeaderboard');
            if (lbEl && data.leaderboard) {
                lbEl.innerHTML = data.leaderboard.map((u, idx) => `
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid rgba(255,255,255,0.1);">
                        <span><b>#${idx+1} ${u.username}</b></span>
                        <span style="color:#fbbf24; font-weight:bold;">Tầng ${u.highest_floor}</span>
                    </div>
                `).join('');
            }
        } catch(e) {
            console.error('Lỗi tải Tháp Thần Bài:', e);
        }
    }

    async function selectCompanion(id) {
        currentCompanionId = id;
        document.querySelectorAll('.companion-box').forEach(el => el.classList.remove('active'));
        const activeBox = document.querySelector(`.companion-box[data-id="${id}"]`);
        if (activeBox) activeBox.classList.add('active');

        const formData = new FormData();
        formData.append('companion_id', id);
        await fetch('api_tower_gods.php?action=companion', { method: 'POST', body: formData });
        if (typeof SoundFXHub !== 'undefined') SoundFXHub.playPop();
    }

    async function battleBoss() {
        const btn = document.getElementById('btnBattle');
        if (btn) btn.disabled = true;

        // Phát âm thanh Boss gầm rú trước khi lật bài
        if (typeof SoundFXHub !== 'undefined') SoundFXHub.playBossRoar();

        // Hiệu ứng lật bài
        const pSlot = document.getElementById('playerCardSlot');
        const bSlot = document.getElementById('bossCardSlot');
        pSlot.innerHTML = '❓'; pSlot.classList.remove('flipped', 'red-suit');
        bSlot.innerHTML = '❓'; bSlot.classList.remove('flipped', 'red-suit');

        try {
            const res = await fetch('api_tower_gods.php?action=battle', { method: 'POST' });
            const data = await res.json();

            setTimeout(() => {
                const cardSymbols = {2:'2', 3:'3', 4:'4', 5:'5', 6:'6', 7:'7', 8:'8', 9:'9', 10:'10', 11:'J', 12:'Q', 13:'K', 14:'A'};
                const pVal = cardSymbols[data.player_card] || data.player_card;
                const bVal = cardSymbols[data.boss_card] || data.boss_card;

                pSlot.innerHTML = pVal + '<small style="font-size:14px; display:block;">♠️</small>';
                pSlot.classList.add('flipped');
                bSlot.innerHTML = bVal + '<small style="font-size:14px; display:block;">♦️</small>';
                bSlot.classList.add('flipped', 'red-suit');

                if (data.is_win) {
                    if (typeof SoundFXHub !== 'undefined') SoundFXHub.playJackpot();
                    Swal.fire({
                        title: '👑 VƯỢT ẢI THÀNH CÔNG!',
                        text: data.message + (data.trophy_awarded ? `\n\n🎁 Bạn vừa nhận báu vật: ${data.trophy_awarded} (Đã thêm vào Biệt Thự!)` : ''),
                        icon: 'success',
                        confirmButtonText: 'Chiến Tầng Tiếp Theo 🚀',
                        confirmButtonColor: '#f59e0b'
                    }).then(() => loadInfo());
                } else {
                    if (typeof SoundFXHub !== 'undefined' && SoundFXHub.playPvpHorn) SoundFXHub.playPvpHorn();
                    Swal.fire({
                        title: '💥 KHIÊU CHIẾN THẤT BẠI!',
                        text: data.message,
                        icon: 'error',
                        confirmButtonText: 'Thử Lại Trận Địa ⚔️',
                        confirmButtonColor: '#ef4444'
                    }).then(() => loadInfo());
                }

                if (btn) btn.disabled = false;
            }, 1000);
        } catch(e) {
            console.error('Lỗi khiêu chiến:', e);
            if (btn) btn.disabled = false;
        }
    }

    return { loadInfo, selectCompanion, battleBoss };
})();

const LoungeEngine = (() => {
    let targetUserId = null;

    async function loadLounge(userId = null) {
        if (!userId) {
            const urlParams = new URLSearchParams(window.location.search);
            const uId = urlParams.get('user_id');
            if (uId) userId = parseInt(uId);
        }
        if (userId) targetUserId = userId;
        const url = targetUserId ? `api_lounge.php?action=view&target_id=${targetUserId}` : `api_lounge.php?action=view`;
        try {
            const res = await fetch(url);
            const data = await res.json();
            if (!data.success) return;

            const room = data.room;
            document.getElementById('loungeTitle').textContent = room.room_name;
            document.getElementById('loungeOwnerAvatar').src = room.avatar;
            document.getElementById('loungeLikes').textContent = room.likes_count;
            document.getElementById('loungeVisits').textContent = room.visits_count;

            // Render vật phẩm đang trưng bày (lounge-room-view grid)
            const roomEl = document.getElementById('loungeRoomGrid');
            if (roomEl) {
                let gridHtml = '';
                const items = data.placed_items || [];
                // Bố trí 12 ô grid trong phòng biệt thự
                for (let i = 0; i < 12; i++) {
                    const item = items[i];
                    if (item) {
                        gridHtml += `
                            <div class="lounge-item-slot">
                                <div class="lounge-item-icon">${item.icon_url}</div>
                                <div class="lounge-item-label">${item.item_name}</div>
                                ${data.is_owner ? `<button class="btn-royal" style="padding:4px 8px; font-size:11px; margin-top:6px;" onclick="LoungeEngine.placeItem(${item.id}, 0)">Cất vào kho</button>` : ''}
                            </div>
                        `;
                    } else {
                        gridHtml += `
                            <div class="lounge-item-slot" style="opacity:0.3;">
                                <div style="font-size:24px;">➕</div>
                                <div style="font-size:12px; margin-top:4px;">Ô Trống</div>
                            </div>
                        `;
                    }
                }
                roomEl.innerHTML = gridHtml;
            }

            // Render Kho Vật Phẩm của chủ phòng (chỉ hiện khi là owner)
            const invContainer = document.getElementById('loungeInventorySection');
            if (invContainer) {
                if (data.is_owner) {
                    invContainer.style.display = 'block';
                    const invList = document.getElementById('loungeInventoryList');
                    const inventory = data.inventory || [];
                    if (inventory.length === 0) {
                        invList.innerHTML = `<p style="color:#94a3b8; font-style:italic;">Kho đồ trống. Hãy mua thêm nội thất bên Cửa Hàng hoặc săn cúp tại Tháp Thần Bài!</p>`;
                    } else {
                        invList.innerHTML = inventory.map(item => `
                            <div style="display:inline-block; background:rgba(30,41,59,0.8); border:1px solid #475569; border-radius:12px; padding:12px; margin:6px; text-align:center; width:140px;">
                                <div style="font-size:36px;">${item.icon_url}</div>
                                <div style="font-size:13px; font-weight:700; margin:6px 0;">${item.item_name}</div>
                                <button class="btn-royal" style="padding:6px 12px; font-size:12px; width:100%;" onclick="LoungeEngine.placeItem(${item.id}, 1)">✨ Trưng bày</button>
                            </div>
                        `).join('');
                    }
                } else {
                    invContainer.style.display = 'none';
                }
            }

            // Render Cửa Hàng (Catalog)
            const shopEl = document.getElementById('loungeShopList');
            if (shopEl && data.catalog) {
                shopEl.innerHTML = data.catalog.map(c => `
                    <div style="background:rgba(30,41,59,0.6); border:1px solid rgba(255,255,255,0.15); border-radius:14px; padding:14px; display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <span style="font-size:36px;">${c.icon}</span>
                            <div>
                                <b style="color:#f8fafc; font-size:15px;">${c.name}</b><br>
                                <span style="color:#fbbf24; font-weight:800;">${new Intl.NumberFormat().format(c.price)} GTLM</span>
                            </div>
                        </div>
                        <button class="btn-royal" style="padding:8px 16px; font-size:13px;" onclick="LoungeEngine.buyItem('${c.code}')">🛒 Mua Ngay</button>
                    </div>
                `).join('');
            }

            // Render Sổ Lưu Niệm
            const gbEl = document.getElementById('loungeGuestbookList');
            if (gbEl && data.guestbook) {
                gbEl.innerHTML = data.guestbook.map(gb => `
                    <div class="guestbook-item">
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                            <img src="${gb.visitor_avatar}" style="width:24px; height:24px; border-radius:50%;">
                            <b style="color:#c084fc;">${gb.visitor_name}</b>
                            <small style="color:#64748b; margin-left:auto;">${gb.created_at}</small>
                        </div>
                        <div style="color:#f8fafc;">${gb.comment}</div>
                    </div>
                `).join('');
            }
            listNeighbors();
        } catch(e) {
            console.error('Lỗi tải Phòng Biệt Thự:', e);
        }
    }

    async function buyItem(itemCode) {
        const formData = new FormData();
        formData.append('item_code', itemCode);
        const res = await fetch('api_lounge.php?action=buy', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            if (typeof SoundFXHub !== 'undefined') SoundFXHub.playLotteryWin();
            Swal.fire({ title: '🎉 Mua Thành Công!', text: data.message, icon: 'success' });
            loadLounge();
        } else {
            Swal.fire({ title: '⚠️ Lỗi Mua Sắm', text: data.message, icon: 'error' });
        }
    }

    async function placeItem(itemId, isPlaced) {
        const formData = new FormData();
        formData.append('item_id', itemId);
        formData.append('is_placed', isPlaced);
        const res = await fetch('api_lounge.php?action=place', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            if (typeof SoundFXHub !== 'undefined') SoundFXHub.playPop();
            loadLounge();
        }
    }

    async function signGuestbook() {
        const input = document.getElementById('gbInput');
        if (!input.value.trim()) return;

        const formData = new FormData();
        if (targetUserId) formData.append('owner_id', targetUserId);
        formData.append('comment', input.value.trim());

        const res = await fetch('api_lounge.php?action=guestbook', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            if (typeof SoundFXHub !== 'undefined') SoundFXHub.playPop();
            input.value = '';
            loadLounge();
        } else {
            Swal.fire({ title: '⚠️ Lỗi Sổ Lưu Niệm', text: data.message, icon: 'error' });
        }
    }

    async function likeRoom() {
        const formData = new FormData();
        if (targetUserId) formData.append('target_id', targetUserId);
        const res = await fetch('api_lounge.php?action=like', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            if (typeof SoundFXHub !== 'undefined') SoundFXHub.playLotteryWin();
            Swal.fire({ title: '❤️ Đã Thả Tim!', text: data.message, icon: 'success', toast: true, position: 'top-end', timer: 2500, showConfirmButton: false });
            loadLounge();
        }
    }

    async function listNeighbors() {
        const modalEl = document.getElementById('neighborListContainer');
        if (!modalEl) return;
        try {
            const res = await fetch('api_lounge.php?action=list_rooms');
            const data = await res.json();
            if (!data.success || !data.rooms) return;

            modalEl.innerHTML = data.rooms.map((r, idx) => `
                <div style="background:rgba(30,41,59,0.75); border:1px solid rgba(255,255,255,0.15); border-radius:14px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; transition:all 0.3s;" onmouseover="this.style.borderColor='#f59e0b'" onmouseout="this.style.borderColor='rgba(255,255,255,0.15)'">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <img src="${r.avatar}" style="width:42px; height:42px; border-radius:50%; border:2px solid #fbbf24; object-fit:cover;">
                        <div>
                            <b style="color:#f8fafc; font-size:15px;">${r.room_name}</b><br>
                            <span style="color:#fbbf24; font-size:12px; font-weight:700;">👤 ${r.username} #${r.user_id}</span>
                            <span style="color:#ef4444; font-size:12px; margin-left:10px;">❤️ ${r.likes_count}</span>
                            <span style="color:#38bdf8; font-size:12px; margin-left:8px;">👁️ ${r.visits_count}</span>
                        </div>
                    </div>
                    <button class="btn-royal" style="padding:8px 16px; font-size:13px;" onclick="LoungeEngine.visitNeighbor(${r.user_id})">🚀 Ghé Thăm</button>
                </div>
            `).join('');
        } catch(e) {
            console.error('Lỗi tải danh sách hàng xóm:', e);
        }
    }

    function visitNeighbor(userId) {
        window.location.href = `my_lounge.php?user_id=${userId}`;
    }

    return { loadLounge, buyItem, placeItem, signGuestbook, likeRoom, listNeighbors, visitNeighbor };
})();

class BlackjackMulti {
    constructor() {
        this.tableId = null;
        this.userId = window.currentUserId;
        this.pollInterval = null;
        this.status = 'waiting';

        this.init();
    }

    init() {
        this.startPolling();
        this.bindEvents();
    }

    bindEvents() {
        document.getElementById('btn-hit').onclick = () => this.action('hit');
        document.getElementById('btn-stand').onclick = () => this.action('stand');
        document.getElementById('btn-bet').onclick = () => this.action('bet');
        document.getElementById('btn-double').onclick = () => this.action('double_down');

        document.getElementById('chat-input').onkeypress = (e) => {
            if (e.key === 'Enter') this.sendChat(e.target.value);
        };
    }

    startPolling() {
        this.fetchState();
        this.pollInterval = setInterval(() => this.fetchState(), 2000);
    }

    async fetchState() {
        try {
            const response = await fetch(`../api_blackjack_multi.php?action=get_state&table_id=${window.tableId}`);
            const data = await response.json();
            if (data.success) {
                this.updateUI(data);
            } else {
                if (data.message === 'Phòng không tồn tại') {
                    clearInterval(this.pollInterval);
                    if (typeof Swal !== 'undefined') Swal.fire('Lỗi', 'Phòng không tồn tại hoặc đã bị xóa!', 'error').then(() => window.location.href = 'blackjack_multi.php');
                    else window.location.href = 'blackjack_multi.php';
                } else if (data.message === 'Thiếu table_id') {
                    clearInterval(this.pollInterval);
                    if (typeof Swal !== 'undefined') Swal.fire('Lỗi', 'Thiếu table_id, vui lòng quay lại sảnh.', 'error').then(() => window.location.href = 'blackjack_multi.php');
                    else window.location.href = 'blackjack_multi.php';
                }
            }
        } catch (e) {
            console.error(e);
        }
    }

    updateUI(data) {
        const { table, players, chat } = data;
        this.tableId = table.id;
        this.status = table.status;

        // Render Dealer
        this.renderCards('dealer-cards', JSON.parse(table.dealer_cards || '[]'));

        window.currentMoney = data.current_user_money;
        const balEl = document.getElementById('balance-amount');
        if (balEl) balEl.innerText = Number(window.currentMoney).toLocaleString();

        const myPlayer = players.find(p => p.user_id == this.userId);

        // Render Players
        for (let i = 0; i < 5; i++) {
            const player = players.find(p => p.seat_index == i);
            const seatEl = document.getElementById(`seat-${i}`);

            if (player) {
                if (seatEl.dataset.userId != player.user_id) {
                    seatEl.style.animation = 'none';
                    seatEl.offsetHeight; // Trigger reflow
                    seatEl.style.animation = 'popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
                    seatEl.dataset.userId = player.user_id;
                }

                seatEl.querySelector('.player-name').innerText = player.Name;

                const betEl = seatEl.querySelector('.player-bet');
                if (betEl) {
                    if (player.bet_amount > 0) {
                        betEl.style.display = 'block';
                        betEl.innerText = Number(player.bet_amount).toLocaleString() + ' GTLM';
                    } else {
                        betEl.style.display = 'none';
                    }
                }

                seatEl.querySelector('.player-avatar').classList.toggle('active-turn', table.current_turn_user_id == player.user_id);
                this.renderCards(`player-cards-${i}`, JSON.parse(player.cards || '[]'));

                // Hiển thị trạng thái
                const statusBadge = seatEl.querySelector('.status-badge');

                let displayStatus = player.status.toUpperCase();
                statusBadge.style.background = '#fbbf24'; // Default yellow
                statusBadge.style.color = '#000';

                if (player.status.startsWith('win:')) {
                    const amt = player.status.split(':')[1];
                    displayStatus = 'THẮNG +' + Number(amt).toLocaleString();
                    statusBadge.style.background = '#10b981'; // Green
                    statusBadge.style.color = '#fff';
                } else if (player.status.startsWith('lose:')) {
                    const amt = player.status.split(':')[1];
                    displayStatus = 'THUA -' + Number(amt).toLocaleString();
                    statusBadge.style.background = '#ef4444'; // Red
                    statusBadge.style.color = '#fff';
                } else if (player.status.startsWith('bust:')) {
                    const amt = player.status.split(':')[1];
                    displayStatus = 'QUẮC -' + Number(amt).toLocaleString();
                    statusBadge.style.background = '#ef4444';
                    statusBadge.style.color = '#fff';
                } else if (player.status.startsWith('draw:')) {
                    displayStatus = 'HÒA';
                    statusBadge.style.background = '#6b7280'; // Gray
                    statusBadge.style.color = '#fff';
                }

                statusBadge.innerText = displayStatus;
                statusBadge.style.display = 'block';
            } else {
                seatEl.dataset.userId = '';
                seatEl.querySelector('.player-name').innerText = "TRỐNG";
                if (!myPlayer && this.status === 'waiting') {
                    seatEl.querySelector('.player-cards').innerHTML = `<button onclick="window.game.sit(${i})" style="padding: 5px 15px; font-weight:bold; cursor:pointer; background:#10b981; border:none; border-radius:5px; color:#fff; z-index: 10;">Ngồi</button>`;
                } else {
                    seatEl.querySelector('.player-cards').innerHTML = "";
                }
                seatEl.querySelector('.status-badge').style.display = 'none';
                if (seatEl.querySelector('.player-bet')) seatEl.querySelector('.player-bet').style.display = 'none';
            }
        }

        // Show/Hide Controls
        const isMyTurn = table.current_turn_user_id == this.userId && this.status === 'playing';

        const controlsDiv = document.getElementById('game-controls');
        let msgDiv = document.getElementById('game-message-overlay');
        if (!msgDiv) {
            msgDiv = document.createElement('div');
            msgDiv.id = 'game-message-overlay';
            controlsDiv.parentNode.insertBefore(msgDiv, controlsDiv);
        }

        const btnBet = document.getElementById('btn-bet');
        const betAmount = document.getElementById('bet-amount');
        const btnHit = document.getElementById('btn-hit');
        const btnStand = document.getElementById('btn-stand');
        const btnDouble = document.getElementById('btn-double');
        const chipContainer = document.getElementById('chip-container');

        // Default Hide All
        controlsDiv.style.display = 'none';
        msgDiv.style.display = 'none';
        btnBet.style.display = 'none';
        betAmount.style.display = 'none';
        if (chipContainer) chipContainer.style.display = 'none';
        btnHit.style.display = 'none';
        btnStand.style.display = 'none';
        btnDouble.style.display = 'none';

        const btnAddBot = document.getElementById('btn-add-bot');
        if (btnAddBot) {
            const occupiedSeats = players.length;
            const hasHumans = players.some(p => typeof p.is_bot !== 'undefined' ? p.is_bot == 0 : false);
            // Spectators can only add bots if the room has NO real players (bot-only room)
            btnAddBot.style.display = ((myPlayer || !hasHumans) && this.status === 'waiting' && occupiedSeats < 5) ? 'inline-block' : 'none';
        }

        if (this.status === 'waiting') {
            if (!myPlayer) {
                // Kiểm tra đủ GTLM cược min không
                if (data.current_user_money < table.min_bet) {
                    msgDiv.style.display = 'block';
                    msgDiv.innerHTML = `<div style="color:#ef4444; font-weight:bold; font-size:18px; text-align:center; padding: 20px;">Bạn cần ít nhất ${Number(table.min_bet).toLocaleString()} GTLM để tham gia phòng này!</div>`;
                } else {
                    msgDiv.style.display = 'block';
                    msgDiv.innerHTML = `<div style="color:#10b981; font-weight:bold; font-size:18px; text-align:center; padding: 20px;">👀 Đang xem với tư cách Khán giả. Bấm "Ngồi" để tham gia!</div>`;
                }
            } else if (myPlayer.status === 'sitting') {
                // Đã ngồi nhưng chưa cược
                controlsDiv.style.display = 'flex';
                btnBet.style.display = 'block';
                betAmount.style.display = 'block';
                if (chipContainer) chipContainer.style.display = 'flex';

                // Đặt giá trị mặc định cho ô cược
                if (!betAmount.hasAttribute('data-init')) {
                    betAmount.value = table.min_bet;
                    betAmount.min = table.min_bet;
                    betAmount.max = table.max_bet;
                    betAmount.setAttribute('data-init', '1');
                }

                msgDiv.style.display = 'block';
                if (table.turn_expires_at) {
                    const secs = Math.max(0, Math.floor((new Date(table.turn_expires_at).getTime() - new Date(data.current_time).getTime()) / 1000));
                    msgDiv.innerHTML = `<div style="color:#10b981; font-weight:bold; font-size:18px; text-align:center; padding: 20px;">Vui lòng cược! Bắt đầu sau: ${secs}s</div>`;
                } else {
                    msgDiv.innerHTML = `<div style="color:#10b981; font-weight:bold; font-size:18px; text-align:center; padding: 20px;">Vui lòng nhập GTLM cược và bấm Chiến (DEAL) để sẵn sàng!</div>`;
                }
            } else {
                // Đã cược, đang chờ
                msgDiv.style.display = 'block';
                if (table.turn_expires_at) {
                    const secs = Math.max(0, Math.floor((new Date(table.turn_expires_at).getTime() - new Date(data.current_time).getTime()) / 1000));
                    msgDiv.innerHTML = `<div style="color:#fbbf24; font-weight:bold; font-size:18px; text-align:center; padding: 20px;">Trận đấu bắt đầu sau: ${secs}s</div>`;
                } else {
                    msgDiv.innerHTML = `<div style="color:#fbbf24; font-weight:bold; font-size:18px; text-align:center; padding: 20px;">Đang đợi người chơi khác...</div>`;
                }
            }
        } else if (this.status === 'playing') {
            if (isMyTurn) {
                controlsDiv.style.display = 'flex';
                btnHit.style.display = 'block';
                btnStand.style.display = 'block';

                // Hiển thị nút Double Down nếu người chơi chỉ có đúng 2 lá bài và đủ GTLM
                let myCards = [];
                try { myCards = JSON.parse(myPlayer.cards); } catch (e) { }
                if (myCards.length === 2 && data.current_user_money >= myPlayer.bet_amount) {
                    btnDouble.style.display = 'block';
                }
            }
        } else if (this.status === 'finished') {
            msgDiv.style.display = 'block';
            msgDiv.innerHTML = `<div style="color:#10b981; font-weight:bold; font-size:20px; text-align:center; padding: 20px; animation: pulse 1s infinite;">🎉 Ván kết thúc! Đang chuẩn bị ván mới...</div>`;

            if (!this.hasShownResult && myPlayer) {
                this.hasShownResult = true;
                if (myPlayer.status.startsWith('win:')) {
                    const amt = myPlayer.status.split(':')[1];
                    if (typeof GameEffects !== 'undefined') GameEffects.showWin(amt);
                    else if (typeof Swal !== 'undefined') Swal.fire('Thắng!', 'Bạn thắng ' + Number(amt).toLocaleString() + ' GTLM', 'success');
                } else if (myPlayer.status.startsWith('lose:') || myPlayer.status.startsWith('bust:')) {
                    const amt = myPlayer.status.split(':')[1];
                    if (typeof GameEffects !== 'undefined') GameEffects.showLoss(amt);
                    else if (typeof Swal !== 'undefined') Swal.fire('Thua!', 'Bạn mất ' + Number(amt).toLocaleString() + ' GTLM', 'error');
                } else if (myPlayer.status.startsWith('draw:')) {
                    const amt = myPlayer.status.split(':')[1];
                    if (typeof Swal !== 'undefined') Swal.fire('Hòa!', 'Bạn được hoàn lại ' + Number(amt).toLocaleString() + ' GTLM', 'info');
                }
            }
        }

        if (this.status !== 'finished') {
            this.hasShownResult = false;
        }

        // Ẩn thanh chat nếu chỉ là khán giả
        const chatInput = document.getElementById('chat-input');
        if (chatInput) {
            chatInput.style.display = myPlayer ? 'block' : 'none';
        }

        // Render Chat
        this.renderChat(chat);
    }

    calculateScore(cards) {
        let score = 0, aces = 0;
        for (const c of cards) {
            if (['J', 'Q', 'K'].includes(c.value)) score += 10;
            else if (c.value === 'A') { score += 11; aces++; }
            else score += parseInt(c.value);
        }
        while (score > 21 && aces > 0) { score -= 10; aces--; }
        return score;
    }

    renderCards(containerId, cards) {
        const container = document.getElementById(containerId);
        if (!cards || cards.length === 0) {
            container.innerHTML = '';
            container.dataset.cardString = '';
            return;
        }
        const cardStr = JSON.stringify(cards);
        if (container.dataset.cardString === cardStr) {
            return; // No change
        }

        let oldCards = [];
        try { oldCards = JSON.parse(container.dataset.cardString || '[]'); } catch (e) { }
        const isNewRound = cards.length <= 2 || cards.length < oldCards.length;
        const animateFromIdx = isNewRound ? 0 : oldCards.length;

        container.dataset.cardString = cardStr;

        const score = this.calculateScore(cards);
        const scoreColor = score > 21 ? '#ef4444' : score === 21 ? '#fbbf24' : '#10b981';
        container.innerHTML = `
            ${cards.map((c, idx) => {
                const suitMap = {'♥': 'hearts', '♦': 'diamonds', '♣': 'clubs', '♠': 'spades'};
                const suitStr = suitMap[c.suit] || c.suit;
                let valStr = c.value;
                if (!isNaN(valStr) && parseInt(valStr) < 10) valStr = '0' + parseInt(valStr);
                const url = `../games/img/anh-bai/PNG/Cards (large)/card_${suitStr}_${valStr}.png`;
                
                return `
                <img class="card card-img" src="${url}" style="${idx >= animateFromIdx ? `animation: dealCard 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) backwards; animation-delay: ${(idx - animateFromIdx) * 0.15}s;` : ''}">
            `}).join('')}
            <div class="score-badge" style="position:absolute; bottom:-25px; left:50%; transform:translateX(-50%); color:${scoreColor}; z-index:10; white-space:nowrap;">
                ${score > 21 ? '💥 QUẮC' : score === 21 ? '⭐ 21!' : score + ' điểm'}
            </div>
        `;
    }

    renderChat(messages) {
        const container = document.getElementById('chat-messages');
        container.innerHTML = messages.map(m => `
            <div style="margin-bottom:5px;">
                <strong style="color:#fbbf24">${m.Name}:</strong> ${m.message}
            </div>
        `).join('');
    }

    async action(type) {
        const amount = document.getElementById('bet-amount').value;
        const response = await fetch(`../api_blackjack_multi.php?action=${type}&table_id=${window.tableId}`, {
            method: 'POST',
            body: new URLSearchParams({ amount: amount, table_id: window.tableId })
        });
        const data = await response.json();
        if (!data.success) {
            if (typeof Swal !== 'undefined') Swal.fire('Lỗi', data.message, 'error');
            else console.error(data.message);
        }
    }

    async sit(seatIndex) {
        const response = await fetch(`../api_blackjack_multi.php?action=sit&table_id=${window.tableId}`, {
            method: 'POST',
            body: new URLSearchParams({ seat_index: seatIndex, table_id: window.tableId })
        });
        const data = await response.json();
        if (!data.success) {
            if (typeof Swal !== 'undefined') Swal.fire('Lỗi', data.message, 'error');
            else console.error(data.message);
        }
        this.fetchState();
    }

    async addBot() {
        const response = await fetch(`../api_blackjack_multi.php?action=add_bot&table_id=${window.tableId}`);
        const data = await response.json();
        if (!data.success) {
            if (typeof Swal !== 'undefined') Swal.fire('Lỗi', data.message, 'error');
            else console.error(data.message);
        }
    }

    async sendChat(msg) {
        if (!msg.trim()) return;
        await fetch(`../api_blackjack_multi.php?action=chat&table_id=${window.tableId}`, {
            method: 'POST',
            body: new URLSearchParams({ message: msg, table_id: window.tableId })
        });
        document.getElementById('chat-input').value = "";
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.game = new BlackjackMulti();
});

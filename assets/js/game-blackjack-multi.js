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
            const response = await fetch('../api_blackjack_multi.php?action=get_state');
            const data = await response.json();
            if (data.success) {
                this.updateUI(data);
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

        // Render Players
        for (let i = 0; i < 5; i++) {
            const player = players.find(p => p.seat_index == i);
            const seatEl = document.getElementById(`seat-${i}`);
            
            if (player) {
                seatEl.querySelector('.player-name').innerText = player.Name;
                seatEl.querySelector('.player-avatar').classList.toggle('active-turn', table.current_turn_user_id == player.user_id);
                this.renderCards(`player-cards-${i}`, JSON.parse(player.cards || '[]'));
                
                // Hiển thị trạng thái
                const statusBadge = seatEl.querySelector('.status-badge');
                statusBadge.innerText = player.status.toUpperCase();
                statusBadge.style.display = 'block';
            } else {
                seatEl.querySelector('.player-name').innerText = "TRỐNG";
                seatEl.querySelector('.player-cards').innerHTML = "";
                seatEl.querySelector('.status-badge').style.display = 'none';
            }
        }

        // Show/Hide Controls
        const myPlayer = players.find(p => p.user_id == this.userId);
        const isMyTurn = table.current_turn_user_id == this.userId && this.status === 'playing';
        document.getElementById('game-controls').style.display = (isMyTurn || this.status === 'waiting') ? 'flex' : 'none';
        
        // Render Chat
        this.renderChat(chat);
    }

    renderCards(containerId, cards) {
        const container = document.getElementById(containerId);
        container.innerHTML = cards.map(c => `
            <div class="card ${['♥','♦'].includes(c.suit) ? 'red' : ''}">
                <div>${c.value}</div>
                <div style="text-align:center; font-size:1.5rem;">${c.suit}</div>
                <div style="text-align:right;">${c.value}</div>
            </div>
        `).join('');
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
        const response = await fetch('../api_blackjack_multi.php?action=' + type, {
            method: 'POST',
            body: new URLSearchParams({ amount: amount })
        });
        const data = await response.json();
        if (!data.success) alert(data.message);
    }

    async sendChat(msg) {
        if (!msg.trim()) return;
        await fetch('../api_blackjack_multi.php?action=chat', {
            method: 'POST',
            body: new URLSearchParams({ message: msg })
        });
        document.getElementById('chat-input').value = "";
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.game = new BlackjackMulti();
});

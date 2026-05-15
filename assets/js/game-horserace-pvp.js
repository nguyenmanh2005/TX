class HorseRacePvP {
    constructor() {
        this.roomId = null;
        this.status = 'waiting';
        this.horses = [0, 0, 0, 0, 0, 0];
        this.selectedHorse = null;
        this.betAmount = 10000;
        this.pollInterval = null;

        this.init();
    }

    init() {
        this.startPolling();
        this.bindEvents();
    }

    bindEvents() {
        document.querySelectorAll('.horse-bet-card').forEach(card => {
            card.onclick = () => {
                document.querySelectorAll('.horse-bet-card').forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                this.selectedHorse = card.dataset.id;
            };
        });

        document.getElementById('place-bet-btn').onclick = () => this.placeBet();
    }

    startPolling() {
        this.fetchState();
        this.pollInterval = setInterval(() => this.fetchState(), 2000);
    }

    async fetchState() {
        try {
            const response = await fetch('../api_horserace_pvp.php?action=get_state');
            const data = await response.json();

            if (data.success) {
                this.updateUI(data);
            }
        } catch (e) {
            console.error("Poll Error:", e);
        }
    }

    updateUI(data) {
        const { room, bets, server_time } = data;
        this.roomId = room.id;
        this.status = room.status;

        // Update Status
        const statusEl = document.getElementById('room-status');
        const countdownEl = document.getElementById('countdown-timer');

        if (this.status === 'waiting') {
            statusEl.innerText = "ĐANG ĐỢI NGƯỜI CHƠI...";
            // Tính thời gian đếm ngược
            const start = new Date(room.start_time).getTime();
            const now = new Date(server_time).getTime();
            const diff = Math.max(0, Math.floor((start - now) / 1000));
            countdownEl.innerText = diff + "s";
            
            this.resetHorses();
        } else if (this.status === 'racing') {
            statusEl.innerText = "CUỘC ĐUA ĐANG DIỄN RA!";
            countdownEl.innerText = "GO!";
            this.animateRace(room.start_time, server_time);
        } else if (this.status === 'finished') {
            statusEl.innerText = "CUỘC ĐUA KẾT THÚC!";
            countdownEl.innerText = "Winner: Horse #" + room.winner_horse;
            this.showFinishPositions(room.winner_horse);
        }

        // Update Bets
        this.updateBetsList(bets);
    }

    animateRace(startTime, serverTime) {
        const start = new Date(startTime).getTime();
        const now = new Date(serverTime).getTime();
        const elapsed = (now - start) / 1000; // số giây đã trôi qua

        // Dùng elapsed để tính vị trí ngựa (giả lập mượt mà)
        for (let i = 1; i <= 6; i++) {
            const horse = document.getElementById(`horse-${i}`);
            // Mỗi ngựa có tốc độ base + random nhẹ dựa trên seed (startTime)
            const speed = 10 + (Math.sin(start + i) * 2); 
            let progress = elapsed * speed;
            
            // Giới hạn không quá vạch đích (90%)
            progress = Math.min(progress, 85); 
            horse.style.left = progress + "%";
        }
    }

    resetHorses() {
        for (let i = 1; i <= 6; i++) {
            document.getElementById(`horse-${i}`).style.left = "0%";
        }
    }

    showFinishPositions(winner) {
        for (let i = 1; i <= 6; i++) {
            const horse = document.getElementById(`horse-${i}`);
            if (i == winner) {
                horse.style.left = "90%";
            } else {
                horse.style.left = (70 + (i * 2)) + "%";
            }
        }
    }

    updateBetsList(bets) {
        const list = document.getElementById('player-bets-list');
        list.innerHTML = bets.map(b => `
            <div style="display:flex; justify-content:space-between; padding:5px; border-bottom:1px solid rgba(255,255,255,0.05)">
                <span>User #${b.user_id}</span>
                <span>Ngựa #${b.horse_id}</span>
                <span style="color:#f59e0b">${new Intl.NumberFormat().format(b.amount)} gtlm</span>
            </div>
        `).join('');
    }

    async placeBet() {
        if (!this.selectedHorse) {
            alert("Vui lòng chọn ngựa!");
            return;
        }

        try {
            const formData = new FormData();
            formData.append('horse_id', this.selectedHorse);
            formData.append('amount', this.betAmount);

            const response = await fetch('../api_horserace_pvp.php?action=place_bet', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.success) {
                alert("Đặt cược thành công!");
            } else {
                alert(data.message);
            }
        } catch (e) {
            console.error(e);
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.game = new HorseRacePvP();
});

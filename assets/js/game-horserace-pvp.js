class HorseRacePvP {
    constructor() {
        this.roomId = null;
        this.status = 'waiting';
        this.horses = [0, 0, 0, 0, 0, 0];
        this.selectedHorse = null;
        this.pollInterval = null;
        this.myBet = null;
        this.notifiedFinishedRoomId = null;

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
            
            if (this.roomId !== this.notifiedFinishedRoomId) {
                this.notifiedFinishedRoomId = this.roomId;
                this.checkResult(room.winner_horse);
            }
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

    checkResult(winner) {
        if (!this.myBet) return;
        
        const winAmount = this.myBet.amount * 6;
        
        if (this.myBet.horseId == winner) {
            Swal.fire({
                icon: 'success',
                title: 'CHIẾN THẮNG!',
                html: `Chúc mừng! Chiến mã #${winner} đã về nhất.<br>Bạn nhận được <b>+${new Intl.NumberFormat('vi-VN').format(winAmount)}</b> GTLM!`,
                confirmButtonText: 'Tuyệt vời'
            });
            // Cộng tiền hiển thị
            const balanceEl = document.getElementById('user-balance');
            if (balanceEl) {
                let currentBal = parseInt(balanceEl.innerText.replace(/\./g, ''));
                currentBal += winAmount;
                balanceEl.innerText = new Intl.NumberFormat('vi-VN').format(currentBal);
            }
        } else {
            Swal.fire({
                icon: 'error',
                title: 'THẤT BẠI!',
                html: `Chiến mã #${winner} đã về nhất.<br>Bạn đã mất <b>${new Intl.NumberFormat('vi-VN').format(this.myBet.amount)}</b> GTLM!`,
                confirmButtonText: 'Thử lại'
            });
        }
        
        this.myBet = null;
    }

    async placeBet() {
        if (!this.selectedHorse) {
            Swal.fire({icon: 'warning', title: 'Oops...', text: 'Vui lòng chọn ngựa!'});
            return;
        }

        const betAmountInput = document.getElementById('bet-amount');
        const betAmount = parseInt(betAmountInput.value) || 0;

        if (betAmount < 1000) {
            Swal.fire({icon: 'warning', title: 'Lỗi', text: 'Tối thiểu 1.000 GTLM'});
            return;
        }

        try {
            const formData = new FormData();
            formData.append('horse_id', this.selectedHorse);
            formData.append('amount', betAmount);

            const response = await fetch('../api_horserace_pvp.php?action=place_bet', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.success) {
                Swal.fire({icon: 'success', title: 'Thành công!', text: 'Đặt cược thành công!', timer: 1500, showConfirmButton: false});
                this.myBet = { horseId: parseInt(this.selectedHorse), amount: betAmount };
                
                // Trừ tiền hiển thị
                const balanceEl = document.getElementById('user-balance');
                if (balanceEl) {
                    let currentBal = parseInt(balanceEl.innerText.replace(/\./g, ''));
                    currentBal -= betAmount;
                    balanceEl.innerText = new Intl.NumberFormat('vi-VN').format(currentBal);
                }
            } else {
                Swal.fire({icon: 'error', title: 'Lỗi', text: data.message});
            }
        } catch (e) {
            console.error(e);
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.game = new HorseRacePvP();
});

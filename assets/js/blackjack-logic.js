const BlackjackLogic = {
    currentBet: 5000,
    isGameRunning: false,
    playerCards: [],
    kingCards: [],
    kingHiddenCard: null,
    deck: [],

    init() {
        this.bindEvents();
        this.updateBetDisplay();
    },

    bindEvents() {
        document.querySelectorAll('.chip').forEach(chip => {
            chip.addEventListener('click', () => {
                if (this.isGameRunning) return;
                const active = document.querySelector('.chip.active');
                if (active) active.classList.remove('active');
                chip.classList.add('active');
                this.currentBet = parseInt(chip.dataset.value);
                document.getElementById('customBetInput').value = ''; // Xóa cược tùy chỉnh
                this.updateBetDisplay();
            });
        });

        const customInput = document.getElementById('customBetInput');
        customInput.addEventListener('input', () => {
            if (this.isGameRunning) return;
            const val = parseInt(customInput.value);
            if (!isNaN(val) && val > 0) {
                const active = document.querySelector('.chip.active');
                if (active) active.classList.remove('active');
                this.currentBet = val;
                this.updateBetDisplay();
            }
        });

        document.getElementById('dealBtn').addEventListener('click', () => this.startGame());
        document.getElementById('hitBtn').addEventListener('click', () => this.playerHit());
        document.getElementById('standBtn').addEventListener('click', () => this.playerStand());
        document.getElementById('doubleBtn').addEventListener('click', () => this.playerDouble());

        document.getElementById('guideBtn').addEventListener('click', () => {
            document.getElementById('guideModal').style.display = 'block';
        });

        document.querySelector('.close-guide').addEventListener('click', () => {
            document.getElementById('guideModal').style.display = 'none';
        });
    },

    updateBetDisplay() {
        document.getElementById('currentBetDisplay').innerText = this.formatNumber(this.currentBet);
    },

    async startGame() {
        if (this.isGameRunning) return;
        this.isGameRunning = true;
        this.playerCards = [];
        this.kingCards = [];
        blackjack3D.clearCards();
        document.getElementById('resultAnnounce').style.display = 'none';
        
        try {
            const response = await fetch('blackjack_process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'start', bet: this.currentBet })
            });
            const data = await response.json();

            if (data.error) {
                Swal.fire({ icon: 'error', title: 'Hoàng gia từ chối', text: data.error, background: '#1a1a1a', color: '#fff' });
                this.isGameRunning = false;
                return;
            }

            document.getElementById('dealBtn').style.display = 'none';
            document.getElementById('gameActions').style.display = 'flex';
            document.getElementById('doubleBtn').disabled = false;

            // Initial Deal
            this.playerCards.push(data.playerCards[0], data.playerCards[1]);
            this.kingCards.push(data.kingCards[0]);
            this.kingHiddenCard = data.kingCards[1];

            // Animation
            blackjack3D.dealCard('player', 0, data.playerCards[0].value, data.playerCards[0].suit);
            await this.delay(400);
            blackjack3D.dealCard('king', 0, data.kingCards[0].value, data.kingCards[0].suit);
            await this.delay(400);
            blackjack3D.dealCard('player', 1, data.playerCards[1].value, data.playerCards[1].suit);
            await this.delay(400);
            // King's hidden card (face down)
            this.kingHiddenMesh = blackjack3D.dealCard('king', 1, data.kingCards[1].value, data.kingCards[1].suit, false);
            
            this.updateScores();

            // Check for immediate Blackjack (Royale 21)
            if (this.calculateScore(this.playerCards) === 21) {
                this.playerStand();
            }

        } catch (e) {
            console.error(e);
            this.isGameRunning = false;
        }
    },

    async playerHit() {
        if (!this.isGameRunning) return;
        document.getElementById('doubleBtn').disabled = true;

        try {
            const response = await fetch('blackjack_process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'hit' })
            });
            const data = await response.json();

            const newCard = data.card;
            this.playerCards.push(newCard);
            blackjack3D.dealCard('player', this.playerCards.length - 1, newCard.value, newCard.suit);
            
            this.updateScores();

            if (this.calculateScore(this.playerCards) >= 21) {
                this.playerStand();
            }
        } catch (e) { console.error(e); }
    },

    async playerDouble() {
        if (!this.isGameRunning) return;
        
        try {
            const response = await fetch('blackjack_process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'double' })
            });
            const data = await response.json();

            if (data.error) {
                Swal.fire({ icon: 'warning', title: 'Không đủ ngân khố', text: data.error, background: '#1a1a1a', color: '#fff' });
                return;
            }

            const newCard = data.card;
            this.playerCards.push(newCard);
            blackjack3D.dealCard('player', this.playerCards.length - 1, newCard.value, newCard.suit);
            this.updateScores();
            
            // In Double, player gets exactly one more card then stands
            setTimeout(() => this.playerStand(), 600);
        } catch (e) { console.error(e); }
    },

    async playerStand() {
        if (!this.isGameRunning) return;
        document.getElementById('gameActions').style.display = 'none';

        try {
            const response = await fetch('blackjack_process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'stand' })
            });
            const data = await response.json();

            // Reveal King's card
            blackjack3D.flipCard(this.kingHiddenMesh);
            this.kingCards.push(this.kingHiddenCard);
            this.updateScores(true);

            // Deal King's additional cards if any
            for (let i = 2; i < data.kingFinalCards.length; i++) {
                await this.delay(600);
                const c = data.kingFinalCards[i];
                this.kingCards.push(c);
                blackjack3D.dealCard('king', i, c.value, c.suit);
                this.updateScores(true);
            }

            await this.delay(800);
            this.showResult(data);

        } catch (e) { console.error(e); }
    },

    showResult(data) {
        const announce = document.getElementById('resultAnnounce');
        const balance = document.getElementById('userBalance');
        
        let text = "";
        let color = "#fff";

        if (data.winStatus === 'blackjack') { text = "ROYALE 21!"; color = "var(--blackjack-gold)"; }
        else if (data.winStatus === 'win') { text = "CHALLENGER WIN!"; color = "var(--challenger-blue)"; }
        else if (data.winStatus === 'lose') { text = "KING WIN!"; color = "var(--king-red)"; }
        else if (data.winStatus === 'bust') { text = "BUSTED!"; color = "var(--king-red)"; }
        else if (data.winStatus === 'push') { text = "DRAW!"; color = "#aaa"; }

        announce.innerText = text;
        announce.style.color = color;
        announce.style.display = 'block';
        announce.style.animation = 'announce 0.6s ease-out forwards';

        balance.innerText = this.formatNumber(data.newBalance);
        
        setTimeout(() => {
            document.getElementById('dealBtn').style.display = 'block';
            this.isGameRunning = false;
        }, 2000);
    },

    calculateScore(cards) {
        let score = 0;
        let aces = 0;
        cards.forEach(c => {
            if (c.value === 1) aces++;
            else if (c.value >= 10) score += 10;
            else score += c.value;
        });
        for (let i = 0; i < aces; i++) {
            if (score + 11 <= 21) score += 11;
            else score += 1;
        }
        return score;
    },

    updateScores(showKing = false) {
        document.getElementById('playerScore').innerText = this.calculateScore(this.playerCards);
        if (showKing) {
            document.getElementById('kingScore').innerText = this.calculateScore(this.kingCards);
        } else {
            document.getElementById('kingScore').innerText = "?";
        }
    },

    formatNumber(num) { return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, "."); },
    delay(ms) { return new Promise(res => setTimeout(res, ms)); }
};

BaccaratLogic = null; // Prevent conflict if any
BlackjackLogic.init();

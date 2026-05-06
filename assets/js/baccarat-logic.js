/**
 * Baccarat Game Logic
 * Handles betting, rules, and server communication
 */

const BaccaratLogic = {
    currentBet: { player: 0, banker: 0, tie: 0 },
    selectedChip: 1000,
    isGameRunning: false,
    history: [],

    init() {
        this.bindEvents();
        this.updateBalanceDisplay();
    },

    bindEvents() {
        // Chip selection
        document.querySelectorAll('.chip').forEach(chip => {
            chip.addEventListener('click', (e) => {
                document.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
                chip.classList.add('active');
                this.selectedChip = parseInt(chip.dataset.value);
            });
        });

        // Betting options
        document.querySelectorAll('.bet-option').forEach(option => {
            option.addEventListener('click', () => {
                if (this.isGameRunning) return;
                const type = option.dataset.type;
                this.placeBet(type);
            });
        });

        // Controls
        document.getElementById('dealBtn').addEventListener('click', () => this.startGame());
        document.getElementById('clearBet').addEventListener('click', () => this.clearBets());

        // Royale Guide Logic
        const modal = document.getElementById('guideModal');
        const btn = document.getElementById('guideBtn');
        const closeSpan = document.querySelector('.close-guide');

        btn.onclick = () => modal.style.display = 'block';
        closeSpan.onclick = () => modal.style.display = 'none';
        window.onclick = (event) => {
            if (event.target == modal) modal.style.display = 'none';
        };
    },

    placeBet(type) {
        this.currentBet[type] += this.selectedChip;
        const betEl = document.getElementById(`bet${type.charAt(0).toUpperCase() + type.slice(1)}`);
        betEl.innerText = this.formatNumber(this.currentBet[type]);
        betEl.style.display = 'block';
        
        document.getElementById('dealBtn').classList.remove('disabled');
        
        // Play chip sound or effect
        if (window.GameEffects) GameEffects.createCoinBlast(event.clientX, event.clientY);
    },

    clearBets() {
        if (this.isGameRunning) return;
        this.currentBet = { player: 0, banker: 0, tie: 0 };
        document.querySelectorAll('.bet-amount').forEach(el => {
            el.innerText = '0';
            el.style.display = 'none';
        });
        document.getElementById('dealBtn').classList.add('disabled');
    },

    async startGame() {
        if (this.isGameRunning || (this.currentBet.player === 0 && this.currentBet.banker === 0 && this.currentBet.tie === 0)) return;
        
        this.isGameRunning = true;
        document.getElementById('dealBtn').classList.add('disabled');
        
        try {
            // 1. Send bet to Server
            const response = await fetch('baccarat_process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(this.currentBet)
            });
            const data = await response.json();

            if (data.error) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Lỗi Hoàng Gia',
                    text: data.error,
                    background: '#1a1a1a',
                    color: '#fff',
                    confirmButtonColor: '#d4af37'
                });
                this.isGameRunning = false;
                document.getElementById('dealBtn').classList.remove('disabled');
                return;
            }

            const result = data.gameResult;
            
            // 2. 3D Animation
            baccarat3D.clearCards();
            
            // Deal 1st pair
            await this.delay(500);
            // Deal 1st pair
            baccarat3D.dealCard('player', 0, result.playerCards[0].value, result.playerCards[0].suit);
            await this.delay(400);
            baccarat3D.dealCard('banker', 0, result.bankerCards[0].value, result.bankerCards[0].suit);
            
            // Deal 2nd pair
            await this.delay(400);
            baccarat3D.dealCard('player', 1, result.playerCards[1].value, result.playerCards[1].suit);
            await this.delay(400);
            baccarat3D.dealCard('banker', 1, result.bankerCards[1].value, result.bankerCards[1].suit);
            
            // Logic for showing intermediate scores
            this.updateScores(this.calculateIntermediateScore(result.playerCards.slice(0,2)), 
                             this.calculateIntermediateScore(result.bankerCards.slice(0,2)));

            // Deal 3rd cards if exists
            if (result.playerCards.length > 2) {
                await this.delay(800);
                baccarat3D.dealCard('player', 2, result.playerCards[2].value, result.playerCards[2].suit, true);
            }
            if (result.bankerCards.length > 2) {
                await this.delay(800);
                baccarat3D.dealCard('banker', 2, result.bankerCards[2].value, result.bankerCards[2].suit, true);
            }

            // 3. Finalize
            await this.delay(1000);
            this.updateScores(result.playerScore, result.bankerScore);
            this.announceWinner(result.winner);
            this.addToHistory(result.winner);
            
            // Update balance display
            document.getElementById('userBalance').innerText = this.formatNumber(data.newBalance);
            
        } catch (e) {
            console.error(e);
            Swal.fire({
                icon: 'error',
                title: 'Mất kết nối',
                text: 'Không thể kết nối tới ngân khố hoàng gia. Vui lòng thử lại!',
                background: '#1a1a1a',
                color: '#fff',
                confirmButtonColor: '#d4af37'
            });
        }
        
        this.isGameRunning = false;
        this.clearBets();
    },

    calculateIntermediateScore(cards) {
        return cards.reduce((sum, c) => sum + (c.value >= 10 ? 0 : c.value), 0) % 10;
    },

    simulateGame() {
        const deck = this.generateDeck();
        const playerCards = [deck.pop(), deck.pop()];
        const bankerCards = [deck.pop(), deck.pop()];
        
        let pScore = this.calculateScore(playerCards);
        let bScore = this.calculateScore(bankerCards);
        
        let pThird = null;
        let bThird = null;

        // Baccarat Rules
        if (pScore < 8 && bScore < 8) {
            // Player's rule
            if (pScore <= 5) {
                pThird = deck.pop();
                playerCards.push(pThird);
            }

            // Banker's rule
            const pThirdVal = pThird ? this.getCardValue(pThird) : -1;
            if (this.shouldBankerDraw(bScore, pThirdVal)) {
                bThird = deck.pop();
                bankerCards.push(bThird);
            }
        }

        const finalP = this.calculateScore(playerCards);
        const finalB = this.calculateScore(bankerCards);
        const winner = finalP > finalB ? 'player' : (finalB > finalP ? 'banker' : 'tie');

        return {
            playerCards, bankerCards,
            playerScore: pScore,
            bankerScore: bScore,
            playerThirdCard: pThird,
            bankerThirdCard: bThird,
            playerScoreAfterThird: finalP,
            bankerScoreAfterThird: finalB,
            winner
        };
    } ,

    calculateScore(cards) {
        const total = cards.reduce((sum, card) => sum + this.getCardValue(card), 0);
        return total % 10;
    },

    getCardValue(card) {
        if (card.value >= 10) return 0;
        return card.value;
    },

    shouldBankerDraw(bScore, pThirdValue) {
        if (pThirdValue === -1) return bScore <= 5;
        
        if (bScore <= 2) return true;
        if (bScore === 3) return pThirdValue !== 8;
        if (bScore === 4) return [2,3,4,5,6,7].includes(pThirdValue);
        if (bScore === 5) return [4,5,6,7].includes(pThirdValue);
        if (bScore === 6) return [6,7].includes(pThirdValue);
        return false;
    },

    generateDeck() {
        const values = [1,2,3,4,5,6,7,8,9,10,11,12,13];
        let deck = [];
        for(let i=0; i<4; i++) values.forEach(v => deck.push({value: v}));
        return deck.sort(() => Math.random() - 0.5);
    },

    updateScores(p, b) {
        document.getElementById('playerScore').innerText = p;
        document.getElementById('bankerScore').innerText = b;
    },

    announceWinner(winner) {
        const announceEl = document.getElementById('resultAnnounce');
        const winnerName = winner === 'player' ? 'QUEEN' : (winner === 'banker' ? 'KING' : 'DRAW');
        announceEl.innerText = winnerName + " VICTORY!";
        announceEl.style.color = winner === 'player' ? 'var(--player-blue)' : (winner === 'banker' ? 'var(--banker-red)' : 'var(--tie-green)');
        announceEl.style.display = 'block';
        announceEl.style.animation = 'announce 0.6s ease-out forwards';
        
        setTimeout(() => announceEl.style.display = 'none', 3000);
    },

    addToHistory(winner) {
        const grid = document.getElementById('beadPlate');
        const bead = document.createElement('div');
        bead.className = `bead bead-${winner}`;
        bead.innerText = winner.charAt(0).toUpperCase();
        grid.appendChild(bead);
    },

    formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    },

    updateBalanceDisplay() {
        // This will be called after AJAX
    },

    delay(ms) { return new Promise(res => setTimeout(res, ms)); }
};

BaccaratLogic.init();

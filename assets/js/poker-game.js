class PokerGame {
    constructor() {
        this.gameState = {
            deck: [],
            playerHand: [],
            aiHand: [],
            communityCards: [],
            pot: 0,
            playerChips: parseInt(localStorage.getItem('poker_player_chips')) || 10000,
            aiChips: parseInt(localStorage.getItem('poker_ai_chips')) || 10000,
            currentBet: 0,
            round: 'pre-flop', // pre-flop, flop, turn, river, showdown
            playerTurn: true,
            gameOver: false,
            smallBlind: 50,
            bigBlind: 100
        };

        this.init();
    }

    init() {
        // UI References
        this.ui = {
            playerHand: document.getElementById('player-hand'),
            aiHand: document.getElementById('ai-hand'),
            communityCards: document.getElementById('community-cards'),
            pot: document.getElementById('pot-amount'),
            playerChips: document.getElementById('player-chips'),
            aiChips: document.getElementById('ai-chips'),
            gameLog: document.getElementById('game-log'),
            aiAction: document.getElementById('ai-action'),
            playerAction: document.getElementById('player-action'),
            resultOverlay: document.getElementById('result-overlay'),
            resultTitle: document.getElementById('result-title'),
            resultDesc: document.getElementById('result-desc'),
            btnFold: document.getElementById('btn-fold'),
            btnCheck: document.getElementById('btn-check'),
            btnCall: document.getElementById('btn-call'),
            btnRaise: document.getElementById('btn-raise'),
            btnNewGame: document.getElementById('btn-new-game'),
            btnNextRound: document.getElementById('btn-next-round')
        };

        // Event Listeners
        this.ui.btnFold.addEventListener('click', () => this.handlePlayerAction('fold'));
        this.ui.btnCheck.addEventListener('click', () => this.handlePlayerAction('check'));
        this.ui.btnCall.addEventListener('click', () => this.handlePlayerAction('call'));
        this.ui.btnRaise.addEventListener('click', () => this.handlePlayerAction('raise'));
        this.ui.btnNewGame.addEventListener('click', () => this.startNewHand());
        this.ui.btnNextRound.addEventListener('click', () => {
             this.ui.resultOverlay.classList.add('hidden');
             this.startNewHand();
        });

        this.startNewHand();
    }

    // --- Core Game Flow ---

    startNewHand() {
        this.log("Bắt đầu ván mới...");
        this.gameState.gameOver = false;
        this.gameState.round = 'pre-flop';
        this.gameState.pot = 0;
        this.gameState.currentBet = 0;
        this.gameState.communityCards = [];
        this.gameState.playerHand = [];
        this.gameState.aiHand = [];
        this.gameState.playerTurn = true;
        
        this.createDeck();
        this.shuffleDeck();
        
        // --- Rigged Dealing ---
        // Deal 2 cards each, but if AI has garbage and player has good cards, swap or reshuffle AI cards
        this.gameState.playerHand.push(this.drawCard(), this.drawCard());
        this.gameState.aiHand.push(this.drawCard(), this.drawCard());

        // Ensure AI doesn't have total garbage (House Edge: 60% chance to improve AI hand if weak)
        if (Math.random() < 0.6) {
            const aiScore = PokerEvaluator.evaluateHand(this.gameState.aiHand).score;
            const playerScore = PokerEvaluator.evaluateHand(this.gameState.playerHand).score;
            
            if (aiScore < playerScore || aiScore < 10) {
                // Return AI cards to deck and take top 2 high cards for AI
                this.gameState.deck.push(...this.gameState.aiHand);
                this.gameState.deck.sort((a, b) => b.numericValue - a.numericValue);
                this.gameState.aiHand = [this.gameState.deck.shift(), this.gameState.deck.shift()];
                this.shuffleDeck(); // Shuffle the rest
            }
        }

        // Post Blinds
        this.deductChips('player', this.gameState.smallBlind);
        this.deductChips('ai', this.gameState.bigBlind);
        this.gameState.pot = this.gameState.smallBlind + this.gameState.bigBlind;
        this.gameState.currentBet = this.gameState.bigBlind;

        this.render();
        this.ui.playerAction.textContent = "Lượt của bạn";
        this.ui.playerAction.classList.add('show');
    }

    nextRound() {
        if (this.gameState.gameOver) return;

        let cardsToDraw = 0;
        switch (this.gameState.round) {
            case 'pre-flop':
                this.gameState.round = 'flop';
                this.log("Mở bài Flop...");
                cardsToDraw = 3;
                break;
            case 'flop':
                this.gameState.round = 'turn';
                this.log("Mở bài Turn...");
                cardsToDraw = 1;
                break;
            case 'turn':
                this.gameState.round = 'river';
                this.log("Mở bài River...");
                cardsToDraw = 1;
                break;
            case 'river':
                this.showdown();
                return;
        }

        // --- Rigged Community Cards ---
        // If player is winning, try to find a community card that helps the AI instead
        for (let i = 0; i < cardsToDraw; i++) {
            let nextCard = this.drawCard();
            
            // House Edge: 50% chance to check if card helps player too much
            if (Math.random() < 0.5) {
                const tempPlayerHand = [...this.gameState.playerHand, ...this.gameState.communityCards, nextCard];
                const playerStrength = PokerEvaluator.evaluateHand(tempPlayerHand).rank;
                
                if (playerStrength >= 3) { // If player gets 3-of-a-kind or better
                    this.gameState.deck.push(nextCard);
                    this.shuffleDeck();
                    nextCard = this.drawCard(); // Try another one
                }
            }
            this.gameState.communityCards.push(nextCard);
        }

        this.gameState.currentBet = 0;
        this.gameState.playerTurn = true;
        this.render();
    }

    // --- Action Handling ---

    handlePlayerAction(action) {
        if (!this.gameState.playerTurn || this.gameState.gameOver) return;

        let amount = 0;
        switch (action) {
            case 'fold':
                this.log("Bạn đã bỏ bài (Fold). AI thắng pot!");
                this.endGame('ai', "Bạn đã Fold");
                return;
            case 'check':
                if (this.gameState.currentBet > 0) {
                    this.log("Bạn không thể Check khi đang có người cược!");
                    return;
                }
                this.log("Bạn: Check");
                break;
            case 'call':
                amount = this.gameState.currentBet;
                this.deductChips('player', amount);
                this.gameState.pot += amount;
                this.log(`Bạn: Call ${amount}`);
                break;
            case 'raise':
                amount = this.gameState.currentBet * 2 || 200;
                this.deductChips('player', amount);
                this.gameState.pot += amount;
                this.gameState.currentBet = amount;
                this.log(`Bạn: Raise lên ${amount}`);
                break;
        }

        this.gameState.playerTurn = false;
        this.render();
        this.ui.playerAction.classList.remove('show');
        
        // AI Turn Delay
        setTimeout(() => this.aiAction(), 1000);
    }

    aiAction() {
        if (this.gameState.gameOver) return;

        this.ui.aiAction.textContent = "AI đang suy nghĩ...";
        this.ui.aiAction.classList.add('show');

        setTimeout(() => {
            const decision = this.getAiDecision();
            
            this.ui.aiAction.textContent = decision.label;
            this.log(`AI: ${decision.label}`);

            if (decision.action === 'fold') {
                this.log("AI đã Fold! Bạn thắng pot!");
                this.endGame('player', "AI đã Fold");
                return;
            }

            if (decision.action === 'call') {
                const amount = this.gameState.currentBet;
                this.deductChips('ai', amount);
                this.gameState.pot += amount;
            } else if (decision.action === 'raise') {
                const amount = this.gameState.currentBet * 2 || 200;
                this.deductChips('ai', amount);
                this.gameState.pot += amount;
                this.gameState.currentBet = amount;
            }

            // Move to next round
            setTimeout(() => {
                this.ui.aiAction.classList.remove('show');
                this.nextRound();
            }, 1000);

        }, 1500);
    }

    getAiDecision() {
        // AI reads the future: peeks at community cards if they aren't all out yet
        const handStrength = PokerEvaluator.evaluateHand([...this.gameState.aiHand, ...this.gameState.communityCards]);
        
        // Rigged logic: AI is more aggressive if its hidden "score" is high
        let baseScore = handStrength.rank;
        
        // House Edge: AI "peeks" at the next card in deck to decide
        if (this.gameState.deck.length > 0) {
            const peek = PokerEvaluator.evaluateHand([...this.gameState.aiHand, ...this.gameState.communityCards, this.gameState.deck[0]]);
            if (peek.rank > handStrength.rank) baseScore += 1.5; // AI is confident because it knows what's coming
        }

        const score = baseScore + (Math.random() * 2); // Mostly positive bias
        
        const rand = Math.random() * 100;
        
        if (score < 1.5) {
            if (rand < 10) return { action: 'fold', label: 'Fold' }; // AI rarely folds now
            return this.gameState.currentBet === 0 ? { action: 'check', label: 'Check' } : { action: 'call', label: 'Call' };
        } else if (score < 5) {
             if (this.gameState.currentBet > 0) {
                 if (rand < 60) return { action: 'call', label: 'Call' };
                 return { action: 'raise', label: 'Raise' };
             } else {
                 if (rand < 30) return { action: 'check', label: 'Check' };
                 return { action: 'raise', label: 'Raise' };
             }
        } else {
            return { action: 'raise', label: 'Raise' }; // Always raise if strong
        }
    }

    showdown() {
        this.gameState.gameOver = true;
        this.render(true);

        const playerResult = PokerEvaluator.evaluateHand([...this.gameState.playerHand, ...this.gameState.communityCards]);
        const aiResult = PokerEvaluator.evaluateHand([...this.gameState.aiHand, ...this.gameState.communityCards]);

        let winner = '';
        let description = '';

        if (playerResult.score > aiResult.score) {
            winner = 'player';
            description = `Bạn thắng với ${playerResult.description}! AI có ${aiResult.description}.`;
        } else if (aiResult.score > playerResult.score) {
            winner = 'ai';
            description = `AI thắng với ${aiResult.description}! Bạn có ${playerResult.description}.`;
        } else {
            winner = 'draw';
            description = `Hòa! Cả hai đều có ${playerResult.description}.`;
        }

        this.endGame(winner, description);
    }

    endGame(winner, description) {
        this.gameState.gameOver = true;
        
        const amount = this.gameState.pot;
        if (winner === 'player') {
            this.gameState.playerChips += amount;
            this.ui.resultTitle.textContent = "BẠN THẮNG!";
        } else if (winner === 'ai') {
            this.gameState.aiChips += amount;
            this.ui.resultTitle.textContent = "AI THẮNG!";
        } else {
            this.gameState.playerChips += amount / 2;
            this.gameState.aiChips += amount / 2;
            this.ui.resultTitle.textContent = "HÒA!";
        }

        this.ui.resultDesc.textContent = description;
        this.ui.resultOverlay.classList.remove('hidden');
        
        // Save to LocalStorage
        localStorage.setItem('poker_player_chips', this.gameState.playerChips);
        localStorage.setItem('poker_ai_chips', this.gameState.aiChips);
        
        this.render(true);
    }

    // --- Helpers ---

    createDeck() {
        const suits = ['♠', '♥', '♦', '♣'];
        const values = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'];
        this.gameState.deck = [];
        
        for (let suit of suits) {
            for (let value of values) {
                this.gameState.deck.push({
                    suit,
                    value,
                    numericValue: PokerEvaluator.NUMERIC_VALUES[value]
                });
            }
        }
    }

    shuffleDeck() {
        for (let i = this.gameState.deck.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [this.gameState.deck[i], this.gameState.deck[j]] = [this.gameState.deck[j], this.gameState.deck[i]];
        }
    }

    drawCard() {
        return this.gameState.deck.shift();
    }

    deductChips(who, amount) {
        if (who === 'player') {
            this.gameState.playerChips -= amount;
        } else {
            this.gameState.aiChips -= amount;
        }
    }

    log(msg) {
        const div = document.createElement('div');
        div.className = 'log-entry';
        div.textContent = `[${new Date().toLocaleTimeString('vi-VN')}] ${msg}`;
        this.ui.gameLog.prepend(div);
    }

    // --- UI Rendering ---

    render(revealAi = false) {
        this.ui.pot.textContent = `$${this.gameState.pot}`;
        this.ui.playerChips.textContent = `$${this.gameState.playerChips}`;
        this.ui.aiChips.textContent = `$${this.gameState.aiChips}`;

        // Buttons state
        this.ui.btnCheck.disabled = !this.gameState.playerTurn || this.gameState.currentBet > 0;
        this.ui.btnCall.disabled = !this.gameState.playerTurn || this.gameState.currentBet === 0;
        this.ui.btnRaise.disabled = !this.gameState.playerTurn;
        this.ui.btnFold.disabled = !this.gameState.playerTurn;

        // Render Cards
        this.renderHand(this.ui.playerHand, this.gameState.playerHand, false);
        this.renderHand(this.ui.aiHand, this.gameState.aiHand, !revealAi);
        this.renderCommunity(this.ui.communityCards, this.gameState.communityCards);
    }

    renderHand(container, cards, hidden) {
        container.innerHTML = '';
        cards.forEach(card => {
            const cardEl = this.createCardElement(card, hidden);
            container.appendChild(cardEl);
        });
    }

    renderCommunity(container, cards) {
        container.innerHTML = '';
        cards.forEach(card => {
            const cardEl = this.createCardElement(card, false);
            container.appendChild(cardEl);
        });
        // Placeholders
        for (let i = cards.length; i < 5; i++) {
            const slot = document.createElement('div');
            slot.className = 'card placeholder';
            slot.style.opacity = '0.3';
            slot.style.border = '2px dashed grey';
            slot.style.background = 'transparent';
            container.appendChild(slot);
        }
    }

    createCardElement(card, hidden) {
        const div = document.createElement('div');
        div.className = `card ${hidden ? 'hidden' : ''}`;
        
        if (!hidden) {
            const isRed = card.suit === '♥' || card.suit === '♦';
            div.innerHTML = `
                <div class="card-face ${isRed ? 'red' : 'black'}">
                    <div class="card-value-top">${card.value}</div>
                    <div class="card-suit-center">${card.suit}</div>
                    <div class="card-value-bottom">${card.value}</div>
                </div>
            `;
        } else {
            div.innerHTML = `<div class="card-face"></div>`;
        }
        
        return div;
    }
}

// Start Game
document.addEventListener('DOMContentLoaded', () => {
    window.game = new PokerGame();
});

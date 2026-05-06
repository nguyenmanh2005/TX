/**
 * Game Animations Enhanced - Thêm animations cụ thể cho từng game
 */

class GameAnimationsEnhanced {
    constructor() {
        this.init();
    }

    init() {
        this.setupRouletteAnimations();
        this.setupSlotAnimations();
        this.setupDiceAnimations();
        this.setupPokerAnimations();
        this.setupCoinFlipAnimations();
        this.setupBaucuaAnimations();
        this.setupPlinkoAnimations();
        this.setupMinesAnimations();
        this.setupWheelAnimations();
        this.setupCrashAnimations();
        this.setupTowerAnimations();
        this.setupLimboAnimations();
        this.setupKenoAnimations();
        this.setupDiceRollAnimations();
    }

    setupRouletteAnimations() {
        // Roulette spin animation
        const rouletteWheel = document.querySelector('.roulette-wheel');
        if (rouletteWheel) {
            const spinBtn = document.querySelector('.spin-button-roulette-enhanced');
            if (spinBtn) {
                spinBtn.addEventListener('click', () => {
                    rouletteWheel.classList.add('roulette-spinning');
                    setTimeout(() => {
                        rouletteWheel.classList.remove('roulette-spinning');
                    }, 3000);
                });
            }
        }
    }

    setupSlotAnimations() {
        // Slot reel spin animation
        const slotReels = document.querySelectorAll('.slot-reel');
        const spinBtn = document.querySelector('.spin-button-slot-enhanced');
        
        if (spinBtn && slotReels.length > 0) {
            spinBtn.addEventListener('click', () => {
                slotReels.forEach((reel, index) => {
                    reel.classList.add('slot-reel-spinning');
                    setTimeout(() => {
                        reel.classList.remove('slot-reel-spinning');
                        reel.classList.add('slot-reel-stop');
                        setTimeout(() => {
                            reel.classList.remove('slot-reel-stop');
                        }, 500);
                    }, 2000 + (index * 200));
                });
            });
        }
    }

    setupDiceAnimations() {
        // Dice roll animation
        const diceContainer = document.querySelector('.dice-3d-enhanced');
        const rollBtn = document.querySelector('.roll-button-dice-enhanced');
        
        if (rollBtn && diceContainer) {
            rollBtn.addEventListener('click', () => {
                diceContainer.classList.add('dice-rolling');
                setTimeout(() => {
                    diceContainer.classList.remove('dice-rolling');
                    diceContainer.classList.add('dice-result-appear');
                }, 1000);
            });
        }
    }

    setupPokerAnimations() {
        // Card deal animation
        const cards = document.querySelectorAll('.card-poker-enhanced');
        cards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
            card.classList.add('card-dealing');
        });
    }

    setupCoinFlipAnimations() {
        // Coin flip animation
        const coin = document.querySelector('.coin-display-enhanced');
        const flipBtn = document.querySelector('.flip-button-coinflip-enhanced');
        
        if (flipBtn && coin) {
            flipBtn.addEventListener('click', () => {
                coin.classList.add('coin-flipping');
                setTimeout(() => {
                    coin.classList.remove('coin-flipping');
                    coin.classList.add('coin-result');
                }, 1000);
            });
        }
    }

    setupBaucuaAnimations() {
        // Dice shake animation
        const dice = document.querySelectorAll('.dice-baucua-enhanced');
        const playBtn = document.querySelector('.play-button-baucua-enhanced');
        
        if (playBtn && dice.length > 0) {
            playBtn.addEventListener('click', () => {
                dice.forEach(die => {
                    die.classList.add('baucua-dice-shaking');
                    setTimeout(() => {
                        die.classList.remove('baucua-dice-shaking');
                    }, 2000);
                });
            });
        }
    }

    setupPlinkoAnimations() {
        // Plinko ball drop animation
        const dropBtn = document.querySelector('.drop-button-plinko-enhanced');
        if (dropBtn) {
            dropBtn.addEventListener('click', () => {
                const ball = document.createElement('div');
                ball.className = 'plinko-ball plinko-ball-dropping';
                document.querySelector('.plinko-board').appendChild(ball);
            });
        }
    }

    setupMinesAnimations() {
        // Mine reveal animation
        const mineCells = document.querySelectorAll('.mine-cell');
        mineCells.forEach(cell => {
            cell.addEventListener('click', () => {
                if (cell.dataset.mine === 'true') {
                    cell.classList.add('mine-explode');
                } else {
                    cell.classList.add('safe-reveal');
                }
            });
        });
    }

    setupWheelAnimations() {
        // Wheel spin animation
        const wheel = document.querySelector('.wheel-display-enhanced');
        const spinBtn = document.querySelector('.spin-button-wheel-enhanced');
        
        if (spinBtn && wheel) {
            spinBtn.addEventListener('click', () => {
                wheel.classList.add('wheel-spinning');
                setTimeout(() => {
                    wheel.classList.remove('wheel-spinning');
                }, 3000);
            });
        }
    }

    setupCrashAnimations() {
        // Multiplier rise animation
        const multiplierDisplay = document.querySelector('.multiplier-value');
        if (multiplierDisplay) {
            multiplierDisplay.classList.add('multiplier-rising');
        }
    }

    setupTowerAnimations() {
        // Tower build animation
        const towerBlocks = document.querySelectorAll('.tower-block');
        towerBlocks.forEach((block, index) => {
            block.style.animationDelay = `${index * 0.1}s`;
            block.classList.add('tower-building');
        });
    }

    setupLimboAnimations() {
        // Limbo multiplier pulse
        const multiplierDisplay = document.querySelector('.multiplier-value-limbo');
        if (multiplierDisplay) {
            multiplierDisplay.classList.add('limbo-multiplier-pulse');
        }
    }

    setupKenoAnimations() {
        // Keno number draw animation
        const drawnNumbers = document.querySelectorAll('.keno-number.drawn');
        drawnNumbers.forEach((num, index) => {
            num.style.animationDelay = `${index * 0.1}s`;
            num.classList.add('keno-number-drawing');
        });

        // Match animation
        const matchedNumbers = document.querySelectorAll('.keno-number.matched');
        matchedNumbers.forEach((num, index) => {
            num.style.animationDelay = `${index * 0.1}s`;
            num.classList.add('keno-number-match');
        });
    }

    setupDiceRollAnimations() {
        // Multiple dice roll animation
        const diceItems = document.querySelectorAll('.dice-item');
        diceItems.forEach((dice, index) => {
            dice.style.animationDelay = `${index * 0.1}s`;
            dice.classList.add('dice-result-stagger');
        });
    }

    // Utility function to add animation to element
    static addAnimation(element, animationClass, duration = 1000) {
        if (!element) return;
        
        element.classList.add(animationClass);
        setTimeout(() => {
            element.classList.remove(animationClass);
        }, duration);
    }

    // Utility function to animate balance update
    static animateBalanceUpdate(element) {
        if (!element) return;
        
        element.classList.add('balance-update');
        setTimeout(() => {
            element.classList.remove('balance-update');
        }, 500);
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    window.gameAnimationsEnhanced = new GameAnimationsEnhanced();
});


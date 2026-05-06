/**
 * Game Poker - JavaScript Enhanced
 */

class PokerEnhanced {
    constructor() {
        this.init();
    }

    init() {
        this.setupQuickBetButtons();
        this.setupCardAnimations();
    }

    setupQuickBetButtons() {
        const quickButtons = document.querySelectorAll('.bet-quick-btn-poker-enhanced');
        quickButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const amount = btn.dataset.amount || btn.textContent.replace(/[^\d]/g, '');
                const input = document.getElementById('cuocInput');
                if (input) {
                    input.value = parseInt(amount).toLocaleString('vi-VN');
                }
                
                quickButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            });
        });
    }

    setupCardAnimations() {
        const cards = document.querySelectorAll('.card-poker-enhanced');
        cards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
        });
    }

    celebrateWin(amount) {
        if (typeof GameEffects !== 'undefined') {
            GameEffects.celebrateWin(amount);
        }
        
        const cards = document.querySelectorAll('.card-poker-enhanced');
        cards.forEach((card, index) => {
            setTimeout(() => {
                card.style.animation = 'none';
                setTimeout(() => {
                    card.style.animation = 'bounceCard 0.5s ease';
                }, 10);
            }, index * 100);
        });
    }
}

// Add bounce animation
const style = document.createElement('style');
style.textContent = `
    @keyframes bounceCard {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-20px); }
    }
`;
document.head.appendChild(style);

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('.cards-container-poker-enhanced')) {
        window.pokerEnhanced = new PokerEnhanced();
    }
});


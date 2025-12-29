/**
 * Game Bầu Cua - JavaScript Enhanced
 * Nâng cấp UI/UX cho Bầu Cua
 */

class BaucuaEnhanced {
    constructor() {
        this.selectedAnimal = null;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupQuickBetButtons();
        this.setupAnimalSelection();
    }

    setupEventListeners() {
        const playButton = document.getElementById('playButton');
        if (playButton) {
            playButton.addEventListener('click', () => this.handlePlay());
        }

        // Keyboard shortcut: Enter để chơi
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !this.isPlaying) {
                e.preventDefault();
                this.handlePlay();
            }
        });

        // Format input khi nhập
        const betInput = document.getElementById('cuocInput');
        if (betInput) {
            betInput.addEventListener('input', (e) => {
                const value = e.target.value.replace(/,/g, '');
                if (value && !isNaN(value)) {
                    e.target.value = parseInt(value).toLocaleString('vi-VN');
                }
            });
        }
    }

    setupQuickBetButtons() {
        const quickButtons = document.querySelectorAll('.bet-quick-btn-baucua-enhanced');
        quickButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const amount = btn.dataset.amount || btn.textContent.replace(/[^\d]/g, '');
                const input = document.getElementById('cuocInput');
                if (input) {
                    input.value = parseInt(amount).toLocaleString('vi-VN');
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    
                    // Highlight button
                    quickButtons.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                }
            });
        });
    }

    setupAnimalSelection() {
        const animalCards = document.querySelectorAll('.animal-card-enhanced');
        animalCards.forEach(card => {
            card.addEventListener('click', () => {
                // Remove previous selection
                animalCards.forEach(c => c.classList.remove('selected'));
                
                // Add selection to clicked card
                card.classList.add('selected');
                this.selectedAnimal = card.dataset.animal || card.querySelector('.animal-name-enhanced')?.textContent.trim();
                
                // Update select dropdown
                const select = document.getElementById('chonSelect');
                if (select && this.selectedAnimal) {
                    select.value = this.selectedAnimal;
                }
            });
        });

        // Sync select dropdown with cards
        const select = document.getElementById('chonSelect');
        if (select) {
            select.addEventListener('change', (e) => {
                const selectedValue = e.target.value;
                animalCards.forEach(card => {
                    const cardAnimal = card.dataset.animal || card.querySelector('.animal-name-enhanced')?.textContent.trim();
                    if (cardAnimal === selectedValue) {
                        animalCards.forEach(c => c.classList.remove('selected'));
                        card.classList.add('selected');
                        this.selectedAnimal = selectedValue;
                    }
                });
            });
        }
    }

    handlePlay() {
        const form = document.getElementById('gameForm');
        if (!form) return;

        const betInput = document.getElementById('cuocInput');
        const select = document.getElementById('chonSelect');
        
        if (!betInput || !betInput.value) {
            this.showError('Vui lòng nhập số tiền cược!');
            return;
        }

        if (!select || !select.value) {
            this.showError('Vui lòng chọn con vật!');
            return;
        }

        // Add loading state
        const playButton = document.getElementById('playButton');
        if (playButton) {
            playButton.disabled = true;
            playButton.innerHTML = '<span class="spinner-small"></span> Đang quay...';
        }

        // Submit form
        form.submit();
    }

    showError(message) {
        // Có thể dùng toast notification hoặc alert
        alert(message);
    }

    // Highlight winning dice
    highlightWinningDice(result, selectedAnimal) {
        const diceItems = document.querySelectorAll('.dice-item-enhanced');
        diceItems.forEach((dice, index) => {
            if (result[index] === selectedAnimal) {
                dice.classList.add('win');
                setTimeout(() => {
                    dice.classList.remove('win');
                }, 2000);
            }
        });
    }
}

// Initialize khi DOM ready
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('gameForm')) {
        window.baucuaEnhanced = new BaucuaEnhanced();
    }
});

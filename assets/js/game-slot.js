/**
 * Game Slot Machine - JavaScript Enhanced
 * Nâng cấp UI/UX cho Slot Machine
 */

class SlotMachineEnhanced {
    constructor() {
        this.isSpinning = false;
        this.autoSpinCount = 0;
        this.maxAutoSpins = 10;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupQuickBetButtons();
        this.setupAutoSpin();
    }

    setupEventListeners() {
        const spinButton = document.getElementById('spinButton');
        if (spinButton) {
            spinButton.addEventListener('click', () => this.handleSpin());
        }

        // Keyboard shortcut: Space để quay
        document.addEventListener('keydown', (e) => {
            if (e.code === 'Space' && !this.isSpinning) {
                e.preventDefault();
                this.handleSpin();
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
        const quickButtons = document.querySelectorAll('.bet-quick-btn-slot-enhanced');
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

    setupAutoSpin() {
        const autoSpinCheckbox = document.getElementById('autoSpinCheckbox');
        const autoSpinCountInput = document.getElementById('autoSpinCount');
        
        if (autoSpinCheckbox) {
            autoSpinCheckbox.addEventListener('change', (e) => {
                if (e.target.checked && this.autoSpinCount < this.maxAutoSpins) {
                    this.startAutoSpin();
                } else {
                    this.stopAutoSpin();
                }
            });
        }

        if (autoSpinCountInput) {
            autoSpinCountInput.addEventListener('change', (e) => {
                const count = parseInt(e.target.value);
                if (count > 0 && count <= this.maxAutoSpins) {
                    this.maxAutoSpins = count;
                }
            });
        }
    }

    handleSpin() {
        if (this.isSpinning) return;

        const form = document.getElementById('gameForm');
        if (!form) return;

        const betInput = document.getElementById('cuocInput');
        if (!betInput || !betInput.value) {
            this.showError('Vui lòng nhập số tiền cược!');
            return;
        }

        // Add spinning animation to reels
        const reels = document.querySelectorAll('.reel-enhanced');
        reels.forEach(reel => {
            reel.classList.add('spinning');
            setTimeout(() => {
                reel.classList.remove('spinning');
            }, 800);
        });

        // Disable button
        const spinButton = document.getElementById('spinButton');
        if (spinButton) {
            spinButton.disabled = true;
            spinButton.innerHTML = '<span class="spinner-small"></span> Đang quay...';
        }

        this.isSpinning = true;

        // Submit form
        form.submit();
    }

    startAutoSpin() {
        if (this.autoSpinCount >= this.maxAutoSpins) {
            this.stopAutoSpin();
            return;
        }

        this.autoSpinCount++;
        setTimeout(() => {
            if (this.autoSpinCount <= this.maxAutoSpins) {
                this.handleSpin();
                this.startAutoSpin();
            }
        }, 2000);
    }

    stopAutoSpin() {
        this.autoSpinCount = 0;
        const checkbox = document.getElementById('autoSpinCheckbox');
        if (checkbox) {
            checkbox.checked = false;
        }
    }

    showError(message) {
        // Có thể dùng toast notification hoặc alert
        console.error(message);
    }

    // Highlight winning reels
    highlightWinningReels(reels) {
        if (reels && reels.length >= 3) {
            const reelElements = document.querySelectorAll('.reel-enhanced');
            if (reels[0] === reels[1] && reels[1] === reels[2]) {
                reelElements.forEach(reel => {
                    reel.classList.add('win');
                    setTimeout(() => {
                        reel.classList.remove('win');
                    }, 2000);
                });
            }
        }
    }
}

// Initialize khi DOM ready
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('gameForm')) {
        window.slotMachineEnhanced = new SlotMachineEnhanced();
    }
});


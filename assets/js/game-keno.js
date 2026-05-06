/**
 * Game Keno - JavaScript
 */

class KenoGame {
    constructor() {
        this.selectedNumbers = [];
        this.maxSelections = 10;
        this.init();
    }

    init() {
        this.setupNumberButtons();
        this.setupQuickBetButtons();
        this.updateDisplay();
    }

    setupNumberButtons() {
        const numberButtons = document.querySelectorAll('.keno-number-btn');
        numberButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const number = parseInt(btn.dataset.number);
                this.toggleNumber(number);
            });
        });
        
        // Clear button
        const clearBtn = document.getElementById('clearNumbers');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                this.clearAll();
            });
        }
    }

    toggleNumber(number) {
        const index = this.selectedNumbers.indexOf(number);
        
        if (index > -1) {
            // Remove
            this.selectedNumbers.splice(index, 1);
        } else {
            // Add (if under limit)
            if (this.selectedNumbers.length < this.maxSelections) {
                this.selectedNumbers.push(number);
            } else {
                alert('Chỉ được chọn tối đa ' + this.maxSelections + ' số!');
                return;
            }
        }
        
        this.updateDisplay();
    }

    clearAll() {
        this.selectedNumbers = [];
        this.updateDisplay();
    }

    updateDisplay() {
        // Update button states
        const numberButtons = document.querySelectorAll('.keno-number-btn');
        numberButtons.forEach(btn => {
            const number = parseInt(btn.dataset.number);
            if (this.selectedNumbers.includes(number)) {
                btn.classList.add('selected');
            } else {
                btn.classList.remove('selected');
            }
        });
        
        // Update selected count
        const countEl = document.getElementById('selectedCount');
        if (countEl) {
            countEl.textContent = this.selectedNumbers.length;
        }
        
        // Update selected numbers list
        const listEl = document.getElementById('selectedNumbersList');
        if (listEl) {
            listEl.innerHTML = '';
            this.selectedNumbers.sort((a, b) => a - b).forEach(num => {
                const span = document.createElement('span');
                span.className = 'selected-number-tag';
                span.textContent = num;
                listEl.appendChild(span);
            });
        }
        
        // Update hidden input
        const input = document.getElementById('selectedNumbersInput');
        if (input) {
            input.value = JSON.stringify(this.selectedNumbers);
        }
    }

    setupQuickBetButtons() {
        const quickButtons = document.querySelectorAll('.bet-quick-btn-keno-enhanced');
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
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('kenoForm')) {
        window.kenoGame = new KenoGame();
        
        // Restore selected numbers if any
        const selectedInput = document.getElementById('selectedNumbersInput');
        if (selectedInput && selectedInput.value) {
            try {
                const saved = JSON.parse(selectedInput.value);
                if (Array.isArray(saved) && saved.length > 0) {
                    window.kenoGame.selectedNumbers = saved;
                    window.kenoGame.updateDisplay();
                }
            } catch (e) {
                console.error('Error parsing selected numbers:', e);
            }
        }
    }
});


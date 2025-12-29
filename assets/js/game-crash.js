/**
 * Game Crash - JavaScript
 */

class CrashGame {
    constructor() {
        this.isRunning = false;
        this.multiplier = 1.00;
        this.animationFrame = null;
        this.init();
    }

    init() {
        this.setupQuickBetButtons();
        this.setupGraph();
        
        // Check if game is running
        if (document.querySelector('.active-bet-display')) {
            this.startGame();
        }
    }

    setupQuickBetButtons() {
        const quickButtons = document.querySelectorAll('.bet-quick-btn-crash-enhanced');
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

    setupGraph() {
        const graph = document.getElementById('crashGraph');
        if (!graph) return;

        const canvas = document.createElement('canvas');
        canvas.width = graph.offsetWidth;
        canvas.height = graph.offsetHeight;
        graph.appendChild(canvas);
        
        this.ctx = canvas.getContext('2d');
        this.canvas = canvas;
    }

    startGame() {
        if (this.isRunning) return;
        
        this.isRunning = true;
        this.multiplier = 1.00;
        this.animate();
    }

    animate() {
        if (!this.isRunning) return;

        // Increment multiplier
        this.multiplier += 0.01;
        
        // Update display
        const display = document.getElementById('multiplierDisplay');
        if (display) {
            const valueEl = display.querySelector('.multiplier-value');
            if (valueEl) {
                valueEl.textContent = this.multiplier.toFixed(2) + 'x';
            }
        }
        
        // Draw graph
        this.drawGraph();
        
        // Continue animation
        this.animationFrame = requestAnimationFrame(() => this.animate());
    }

    drawGraph() {
        if (!this.ctx || !this.canvas) return;

        const ctx = this.ctx;
        const width = this.canvas.width;
        const height = this.canvas.height;
        
        // Clear
        ctx.clearRect(0, 0, width, height);
        
        // Draw background gradient
        const gradient = ctx.createLinearGradient(0, 0, 0, height);
        gradient.addColorStop(0, 'rgba(102, 126, 234, 0.1)');
        gradient.addColorStop(1, 'rgba(118, 75, 162, 0.1)');
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, width, height);
        
        // Draw multiplier line
        ctx.strokeStyle = '#667eea';
        ctx.lineWidth = 3;
        ctx.beginPath();
        
        const maxMultiplier = Math.min(this.multiplier, 10.00);
        const x = (maxMultiplier / 10.00) * width;
        const y = height - (maxMultiplier / 10.00) * height;
        
        ctx.moveTo(0, height);
        ctx.lineTo(x, y);
        ctx.stroke();
        
        // Draw point
        ctx.fillStyle = '#667eea';
        ctx.beginPath();
        ctx.arc(x, y, 6, 0, Math.PI * 2);
        ctx.fill();
    }

    stopGame() {
        this.isRunning = false;
        if (this.animationFrame) {
            cancelAnimationFrame(this.animationFrame);
        }
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    window.crashGame = new CrashGame();
});


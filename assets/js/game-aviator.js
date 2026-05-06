/**
 * Game Aviator - JavaScript
 */

class AviatorGame {
    constructor() {
        this.isFlying = false;
        this.multiplier = 1.00;
        this.animationFrame = null;
        this.init();
    }

    init() {
        this.setupQuickBetButtons();
        this.setupGraph();
        
        // Check if game is running
        if (document.querySelector('.active-bet-display-aviator')) {
            this.startFlight();
        }
    }

    setupQuickBetButtons() {
        const quickButtons = document.querySelectorAll('.bet-quick-btn-aviator-enhanced');
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
        const graph = document.getElementById('aviatorGraph');
        if (!graph) return;

        const canvas = document.createElement('canvas');
        canvas.width = graph.offsetWidth;
        canvas.height = graph.offsetHeight;
        graph.appendChild(canvas);
        
        this.ctx = canvas.getContext('2d');
        this.canvas = canvas;
    }

    startFlight() {
        if (this.isFlying) return;
        
        this.isFlying = true;
        this.multiplier = 1.00;
        const airplane = document.getElementById('airplane');
        if (airplane) {
            airplane.classList.add('flying');
        }
        this.animate();
    }

    animate() {
        if (!this.isFlying) return;

        // Increment multiplier
        this.multiplier += 0.01;
        
        // Update display
        const display = document.getElementById('multiplierDisplay');
        if (display) {
            const valueEl = display.querySelector('.multiplier-value-aviator');
            if (valueEl) {
                valueEl.textContent = this.multiplier.toFixed(2) + 'x';
            }
        }
        
        // Update airplane position
        const airplane = document.getElementById('airplane');
        if (airplane) {
            const container = document.querySelector('.airplane-container');
            if (container) {
                const maxHeight = container.offsetHeight - 100;
                const progress = Math.min((this.multiplier - 1) / 10, 1); // Normalize to 0-1
                const bottom = 50 + (progress * maxHeight);
                airplane.style.bottom = bottom + 'px';
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
        gradient.addColorStop(0, 'rgba(52, 152, 219, 0.1)');
        gradient.addColorStop(1, 'rgba(41, 128, 185, 0.1)');
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, width, height);
        
        // Draw multiplier line
        ctx.strokeStyle = '#3498db';
        ctx.lineWidth = 3;
        ctx.beginPath();
        
        const maxMultiplier = Math.min(this.multiplier, 1000.00);
        const x = (maxMultiplier / 1000.00) * width;
        const y = height - (maxMultiplier / 1000.00) * height;
        
        ctx.moveTo(0, height);
        ctx.lineTo(x, y);
        ctx.stroke();
        
        // Draw point
        ctx.fillStyle = '#3498db';
        ctx.beginPath();
        ctx.arc(x, y, 6, 0, Math.PI * 2);
        ctx.fill();
    }

    stopFlight() {
        this.isFlying = false;
        if (this.animationFrame) {
            cancelAnimationFrame(this.animationFrame);
        }
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('betForm') || document.querySelector('.active-bet-display-aviator')) {
        window.aviatorGame = new AviatorGame();
    }
});


/**
 * Game Wheel - JavaScript Enhanced
 */

class WheelEnhanced {
    constructor() {
        this.canvas = document.getElementById('wheelCanvas');
        this.ctx = null;
        this.isSpinning = false;
        this.currentRotation = 0;
        this.init();
    }

    init() {
        if (!this.canvas) return;
        
        this.ctx = this.canvas.getContext('2d');
        this.drawWheel();
        this.setupEventListeners();
        this.setupQuickBetButtons();
        
        // Nếu có kết quả từ server, hiển thị
        if (typeof wheelResult !== 'undefined' && wheelResult !== null) {
            setTimeout(() => {
                this.showResult(wheelResult);
            }, 500);
        }
    }

    setupEventListeners() {
        const form = document.getElementById('gameForm');
        if (form) {
            form.addEventListener('submit', (e) => {
                if (this.isSpinning) {
                    e.preventDefault();
                    return;
                }
                this.spinWheel();
            });
        }
    }

    setupQuickBetButtons() {
        const quickButtons = document.querySelectorAll('.bet-quick-btn-wheel-enhanced');
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

    drawWheel() {
        if (!this.ctx || !this.canvas) return;
        
        const centerX = this.canvas.width / 2;
        const centerY = this.canvas.height / 2;
        const radius = this.canvas.width / 2 - 10;
        
        if (!wheelSectors || wheelSectors.length === 0) return;
        
        const anglePerSector = (2 * Math.PI) / wheelSectors.length;
        
        // Clear canvas
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        
        // Vẽ các sectors
        wheelSectors.forEach((sector, index) => {
            const startAngle = index * anglePerSector - Math.PI / 2;
            const endAngle = (index + 1) * anglePerSector - Math.PI / 2;
            
            // Vẽ sector
            this.ctx.beginPath();
            this.ctx.moveTo(centerX, centerY);
            this.ctx.arc(centerX, centerY, radius, startAngle, endAngle);
            this.ctx.closePath();
            this.ctx.fillStyle = sector.color;
            this.ctx.fill();
            
            // Vẽ border
            this.ctx.strokeStyle = '#fff';
            this.ctx.lineWidth = 2;
            this.ctx.stroke();
            
            // Vẽ text
            const middleAngle = startAngle + anglePerSector / 2;
            const textDistance = radius * 0.7;
            const textX = centerX + Math.cos(middleAngle) * textDistance;
            const textY = centerY + Math.sin(middleAngle) * textDistance;
            
            this.ctx.save();
            this.ctx.translate(textX, textY);
            this.ctx.rotate(middleAngle + Math.PI / 2);
            
            this.ctx.textAlign = 'center';
            this.ctx.textBaseline = 'middle';
            this.ctx.font = 'bold 14px Arial';
            this.ctx.fillStyle = '#fff';
            this.ctx.strokeStyle = '#000';
            this.ctx.lineWidth = 3;
            this.ctx.strokeText(sector.label, 0, 0);
            this.ctx.fillText(sector.label, 0, 0);
            
            this.ctx.restore();
        });
        
        // Vẽ center circle
        this.ctx.beginPath();
        this.ctx.arc(centerX, centerY, 30, 0, 2 * Math.PI);
        this.ctx.fillStyle = '#2c3e50';
        this.ctx.fill();
        this.ctx.strokeStyle = '#fff';
        this.ctx.lineWidth = 3;
        this.ctx.stroke();
    }

    spinWheel() {
        if (this.isSpinning) return;
        
        this.isSpinning = true;
        const spinButton = document.getElementById('spinButton');
        if (spinButton) {
            spinButton.disabled = true;
            spinButton.textContent = '⏳ Đang quay...';
        }
        
        // Animation sẽ được xử lý bởi server response
        // Canvas sẽ được rotate khi có kết quả
    }

    showResult(sectorIndex) {
        if (!this.canvas || sectorIndex === null || sectorIndex === undefined) return;
        
        // Tính góc rotation để pointer chỉ vào sector
        const targetAngle = (sectorIndex * anglePerSector) + (anglePerSector / 2) - 90;
        const fullRotations = 5; // Quay 5 vòng
        const finalRotation = fullRotations * 360 + targetAngle;
        
        this.canvas.style.transform = `rotate(${finalRotation}deg)`;
        
        // Reset sau animation
        setTimeout(() => {
            this.isSpinning = false;
            const spinButton = document.getElementById('spinButton');
            if (spinButton) {
                spinButton.disabled = false;
                spinButton.textContent = '🎡 Quay Ngay';
    }
        }, 4000);
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('wheelCanvas')) {
        window.wheelEnhanced = new WheelEnhanced();
    }
});

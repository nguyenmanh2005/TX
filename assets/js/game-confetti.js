/**
 * Game Confetti Effects - Hiệu ứng confetti nâng cao cho games
 * Version 2.0
 */

class GameConfetti {
    constructor() {
        this.colors = [
            '#ff6b6b', '#4ecdc4', '#ffe66d', '#a8e6cf', 
            '#ff8b94', '#95e1d3', '#f38181', '#ffd93d',
            '#6bcf7f', '#4d9de0', '#e15554', '#f1a208'
        ];
        this.shapes = ['circle', 'square', 'triangle'];
    }

    /**
     * Tạo confetti với nhiều loại shapes
     */
    createConfetti(count = 100, options = {}) {
        const {
            x = window.innerWidth / 2,
            y = window.innerHeight / 2,
            spread = 360,
            gravity = 0.5,
            duration = 3000,
            colors = this.colors,
            shapes = this.shapes
        } = options;

        const container = document.createElement('div');
        container.className = 'confetti-container';
        container.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 9999;
        `;
        document.body.appendChild(container);

        for (let i = 0; i < count; i++) {
            const confetti = this.createConfettiPiece({
                x,
                y,
                spread,
                gravity,
                duration,
                colors,
                shapes,
                index: i
            });
            container.appendChild(confetti);
        }

        // Remove container sau khi animation xong
        setTimeout(() => {
            container.style.opacity = '0';
            container.style.transition = 'opacity 0.5s';
            setTimeout(() => container.remove(), 500);
        }, duration);
    }

    /**
     * Tạo một piece confetti
     */
    createConfettiPiece(options) {
        const {
            x,
            y,
            spread,
            gravity,
            duration,
            colors,
            shapes,
            index
        } = options;

        const piece = document.createElement('div');
        const size = Math.random() * 12 + 6;
        const color = colors[Math.floor(Math.random() * colors.length)];
        const shape = shapes[Math.floor(Math.random() * shapes.length)];
        const angle = (Math.random() * spread - spread / 2) * Math.PI / 180;
        const velocity = Math.random() * 10 + 5;
        const rotation = Math.random() * 360;
        const rotationSpeed = (Math.random() - 0.5) * 720;
        const delay = Math.random() * 200;

        // Setup shape
        piece.style.position = 'absolute';
        piece.style.width = size + 'px';
        piece.style.height = size + 'px';
        piece.style.backgroundColor = color;
        piece.style.left = x + 'px';
        piece.style.top = y + 'px';
        piece.style.borderRadius = shape === 'circle' ? '50%' : shape === 'triangle' ? '0' : '2px';
        
        if (shape === 'triangle') {
            piece.style.width = '0';
            piece.style.height = '0';
            piece.style.borderLeft = (size / 2) + 'px solid transparent';
            piece.style.borderRight = (size / 2) + 'px solid transparent';
            piece.style.borderBottom = size + 'px solid ' + color;
            piece.style.backgroundColor = 'transparent';
        }

        piece.style.boxShadow = `0 0 ${size}px ${color}`;
        piece.style.transform = `rotate(${rotation}deg)`;
        piece.style.opacity = '1';

        // Animation
        const vx = Math.cos(angle) * velocity;
        const vy = Math.sin(angle) * velocity;
        const endX = x + vx * (duration / 10);
        const endY = y + vy * (duration / 10) + gravity * (duration / 10) * (duration / 10) / 2;
        const endRotation = rotation + rotationSpeed * (duration / 1000);

        piece.style.animation = `
            confettiMove ${duration}ms ease-out ${delay}ms forwards,
            confettiRotate ${duration}ms linear ${delay}ms forwards,
            confettiFade ${duration}ms ease-out ${delay}ms forwards
        `;
        piece.style.setProperty('--end-x', endX + 'px');
        piece.style.setProperty('--end-y', endY + 'px');
        piece.style.setProperty('--end-rotation', endRotation + 'deg');

        return piece;
    }

    /**
     * Confetti từ trên xuống (như mưa)
     */
    createConfettiRain(count = 200, options = {}) {
        const {
            duration = 3000,
            colors = this.colors
        } = options;

        const container = document.createElement('div');
        container.className = 'confetti-rain-container';
        container.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 9999;
        `;
        document.body.appendChild(container);

        for (let i = 0; i < count; i++) {
            const confetti = document.createElement('div');
            const size = Math.random() * 10 + 5;
            const color = colors[Math.floor(Math.random() * colors.length)];
            const startX = Math.random() * window.innerWidth;
            const delay = Math.random() * 2;
            const duration = Math.random() * 2 + 2;

            confetti.style.cssText = `
                position: absolute;
                width: ${size}px;
                height: ${size}px;
                background: ${color};
                left: ${startX}px;
                top: -10px;
                border-radius: 50%;
                box-shadow: 0 0 ${size}px ${color};
                animation: confettiRain ${duration}s linear ${delay}s forwards;
            `;

            container.appendChild(confetti);
        }

        setTimeout(() => {
            container.style.opacity = '0';
            container.style.transition = 'opacity 0.5s';
            setTimeout(() => container.remove(), 500);
        }, 5000);
    }

    /**
     * Confetti nổ từ một điểm
     */
    createConfettiBurst(x, y, count = 150) {
        this.createConfetti(count, {
            x,
            y,
            spread: 360,
            gravity: 0.3,
            duration: 2000
        });
    }

    /**
     * Confetti khi thắng lớn
     */
    createBigWinConfetti() {
        // Nhiều burst từ các điểm khác nhau
        const points = [
            { x: window.innerWidth * 0.2, y: window.innerHeight * 0.3 },
            { x: window.innerWidth * 0.5, y: window.innerHeight * 0.2 },
            { x: window.innerWidth * 0.8, y: window.innerHeight * 0.3 },
            { x: window.innerWidth * 0.3, y: window.innerHeight * 0.7 },
            { x: window.innerWidth * 0.7, y: window.innerHeight * 0.7 }
        ];

        points.forEach((point, index) => {
            setTimeout(() => {
                this.createConfettiBurst(point.x, point.y, 100);
            }, index * 200);
        });

        // Thêm confetti rain
        setTimeout(() => {
            this.createConfettiRain(300);
        }, 500);
    }

    /**
     * Confetti khi thắng bình thường
     */
    createWinConfetti() {
        this.createConfetti(80, {
            x: window.innerWidth / 2,
            y: window.innerHeight / 2,
            spread: 180,
            gravity: 0.4,
            duration: 2500
        });
    }
}

// Add CSS animations
const confettiStyle = document.createElement('style');
confettiStyle.textContent = `
    @keyframes confettiMove {
        0% {
            transform: translate(0, 0) rotate(var(--start-rotation, 0deg));
        }
        100% {
            transform: translate(
                calc(var(--end-x) - var(--start-x, 0px)),
                calc(var(--end-y) - var(--start-y, 0px))
            ) rotate(var(--end-rotation));
        }
    }
    
    @keyframes confettiRotate {
        0% {
            transform: rotate(0deg);
        }
        100% {
            transform: rotate(var(--end-rotation));
        }
    }
    
    @keyframes confettiFade {
        0% {
            opacity: 1;
        }
        80% {
            opacity: 1;
        }
        100% {
            opacity: 0;
        }
    }
    
    @keyframes confettiRain {
        0% {
            transform: translateY(0) rotate(0deg);
            opacity: 1;
        }
        100% {
            transform: translateY(100vh) rotate(720deg);
            opacity: 0;
        }
    }
`;
document.head.appendChild(confettiStyle);

// Initialize
window.gameConfetti = new GameConfetti();

// Auto-trigger khi có class big-win
document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('.big-win')) {
        setTimeout(() => {
            window.gameConfetti.createBigWinConfetti();
        }, 300);
    } else if (document.querySelector('.game-result-win-enhanced')) {
        setTimeout(() => {
            window.gameConfetti.createWinConfetti();
        }, 300);
    }
});

// Export
window.GameConfetti = GameConfetti;









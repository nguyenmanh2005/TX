window.GamePlinko = (function() {
    function initQuickAmounts() {
        const btns = document.querySelectorAll('.bet-quick-btn-enhanced');
        const input = document.querySelector('#cuocInput');
        btns.forEach(btn => {
            btn.addEventListener('click', () => {
                const amount = btn.getAttribute('data-amount');
                if (input) input.value = amount;
            });
        });
    }

    function fireConfetti() {
        // Minimal confetti fallback (non-blocking)
        const duration = 800;
        const end = Date.now() + duration;
        const colors = ['#4ade80', '#60a5fa', '#fbbf24', '#f472b6'];
        (function frame() {
            // use built-in canvas-confetti if available
            if (window.confetti) {
                window.confetti({
                    particleCount: 30,
                    angle: 90,
                    spread: 80,
                    origin: { x: Math.random(), y: Math.random() * 0.4 + 0.1 },
                    colors
                });
            }
            if (Date.now() < end) requestAnimationFrame(frame);
        })();
    }

    document.addEventListener('DOMContentLoaded', () => {
        initQuickAmounts();
    });

    return { fireConfetti };
})();



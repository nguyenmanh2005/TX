window.GameRoulette = (function() {
    function initQuickAmounts() {
        const buttons = document.querySelectorAll('.bet-quick-btn-enhanced');
        const input = document.querySelector('#cuocInput');
        buttons.forEach(btn => {
            btn.addEventListener('click', () => {
                const amount = btn.getAttribute('data-amount');
                if (input && amount) {
                    input.value = amount;
                    input.focus();
                }
                buttons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            });
        });
    }

    function resetFormAfterResult() {
        const hasResult = document.querySelector('.result-banner');
        if (!hasResult) return;
        setTimeout(() => {
            const form = document.getElementById('gameForm');
            const input = document.getElementById('cuocInput');
            const select = document.getElementById('chonSelect');
            if (form) form.reset();
            if (input) input.value = '';
            if (select) select.selectedIndex = 0;
        }, 2500);
    }

    function init() {
        initQuickAmounts();
        resetFormAfterResult();
    }

    return { init };
})();



/**
 * Game effects auto-init
 */
const GameEffectsAuto = {
    init: function() {
        console.log('Game effects auto-init starting...');
        
        // Add ripples to all buttons
        this.applyRipples();
        
        // Observe DOM for new buttons
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.addedNodes.length) {
                    this.applyRipples();
                }
            });
        });
        
        observer.observe(document.body, { childList: true, subtree: true });
    },

    applyRipples: function() {
        const selectors = 'button, .btn, .num-btn, .qbtn, .game-link, .btn-action';
        document.querySelectorAll(selectors).forEach(btn => {
            if (btn.dataset.rippleBound) return;
            
            // Ensure relative positioning for ripple
            if (window.getComputedStyle(btn).position === 'static') {
                btn.style.position = 'relative';
            }
            btn.style.overflow = 'hidden';
            
            btn.addEventListener('mousedown', (e) => {
                if (window.GameEffects) window.GameEffects.addRipple(e);
            });
            
            btn.dataset.rippleBound = "true";
        });
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => GameEffectsAuto.init());
} else {
    GameEffectsAuto.init();
}

window.GameEffectsAuto = GameEffectsAuto;

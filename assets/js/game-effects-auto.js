/**
 * Game effects auto-init
 */
const GameEffectsAuto = {
    init: function() {
        console.log('Game effects auto-init starting...');
        
        // Tự động thêm SoundManager vào tất cả các trang sử dụng script này
(function() {
    // Dùng đường dẫn tuyệt đối để tránh lỗi khi load từ iframe LiveStream
    // Tự phát hiện root của app (thư mục chứa /assets/)
    const origin = window.location.origin;
    const pathParts = window.location.pathname.split('/');
    // Tìm segment đầu sau domain: /1/ hoặc / nếu là root
    const appRoot = '/' + (pathParts[1] && !pathParts[1].includes('.') ? pathParts[1] + '/' : '');
    const assetsBase = origin + appRoot;
    
    // Tự động load SoundManager nếu chưa có
    if (!window.SoundManager) {
        const script = document.createElement('script');
        script.src = assetsBase + 'assets/js/sound-manager.js';
        script.async = false;
        script.onload = () => {
            console.log('🔊 Global SoundManager Loaded');
            initGlobalSounds();
        };
        document.head.appendChild(script);
        
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = assetsBase + 'assets/css/sound-ui.css';
        document.head.appendChild(link);
    } else {
        initGlobalSounds();
    }

    function initGlobalSounds() {
        document.addEventListener('click', (e) => {
            // Phát tiếng nhạc nền lobby nếu là trang chủ
            if (window.location.pathname.endsWith('index.php') || window.location.pathname === '/') {
                if (window.SoundManager) window.SoundManager.startBgMusic();
            }
            
            // Phát tiếng click cho tất cả button và link
            if (e.target.closest('button, a, .chip, .game-card')) {
                if (window.SoundManager) window.SoundManager.play('click');
            }
        });

        document.addEventListener('mouseover', (e) => {
            if (e.target.closest('button, a, .game-card')) {
                if (window.SoundManager) window.SoundManager.play('hover');
            }
        });
    }
})();
        
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

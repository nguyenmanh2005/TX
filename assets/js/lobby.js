document.addEventListener('DOMContentLoaded', () => {
    // Tab Filtering Logic
    const tabs = document.querySelectorAll('.tab-btn');
    const games = document.querySelectorAll('.game-card');

    tabs.forEach(tab => {
        tab.addEventListener('mouseenter', () => { if (typeof SoundManager !== 'undefined') SoundManager.play('hover'); });
        tab.addEventListener('click', () => {
            if (typeof SoundManager !== 'undefined') SoundManager.play('tab');
            const category = tab.dataset.category;

            // Update active tab
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            // Filter games
            games.forEach(game => {
                if (category === 'all' || game.dataset.category === category) {
                    game.style.display = 'block';
                    gsap.fromTo(game, { opacity: 0, scale: 0.8 }, { opacity: 1, scale: 1, duration: 0.4 });
                } else {
                    game.style.display = 'none';
                }
            });
        });
    });

    // Game Cards Interaction
    games.forEach(card => {
        card.addEventListener('mouseenter', () => { if (typeof SoundManager !== 'undefined') SoundManager.play('hover'); });
        card.addEventListener('click', () => { if (typeof SoundManager !== 'undefined') SoundManager.play('click'); });
    });

    // Simple Auto-Slider for Banner
    const slides = document.querySelectorAll('.slide');
    let currentSlide = 0;

    function nextSlide() {
        slides[currentSlide].classList.remove('active');
        currentSlide = (currentSlide + 1) % slides.length;
        slides[currentSlide].classList.add('active');

        gsap.fromTo(slides[currentSlide].querySelector('.slide-content'),
            { x: 50, opacity: 0 },
            { x: 0, opacity: 1, duration: 0.8, ease: "power2.out" }
        );
    }

    if (slides.length > 1) {
        setInterval(nextSlide, 5000);
    }

    // Start Lobby Background Music on first interaction
    document.addEventListener('click', () => {
        if (typeof SoundManager !== 'undefined') SoundManager.startBgMusic();
    }, { once: true });
});

// Sidebar Tab Switching Logic
function showSidebarTab(tabId) {
    document.querySelectorAll('.sidebar-tab-content').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.sidebar-tab-btn').forEach(btn => btn.classList.remove('active'));
    const targetTab = document.getElementById('sidebar-' + tabId);
    if (targetTab) targetTab.classList.add('active');
    const activeBtn = Array.from(document.querySelectorAll('.sidebar-tab-btn')).find(btn => btn.getAttribute('onclick').includes(tabId));
    if (activeBtn) activeBtn.classList.add('active');
}

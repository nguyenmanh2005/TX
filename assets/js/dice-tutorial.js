/**
 * Refined Interactive Tutorial for Dice Slider
 * Positions text at the top and removes background blur for maximum clarity.
 */
const DiceTutorial = {
    isStepRunning: false,

    async start() {
        if (this.isStepRunning) return;
        this.cleanup();

        // Transparent overlay, NO BLUR
        const overlay = $('<div id="tutOverlay" style="position:fixed;inset:0;background:rgba(0,0,0,0.3);z-index:9999;display:flex;flex-direction:column;align-items:center;padding-top:20px;opacity:0;"></div>').appendTo('body');

        // Spotlight element
        $('<div id="tutSpotlight" style="position:fixed;z-index:9998;border-radius:15px;box-shadow:0 0 0 2000px rgba(0,0,0,0.6), 0 0 30px var(--primary);pointer-events:none;opacity:0;"></div>').appendTo('body');

        // Text box at the TOP
        const box = $('<div id="tutBox" style="background:rgba(20,20,20,0.9);border:1px solid var(--primary);border-radius:1.5rem;padding:1.5rem 2rem;max-width:600px;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,0.8);position:relative;z-index:10000;transform:translateY(-20px);"></div>').appendTo(overlay);

        box.html(`
            <h3 style="color:#f5c842;font-family:'Orbitron';margin:0 0 0.5rem 0;font-size:1.1rem;">HƯỚNG DẪN CHIẾN THẮNG</h3>
            <p style="margin:0;font-size:0.9rem;opacity:0.9;">Nhấn nút bên dưới để bắt đầu tìm hiểu cách chơi.</p>
            <button id="startTutBtn" class="btn-roll" style="margin-top:1rem;padding:0.6rem 2rem;font-size:0.8rem;background:var(--primary);color:#000;">BẮT ĐẦU</button>
        `);

        gsap.to(overlay, { opacity: 1, duration: 0.3 });
        gsap.to(box, { y: 0, duration: 0.4, ease: "power2.out" });

        $('#startTutBtn').on('click', () => this.nextStep(1));
    },

    async nextStep(step) {
        this.isStepRunning = true;
        const box = $('#tutBox');

        switch (step) {
            case 1: // Case OVER
                this.updateSpotlight('.mode-toggle', 10);
                this.showStepDesc('Bước 1: Chọn chế độ <b>LỚN HƠN</b>.<br>Thắng nếu kết quả > mốc dự đoán.');
                await this.delay(1000);
                $('#tutOverlay').css('pointer-events', 'none');

                this.createPointer('.mode-btn:first-child');
                await this.delay(1200);
                setMode('over');

                this.updateSpotlight('.dice-container', 20);
                this.createPointer('#sliderHandle');
                await this.animateSlider(70);
                this.showStepDesc('Kéo thanh trượt đến mức <b>70.00</b>.<br>Vùng <b style="color:#2ecc71">XANH</b> (bên phải) là vùng thắng.');
                await this.delay(1800);

                this.updateSpotlight('#rollBtn', 10);
                this.createPointer('#rollBtn');
                await this.delay(800);
                await this.simulateWin(82);
                this.showStepDesc('Kết quả <b>82.00</b> rơi đúng vào vùng xanh!<br><b style="color:#2ecc71;font-size:1.2rem;">CHÚC MỪNG BẠN THẮNG CƯỢC!</b>');

                await this.delay(3500);
                this.nextStep(2);
                break;

            case 2: // Case UNDER
                this.updateSpotlight('.mode-toggle', 10);
                this.showStepDesc('Bước 2: Chuyển sang <b>NHỎ HƠN</b>.');
                await this.delay(1000);

                setMode('under');
                this.createPointer('.mode-btn:last-child');
                this.updateSpotlight('.dice-container', 20);
                await this.animateSlider(35);
                this.showStepDesc('Vùng <b style="color:#2ecc71">XANH</b> bây giờ nằm bên trái.<br>Kết quả < 35.00 là bạn nhận thưởng.');
                await this.delay(1800);

                await this.simulateWin(12);
                this.showStepDesc('Kết quả <b>12.00</b> nằm gọn trong vùng xanh!<br><b style="color:#2ecc71;font-size:1.2rem;">TIẾP TỤC CHIẾN THẮNG!</b>');

                await this.delay(3500);
                this.nextStep(3);
                break;

            case 3: // Finish
                $('#tutOverlay').css('pointer-events', 'auto');
                this.updateSpotlight(null);
                box.html(`
                    <h2 style="color:#2ecc71;font-family:'Orbitron';margin-bottom:0.5rem;">XONG RỒI!</h2>
                    <p style="font-size:0.9rem;">Hãy nhớ: Vùng xanh càng hẹp, Gtlm thưởng càng lớn.</p>
                    <button class="btn-roll" onclick="location.reload()" style="margin-top:1rem;padding:0.6rem 2rem;background:#2ecc71;color:#fff;">CHƠI NGAY!</button>
                `);
                this.cleanup();
                break;
        }
    },

    updateSpotlight(targetSelector, padding = 10) {
        const spotlight = $('#tutSpotlight');
        if (!targetSelector) {
            gsap.to(spotlight, { opacity: 0, duration: 0.3 });
            return;
        }
        const el = $(targetSelector);
        const offset = el.offset();
        gsap.to(spotlight, {
            opacity: 1,
            top: offset.top - padding,
            left: offset.left - padding,
            width: el.outerWidth() + padding * 2,
            height: el.outerHeight() + padding * 2,
            duration: 0.4,
            ease: "power2.out"
        });
    },

    createPointer(target) {
        $('.tut-pointer, .tut-click-wave').remove();
        const el = $(target);
        const offset = el.offset();
        const pointer = $('<div class="tut-pointer" style="position:fixed;z-index:10001;pointer-events:none;font-size:3rem;filter:drop-shadow(0 0 10px rgba(0,0,0,0.5));">✋</div>').appendTo('body');

        gsap.set(pointer, { top: offset.top + el.height() / 2, left: offset.left + el.width() / 2 });
        gsap.fromTo(pointer, { y: 20 }, { y: -10, repeat: -1, yoyo: true, duration: 0.4, ease: "power1.inOut" });

        const wave = $('<div class="tut-click-wave" style="position:fixed;z-index:10000;border:2px solid #f5c842;border-radius:50%;pointer-events:none;"></div>').appendTo('body');
        gsap.set(wave, { top: offset.top + el.height() / 2, left: offset.left + el.width() / 2, width: 10, height: 10, xPercent: -50, yPercent: -50 });
        gsap.to(wave, { width: 80, height: 80, opacity: 0, duration: 0.8, repeat: -1 });
    },

    showStepDesc(text) {
        $('#tutBox').find('p').html(text);
    },

    async animateSlider(target) {
        return new Promise(resolve => {
            const obj = { val: currentTarget };
            gsap.to(obj, {
                val: target, duration: 1.2, ease: "power2.inOut",
                onUpdate: () => { currentTarget = obj.val; updateSlider(); },
                onComplete: resolve
            });
        });
    },

    async simulateWin(result) {
        return new Promise(resolve => {
            const marker = document.getElementById('resultMarker');
            const diceVal = document.getElementById('diceVal');
            marker.classList.remove('visible', 'win', 'lose');

            const tl = gsap.timeline();
            tl.to(marker, { opacity: 1, scale: 1, duration: 0.1 });
            for (let i = 0; i < 6; i++) {
                tl.to(marker, { left: (Math.random() * 80 + 10) + '%', duration: 0.08, onUpdate: () => { diceVal.innerText = Math.floor(Math.random() * 100); } });
            }
            tl.to(marker, {
                left: result + '%', duration: 0.5, ease: "back.out(1.5)",
                onStart: () => { marker.classList.add('visible'); },
                onComplete: () => {
                    diceVal.innerText = result.toFixed(2);
                    marker.classList.add('win');
                    if (window.GameEffects) window.GameEffects.showWin(100000);
                    resolve();
                }
            });
        });
    },

    cleanup() {
        $('.tut-pointer, .tut-click-wave').remove();
    },

    delay(ms) { return new Promise(res => setTimeout(res, ms)); }
};

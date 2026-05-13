<?php
/**
 * Casino Help System - Shared Component
 * Includes GSAP, CSS, and Modal for game instructions
 */
?>
<!-- GSAP Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>

<style>
    /* Help Button Styling */
    .btn-help-game {
        position: fixed;
        top: 20px;
        right: 20px;
        width: 45px;
        height: 45px;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        font-weight: 900;
        cursor: pointer;
        z-index: 9999;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    }

    .btn-help-game:hover {
        background: var(--primary-color, #3498db);
        transform: scale(1.1) rotate(15deg);
        box-shadow: 0 0 20px var(--primary-color, #3498db);
        color: #000;
    }

    /* Modal Overlay */
    .help-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.85);
        backdrop-filter: blur(12px);
        z-index: 100000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    /* Modal Content */
    .help-content {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 2.5rem;
        max-width: 550px;
        width: 100%;
        padding: 3rem;
        color: #fff;
        position: relative;
        box-shadow: 0 30px 60px rgba(0, 0, 0, 0.5);
        transform: translateY(30px);
        opacity: 0;
    }

    .help-header {
        display: flex;
        align-items: center;
        gap: 20px;
        margin-bottom: 2rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        padding-bottom: 1rem;
    }

    .help-icon-large {
        font-size: 3rem;
    }

    .help-title-large {
        font-size: 2rem;
        font-weight: 900;
        color: var(--primary-color, #3498db);
    }

    .help-body {
        font-size: 1.1rem;
        line-height: 1.8;
    }

    .help-step {
        margin-bottom: 15px;
        display: flex;
        gap: 15px;
        opacity: 0;
        transform: translateX(-20px);
    }

    .help-step-num {
        background: var(--primary-color, #3498db);
        color: #000;
        min-width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        flex-shrink: 0;
    }

    .help-close-x {
        position: absolute;
        top: 25px;
        right: 25px;
        background: none;
        border: 2px solid rgba(255, 255, 255, 0.2);
        color: #fff;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        cursor: pointer;
        transition: 0.3s;
        font-size: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .help-close-x:hover {
        background: var(--danger-color, #e74c3c);
        border-color: var(--danger-color, #e74c3c);
        transform: rotate(90deg);
    }

    /* Tutorial Mode Styles */
    .tutorial-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.4);
        z-index: 110000;
        display: none;
        pointer-events: none;
    }

    #tutorialStage {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
    }

    .tutorial-element {
        position: absolute;
        pointer-events: none;
        z-index: 110010;
        filter: drop-shadow(0 10px 20px rgba(0, 0, 0, 0.5));
    }

    .tutorial-hand {
        width: 64px;
        height: 64px;
        position: absolute;
        z-index: 110050;
        pointer-events: none;
        opacity: 0;
    }

    .tutorial-caption {
        position: absolute;
        bottom: 10%;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(10px);
        border: 1px solid var(--primary-color, #3498db);
        padding: 1.5rem 3rem;
        border-radius: 5rem;
        color: white;
        font-size: 1.5rem;
        font-weight: 700;
        text-align: center;
        z-index: 110100;
        opacity: 0;
        box-shadow: 0 0 30px rgba(52, 152, 219, 0.3);
    }

    .btn-watch-demo {
        margin-top: 1.5rem;
        width: 100%;
        padding: 12px;
        background: linear-gradient(135deg, var(--primary-color, #3498db), #2ecc71);
        border: none;
        border-radius: 12px;
        color: white;
        font-weight: 900;
        font-size: 1.1rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        transition: 0.3s;
    }

    .btn-watch-demo:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(46, 204, 113, 0.3);
    }

    .tutorial-highlight {
        position: absolute;
        border: 4px solid #f1c40f;
        border-radius: 10px;
        box-shadow: 0 0 30px #f1c40f;
        z-index: 110005;
        opacity: 0;
        pointer-events: none;
    }

    .btn-skip-tut {
        position: absolute;
        top: 30px;
        right: 30px;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border: 2px solid rgba(255, 255, 255, 0.2);
        color: white;
        padding: 10px 25px;
        border-radius: 30px;
        cursor: pointer;
        z-index: 110200;
        pointer-events: auto;
        font-weight: 900;
        transition: 0.3s;
    }

    .btn-skip-tut:hover {
        background: var(--danger-color, #e74c3c);
        border-color: var(--danger-color, #e74c3c);
    }

    /* Cinematic Card Style */
    .tut-card {
        width: 100px;
        height: 140px;
        background: white;
        border-radius: 12px;
        position: absolute;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 10px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
        border: 1px solid rgba(0, 0, 0, 0.1);
        color: #000;
        z-index: 110020;
        opacity: 0;
        transform: translate(-50%, -50%) scale(0.5);
    }

    .tut-card-val {
        font-size: 1.8rem;
        font-weight: 900;
        line-height: 1;
    }

    .tut-card-suit {
        font-size: 2rem;
        align-self: center;
    }

    .tut-card-suit-small {
        font-size: 1.2rem;
        align-self: flex-end;
        transform: rotate(180deg);
    }

    .red-suit {
        color: #e74c3c;
    }

    /* Ripple Effect */
    .tut-ripple {
        position: absolute;
        width: 100px;
        height: 100px;
        border: 2px solid var(--primary-color, #3498db);
        border-radius: 50%;
        transform: translate(-50%, -50%) scale(0);
        z-index: 110040;
        opacity: 0;
    }
</style>

<!-- Help Button Trigger -->
<div class="btn-help-game" id="helpTrigger" onclick="openCasinoHelp()">?</div>

<!-- Help Modal Structure -->
<div class="help-overlay" id="casinoHelpModal">
    <div class="help-content" id="casinoHelpContent">
        <button class="help-close-x" onclick="closeCasinoHelp()">&times;</button>
        <div class="help-header">
            <div class="help-icon-large" id="modalGameIcon">🃏</div>
            <div class="help-title-large" id="modalGameTitle">Hướng dẫn</div>
        </div>
        <div class="help-body" id="modalGameSteps">
            <!-- Steps will be injected here -->
        </div>
        <button class="btn-watch-demo" onclick="startTutorial()">
            <span>▶️ Xem Video Hướng dẫn (Demo)</span>
        </button>
    </div>
</div>

<!-- Tutorial Stage -->
<div class="tutorial-overlay" id="tutorialOverlay">
    <div id="tutorialStage"></div>
    <div class="tutorial-caption" id="tutorialCaption">Bắt đầu hướng dẫn...</div>
    <img src="https://cdns.iconmonstr.com/wp-content/releases/preview/2012/240/iconmonstr-cursor-14.png"
        class="tutorial-hand" id="tutorialHand" style="filter: invert(1) drop-shadow(2px 2px 5px black);">
    <button class="btn-skip-tut" onclick="stopTutorial()">Bỏ qua hướng dẫn</button>
</div>

<script>
    const helpInstructions = {
        'war': {
            title: 'Casino War',
            icon: '🃏',
            steps: [
                'So sánh lá bài của bạn với Queen GTLM. Lá bài cao hơn sẽ thắng.',
                'Nếu bài bằng nhau, bạn có thể chọn "Chiến tranh" (đặt thêm cược) hoặc "Hàng phục" (mất nửa gtlm).',
                'Trong "Chiến tranh", bạn và Queen GTLM mỗi người được chia thêm 1 lá bài mới để quyết định thắng thua.'
            ]
        },
        'dragontiger': {
            title: 'Long Hổ',
            icon: '🐉',
            steps: [
                'Chọn đặt cược vào cửa Rồng (Dragon) hoặc Hổ (Tiger).',
                'Mỗi bên nhận 1 lá bài, bên có điểm cao hơn sẽ thắng.',
                'Cửa Hòa (Tie) thắng khi hai bên bằng điểm nhau (thưởng 8:1).',
                'Nếu ra Hòa mà bạn cược Rồng/Hổ, bạn sẽ bị trừ 50% số gtlm cược.'
            ]
        },
        'baccarat': {
            title: 'Baccarat Premium',
            icon: '🃏',
            steps: [
                'Đặt cược vào Người chơi (Player), Queen GTLM (Banker) hoặc cửa Hòa (Tie).',
                'Bên nào có tổng điểm gần 9 nhất sẽ thắng (10, J, Q, K tính là 0 điểm).',
                'Hệ thống tự động thực hiện luật rút lá bài thứ 3 theo quy chuẩn quốc tế.'
            ]
        },
        'threecard': {
            title: 'Three Card Poker',
            icon: '🃏',
            steps: [
                'Cược Ante để bắt đầu. Sau khi xem bài, chọn Play (để so bài) hoặc Fold (bỏ bài).',
                'Queen GTLM cần có từ Nữ (Queen) trở lên để đủ điều kiện so bài.',
                'Thứ tự tay bài: Thùng phá sảnh > Xám chi > Sảnh > Thùng > Đôi > Bài cao.'
            ]
        },
        'letitride': {
            title: 'Let It Ride',
            icon: '🃏',
            steps: [
                'Bạn nhận 3 lá bài và có 2 lá bài chung lật sau.',
                'Có 2 giai đoạn để quyết định rút lại hoặc giữ nguyên (Let It Ride) phần cược của mình.',
                'Thắng nếu tạo được tay bài Poker từ Đôi 10 trở lên.'
            ]
        },
        'paigow': {
            title: 'Pai Gow Poker',
            icon: '🃏',
            steps: [
                'Chia 7 lá bài thành 2 chi: Chi 5 lá (Cao) và Chi 2 lá (Thấp).',
                'Tay bài 5 lá phải mạnh hơn tay bài 2 lá.',
                'Bạn thắng nếu cả hai tay bài của mình đều mạnh hơn của Queen GTLM.'
            ]
        },
        'sicbo': {
            title: 'Xanh Đỏ Đối Kháng',
            icon: '🎲',
            steps: [
                'Cược vào kết quả của 3 viên xúc xắc.',
                'Thiên thần (11-17 điểm), Ác quỷ (4-10 điểm).',
                'Có nhiều lựa chọn cược khác như: Cược bộ ba, cược tổng điểm, cược cặp số.'
            ]
        },
        'craps': {
            title: 'Craps (Xúc Xắc)',
            icon: '🎲',
            steps: [
                'Lượt ném đầu (Come-out): 7, 11 thắng ngay; 2, 3, 12 thua ngay.',
                'Các số còn lại trở thành điểm "Point".',
                'Sau đó, ném trúng Point trước khi ném ra số 7 để giành chiến thắng.'
            ]
        },
        'videopoker': {
            title: 'Video Poker',
            icon: '🎰',
            steps: [
                'Nhận 5 lá bài. Chọn giữ (Hold) các lá muốn và đổi các lá còn lại.',
                'Tay bài cuối cùng so với bảng thưởng (Paytable) để nhận gtlm.',
                'Phiên bản Jacks or Better: Thưởng từ đôi J trở lên.'
            ]
        },
        'fantan': {
            title: 'Fan-Tan',
            icon: '🔘',
            steps: [
                'Queen GTLM chia một đống nút thành các nhóm 4 nút.',
                'Nhiệm vụ của bạn là dự đoán số nút dư ở nhóm cuối cùng (1, 2, 3 hoặc 4).'
            ]
        },
        'mahjong': {
            title: 'Mahjong Clash',
            icon: '🀄',
            steps: [
                'Mỗi bên có 3 quân bài Mạt chược. So sánh theo tổ hợp bài.',
                'Thứ tự mạnh yếu: Bộ ba (Triple) > Đôi (Pair) > Bài cao (High Tile).',
                'Quân bài mạnh nhất là các quân Rồng, sau đó là Gió và các số.'
            ]
        },
        'crash': {
            title: 'Crash Flight',
            icon: '🚀',
            steps: [
                'Đặt cược số gtlm bạn muốn trước khi máy bay cất cánh.',
                'Theo dõi hệ số nhân (multiplier) tăng dần từ 1.00x.',
                'Nhấn "NHẢY DÙ" (Cash Out) trước khi máy bay nổ (Crash) để nhận thưởng.',
                'Gtlm thắng = Gtlm cược x Hệ số nhân tại thời điểm bạn nhảy dù.'
            ]
        },
        'mines': {
            title: 'Minesweeper',
            icon: '💣',
            steps: [
                'Chọn số lượng bom trên bản đồ (càng nhiều bom, Gtlm thưởng càng cao).',
                'Lật các ô để tìm Kim cương. Mỗi viên Kim cương tìm thấy sẽ tăng hệ số nhân.',
                'Bạn có thể dừng lại và nhận thưởng bất cứ lúc nào.',
                'Nếu lật trúng Bom, bạn sẽ mất toàn bộ số Gtlm cược.'
            ]
        },
        'plinko': {
            title: 'Plinko Royale',
            icon: '🔴',
            steps: [
                'Chọn mức độ rủi ro (Thấp, Trung bình, Cao) và số hàng (Rows).',
                'Thả bóng và theo dõi nó rơi qua các chướng ngại vật.',
                'Số Gtlm nhận lại tùy thuộc vào ô mà bóng rơi vào ở phía dưới cùng.'
            ]
        },
        'dice': {
            title: 'Dice Master',
            icon: '🎲',
            steps: [
                'Chọn một con số mục tiêu (Target).',
                'Dự đoán kết quả tung xúc xắc sẽ Cao hơn (Over) hoặc Thấp hơn (Under) số đó.',
                'Điều chỉnh tỷ lệ thắng để thay đổi mức thưởng tiềm năng.'
            ]
        },
        'limbo': {
            title: 'Limbo Mania',
            icon: '⚡',
            steps: [
                'Đặt mục tiêu hệ số nhân (Target Multiplier) bạn muốn đạt được.',
                'Nếu kết quả nổ ra cao hơn hoặc bằng mục tiêu của bạn, bạn thắng.',
                'Gtlm thắng sẽ bằng Gtlm cược nhân với mục tiêu bạn đã đặt.'
            ]
        },
        'hilo': {
            title: 'Hi-Lo Cards',
            icon: '🃏',
            steps: [
                'Dự đoán lá bài tiếp theo sẽ Cao hơn (Higher) hoặc Thấp hơn (Lower) lá bài hiện tại.',
                'Bạn có thể tiếp tục dự đoán để tăng mức thưởng hoặc dừng lại để nhận Gtlm.',
                'Lá bài cùng giá trị (Equal) thường tính là thua hoặc tùy theo luật cụ thể.'
            ]
        },
        'keno': {
            title: 'Keno Classic',
            icon: '🎱',
            steps: [
                'Chọn từ 1 đến 10 con số trong bảng từ 1 đến 80.',
                'Hệ thống sẽ rút ngẫu nhiên 20 con số.',
                'Số Gtlm thưởng phụ thuộc vào số lượng số bạn chọn trùng khớp với kết quả.'
            ]
        },
        'tower': {
            title: 'Tower Climb',
            icon: '🏰',
            steps: [
                'Mỗi tầng có các ô chứa vật phẩm hoặc cạm bẫy.',
                'Chọn ô an toàn để leo lên tầng cao hơn và tăng hệ số nhân.',
                'Bạn có thể "Dừng lại" bất cứ lúc nào để thu Gtlm thắng cược.'
            ]
        },
        'baucua': {
            title: 'Thế Giới Linh Thú',
            icon: '🎲',
            steps: [
                'Đặt cược vào một hoặc nhiều linh vật: Bầu, Cua, Tôm, Cá, Gà, Nai.',
                'Ba viên xúc xắc linh vật sẽ được tung.',
                'Bạn thắng nếu xúc xắc hiện ra linh vật bạn đã chọn (thắng gấp đôi nếu ra 2 con, gấp ba nếu ra 3 con).'
            ]
        },
        'xocdia': {
            title: 'Trận Địa Trắng Đỏ',
            icon: '🔘',
            steps: [
                'Dự đoán kết quả của 4 nút (đồng xu) sau khi xóc.',
                'Các cửa cược: Chẵn (4 trắng, 4 đỏ, 2 trắng 2 đỏ) hoặc Lẻ (3 trắng 1 đỏ, 3 đỏ 1 trắng).',
                'Bạn cũng có thể cược chính xác màu sắc (ví dụ: 4 đỏ) để nhận thưởng cao hơn.'
            ]
        }
    };

    function openCasinoHelp() {
        const gameKey = getCurrentGameKey();
        const data = helpInstructions[gameKey];
        if (!data) {
            // Default generic help if game not found
            document.getElementById('modalGameTitle').innerText = 'Hướng dẫn chung';
            document.getElementById('modalGameIcon').innerText = '❓';
            document.getElementById('modalGameSteps').innerHTML = '<div class="help-step"><div class="help-step-num">!</div><div>Trò chơi này đang được cập nhật hướng dẫn chi tiết. Vui lòng quay lại sau!</div></div>';
        } else {
            document.getElementById('modalGameTitle').innerText = data.title;
            document.getElementById('modalGameIcon').innerText = data.icon;

            let html = '';
            data.steps.forEach((step, i) => {
                html += `<div class="help-step">
                            <div class="help-step-num">${i + 1}</div>
                            <div>${step}</div>
                         </div>`;
            });
            document.getElementById('modalGameSteps').innerHTML = html;
        }

        // GSAP Animations
        const modal = document.getElementById('casinoHelpModal');
        modal.style.display = 'flex';

        gsap.to('#casinoHelpContent', {
            y: 0,
            opacity: 1,
            duration: 0.5,
            ease: 'power3.out'
        });

        gsap.to('.help-step', {
            x: 0,
            opacity: 1,
            stagger: 0.1,
            duration: 0.4,
            delay: 0.2
        });
    }

    function closeCasinoHelp() {
        gsap.to('#casinoHelpContent', {
            y: 30,
            opacity: 0,
            duration: 0.3,
            ease: 'power3.in',
            onComplete: () => {
                document.getElementById('casinoHelpModal').style.display = 'none';
                gsap.set('.help-step', { x: -20, opacity: 0 });
            }
        });
    }

    // --- Tutorial Engine ---
    let tutorialTimeline = null;

    function startTutorial() {
        const gameKey = getCurrentGameKey();
        if (!gameKey) return;

        closeCasinoHelp();

        const overlay = document.getElementById('tutorialOverlay');
        const stage = document.getElementById('tutorialStage');
        const caption = document.getElementById('tutorialCaption');
        const hand = document.getElementById('tutorialHand');

        overlay.style.display = 'block';
        stage.innerHTML = '';

        if (tutorialTimeline) tutorialTimeline.kill();
        tutorialTimeline = gsap.timeline({
            onComplete: () => {
                stopTutorial();
            }
        });

        // Universal Intro
        tutorialTimeline.to(caption, { opacity: 1, y: -20, duration: 0.5, text: "Chào mừng bạn đến với hướng dẫn chơi!" });
        tutorialTimeline.to(hand, { opacity: 0.8, x: window.innerWidth / 2, y: window.innerHeight / 2, duration: 1 });

        // Game Specific Tutorial Scripts
        if (gameKey === 'war') runWarTutorial(tutorialTimeline);
        else if (gameKey === 'dragontiger') runDragonTigerTutorial(tutorialTimeline);
        else if (gameKey === 'sicbo') runSicBoTutorial(tutorialTimeline);
        else if (gameKey === 'baccarat') runBaccaratTutorial(tutorialTimeline);
        else runGenericTutorial(tutorialTimeline, gameKey);
    }

    function getCurrentGameKey() {
        const path = window.location.pathname;
        const fileName = path.split('/').pop().split('.')[0];

        // Map common filename variations to keys
        const mapping = {
            'crash': 'crash',
            'mines': 'mines',
            'plinko': 'plinko',
            'dice': 'dice',
            'limbo': 'limbo',
            'hilo': 'hilo',
            'keno': 'keno',
            'tower': 'tower',
            'baucua': 'baucua',
            'xocdia': 'xocdia',
            'war': 'war',
            'dragontiger': 'dragontiger',
            'baccarat': 'baccarat',
            'threecard': 'threecard',
            'letitride': 'letitride',
            'paigow': 'paigow',
            'sicbo': 'sicbo',
            'craps': 'craps',
            'videopoker': 'videopoker',
            'fantan': 'fantan',
            'mahjong': 'mahjong'
        };

        if (mapping[fileName]) return mapping[fileName];

        // Fallback to substring matching if filename doesn't match exactly
        for (let key in mapping) {
            if (path.includes(key)) return mapping[key];
        }

        return fileName; // Return the filename as a last resort
    }

    function runWarTutorial(tl) {
        const stage = document.getElementById('tutorialStage');
        const hand = document.getElementById('tutorialHand');

        updateCaption(tl, "Casino War - Đối đầu trực tiếp với Queen GTLM!");
        tl.to(hand, { x: 200, y: window.innerHeight - 100, duration: 1 });

        // Step 1: Bet
        updateCaption(tl, "Đầu tiên, bạn chọn mức cược mong muốn.");
        tl.to(hand, { scale: 0.8, duration: 0.2, repeat: 1, yoyo: true, onStart: () => playRipple(tl, 200, window.innerHeight - 100) });

        // Step 2: Deal
        updateCaption(tl, "Nhấn DEAL để bắt đầu ván bài...");
        tl.to(hand, { x: window.innerWidth / 2, y: window.innerHeight - 150, duration: 0.8 });
        tl.to(hand, { scale: 0.8, duration: 0.2, repeat: 1, yoyo: true, onStart: () => playRipple(tl, window.innerWidth / 2, window.innerHeight - 150) });

        // Deal Cards
        const pCard = createTutorialCard('A', '♠', '50%', '60%');
        const dCard = createTutorialCard('K', '♥', '50%', '30%');
        stage.appendChild(pCard); stage.appendChild(dCard);

        tl.to(pCard, { opacity: 1, scale: 1.5, y: "-=50", duration: 0.6, ease: "back.out(1.7)" });
        tl.to(dCard, { opacity: 1, scale: 1.5, y: "+=50", duration: 0.6, ease: "back.out(1.7)" }, "-=0.4");

        updateCaption(tl, "Bạn: Ách (14đ) vs Queen GTLM: Già (13đ)");
        tl.to(pCard, { boxShadow: "0 0 40px #f1c40f", scale: 1.7, duration: 0.5 });
        updateCaption(tl, "BÀI CỦA BẠN CAO HƠN! BẠN THẮNG!");

        // Bonus: Show Tie Scenario
        tl.to([pCard, dCard], { opacity: 0, scale: 0.5, duration: 0.5, delay: 1 });
        updateCaption(tl, "Nếu hai bên bằng điểm nhau, bạn có 2 lựa chọn:");

        const pCard2 = createTutorialCard('10', '♦', '50%', '60%');
        const dCard2 = createTutorialCard('10', '♣', '50%', '30%');
        stage.appendChild(pCard2); stage.appendChild(dCard2);
        tl.to([pCard2, dCard2], { opacity: 1, scale: 1.5, duration: 0.5 });

        updateCaption(tl, "1. Hàng phục (Surrender) - Mất nửa gtlm cược.");
        updateCaption(tl, "2. Chiến tranh (Go to War) - Gấp đôi mức cược để phục thù!");
        tl.to(pCard2, { x: "-=20", rotation: -10, duration: 0.3 });
        tl.to(dCard2, { x: "+=20", rotation: 10, duration: 0.3 }, "-=0.3");

        updateCaption(tl, "Hãy sẵn sàng để giành chiến thắng trong Casino War!");
    }

    function runDragonTigerTutorial(tl) {
        const stage = document.getElementById('tutorialStage');
        const hand = document.getElementById('tutorialHand');

        updateCaption(tl, "Long Hổ (Dragon Tiger) - Trò chơi so bài siêu tốc!");

        // Buttons
        const dragonBtn = createTutorialElement('<div style="background:rgba(231, 76, 60, 0.8); padding:30px; border-radius:15px; border:2px solid #fff; font-weight:900; width:150px; text-align:center;">RỒNG</div>', '35%', '50%');
        const tigerBtn = createTutorialElement('<div style="background:rgba(52, 152, 219, 0.8); padding:30px; border-radius:15px; border:2px solid #fff; font-weight:900; width:150px; text-align:center;">HỔ</div>', '65%', '50%');
        stage.appendChild(dragonBtn); stage.appendChild(tigerBtn);

        tl.to([dragonBtn, tigerBtn], { opacity: 1, scale: 1, duration: 0.5 });

        updateCaption(tl, "Bạn chọn đặt cược vào bên Rồng hoặc bên Hổ.");
        tl.to(hand, { x: window.innerWidth * 0.35, y: window.innerHeight * 0.5, duration: 0.8 });
        tl.to(hand, { scale: 0.8, duration: 0.2, repeat: 1, yoyo: true, onStart: () => playRipple(tl, window.innerWidth * 0.35, window.innerHeight * 0.5) });

        // Deal Cards
        updateCaption(tl, "Mỗi bên nhận duy nhất 1 lá bài...");
        const dCard = createTutorialCard('9', '♦', '35%', '30%');
        const tCard = createTutorialCard('5', '♣', '65%', '30%');
        stage.appendChild(dCard); stage.appendChild(tCard);

        tl.to(dCard, { opacity: 1, scale: 1.5, duration: 0.5 });
        tl.to(tCard, { opacity: 1, scale: 1.5, duration: 0.5 }, "-=0.3");

        updateCaption(tl, "Rồng: 9 vs Hổ: 5. RỒNG THẮNG!");
        tl.to(dragonBtn, { scale: 1.2, boxShadow: "0 0 30px #e74c3c", duration: 0.5 });

        updateCaption(tl, "Lưu ý: Nếu kết quả HÒA, bạn sẽ bị trừ 50% gtlm cược.");
        tl.to([dCard, tCard], { opacity: 0, duration: 0.5, delay: 1 });
    }

    function runSicBoTutorial(tl) {
        const stage = document.getElementById('tutorialStage');
        const hand = document.getElementById('tutorialHand');

        updateCaption(tl, "Sic Bo  - Dự đoán điểm của 3 viên xúc xắc.");

        const tai = createTutorialElement('<div style="border:4px solid #f1c40f; padding:40px; border-radius:20px; font-weight:900; background:rgba(0,0,0,0.5);">Thiên thần</div>', '70%', '55%');
        const xiu = createTutorialElement('<div style="border:4px solid #f1c40f; padding:40px; border-radius:20px; font-weight:900; background:rgba(0,0,0,0.5);">Ác quỷ</div>', '30%', '55%');
        stage.appendChild(tai); stage.appendChild(xiu);

        tl.to([tai, xiu], { opacity: 1, scale: 1, duration: 0.5 });

        updateCaption(tl, "Bạn đặt cược vào 'Thiên Thần' (11-17 điểm) hoặc 'Ác Quỷ' (4-10 điểm).");
        tl.to(hand, { x: window.innerWidth * 0.7, y: window.innerHeight * 0.55, duration: 0.8 });
        tl.to(hand, { scale: 0.8, duration: 0.2, repeat: 1, yoyo: true, onStart: () => playRipple(tl, window.innerWidth * 0.7, window.innerHeight * 0.55) });

        updateCaption(tl, "Hệ thống lắc xúc xắc...");

        // Realistic Dice Boxes
        const diceBox = document.createElement('div');
        diceBox.className = 'tutorial-element';
        diceBox.style.left = '50%'; diceBox.style.top = '30%';
        diceBox.style.display = 'flex'; diceBox.style.gap = '20px';
        stage.appendChild(diceBox);

        for (let i = 0; i < 3; i++) {
            const d = document.createElement('div');
            d.style.width = '60px'; d.style.height = '60px';
            d.style.background = 'white'; d.style.borderRadius = '10px';
            d.style.color = 'black'; d.style.display = 'flex';
            d.style.alignItems = 'center'; d.style.justifyContent = 'center';
            d.style.fontSize = '30px'; d.style.fontWeight = '900';
            d.innerHTML = '?';
            diceBox.appendChild(d);
            tl.to(d, { rotation: 360, duration: 0.5 }, "lắc");
        }

        const results = ['4', '5', '6'];
        tl.add(() => {
            diceBox.childNodes.forEach((d, i) => d.innerHTML = results[i]);
        });

        tl.to(diceBox, { scale: 1.2, duration: 0.5 });
        updateCaption(tl, "Tổng điểm là 15 (Thiên Thần). Chúc mừng bạn chiến thắng!");
        tl.to(tai, { scale: 1.3, backgroundColor: "rgba(241, 196, 15, 0.4)", duration: 0.5 });
    }

    function runBaccaratTutorial(tl) {
        const stage = document.getElementById('tutorialStage');
        const hand = document.getElementById('tutorialHand');

        updateCaption(tl, "Baccarat Premium - Người chơi (Player) đối đầu Queen GTLM (Banker)");

        const pBtn = createTutorialElement('<div style="background:rgba(46, 204, 113, 0.8); padding:30px; border-radius:15px; border:2px solid #fff; font-weight:900; width:150px; text-align:center;">PLAYER</div>', '35%', '55%');
        const bBtn = createTutorialElement('<div style="background:rgba(231, 76, 60, 0.8); padding:30px; border-radius:15px; border:2px solid #fff; font-weight:900; width:150px; text-align:center;">BANKER</div>', '65%', '55%');
        stage.appendChild(pBtn); stage.appendChild(bBtn);

        tl.to([pBtn, bBtn], { opacity: 1, scale: 1, duration: 0.5 });

        updateCaption(tl, "Bạn có thể cược vào Player, Banker hoặc cửa Hòa (Tie).");
        tl.to(hand, { x: window.innerWidth * 0.65, y: window.innerHeight * 0.55, duration: 0.8 });
        tl.to(hand, { scale: 0.8, duration: 0.2, repeat: 1, yoyo: true, onStart: () => playRipple(tl, window.innerWidth * 0.65, window.innerHeight * 0.55) });

        updateCaption(tl, "Mục tiêu: Bên nào có tổng điểm gần 9 nhất sẽ thắng.");

        const pCard1 = createTutorialCard('5', '♠', '30%', '30%');
        const pCard2 = createTutorialCard('3', '♥', '40%', '30%');
        const bCard1 = createTutorialCard('10', '♣', '60%', '30%');
        const bCard2 = createTutorialCard('6', '♦', '70%', '30%');

        stage.appendChild(pCard1); stage.appendChild(pCard2);
        stage.appendChild(bCard1); stage.appendChild(bCard2);

        tl.to([pCard1, pCard2], { opacity: 1, scale: 1.2, stagger: 0.2 });
        tl.to([bCard1, bCard2], { opacity: 1, scale: 1.2, stagger: 0.2 }, "-=0.2");

        updateCaption(tl, "Player: 5+3 = 8 điểm | Banker: 10+6 = 6 điểm.");
        tl.to(pBtn, { scale: 1.2, boxShadow: "0 0 30px #2ecc71", duration: 0.5 });
        updateCaption(tl, "Người chơi (Player) giành chiến thắng!");
    }

    function runGenericTutorial(tl, name) {
        const caption = document.getElementById('tutorialCaption');
        tl.to(caption, { text: `Đang chuẩn bị hướng dẫn cho ${name}...`, duration: 0.5, delay: 1 });
        tl.to(caption, { text: "Hệ thống sẽ hướng dẫn bạn cách đặt cược và tối ưu hóa tỷ lệ thắng.", duration: 0.5, delay: 1 });
    }

    function createTutorialElement(content, x, y) {
        const div = document.createElement('div');
        div.className = 'tutorial-element';
        div.innerHTML = content;
        div.style.left = x;
        div.style.top = y;
        div.style.fontSize = '40px';
        div.style.opacity = '0';
        div.style.transform = 'translate(-50%, -50%)';
        return div;
    }

    function createTutorialCard(value, suit, x, y) {
        const isRed = (suit === '♥' || suit === '♦');
        const colorClass = isRed ? 'red-suit' : '';
        const card = document.createElement('div');
        card.className = 'tut-card';
        card.style.left = x;
        card.style.top = y;
        card.innerHTML = `
            <div class="tut-card-val ${colorClass}">${value}</div>
            <div class="tut-card-suit ${colorClass}">${suit}</div>
            <div class="tut-card-val tut-card-suit-small ${colorClass}">${value}</div>
        `;
        return card;
    }

    function playRipple(tl, x, y) {
        const ripple = document.createElement('div');
        ripple.className = 'tut-ripple';
        ripple.style.left = x + 'px';
        ripple.style.top = y + 'px';
        document.getElementById('tutorialStage').appendChild(ripple);

        tl.to(ripple, { scale: 1.5, opacity: 1, duration: 0.3 });
        tl.to(ripple, { scale: 2, opacity: 0, duration: 0.3 }, "-=0.1");
    }

    function updateCaption(tl, text) {
        const caption = document.getElementById('tutorialCaption');
        tl.to(caption, { opacity: 0, y: 10, duration: 0.2 });
        tl.to(caption, {
            opacity: 1,
            y: -20,
            duration: 0.1,
            onStart: () => { caption.innerText = text; }
        });
        tl.fromTo(caption, { letterSpacing: "10px" }, { letterSpacing: "1px", duration: 0.4, ease: "power2.out" }, "-=0.1");
    }

    function closeCasinoHelp() {
        gsap.to('#casinoHelpContent', {
            y: 30,
            opacity: 0,
            duration: 0.3,
            ease: 'power3.in',
            onComplete: () => {
                document.getElementById('casinoHelpModal').style.display = 'none';
                gsap.set('.help-step', { x: -20, opacity: 0 });
            }
        });
    }

    // Modal background click close
    document.getElementById('casinoHelpModal').onclick = function (e) {
        if (e.target === this) closeCasinoHelp();
    };
</script>
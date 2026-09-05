const API_URL = '../api_cyber_racing.php';
let currentCycle = 0;
let currentPhase = '';
let isRacing = false;

let myTotalBet = 0;
let myBetsMap = {};

function fetchState() {
    $.getJSON(API_URL + '?action=get_state', function (data) {
        if (!data.success) return;

        const phase = data.phase;
        const timeLeft = data.time_left;
        const newCycle = data.cycle_id;

        // Update timer
        $('#timerDisplay').text(timeLeft);

        // Update pool
        if (data.total_bets) {
            for (let animal in data.total_bets) {
                $('#pool_' + animal).text(new Intl.NumberFormat().format(data.total_bets[animal]));
            }
        }

        if (data.user_money !== undefined && !window.IS_LIVE_MODE) {
            currentBalanceValue = data.user_money;
            $('#currentBalance').text(new Intl.NumberFormat().format(data.user_money));
        }

        if (currentCycle !== newCycle) {
            currentCycle = newCycle;
            window.currentCycleId = newCycle;
            resetRace();
        }

        if (phase !== currentPhase) {
            currentPhase = phase;
            window.currentPhase = phase;
            handlePhaseChange(phase, data);
        }
    });
}

function handlePhaseChange(phase, data) {
    const overlay = $('#statusOverlay');
    const phaseText = $('#phaseText');
    const btnSubmit = $('#btnSubmitBet');
    const betInputs = $('.bet-input');

    if (phase === 'betting') {
        overlay.show();
        phaseText.text("CHỜ ĐẶT CƯỢC");
        btnSubmit.prop('disabled', false);
        betInputs.prop('disabled', false);
        isRacing = false;
        resetRace();
    }
    else if (phase === 'racing') {
        overlay.hide();
        btnSubmit.prop('disabled', true);
        betInputs.prop('disabled', true);
        if (!isRacing && data.rankings) {
            startRace(data.rankings);
        }
    }
    else if (phase === 'result') {
        overlay.show();
        btnSubmit.prop('disabled', true);
        betInputs.prop('disabled', true);

        if (data.rankings && data.rankings.length > 0) {
            let top1 = data.rankings[0];
            let top2 = data.rankings[1];
            let top3 = data.rankings[2];

            phaseText.html(`TOP 1: <span>${top1.toUpperCase()}</span> | TOP 2: ${top2.toUpperCase()} | TOP 3: ${top3.toUpperCase()}`);

            // Calculate win/loss based on new payouts
            let totalWin = 0;
            if (myBetsMap[top1] > 0) totalWin += myBetsMap[top1] * 3.0;
            if (myBetsMap[top2] > 0) totalWin += myBetsMap[top2] * 2.0;
            if (myBetsMap[top3] > 0) totalWin += myBetsMap[top3] * 0.5;

            if (totalWin > 0) {
                if (window.GameEffects) window.GameEffects.showWin(totalWin);
            } else if (myTotalBet > 0) {
                if (window.GameEffects) window.GameEffects.showLoss(myTotalBet);
            }

            // reset bets for next round
            myTotalBet = 0;
            myBetsMap = {};
            clearBets();
        } else {
            phaseText.text("ĐANG TÍNH TOÁN KẾT QUẢ...");
        }
    }
}

function resetRace() {
    gsap.killTweensOf('.animal-sprite');
    gsap.set('.animal-sprite', { left: '10px', y: 0 });
    $('.flame').hide();
    isRacing = false;
}

function startRace(rankings) {
    isRacing = true;
    $('.flame').show(); // Show boost flames
    const animals = ['wolf', 'fox', 'panther', 'bear', 'eagle'];

    animals.forEach(animal => {
        // Calculate finish time based on rank. Rank 0 (Top 1) crosses first at ~10s.
        let rankIndex = rankings.indexOf(animal);
        if (rankIndex === -1) rankIndex = 4;
        let duration = 8 + (rankIndex * 1.5) + (Math.random() * 0.5);

        // Bounce effect
        gsap.to('#anim_' + animal, {
            y: "random(-10, 10)",
            duration: 0.2,
            repeat: -1,
            yoyo: true
        });

        gsap.to('#anim_' + animal, {
            left: 'calc(95% - 40px)', // Finish line
            duration: duration,
            ease: "power2.inOut",
            onComplete: () => {
                gsap.killTweensOf('#anim_' + animal, "y"); // stop bouncing
                gsap.to('#anim_' + animal, { y: 0, duration: 0.2 });
                $(`#anim_${animal} .flame`).hide(); // hide flame
            }
        });
    });

    // Play sound if possible
    try {
        const audio = new Audio('https://assets.mixkit.co/sfx/preview/mixkit-futuristic-engine-power-up-3211.mp3');
        audio.volume = 0.5;
        audio.play();
    } catch (e) { }
}

let selectedAnimals = [];
let currentBalanceValue = 0;

function selectAnimal(animal) {
    if (currentPhase !== 'betting') return;
    const tile = $(`.bet-item[data-animal="${animal}"]`);
    if (selectedAnimals.includes(animal)) {
        selectedAnimals = selectedAnimals.filter(a => a !== animal);
        tile.removeClass('active');
    } else {
        selectedAnimals.push(animal);
        tile.addClass('active');
    }
}

function updateBetInput(animal) {
    if (currentPhase !== 'betting') return;
    let val = parseInt($(`#bet_${animal}`).val());
    if (isNaN(val) || val < 0) {
        $(`#bet_${animal}`).val('');
    }
    calculatePotentialWin();
}

function calculatePotentialWin() {
    const animals = ['wolf', 'fox', 'panther', 'bear', 'eagle'];
    animals.forEach(animal => {
        let current = parseInt($(`#bet_${animal}`).val()) || 0;
        let win = current * 3; // Max win (Top 1)
        $(`#win_${animal}`).text(new Intl.NumberFormat().format(win) + ' GTLM');
    });
}

function addBetSelected(amount) {
    if (currentPhase !== 'betting') return;
    if (selectedAnimals.length === 0) {
        Swal.fire('Chú ý', 'Vui lòng chọn ít nhất một con thú trước khi thêm GTLM cược!', 'info');
        return;
    }

    // Calculate current total bet in inputs
    let currentTotalInput = 0;
    $('.bet-input').each(function () {
        currentTotalInput += parseInt($(this).val()) || 0;
    });

    if (amount === 'ALL') {
        let maxCanBet = currentBalanceValue - currentTotalInput;
        if (maxCanBet <= 0) return;

        let splitAmount = Math.floor(maxCanBet / selectedAnimals.length);
        selectedAnimals.forEach(animal => {
            let input = $(`#bet_${animal}`);
            let current = parseInt(input.val()) || 0;
            input.val(current + splitAmount);
        });
    } else {
        selectedAnimals.forEach(animal => {
            let input = $(`#bet_${animal}`);
            let current = parseInt(input.val()) || 0;
            input.val(current + amount);
        });
    }
    calculatePotentialWin();
}

function clearBets() {
    $('.bet-input').val('');
    $('.bet-item').removeClass('active');
    selectedAnimals = [];
    calculatePotentialWin();
}

function submitBet() {
    if (currentPhase !== 'betting') {
        Swal.fire('Lỗi', 'Đã hết thời gian cược!', 'error');
        return;
    }

    let bets = [];
    myTotalBet = 0;
    myBetsMap = {};
    $('.bet-input').each(function () {
        let amount = parseInt($(this).val());
        if (amount > 0) {
            const animal = $(this).attr('id').replace('bet_', '');
            bets.push({
                animal: animal,
                amount: amount
            });
            myTotalBet += amount;
            myBetsMap[animal] = amount;
        }
    });

    if (bets.length === 0) {
        Swal.fire('Lỗi', 'Vui lòng nhập số GTLM cược!', 'warning');
        return;
    }

    $('#btnSubmitBet').prop('disabled', true).text('ĐANG XỬ LÝ...');

    $.post(API_URL, {
        action: 'bet',
        bets: JSON.stringify(bets)
    }, function (res) {
        if (res.success) {
            Swal.fire({
                title: 'Thành công!',
                text: 'Đã đặt cược thành công',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        } else {
            Swal.fire('Thất bại', res.message, 'error');
            myTotalBet = 0;
            myBetsMap = {};
        }
        $('#btnSubmitBet').prop('disabled', false).text('XÁC NHẬN CƯỢC');
    }, 'json').fail(function () {
        Swal.fire('Lỗi', 'Không thể kết nối đến máy chủ', 'error');
        $('#btnSubmitBet').prop('disabled', false).text('XÁC NHẬN CƯỢC');
        myTotalBet = 0;
        myBetsMap = {};
    });
}

// Start loop
setInterval(fetchState, 1000);
fetchState();

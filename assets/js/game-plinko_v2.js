$(document).ready(function() {
    const canvas = document.getElementById('plinkoCanvas');
    const ctx = canvas.getContext('2d');
    const pocketsWrapper = document.getElementById('pocketsWrapper');
    
    let config = null;
    let currentRows = 12;
    let currentRisk = 'medium';
    
    let pins = [];
    let balls = [];
    let multipliers = [];
    
    let isPlaying = false;
    let activeBalls = 0;
    let sessionWin = 0;
    let sessionBet = 0;

    // Canvas settings
    const CANVAS_WIDTH = 800;
    let CANVAS_HEIGHT = 600;
    
    // Physics & Layout settings
    const PIN_RADIUS = 4;
    const BALL_RADIUS = 7;
    let ROW_SPACING = 40;
    let COL_SPACING = 45;
    
    function init() {
        $.get('../api_plinko_v2.php?action=config', function(res) {
            if (res.success) {
                config = res.config;
                setupBoard();
                resizeCanvas();
                requestAnimationFrame(render);
            }
        });
        
        updateTotalBet();
    }
    
    function setupBoard() {
        if (!config) return;
        multipliers = config[currentRows][currentRisk];
        
        // Calculate dynamic spacing
        // Width needed for max row: (currentRows + 2) * COL_SPACING
        // We want it to fit in CANVAS_WIDTH
        COL_SPACING = Math.min(45, (CANVAS_WIDTH - 100) / (currentRows + 2));
        ROW_SPACING = COL_SPACING * 0.9;
        
        CANVAS_HEIGHT = (currentRows + 3) * ROW_SPACING;
        canvas.width = CANVAS_WIDTH;
        canvas.height = CANVAS_HEIGHT;
        
        pins = [];
        
        // Generate pins (Pascal's triangle shape)
        for (let r = 0; r < currentRows; r++) {
            const rowPins = r + 3; // start with 3 pins at top
            const startX = CANVAS_WIDTH / 2 - ((rowPins - 1) * COL_SPACING) / 2;
            const y = 40 + r * ROW_SPACING;
            
            for (let i = 0; i < rowPins; i++) {
                pins.push({
                    x: startX + i * COL_SPACING,
                    y: y,
                    hitFlash: 0
                });
            }
        }
        
        buildPockets();
    }
    
    function buildPockets() {
        pocketsWrapper.innerHTML = '';
        const numPockets = currentRows + 1;
        const pocketWidth = COL_SPACING - 4;
        
        multipliers.forEach((mult, i) => {
            const div = document.createElement('div');
            div.className = 'pocket';
            div.style.width = pocketWidth + 'px';
            div.style.height = '35px';
            div.innerText = mult + 'x';
            
            // Color logic based on multiplier value
            if (mult >= 10) div.classList.add('pkt-ultra');
            else if (mult >= 3) div.classList.add('pkt-high');
            else if (mult >= 1.5) div.classList.add('pkt-mid');
            else if (mult >= 1) div.classList.add('pkt-mid-low');
            else div.classList.add('pkt-low');
            
            div.id = 'pocket-' + i;
            pocketsWrapper.appendChild(div);
        });
        
        // Adjust pocket wrapper width
        pocketsWrapper.style.width = (numPockets * COL_SPACING) + 'px';
    }
    
    function render() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        // Draw pins
        pins.forEach(pin => {
            ctx.beginPath();
            ctx.arc(pin.x, pin.y, PIN_RADIUS, 0, Math.PI * 2);
            
            if (pin.hitFlash > 0) {
                ctx.fillStyle = '#fff';
                ctx.shadowBlur = 15;
                ctx.shadowColor = '#fff';
                pin.hitFlash -= 0.05;
            } else {
                ctx.fillStyle = 'rgba(255,255,255,0.6)';
                ctx.shadowBlur = 5;
                ctx.shadowColor = 'rgba(255,255,255,0.3)';
            }
            
            ctx.fill();
            ctx.shadowBlur = 0;
        });
        
        // Draw balls
        balls.forEach(ball => {
            ctx.beginPath();
            ctx.arc(ball.x, ball.y, BALL_RADIUS, 0, Math.PI * 2);
            ctx.fillStyle = '#f1c40f'; // Accent color
            ctx.shadowBlur = 10;
            ctx.shadowColor = '#f1c40f';
            ctx.fill();
            ctx.shadowBlur = 0;
            
            // Trail
            if (ball.trail && ball.trail.length > 0) {
                ctx.beginPath();
                ctx.moveTo(ball.trail[0].x, ball.trail[0].y);
                for(let i=1; i<ball.trail.length; i++) {
                    ctx.lineTo(ball.trail[i].x, ball.trail[i].y);
                }
                ctx.strokeStyle = 'rgba(241, 196, 15, 0.3)';
                ctx.lineWidth = 2;
                ctx.stroke();
            }
        });
        
        requestAnimationFrame(render);
    }
    
    function dropBall(data) {
        activeBalls++;
        
        const ball = {
            x: CANVAS_WIDTH / 2 + (Math.random() * 10 - 5),
            y: -10,
            trail: []
        };
        balls.push(ball);
        
        const tl = gsap.timeline({
            onComplete: () => {
                // Ball reached pocket
                const pkt = document.getElementById('pocket-' + data.slot);
                if (pkt) {
                    pkt.classList.add('hit');
                    setTimeout(() => pkt.classList.remove('hit'), 200);
                }
                
                // Remove ball
                balls = balls.filter(b => b !== ball);
                activeBalls--;
                
                updateSessionProfit(data.winAmount);
                
                if (window.GameEffects && data.multiplier >= 2) {
                    const rect = pkt.getBoundingClientRect();
                    window.GameEffects.showWin(data.winAmount);
                    window.GameEffects.plinkoTrail(rect.left + rect.width/2, rect.top, '#f1c40f');
                }
                
                checkGameEnd();
            }
        });
        
        let curX = CANVAS_WIDTH / 2;
        let curY = 40; // first row y
        
        // Drop to first pin
        tl.to(ball, {
            y: curY - PIN_RADIUS - BALL_RADIUS,
            duration: 0.2,
            ease: "power1.in"
        });
        
        // Traverse path
        data.path.forEach((dir, r) => {
            const nextY = 40 + (r + 1) * ROW_SPACING;
            const xOffset = COL_SPACING / 2;
            const nextX = curX + (dir === 1 ? xOffset : -xOffset);
            
            tl.to(ball, {
                x: nextX,
                y: nextY - PIN_RADIUS - BALL_RADIUS,
                duration: 0.3,
                ease: "bounce.out",
                onUpdate: () => {
                    ball.trail.push({x: ball.x, y: ball.y});
                    if (ball.trail.length > 10) ball.trail.shift();
                },
                onStart: () => {
                    // Find nearest pin to flash
                    pins.forEach(p => {
                        if (Math.abs(p.x - curX) < 10 && Math.abs(p.y - curY) < 10) {
                            p.hitFlash = 1.0;
                        }
                    });
                }
            });
            
            curX = nextX;
            curY = nextY;
        });
        
        // Drop into pocket exactly at center
        const pocketTargetY = CANVAS_HEIGHT + 20;
        const pktEl = document.getElementById('pocket-' + data.slot);
        let targetX = curX;
        if (pktEl && pocketsWrapper) {
            const pRect = pktEl.getBoundingClientRect();
            const wRect = pocketsWrapper.getBoundingClientRect();
            const offsetInWrapper = (pRect.left - wRect.left) + (pRect.width / 2);
            const startX = CANVAS_WIDTH / 2 - (((currentRows + 1) * COL_SPACING) / 2);
            targetX = startX + offsetInWrapper;
        }
        tl.to(ball, {
            x: targetX,
            y: pocketTargetY,
            duration: 0.22,
            ease: "power2.in"
        });
    }

    function updateSessionProfit(winAmount) {
        sessionWin += winAmount;
        const profit = sessionWin - sessionBet;
        const profitEl = $('#sessionProfit');
        
        if (profit >= 0) {
            profitEl.text('+' + profit.toLocaleString('vi-VN'));
            profitEl.removeClass('loss');
        } else {
            profitEl.text(profit.toLocaleString('vi-VN'));
            profitEl.addClass('loss');
        }
        
        if (window.GameEffects && winAmount > 0) {
            // Optional big win trigger if single ball drops massive multiplier
        }
    }

    function checkGameEnd() {
        if (activeBalls === 0) {
            $('#btnPlay').prop('disabled', false).text('🟢 THẢ BÓNG');
            isPlaying = false;
        }
    }

    // UI Events
    $('.segment-btn').click(function() {
        if (isPlaying) return; // Prevent changing during play
        
        $(this).siblings().removeClass('active');
        $(this).addClass('active');
        
        if ($(this).parent().attr('id') === 'riskControl') {
            currentRisk = $(this).data('val');
        } else {
            currentRows = parseInt($(this).data('val'));
        }
        
        setupBoard();
    });
    
    function updateTotalBet() {
        const bet = parseFloat($('#betAmount').val()) || 0;
        const count = parseInt($('#ballCount').val()) || 1;
        $('#totalBetDisplay').text((bet * count).toLocaleString('vi-VN'));
    }
    
    $('#betAmount, #ballCount').on('input change', updateTotalBet);
    
    window.setBet = function(val) {
        if (val === 'ALL') {
            $('#betAmount').val(window.USER_MONEY);
            $('#ballCount').val(1);
        } else {
            $('#betAmount').val(val);
        }
        updateTotalBet();
    };
    
    $('#btnPlay').click(function() {
        if (isPlaying) return;
        
        const bet = parseFloat($('#betAmount').val()) || 0;
        const ballCount = parseInt($('#ballCount').val()) || 1;
        const totalBet = bet * ballCount;
        
        if (totalBet > window.USER_MONEY) {
            Swal.fire('Lỗi', 'Số dư không đủ!', 'error');
            return;
        }
        if (totalBet < 1000) {
            Swal.fire('Lỗi', 'Cược tối thiểu 1.000 GTLM', 'error');
            return;
        }
        
        isPlaying = true;
        $(this).prop('disabled', true).text('⏳ ĐANG THẢ...');
        
        // Reset session if it's a new round of clicks
        if (activeBalls === 0) {
            sessionWin = 0;
            sessionBet = totalBet;
            updateSessionProfit(0); // Reset display
        } else {
            sessionBet += totalBet; // Add to ongoing session
        }
        
        // Update local money optimistically
        window.USER_MONEY -= totalBet;
        $('#userMoney').text(window.USER_MONEY.toLocaleString('vi-VN'));
        
        $.post('../api_plinko_v2.php?action=drop', {
            bet: totalBet,
            ballCount: ballCount,
            rows: currentRows,
            risk: currentRisk
        }, function(res) {
            if (res.success) {
                window.USER_MONEY = parseFloat(res.money);
                $('#userMoney').text(window.USER_MONEY.toLocaleString('vi-VN'));
                
                // Stagger ball drops
                res.results.forEach((data, i) => {
                    setTimeout(() => {
                        dropBall(data);
                    }, i * 150); // 150ms between balls
                });
                
            } else {
                Swal.fire('Lỗi', res.message, 'error');
                $('#btnPlay').prop('disabled', false).text('🟢 THẢ BÓNG');
                isPlaying = false;
                
                // Restore money if failed
                window.USER_MONEY += totalBet;
                $('#userMoney').text(window.USER_MONEY.toLocaleString('vi-VN'));
            }
        });
    });

    function resizeCanvas() {
        const container = document.getElementById('scaleContainer');
        const wrapper = document.getElementById('scaleWrapper');
        if (container && wrapper) {
            // Tính toán tỷ lệ scale dựa trên width & height của container vs kích thước Canvas
            // Thêm padding cho height (khoảng 40px cho phần pocket bottom)
            const scaleX = container.clientWidth / CANVAS_WIDTH;
            const scaleY = container.clientHeight / (CANVAS_HEIGHT + 40);
            
            // Lấy scale nhỏ nhất để đảm bảo không bị cắt viền
            let scale = Math.min(1, scaleX, scaleY);
            
            // Giới hạn không cho thu nhỏ quá 55% để tránh mờ/khó nhìn
            scale = Math.max(0.55, scale);
            
            // Căn giữa mượt mà
            wrapper.style.transform = `scale(${scale})`;
            wrapper.style.transformOrigin = 'center center';
            
            // Xử lý loại bỏ thanh cuộn ảo: do transform không thay đổi kích thước DOM thực,
            // ta dùng margin âm để gọt bớt kích thước ảo.
            const marginY = -((CANVAS_HEIGHT + 40) * (1 - scale)) / 2;
            const marginX = -(CANVAS_WIDTH * (1 - scale)) / 2;
            wrapper.style.marginTop = `${marginY}px`;
            wrapper.style.marginBottom = `${marginY}px`;
            wrapper.style.marginLeft = `${marginX}px`;
            wrapper.style.marginRight = `${marginX}px`;
        }
    }

    $(window).resize(resizeCanvas);

    // Run
    init();
});

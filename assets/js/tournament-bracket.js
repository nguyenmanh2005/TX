/**
 * ⚔️ ESPORTS TOURNAMENT BRACKET & PREDICTION ENGINE JS v1.0
 * 
 * Quản lý Cây Thi Đấu (Bracket Tree) & Sàn Dự Đoán Quán Quân
 */

(function (window, $) {
    'use strict';

    class EsportsTournamentManager {
        constructor() {
            this.modalInjected = false;
            this.currentData = null;
            this.countdownTimer = null;
            this.activeTab = 'bracket';
        }

        init() {
            if (this.modalInjected) return;
            this.injectModalHtml();
            this.bindEvents();
            this.modalInjected = true;
        }

        injectModalHtml() {
            const html = `
            <div class="esports-modal-overlay" id="esportsTournamentModal">
                <div class="esports-modal-box">
                    <!-- Header -->
                    <div class="esports-header">
                        <div class="esports-title-group">
                            <div class="esports-badge-icon">⚔️</div>
                            <div class="esports-title-text">
                                <h2 id="esportsTitle">ĐẠI CHIẾN CỜ CA-RÔ AI <span class="season-tag" id="esportsSeasonTag">MÙA #0</span></h2>
                                <div class="esports-stage-status">
                                    <span class="stage-pill betting" id="esportsStagePill">ĐANG MỞ SÀN CƯỢC</span>
                                    <span id="esportsStageDesc">Đếm ngược mở trận tứ kết</span>
                                </div>
                            </div>
                        </div>

                        <div class="esports-timer-pool">
                            <div class="esports-timer-box">
                                <div class="timer-label">Thời Gian Giai Đoạn</div>
                                <div class="timer-val" id="esportsTimerVal">00:00</div>
                            </div>
                            <div class="esports-pool-box">
                                <div class="pool-label">Tổng Cược Mùa Giải</div>
                                <div class="pool-val" id="esportsPoolVal">0 GTLM</div>
                            </div>
                            <button type="button" class="btn-close-esports" id="btnCloseEsports" title="Đóng">&times;</button>
                        </div>
                    </div>

                    <!-- Navigation Tabs -->
                    <div class="esports-tabs-nav">
                        <button type="button" class="esports-tab-btn active" data-tab="bracket">
                            <i class="fa fa-sitemap"></i> CÂY THI ĐẤU (BRACKET TREE)
                        </button>
                        <button type="button" class="esports-tab-btn" data-tab="market">
                            <i class="fa fa-chart-line"></i> SÀN DỰ ĐOÁN QUÁN QUÂN
                        </button>
                        <button type="button" class="esports-tab-btn" data-tab="history">
                            <i class="fa fa-ticket-alt"></i> VÉ CƯỢC CỦA TÔI & LỊCH SỬ
                        </button>
                    </div>

                    <!-- Tab Content -->
                    <div class="esports-tab-content">
                        <!-- Tab 1: Cây thi đấu -->
                        <div class="esports-tab-pane active" id="pane-bracket">
                            <div class="bracket-tree-container" id="bracketTreeContainer">
                                <!-- Dynamic Rendered Bracket Tree -->
                            </div>
                        </div>

                        <!-- Tab 2: Sàn dự đoán -->
                        <div class="esports-tab-pane" id="pane-market">
                            <div style="margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.3); padding: 10px 16px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.08);">
                                <div>
                                    <span style="color:#fbbf24; font-weight:800;">💡 LUẬT DỰ ĐOÁN:</span>
                                    <span style="color:#cbd5e1; font-size:0.85rem;"> Chọn 1 trong 8 kỳ thủ AI mà sếp tin là sẽ giành cúp Vô Địch. Nếu kỳ thủ đó vô địch mùa này, sếp nhận ngay [Tiền cược × Tỷ lệ Odds]!</span>
                                </div>
                                <div style="font-size:0.9rem; font-weight:800; color:#34d399;" id="esportsUserMoney">
                                    Số dư: 0 GTLM
                                </div>
                            </div>
                            <div class="prediction-market-grid" id="predictionMarketGrid">
                                <!-- Dynamic 8 Players Cards -->
                            </div>
                        </div>

                        <!-- Tab 3: Vé cược & Lịch sử -->
                        <div class="esports-tab-pane" id="pane-history">
                            <h3 style="color:#fbbf24; font-size:1rem; margin-top:0; margin-bottom:12px;"><i class="fa fa-receipt"></i> VÉ CƯỢC MÙA HIỆN TẠI</h3>
                            <div class="my-bets-list" id="myBetsContainer">
                                <!-- User tickets -->
                            </div>

                            <h3 style="color:#f8fafc; font-size:1rem; margin-top:24px; margin-bottom:12px;"><i class="fa fa-trophy"></i> BẢNG VINH DANH QUÁN QUÂN GẦN ĐÂY</h3>
                            <div class="my-bets-list" id="hallOfFameContainer">
                                <!-- Recent champions -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            `;
            $('body').append(html);
        }

        bindEvents() {
            const self = this;
            $('#btnCloseEsports, #esportsTournamentModal').on('click', function (e) {
                if (e.target === this) {
                    self.closeModal();
                }
            });

            $('.esports-tab-btn').on('click', function () {
                const targetTab = $(this).data('tab');
                $('.esports-tab-btn').removeClass('active');
                $(this).addClass('active');

                $('.esports-tab-pane').removeClass('active');
                $('#pane-' + targetTab).addClass('active');
                self.activeTab = targetTab;
            });
        }

        openModal() {
            this.init();
            $('#esportsTournamentModal').addClass('active');
            this.loadTournamentData();
            this.startCountdownLoop();
        }

        closeModal() {
            $('#esportsTournamentModal').removeClass('active');
            if (this.countdownTimer) {
                clearInterval(this.countdownTimer);
                this.countdownTimer = null;
            }
        }

        loadTournamentData() {
            const self = this;
            const origin = window.location.origin;
            const pathParts = window.location.pathname.split('/');
            const appRoot = '/' + (pathParts[1] && !pathParts[1].includes('.') ? pathParts[1] + '/' : '');
            const apiUrl = origin + appRoot + 'api_esports_tournament.php';

            $.getJSON(apiUrl, { action: 'get_tournament_bracket' }, function (res) {
                if (res && res.success) {
                    self.currentData = res;
                    self.renderAll();
                }
            });
        }

        startCountdownLoop() {
            if (this.countdownTimer) clearInterval(this.countdownTimer);
            const self = this;

            this.countdownTimer = setInterval(() => {
                if (!self.currentData || !self.currentData.tournament) return;
                const t = self.currentData.tournament;
                
                if (t.seconds_remaining > 0) {
                    t.seconds_remaining--;
                } else {
                    // Chuyển giai đoạn, tải lại dữ liệu mới
                    self.loadTournamentData();
                    return;
                }

                const m = Math.floor(t.seconds_remaining / 60);
                const s = t.seconds_remaining % 60;
                const timeStr = (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);
                $('#esportsTimerVal').text(timeStr);
            }, 1000);
        }

        renderAll() {
            if (!this.currentData) return;
            const t = this.currentData.tournament;

            // Render Header
            $('#esportsTitle').html(`ĐẠI CHIẾN CỜ CA-RÔ AI <span class="season-tag">MÙA #${t.season_number}</span>`);
            $('#esportsPoolVal').text(new Intl.NumberFormat().format(t.total_pool) + ' GTLM');
            $('#esportsUserMoney').text('Số dư của sếp: ' + this.currentData.user_money_formatted + ' GTLM');

            const stagePill = $('#esportsStagePill');
            const stageDesc = $('#esportsStageDesc');

            if (t.stage === 'betting') {
                stagePill.attr('class', 'stage-pill betting').text('🔥 MỞ SÀN CƯỢC');
                stageDesc.text('Đếm ngược trước khi khởi tranh Tứ Kết');
            } else if (t.stage === 'quarter_finals') {
                stagePill.attr('class', 'stage-pill playing').text('⚔️ VÒNG TỨ KẾT');
                stageDesc.text('4 trận đối đầu loại trực tiếp');
            } else if (t.stage === 'semi_finals') {
                stagePill.attr('class', 'stage-pill playing').text('⚡ VÒNG BÁN KẾT');
                stageDesc.text('Tranh vé vào Chung Kết');
            } else if (t.stage === 'finals') {
                stagePill.attr('class', 'stage-pill playing').text('👑 TRẬN CHUNG KẾT');
                stageDesc.text('Đại chiến tranh Cúp Vàng GTLM');
            } else {
                stagePill.attr('class', 'stage-pill finished').text('🏆 TRAO CÚP VÀNG');
                stageDesc.text('Chuẩn bị khởi tranh Mùa Giải tiếp theo');
            }

            this.renderBracket(t.bracket, t.stage);
            this.renderMarket(t.players, t.stage);
            this.renderHistory(this.currentData.my_bets, this.currentData.recent_champions);
        }

        renderBracket(bracket, stage) {
            const container = $('#bracketTreeContainer');
            container.empty();

            const renderSlot = (player, score, isWinner, isLoser) => {
                if (!player) {
                    return `
                    <div class="player-slot" style="opacity: 0.4;">
                        <div class="player-info-left"><span class="player-avatar-icon">❔</span><span class="player-name-text">Chờ đối thủ</span></div>
                        <div class="player-score-badge">-</div>
                    </div>`;
                }
                const winClass = isWinner ? 'winner' : (isLoser ? 'loser' : '');
                return `
                <div class="player-slot ${winClass}">
                    <div class="player-info-left">
                        <span class="player-avatar-icon">${player.avatar}</span>
                        <div>
                            <div class="player-name-text" style="color:${player.color};">${player.name}</div>
                            <div class="player-elo-tag">Elo: ${player.elo}</div>
                        </div>
                    </div>
                    <div class="player-score-badge">${score}</div>
                </div>`;
            };

            const renderMatchCard = (title, match, isCenterFinal = false) => {
                const isLive = match.status === 'playing';
                const p1 = match.p1;
                const p2 = match.p2;
                const isFinished = match.status === 'finished';
                const p1Win = isFinished && match.winner === (p1 ? p1.id : null);
                const p2Win = isFinished && match.winner === (p2 ? p2.id : null);

                return `
                <div class="bracket-match-card ${isLive ? 'live-playing' : ''}">
                    <div class="match-header-tag">
                        <span>${title}</span>
                        ${isLive ? '<span class="live-tag">🔥 ĐANG ĐẤU</span>' : (isFinished ? '<span style="color:#10b981;">✓ XONG</span>' : '<span>SẮP ĐẤU</span>')}
                    </div>
                    ${renderSlot(p1, match.p1_score, p1Win, p2Win)}
                    ${renderSlot(p2, match.p2_score, p2Win, p1Win)}
                </div>
                `;
            };

            // Cột 1: Tứ Kết Trái (Match 1 & 2)
            let col1Html = `
            <div class="bracket-column">
                <div class="bracket-col-title">TỨ KẾT (NHÁNH TRÁI)</div>
                ${renderMatchCard('TRẬN 1 (#1 vs #8)', bracket.qf[0])}
                ${renderMatchCard('TRẬN 2 (#4 vs #5)', bracket.qf[1])}
            </div>`;

            // Cột 2: Bán Kết Trái (Match 5)
            let col2Html = `
            <div class="bracket-column">
                <div class="bracket-col-title">BÁN KẾT 1</div>
                ${renderMatchCard('BÁN KẾT NHÁNH TRÁI', bracket.sf[0])}
            </div>`;

            // Cột 3: Trung Tâm - Cúp Vàng & Chung Kết
            let centerHtml = `
            <div class="bracket-column col-center">
                <div class="trophy-glory-box">
                    <div class="big-trophy-icon">🏆</div>
                    <div style="font-weight:900; color:#fbbf24; font-size:1.1rem; letter-spacing:1px;">CÚP VÔ ĐỊCH GTLM</div>
                </div>
                <div style="width: 100%; max-width: 320px;">
                    <div class="bracket-col-title">TRẬN CHUNG KẾT KINH ĐIỂN</div>
                    ${renderMatchCard('CHUNG KẾT VÔ ĐỊCH', bracket.final, true)}
                </div>
                ${bracket.champion ? `
                <div class="champion-crowned-card">
                    <div class="champion-crowned-title">👑 ĐƯƠNG KIM QUÁN QUÂN</div>
                    <div class="champion-crowned-name">${bracket.champion.avatar} ${bracket.champion.name}</div>
                    <div style="font-size:0.8rem; color:#fef08a;">${bracket.champion.title}</div>
                </div>` : ''}
            </div>`;

            // Cột 4: Bán Kết Phải (Match 6)
            let col4Html = `
            <div class="bracket-column">
                <div class="bracket-col-title">BÁN KẾT 2</div>
                ${renderMatchCard('BÁN KẾT NHÁNH PHẢI', bracket.sf[1])}
            </div>`;

            // Cột 5: Tứ Kết Phải (Match 3 & 4)
            let col5Html = `
            <div class="bracket-column">
                <div class="bracket-col-title">TỨ KẾT (NHÁNH PHẢI)</div>
                ${renderMatchCard('TRẬN 3 (#2 vs #7)', bracket.qf[2])}
                ${renderMatchCard('TRẬN 4 (#3 vs #6)', bracket.qf[3])}
            </div>`;

            container.html(col1Html + col2Html + centerHtml + col4Html + col5Html);
        }

        renderMarket(players, stage) {
            const grid = $('#predictionMarketGrid');
            grid.empty();

            const isBettingOpen = stage === 'betting';

            players.forEach(p => {
                const card = `
                <div class="player-predict-card">
                    <div>
                        <div class="card-top-header">
                            <div class="card-player-hero">
                                <div class="hero-avatar-frame" style="border-color:${p.color};">
                                    ${p.avatar}
                                </div>
                                <div class="hero-meta">
                                    <h3 style="color:${p.color};">${p.name}</h3>
                                    <div class="hero-title-tag">${p.title}</div>
                                    <div style="font-size:0.75rem; color:#94a3b8; font-weight:700;">Hạt giống #${p.seed} • Elo: ${p.elo}</div>
                                </div>
                            </div>
                            <div class="odds-badge" title="Tỷ lệ trả thưởng">
                                x${p.odds.toFixed(2)}
                            </div>
                        </div>

                        <div class="card-lore-text">
                            ⚔️ <b>Lối đánh:</b> ${p.style}
                        </div>
                    </div>

                    <div class="card-action-bar">
                        <button type="button" class="btn-predict-now" 
                            ${!isBettingOpen ? 'disabled' : ''} 
                            onclick="window.esportsManager.promptBet('${p.id}', '${p.name}', ${p.odds})">
                            <i class="fa fa-crosshairs"></i> ${isBettingOpen ? 'DỰ ĐOÁN QUÁN QUÂN' : 'ĐÃ ĐÓNG CƯỢC'}
                        </button>
                    </div>
                </div>
                `;
                grid.append(card);
            });
        }

        renderHistory(myBets, champions) {
            const betsContainer = $('#myBetsContainer');
            betsContainer.empty();

            if (!myBets || myBets.length === 0) {
                betsContainer.html('<div style="color:#94a3b8; font-style:italic; padding:12px 0;">Sếp chưa đặt cược dự đoán kỳ thủ nào ở mùa giải này. Hãy sang tab SÀN DỰ ĐOÁN để chọn Quán Quân nhé!</div>');
            } else {
                myBets.forEach(b => {
                    let statusPill = '<span class="ticket-status-pill pending">⏳ Đang Thi Đấu</span>';
                    let statusClass = '';
                    if (b.status === 'won') {
                        statusPill = `<span class="ticket-status-pill won">🎉 THẮNG +${new Intl.NumberFormat().format(b.payout_amount)} GTLM</span>`;
                        statusClass = 'status-won';
                    } else if (b.status === 'lost') {
                        statusPill = '<span class="ticket-status-pill lost">❌ Thua Cược</span>';
                        statusClass = 'status-lost';
                    }

                    const card = `
                    <div class="bet-ticket-card ${statusClass}">
                        <div>
                            <div style="font-size:1rem; font-weight:900; color:#f8fafc;">
                                🎯 Dự đoán: <span style="color:#fbbf24;">${b.predicted_player_name}</span> (Odds x${b.odds})
                            </div>
                            <div style="font-size:0.8rem; color:#94a3b8; margin-top:4px;">
                                Cược: <b>${new Intl.NumberFormat().format(b.bet_amount)} GTLM</b> • Tiền thắng ước tính: <b style="color:#34d399;">${new Intl.NumberFormat().format(b.bet_amount * b.odds)} GTLM</b>
                            </div>
                        </div>
                        <div>
                            ${statusPill}
                        </div>
                    </div>
                    `;
                    betsContainer.append(card);
                });
            }

            const hallContainer = $('#hallOfFameContainer');
            hallContainer.empty();

            if (!champions || champions.length === 0) {
                hallContainer.html('<div style="color:#94a3b8; font-style:italic;">Chưa có dữ liệu quán quân các mùa trước.</div>');
            } else {
                champions.forEach(c => {
                    const row = `
                    <div class="bet-ticket-card" style="border-color: rgba(251,191,36,0.3); background: rgba(251,191,36,0.05);">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <span style="font-size:1.8rem;">🏆</span>
                            <div>
                                <div style="font-size:0.95rem; font-weight:900; color:#fbbf24;">${c.title}</div>
                                <div style="font-size:0.8rem; color:#cbd5e1;">Quán Quân: <b style="color:#fff;">${c.champion_name}</b></div>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:0.75rem; color:#94a3b8;">Tổng Thưởng Pool</div>
                            <div style="font-size:1rem; font-weight:900; color:#34d399;">${new Intl.NumberFormat().format(c.total_bets_pool)} GTLM</div>
                        </div>
                    </div>
                    `;
                    hallContainer.append(row);
                });
            }
        }

        promptBet(playerId, playerName, odds) {
            const self = this;
            const origin = window.location.origin;
            const pathParts = window.location.pathname.split('/');
            const appRoot = '/' + (pathParts[1] && !pathParts[1].includes('.') ? pathParts[1] + '/' : '');
            const apiUrl = origin + appRoot + 'api_esports_tournament.php';

            Swal.fire({
                title: `<span style="color:#fbbf24; font-size:1.25rem;">🎯 DỰ ĐOÁN QUÁN QUÂN: ${playerName}</span>`,
                html: `
                <div style="text-align: left; font-size:0.9rem; color:#cbd5e1; font-family:'Outfit', sans-serif;">
                    <div style="background:rgba(0,0,0,0.3); padding:10px 14px; border-radius:10px; margin-bottom:14px; border:1px solid rgba(255,255,255,0.08);">
                        <div>Kỳ Thủ Chọn: <b style="color:#fff;">${playerName}</b></div>
                        <div>Tỷ Lệ Odds: <b style="color:#10b981; font-size:1.1rem;">x${odds.toFixed(2)}</b></div>
                        <div>Số dư của bạn: <b style="color:#fbbf24;">${self.currentData ? self.currentData.user_money_formatted : 0} GTLM</b></div>
                    </div>

                    <label style="display:block; margin-bottom:6px; font-weight:700;">Nhập Số GTLM Muốn Đặt Cược:</label>
                    <input type="number" id="swalBetAmount" class="swal2-input" placeholder="Ví dụ: 20000" style="width:100%; margin:0 0 10px 0; background:#0b1329; color:#fbbf24; border:1px solid rgba(251,191,36,0.4); font-weight:900;" value="20000">

                    <div style="display:flex; gap:6px; margin-bottom:14px; flex-wrap:wrap;">
                        <button type="button" class="swal-chip-btn" onclick="$('#swalBetAmount').val(10000).trigger('input')">10K</button>
                        <button type="button" class="swal-chip-btn" onclick="$('#swalBetAmount').val(50000).trigger('input')">50K</button>
                        <button type="button" class="swal-chip-btn" onclick="$('#swalBetAmount').val(100000).trigger('input')">100K</button>
                        <button type="button" class="swal-chip-btn" onclick="$('#swalBetAmount').val(500000).trigger('input')">500K</button>
                    </div>

                    <div style="background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.3); padding:10px 14px; border-radius:10px;">
                        Tiền Thắng Ước Tính: <b style="color:#34d399; font-size:1.15rem;" id="swalPayoutCalc">${new Intl.NumberFormat().format(20000 * odds)} GTLM</b>
                    </div>
                </div>
                <style>
                    .swal-chip-btn { background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); color: #cbd5e1; padding: 4px 12px; border-radius: 6px; font-weight: 700; cursor: pointer; transition: 0.2s; font-size:0.8rem; }
                    .swal-chip-btn:hover { background: #fbbf24; color: #000; }
                </style>
                `,
                background: '#0f172a',
                color: '#f8fafc',
                showCancelButton: true,
                confirmButtonColor: '#f59e0b',
                cancelButtonColor: '#475569',
                confirmButtonText: 'Xác Nhận Đặt Cược',
                cancelButtonText: 'Hủy',
                didOpen: () => {
                    $('#swalBetAmount').on('input', function () {
                        const val = parseFloat($(this).val()) || 0;
                        const payout = val * odds;
                        $('#swalPayoutCalc').text(new Intl.NumberFormat().format(payout) + ' GTLM');
                    });
                },
                preConfirm: () => {
                    const amt = parseFloat($('#swalBetAmount').val());
                    if (!amt || amt < 1000) {
                        Swal.showValidationMessage('Vui lòng nhập tối thiểu 1,000 GTLM!');
                        return false;
                    }
                    return amt;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const betAmount = result.value;
                    $.post(apiUrl, { action: 'predict_champion', player_id: playerId, amount: betAmount }, function (res) {
                        if (res && res.success) {
                            Swal.fire({
                                title: 'Đặt Cược Thành Công! 🏆',
                                text: res.message,
                                icon: 'success',
                                background: '#0f172a',
                                color: '#f8fafc',
                                confirmButtonColor: '#10b981'
                            });
                            self.loadTournamentData();
                        } else {
                            Swal.fire({
                                title: 'Lỗi Đặt Cược',
                                text: res ? res.message : 'Có lỗi xảy ra, vui lòng thử lại!',
                                icon: 'error',
                                background: '#0f172a',
                                color: '#f8fafc',
                                confirmButtonColor: '#ef4444'
                            });
                        }
                    }, 'json');
                }
            });
        }
    }

    // Khởi tạo đối tượng toàn cục
    window.esportsManager = new EsportsTournamentManager();

    window.openEsportsTournament = function () {
        window.esportsManager.openModal();
    };

    window.closeEsportsTournament = function () {
        window.esportsManager.closeModal();
    };

})(window, jQuery);

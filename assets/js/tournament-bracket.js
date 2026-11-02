/**
 * ⚔️ ESPORTS TOURNAMENT BRACKET & LIVE CARO ARENA JS v2.0
 * 
 * - Quản lý Cây Thi Đấu (Bracket Tree) & Sàn Dự Đoán Quán Quân
 * - Đấu Trường Trực Tiếp 15x15 (Live Caro Match Arena) với Minimax AI & Web Audio
 * - Bộ điều khiển giai đoạn (Stage Controller) linh hoạt cho phép xem từ đầu hoặc giờ thật
 */

(function (window, $) {
    'use strict';

    class EsportsTournamentManager {
        constructor() {
            this.modalInjected = false;
            this.currentData = null;
            this.countdownTimer = null;
            this.activeTab = 'bracket';

            // Arena Game State
            this.currentArenaMatch = null;
            this.boardSize = 15;
            this.boardState = [];
            this.currentTurn = 1; // 1: X (p1), 2: O (p2)
            this.isGameOver = false;
            this.isThinking = false;
            this.moveCount = 0;
            this.speedMultiplier = 1; // 1x, 2x, 4x
            this.soundEnabled = true;
            this.audioCtx = null;
            this.nextMoveTimeout = null;

            this.letters = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O'];
            this.starPoints = [[7,7], [3,3], [11,3], [3,11], [11,11]]; // H8, D4, L4, D12, L12
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
                        <button type="button" class="esports-tab-btn active" data-tab="bracket" id="tabBtnBracket">
                            <i class="fa fa-sitemap"></i> CÂY THI ĐẤU (BRACKET TREE)
                        </button>
                        <button type="button" class="esports-tab-btn" data-tab="arena" id="tabBtnArena">
                            <i class="fa fa-gamepad"></i> 🔴 XEM TRẬN ĐẤU LIVE <span class="badge-pulse" id="arenaLiveBadge" style="display:none;">LIVE</span>
                        </button>
                        <button type="button" class="esports-tab-btn" data-tab="market" id="tabBtnMarket">
                            <i class="fa fa-chart-line"></i> SÀN DỰ ĐOÁN QUÁN QUÂN
                        </button>
                        <button type="button" class="esports-tab-btn" data-tab="history" id="tabBtnHistory">
                            <i class="fa fa-ticket-alt"></i> VÉ CƯỢC CỦA TÔI & LỊCH SỬ
                        </button>
                    </div>

                    <!-- Tab Content -->
                    <div class="esports-tab-content">
                        <!-- Tab 1: Cây thi đấu -->
                        <div class="esports-tab-pane active" id="pane-bracket">
                            <!-- Stage Controller Bar -->
                            <div class="esports-stage-controller-bar">
                                <div class="stage-info-pill">
                                    <span>Giai đoạn hiện tại: <b id="ctrlStageName" style="color:#fbbf24;">Đang tải...</b></span>
                                </div>
                                <div class="stage-quick-actions">
                                    <button type="button" class="btn-stage-action btn-watch-live-top" id="btnWatchLiveTop" onclick="window.esportsManager.watchCurrentLiveMatch()">
                                        <i class="fa fa-play"></i> 🔴 VÀO XEM TRẬN LIVE NGAY
                                    </button>
                                    <button type="button" class="btn-stage-action" id="btnStageReset" onclick="window.esportsManager.setStage('betting')" title="Mở lại sàn cược từ đầu để đặt cược">
                                        <i class="fa fa-undo"></i> Đấu Lại Từ Đầu (Mở Cược)
                                    </button>
                                    <button type="button" class="btn-stage-action" id="btnStageQf" onclick="window.esportsManager.setStage('quarter_finals')" title="Chuyển ngay đến Vòng Tứ Kết">
                                        ⚔️ Vòng Tứ Kết
                                    </button>
                                    <button type="button" class="btn-stage-action" id="btnStageSf" onclick="window.esportsManager.setStage('semi_finals')" title="Chuyển ngay đến Vòng Bán Kết">
                                        ⚡ Vòng Bán Kết
                                    </button>
                                    <button type="button" class="btn-stage-action" id="btnStageFinals" onclick="window.esportsManager.setStage('finals')" title="Chuyển ngay đến Trận Chung Kết">
                                        👑 Trận Chung Kết
                                    </button>
                                    <button type="button" class="btn-stage-action" id="btnStageAuto" onclick="window.esportsManager.setStage('auto')" title="Chạy tự động theo đồng hồ hệ thống 30 phút">
                                        ⏰ Giờ Thật (Tự Động)
                                    </button>
                                    <div class="season-quick-pills" style="display:inline-flex; gap:4px; margin-left:6px; border-left:1px solid rgba(255,255,255,0.15); padding-left:6px;">
                                        <button type="button" class="btn-stage-action" onclick="window.esportsManager.setSeason('xuan')" title="Chuyển sang Mùa Xuân">🌸 Xuân</button>
                                        <button type="button" class="btn-stage-action" onclick="window.esportsManager.setSeason('ha')" title="Chuyển sang Mùa Hạ">☀️ Hạ</button>
                                        <button type="button" class="btn-stage-action" onclick="window.esportsManager.setSeason('thu')" title="Chuyển sang Mùa Thu">🍂 Thu</button>
                                        <button type="button" class="btn-stage-action" onclick="window.esportsManager.setSeason('dong')" title="Chuyển sang Mùa Đông">❄️ Đông</button>
                                    </div>
                                </div>
                            </div>

                            <div class="bracket-tree-container" id="bracketTreeContainer">
                                <!-- Dynamic Rendered Bracket Tree -->
                            </div>
                        </div>

                        <!-- Tab 2: Đấu Trường Cờ Ca-rô Live (15x15 Arena) -->
                        <div class="esports-tab-pane" id="pane-arena">
                            <div class="arena-container">
                                <!-- Match Selection Bar -->
                                <div class="arena-match-header-bar">
                                    <div class="arena-match-selector">
                                        <span style="font-size:0.8rem; font-weight:800; color:#fbbf24;">TRẬN ĐẤU:</span>
                                        <div class="match-pill-btns" id="arenaMatchPills">
                                            <!-- Dynamically filled with QF1..4, SF1..2, FINAL -->
                                        </div>
                                    </div>
                                    <div class="arena-quick-controls">
                                        <button type="button" class="arena-tool-btn" id="arenaSpeedBtn" onclick="window.esportsManager.toggleSpeed()">⚡ Tốc độ: 1x</button>
                                        <button type="button" class="arena-tool-btn" id="arenaSoundBtn" onclick="window.esportsManager.toggleSound()">🔊 Âm thanh</button>
                                        <button type="button" class="arena-tool-btn" onclick="window.esportsManager.restartCurrentMatch()"><i class="fa fa-redo"></i> Đấu Lại</button>
                                        <button type="button" class="arena-tool-btn" onclick="window.esportsManager.switchTab('bracket')"><i class="fa fa-sitemap"></i> Cây Thi Đấu</button>
                                    </div>
                                </div>

                                <!-- Duel HUD -->
                                <div class="arena-hud-duel">
                                    <!-- Player 1 Card (Red X) -->
                                    <div class="duel-player-card player-x" id="arenaPlayer1Card">
                                        <div class="duel-player-avatar" id="arenaP1Avatar">🔴</div>
                                        <div class="duel-player-details">
                                            <div class="duel-player-name" id="arenaP1Name">AlphaX</div>
                                            <div class="duel-player-meta"><span class="badge-role">QUÂN ĐỎ (❌)</span> • Elo <span id="arenaP1Elo">2850</span></div>
                                            <div class="duel-player-style" id="arenaP1Style">Tấn công dồn dập, ép biên thần tốc</div>
                                        </div>
                                        <div class="turn-indicator-badge" id="arenaP1TurnBadge">ĐANG ĐẾN LƯỢT</div>
                                    </div>

                                    <!-- Center Score & Status -->
                                    <div class="duel-center-score">
                                        <div class="duel-stage-tag" id="arenaRoundTitle">TRẬN CHUNG KẾT VÔ ĐỊCH</div>
                                        <div class="duel-score-display">
                                            <span class="score-num p1" id="arenaScoreP1">0</span>
                                            <span class="score-colon">:</span>
                                            <span class="score-num p2" id="arenaScoreP2">0</span>
                                        </div>
                                        <div class="duel-move-count">Nước cờ: <b id="arenaMoveCounter" style="color:#fbbf24;">0</b></div>
                                    </div>

                                    <!-- Player 2 Card (Purple/Blue O) -->
                                    <div class="duel-player-card player-o" id="arenaPlayer2Card">
                                        <div class="duel-player-avatar" id="arenaP2Avatar">🟣</div>
                                        <div class="duel-player-details">
                                            <div class="duel-player-name" id="arenaP2Name">ZenZero</div>
                                            <div class="duel-player-meta"><span class="badge-role o">QUÂN TÍM (⭕)</span> • Elo <span id="arenaP2Elo">2780</span></div>
                                            <div class="duel-player-style" id="arenaP2Style">Bậc thầy phòng thủ & phản kích</div>
                                        </div>
                                        <div class="turn-indicator-badge" id="arenaP2TurnBadge">ĐANG ĐẾN LƯỢT</div>
                                    </div>
                                </div>

                                <!-- 15x15 Caro Board -->
                                <div class="arena-board-wrap">
                                    <div class="board-coord-row top-letters">
                                        <span></span>
                                        <span>A</span><span>B</span><span>C</span><span>D</span><span>E</span>
                                        <span>F</span><span>G</span><span>H</span><span>I</span><span>J</span>
                                        <span>K</span><span>L</span><span>M</span><span>N</span><span>O</span>
                                        <span></span>
                                    </div>

                                    <div class="board-with-side-coords">
                                        <div class="board-coord-col left-numbers">
                                            <span>1</span><span>2</span><span>3</span><span>4</span><span>5</span>
                                            <span>6</span><span>7</span><span>8</span><span>9</span><span>10</span>
                                            <span>11</span><span>12</span><span>13</span><span>14</span><span>15</span>
                                        </div>

                                        <div class="caro-match-board" id="tournamentCaroBoard">
                                            <!-- Dynamically generated 225 cells -->
                                        </div>

                                        <div class="board-coord-col right-numbers">
                                            <span>1</span><span>2</span><span>3</span><span>4</span><span>5</span>
                                            <span>6</span><span>7</span><span>8</span><span>9</span><span>10</span>
                                            <span>11</span><span>12</span><span>13</span><span>14</span><span>15</span>
                                        </div>
                                    </div>

                                    <div class="board-coord-row bottom-letters">
                                        <span></span>
                                        <span>A</span><span>B</span><span>C</span><span>D</span><span>E</span>
                                        <span>F</span><span>G</span><span>H</span><span>I</span><span>J</span>
                                        <span>K</span><span>L</span><span>M</span><span>N</span><span>O</span>
                                        <span></span>
                                    </div>

                                    <!-- Victory Modal Overlay -->
                                    <div class="arena-victory-modal" id="arenaVictoryModal">
                                        <div class="victory-content-box">
                                            <div class="victory-icon-trophy">🏆</div>
                                            <h2 class="victory-headline" id="arenaVictoryHeadline">ALPHAX CHIẾN THẮNG!</h2>
                                            <div class="victory-subtext" id="arenaVictorySubtext">Hoàn tất 5 quân liên tiếp và xuất sắc đoạt vé đi tiếp!</div>
                                            <div class="victory-action-btns">
                                                <button type="button" class="btn-vic-restart" onclick="window.esportsManager.restartCurrentMatch()"><i class="fa fa-redo"></i> Xem Lại Ván Này</button>
                                                <button type="button" class="btn-vic-next" id="btnVicNextMatch" onclick="window.esportsManager.goToNextMatch()"><i class="fa fa-arrow-right"></i> Xem Trận Kế Tiếp</button>
                                                <button type="button" class="btn-vic-bracket" onclick="window.esportsManager.switchTab('bracket')"><i class="fa fa-sitemap"></i> Về Cây Thi Đấu</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Live Commentary Ticker -->
                                <div class="arena-commentary-bar">
                                    <div class="comm-badge"><i class="fa fa-broadcast-tower"></i> BÌNH LUẬN TRỰC TIẾP:</div>
                                    <div class="comm-ticker-text" id="arenaCommentaryText">
                                        Trận đấu đang chuẩn bị khởi tranh trên bàn cờ tiêu chuẩn 15x15...
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 3: Sàn dự đoán -->
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

                        <!-- Tab 4: Vé cược & Lịch sử -->
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
                self.switchTab(targetTab);
            });
        }

        switchTab(tabId) {
            $('.esports-tab-btn').removeClass('active');
            $(`[data-tab="${tabId}"]`).addClass('active');

            $('.esports-tab-pane').removeClass('active');
            $('#pane-' + tabId).addClass('active');
            this.activeTab = tabId;

            if (tabId === 'arena' && !this.currentArenaMatch && this.currentData) {
                this.watchCurrentLiveMatch();
            }
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
            if (this.nextMoveTimeout) {
                clearTimeout(this.nextMoveTimeout);
                this.nextMoveTimeout = null;
            }
        }

        loadTournamentData(callback) {
            const self = this;
            const origin = window.location.origin;
            const pathParts = window.location.pathname.split('/');
            const appRoot = '/' + (pathParts[1] && !pathParts[1].includes('.') ? pathParts[1] + '/' : '');
            const apiUrl = origin + appRoot + 'api_esports_tournament.php';

            $.getJSON(apiUrl, { action: 'get_tournament_bracket' }, function (res) {
                if (res && res.success) {
                    self.currentData = res;
                    self.renderAll();
                    if (callback) callback(res);
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

            // Header Elements
            const seasonTag = t.season_tag || ('MÙA #' + t.season_number);
            const badgeClass = t.season_badge_class || '';
            $('#esportsTitle').html(`ĐẠI CHIẾN CỜ CA-RÔ AI <span class="season-tag ${badgeClass}">${seasonTag}</span>`);
            $('#esportsPoolVal').text(new Intl.NumberFormat().format(t.total_pool) + ' GTLM');
            $('#esportsUserMoney').text('Số dư của sếp: ' + this.currentData.user_money_formatted + ' GTLM');
            $('#ctrlStageName').html(`${t.stage_title} • <span class="season-tag ${badgeClass}" style="padding:1px 8px; font-size:0.75rem;">${seasonTag}</span>`);

            const stagePill = $('#esportsStagePill');
            const stageDesc = $('#esportsStageDesc');

            $('.btn-stage-action').removeClass('active-stage');

            if (t.stage === 'betting') {
                stagePill.attr('class', 'stage-pill betting').text('🔥 MỞ SÀN CƯỢC');
                stageDesc.text('Đếm ngược trước khi khởi tranh Tứ Kết');
                $('#btnStageReset').addClass('active-stage');
                $('#arenaLiveBadge').hide();
            } else if (t.stage === 'quarter_finals') {
                stagePill.attr('class', 'stage-pill playing').text('⚔️ VÒNG TỨ KẾT');
                stageDesc.text('4 trận đối đầu loại trực tiếp');
                $('#btnStageQf').addClass('active-stage');
                $('#arenaLiveBadge').show();
            } else if (t.stage === 'semi_finals') {
                stagePill.attr('class', 'stage-pill playing').text('⚡ VÒNG BÁN KẾT');
                stageDesc.text('Tranh 2 vé vào Chung Kết');
                $('#btnStageSf').addClass('active-stage');
                $('#arenaLiveBadge').show();
            } else if (t.stage === 'finals') {
                stagePill.attr('class', 'stage-pill playing').text('👑 TRẬN CHUNG KẾT');
                stageDesc.text('Đại chiến tranh Cúp Vàng GTLM');
                $('#btnStageFinals').addClass('active-stage');
                $('#arenaLiveBadge').show();
            } else {
                stagePill.attr('class', 'stage-pill finished').text('🏆 TRAO CÚP VÀNG');
                stageDesc.text('Chuẩn bị khởi tranh Mùa Giải tiếp theo');
                $('#arenaLiveBadge').hide();
            }

            this.renderBracket(t.bracket, t.stage);
            this.renderMarket(t.players, t.stage);
            this.renderHistory(this.currentData.my_bets, this.currentData.recent_champions);
            this.renderMatchPills();
        }

        renderBracket(bracket, stage) {
            const container = $('#bracketTreeContainer');
            container.empty();

            const renderSlot = (player, score, isWinner, isLoser) => {
                if (!player) {
                    return `
                    <div class="player-slot" style="opacity: 0.45; border: 1px dashed rgba(255,255,255,0.12); background: rgba(0,0,0,0.15);">
                        <div class="player-info-left">
                            <span class="player-avatar-icon">❔</span>
                            <span class="player-name-text" style="color:#94a3b8; font-style:italic;">Chờ đối thủ</span>
                        </div>
                        <div class="player-score-badge" style="opacity:0.3;">-</div>
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
                const hasBothPlayers = !!(p1 && p2);

                let btnHtml = '';
                let canClick = false;
                if (isLive && hasBothPlayers) {
                    btnHtml = `<button type="button" class="btn-watch-match live"><i class="fa fa-play-circle"></i> 🔴 XEM TRỰC TIẾP TRẬN NÀY</button>`;
                    canClick = true;
                } else if (isFinished && hasBothPlayers) {
                    btnHtml = `<button type="button" class="btn-watch-match finished"><i class="fa fa-eye"></i> 👁️ Xem Lại Ván Đấu (AI Replay)</button>`;
                    canClick = true;
                } else {
                    // BỎ HOÀN TOÀN ĐẤU THỬ - CHỈ HIỂN THỊ CHỜ VÒNG ĐẤU
                    btnHtml = `<div class="match-waiting-tag"><i class="fa fa-hourglass-half"></i> <span>${!hasBothPlayers ? 'Chờ đối thủ vòng trước' : 'Chưa đến giờ thi đấu'}</span></div>`;
                    canClick = false;
                }

                const clickAttr = canClick ? `onclick="window.esportsManager.openMatchArena(${match.match_id})" title="Bấm vào để xem trận đấu"` : `style="cursor: default;"`;

                return `
                <div class="bracket-match-card ${isLive ? 'live-playing' : ''} ${!canClick ? 'non-clickable' : ''}" ${clickAttr}>
                    <div class="match-header-tag">
                        <span>${title}</span>
                        ${isLive ? '<span class="live-tag">🔥 ĐANG ĐẤU</span>' : (isFinished ? '<span style="color:#10b981;">✓ XONG</span>' : '<span style="color:#94a3b8;">SẮP ĐẤU</span>')}
                    </div>
                    ${renderSlot(p1, match.p1_score, p1Win, p2Win)}
                    ${renderSlot(p2, match.p2_score, p2Win, p1Win)}
                    ${btnHtml}
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
                <div style="width: 100%; max-width: 330px;">
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

        renderMatchPills() {
            const container = $('#arenaMatchPills');
            container.empty();

            const allMatches = this.getAllMatchesList();
            allMatches.forEach(m => {
                const isActive = this.currentArenaMatch && this.currentArenaMatch.match_id === m.match_id;
                const isPlaying = m.status === 'playing';
                const hasPlayers = !!(m.p1 && m.p2);
                const isClickable = (m.status === 'playing' || m.status === 'finished') && hasPlayers;

                const pill = $(`
                    <button type="button" class="match-pill-btn ${isActive ? 'active' : ''} ${!isClickable ? 'disabled' : ''}" data-id="${m.match_id}" style="${!isClickable ? 'opacity:0.4; cursor:not-allowed;' : ''}">
                        ${isPlaying ? '🔴 ' : ''}${m.round_name || ('Trận ' + m.match_id)}
                    </button>
                `);
                pill.on('click', () => {
                    if (!isClickable) {
                        Swal.mixin({
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 2200,
                            background: '#0f172a',
                            color: '#f8fafc'
                        }).fire({
                            icon: 'info',
                            title: !hasPlayers ? 'Trận đấu này chưa xác định đối thủ (Chờ kết quả vòng trước)!' : 'Trận đấu này chưa đến giờ thi đấu!'
                        });
                        return;
                    }
                    this.openMatchArena(m.match_id);
                });
                container.append(pill);
            });
        }

        getAllMatchesList() {
            if (!this.currentData || !this.currentData.tournament || !this.currentData.tournament.bracket) return [];
            const b = this.currentData.tournament.bracket;
            const list = [];
            if (b.qf) list.push(...b.qf);
            if (b.sf) list.push(...b.sf);
            if (b.final) list.push(b.final);
            return list;
        }

        watchCurrentLiveMatch() {
            if (!this.currentData || !this.currentData.tournament) return;
            const stage = this.currentData.tournament.stage;

            if (stage === 'betting') {
                Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    background: '#0f172a',
                    color: '#f8fafc'
                }).fire({
                    icon: 'info',
                    title: '🔥 Sàn cược đang mở! Tứ kết sẽ tự động khởi tranh khi hết đếm ngược (hoặc sếp bấm [⚔️ Vòng Tứ Kết] để mở ngay).'
                });
                return;
            }

            const allMatches = this.getAllMatchesList();
            // Ưu tiên trận đang đấu (playing) có đủ 2 người chơi
            let matchToWatch = allMatches.find(m => m.status === 'playing' && m.p1 && m.p2);
            // Nếu không có trận nào đang đấu, chọn trận đã xong gần nhất
            if (!matchToWatch) {
                const finishedMatches = allMatches.filter(m => m.status === 'finished' && m.p1 && m.p2);
                matchToWatch = finishedMatches[finishedMatches.length - 1] || allMatches[0];
            }

            if (matchToWatch && matchToWatch.p1 && matchToWatch.p2) {
                this.openMatchArena(matchToWatch.match_id);
            }
        }

        openMatchArena(matchId) {
            const allMatches = this.getAllMatchesList();
            let match = allMatches.find(m => m.match_id === matchId);
            if (!match || !match.p1 || !match.p2) {
                Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2500,
                    background: '#0f172a',
                    color: '#f8fafc'
                }).fire({
                    icon: 'warning',
                    title: 'Trận đấu này chưa có đủ 2 kỳ thủ (chờ kết quả vòng trước)!'
                });
                return;
            }
            if (!match) return;

            this.currentArenaMatch = match;
            this.switchTab('arena');
            this.renderMatchPills();

            // Populate HUD
            const p1 = match.p1 || { name: 'AlphaX', avatar: '🔴', elo: 2850, color: '#ef4444', style: 'Tấn công dồn dập' };
            const p2 = match.p2 || { name: 'ZenZero', avatar: '🟣', elo: 2780, color: '#a855f7', style: 'Khiên thần Ca-rô' };

            $('#arenaP1Avatar').text(p1.avatar || '🔴');
            $('#arenaP1Name').text(p1.name || 'Bot X').css('color', p1.color || '#ef4444');
            $('#arenaP1Elo').text(p1.elo || 2800);
            $('#arenaP1Style').text(p1.style || 'Lối đánh toàn diện');

            $('#arenaP2Avatar').text(p2.avatar || '🟣');
            $('#arenaP2Name').text(p2.name || 'Bot O').css('color', p2.color || '#a855f7');
            $('#arenaP2Elo').text(p2.elo || 2750);
            $('#arenaP2Style').text(p2.style || 'Phản kích trung tâm');

            $('#arenaRoundTitle').text(match.title || match.round_name || 'ĐẤU TRƯỜNG CỜ CA-RÔ');
            $('#arenaScoreP1').text(match.p1_score || 0);
            $('#arenaScoreP2').text(match.p2_score || 0);
            $('#arenaMoveCounter').text('0');

            $('#arenaVictoryModal').removeClass('active');

            // Init and Start Board Game
            this.initArenaBoard();
            this.startArenaGame();
        }

        initArenaBoard() {
            const boardEl = $('#tournamentCaroBoard');
            boardEl.empty();

            for (let r = 0; r < this.boardSize; r++) {
                for (let c = 0; c < this.boardSize; c++) {
                    const isStar = this.starPoints.some(([sr, sc]) => sr === r && sc === c);
                    const cell = $(`
                        <div class="caro-match-cell ${isStar ? 'star-point' : ''}" data-row="${r}" data-col="${c}"></div>
                    `);
                    boardEl.append(cell);
                }
            }
        }

        startArenaGame() {
            if (this.nextMoveTimeout) {
                clearTimeout(this.nextMoveTimeout);
                this.nextMoveTimeout = null;
            }

            this.boardState = Array.from({ length: this.boardSize }, () => Array(this.boardSize).fill(0));
            this.currentTurn = 1; // 1: X (p1), 2: O (p2)
            this.isGameOver = false;
            this.isThinking = false;
            this.moveCount = 0;

            $('#arenaMoveCounter').text('0');
            $('.caro-match-cell').removeClass('piece-x piece-o last-move win-cell').text('');

            const p1Name = this.currentArenaMatch.p1 ? this.currentArenaMatch.p1.name : 'AlphaX';
            const p2Name = this.currentArenaMatch.p2 ? this.currentArenaMatch.p2.name : 'ZenZero';

            this.setCommentary(`⚔️ Trận chiến bắt đầu! <b>${p1Name} (❌)</b> vs <b>${p2Name} (⭕)</b>.`);
            this.updateTurnHUD();
            this.scheduleNextMove();
        }

        updateTurnHUD() {
            if (this.currentTurn === 1) {
                $('#arenaPlayer1Card').addClass('active-turn');
                $('#arenaPlayer2Card').removeClass('active-turn');
            } else {
                $('#arenaPlayer2Card').addClass('active-turn');
                $('#arenaPlayer1Card').removeClass('active-turn');
            }
        }

        scheduleNextMove() {
            if (this.isGameOver || this.isThinking) return;

            const baseDelay = 1200;
            const delay = Math.max(150, Math.floor(baseDelay / this.speedMultiplier));

            this.isThinking = true;
            this.nextMoveTimeout = setTimeout(() => {
                this.isThinking = false;
                if (this.isGameOver) return;

                const [r, c] = this.findBestMove(this.currentTurn);
                this.placePiece(r, c, this.currentTurn);
            }, delay);
        }

        placePiece(r, c, player) {
            this.boardState[r][c] = player;
            this.moveCount++;
            $('#arenaMoveCounter').text(this.moveCount);

            const cell = $(`#tournamentCaroBoard .caro-match-cell[data-row="${r}"][data-col="${c}"]`);
            $('.caro-match-cell').removeClass('last-move');
            cell.addClass('last-move');

            const p1 = this.currentArenaMatch.p1 || { name: 'AlphaX' };
            const p2 = this.currentArenaMatch.p2 || { name: 'ZenZero' };
            const currentPlayerObj = (player === 1) ? p1 : p2;

            if (player === 1) {
                cell.addClass('piece-x').text('❌');
            } else {
                cell.addClass('piece-o').text('⭕');
            }

            this.playStoneClick();

            const coordName = this.letters[c] + (r + 1);
            this.setCommentary(`<b>${currentPlayerObj.name}</b> hạ quân tại ô <b>[${coordName}]</b> (Nước cờ ${this.moveCount})`);

            // Check Win
            const winLine = this.checkWin(r, c, player);
            if (winLine) {
                this.handleVictory(player, winLine);
                return;
            }

            // Check Draw
            if (this.moveCount >= this.boardSize * this.boardSize) {
                this.handleDraw();
                return;
            }

            // Switch Turn
            this.currentTurn = (this.currentTurn === 1) ? 2 : 1;
            this.updateTurnHUD();
            this.scheduleNextMove();
        }

        checkWin(r, c, player) {
            const directions = [
                [[0, 1], [0, -1]],   // Ngang
                [[1, 0], [-1, 0]],   // Dọc
                [[1, 1], [-1, -1]],  // Chéo chính
                [[1, -1], [-1, 1]]   // Chéo phụ
            ];

            for (let pair of directions) {
                let line = [[r, c]];
                for (let [dr, dc] of pair) {
                    let nr = r + dr;
                    let nc = c + dc;
                    while (nr >= 0 && nr < this.boardSize && nc >= 0 && nc < this.boardSize && this.boardState[nr][nc] === player) {
                        line.push([nr, nc]);
                        nr += dr;
                        nc += dc;
                    }
                }
                if (line.length >= 5) return line;
            }
            return null;
        }

        handleVictory(winner, winLine) {
            this.isGameOver = true;
            this.playWinSound();

            winLine.forEach(([wr, wc]) => {
                $(`#tournamentCaroBoard .caro-match-cell[data-row="${wr}"][data-col="${wc}"]`).addClass('win-cell');
            });

            const p1 = this.currentArenaMatch.p1 || { name: 'AlphaX' };
            const p2 = this.currentArenaMatch.p2 || { name: 'ZenZero' };
            const winnerObj = (winner === 1) ? p1 : p2;
            const loserObj = (winner === 1) ? p2 : p1;

            if (winner === 1) {
                $('#arenaScoreP1').text('1');
                $('#arenaScoreP2').text('0');
            } else {
                $('#arenaScoreP1').text('0');
                $('#arenaScoreP2').text('1');
            }

            const isFinal = this.currentArenaMatch.match_id === 7;
            const headline = `👑 ${winnerObj.name} CHIẾN THẮNG TUYỆT ĐỐI!`;
            const subtext = isFinal
                ? `Xuất sắc kết chuỗi 5 quân liên hoàn và chính thức ĐOẠT CÚP VÔ ĐỊCH MÙA GIẢI GTLM!`
                : `Hạ gục đối thủ ${loserObj.name} với nước cờ 5 quân liên tiếp và giành quyền đi tiếp!`;

            $('#arenaVictoryHeadline').html(headline);
            $('#arenaVictorySubtext').html(subtext);
            $('#arenaVictoryModal').addClass('active');

            this.setCommentary(`🎉 <b>${winnerObj.name}</b> đã xuất sắc hoàn tất thế cờ ngũ tử và GIÀNH CHIẾN THẮNG!`);
        }

        handleDraw() {
            this.isGameOver = true;
            $('#arenaVictoryHeadline').html('🤝 BÀN CỜ BẤT PHÂN THẮNG BẠI!');
            $('#arenaVictorySubtext').html('225 ô cờ đã kín, hai đại sư kỳ phùng địch thủ!');
            $('#arenaVictoryModal').addClass('active');
            this.setCommentary('🤝 Hòa cờ! Cả hai kỳ thủ thi triển chiến thuật phòng thủ kín kẽ.');
        }

        goToNextMatch() {
            const currentId = this.currentArenaMatch ? this.currentArenaMatch.match_id : 1;
            const nextId = (currentId >= 7) ? 1 : (currentId + 1);
            this.openMatchArena(nextId);
        }

        restartCurrentMatch() {
            if (this.currentArenaMatch) {
                this.openMatchArena(this.currentArenaMatch.match_id);
            }
        }

        toggleSpeed() {
            if (this.speedMultiplier === 1) {
                this.speedMultiplier = 2;
                $('#arenaSpeedBtn').text('⚡ Tốc độ: 2x');
            } else if (this.speedMultiplier === 2) {
                this.speedMultiplier = 4;
                $('#arenaSpeedBtn').text('🚀 Tốc độ: 4x');
            } else {
                this.speedMultiplier = 1;
                $('#arenaSpeedBtn').text('⚡ Tốc độ: 1x');
            }
        }

        toggleSound() {
            this.soundEnabled = !this.soundEnabled;
            $('#arenaSoundBtn').text(this.soundEnabled ? '🔊 Âm thanh' : '🔇 Tắt âm');
        }

        setCommentary(htmlText) {
            $('#arenaCommentaryText').html(htmlText);
        }

        // Web Audio Synthesizer
        initAudio() {
            if (!this.audioCtx) {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (AudioContext) this.audioCtx = new AudioContext();
            }
        }

        playTone(freq, type, duration, gainVal = 0.15) {
            if (!this.soundEnabled) return;
            try {
                this.initAudio();
                if (!this.audioCtx) return;
                if (this.audioCtx.state === 'suspended') this.audioCtx.resume();
                const osc = this.audioCtx.createOscillator();
                const gain = this.audioCtx.createGain();
                osc.type = type;
                osc.frequency.setValueAtTime(freq, this.audioCtx.currentTime);
                gain.gain.setValueAtTime(gainVal, this.audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, this.audioCtx.currentTime + duration);
                osc.connect(gain);
                gain.connect(this.audioCtx.destination);
                osc.start();
                osc.stop(this.audioCtx.currentTime + duration);
            } catch (e) {}
        }

        playStoneClick() {
            this.playTone(320, 'triangle', 0.08, 0.22);
            setTimeout(() => this.playTone(180, 'sine', 0.05, 0.12), 25);
        }

        playWinSound() {
            [523, 659, 784, 1046].forEach((f, idx) => {
                setTimeout(() => this.playTone(f, 'triangle', 0.3, 0.25), idx * 110);
            });
        }

        // 🧠 Heuristic Caro Minimax Engine
        findBestMove(player) {
            if (this.moveCount === 0) return [7, 7]; // First move center
            if (this.moveCount === 1) {
                const candidates = [[6,6], [6,7], [6,8], [7,6], [7,8], [8,6], [8,7], [8,8]];
                return candidates[Math.floor(Math.random() * candidates.length)];
            }

            let bestScore = -1;
            let bestMoves = [];

            for (let r = 0; r < this.boardSize; r++) {
                for (let c = 0; c < this.boardSize; c++) {
                    if (this.boardState[r][c] !== 0) continue;

                    // Chỉ xét ô gần quân đã đánh (trong bán kính 2 ô)
                    let hasNeighbor = false;
                    for (let dr = -2; dr <= 2 && !hasNeighbor; dr++) {
                        for (let dc = -2; dc <= 2 && !hasNeighbor; dc++) {
                            const nr = r + dr;
                            const nc = c + dc;
                            if (nr >= 0 && nr < this.boardSize && nc >= 0 && nc < this.boardSize && this.boardState[nr][nc] !== 0) {
                                hasNeighbor = true;
                            }
                        }
                    }
                    if (!hasNeighbor) continue;

                    const score = this.evaluateCell(r, c, player);
                    if (score > bestScore) {
                        bestScore = score;
                        bestMoves = [[r, c]];
                    } else if (Math.abs(score - bestScore) < 5) {
                        bestMoves.push([r, c]);
                    }
                }
            }

            if (bestMoves.length === 0) {
                for (let r = 0; r < this.boardSize; r++) {
                    for (let c = 0; c < this.boardSize; c++) {
                        if (this.boardState[r][c] === 0) return [r, c];
                    }
                }
            }

            return bestMoves[Math.floor(Math.random() * bestMoves.length)];
        }

        evaluateCell(r, c, player) {
            const opp = (player === 1) ? 2 : 1;
            const myScore = this.getPatternScore(r, c, player);
            const oppScore = this.getPatternScore(r, c, opp);

            // Điều chỉnh trọng số công/thủ
            const attackWeight = (player === 1) ? 1.25 : 1.1;
            const defenseWeight = (player === 1) ? 1.1 : 1.28;

            const distFromCenter = Math.abs(r - 7) + Math.abs(c - 7);
            const centerBonus = Math.max(0, 16 - distFromCenter);
            const jitter = Math.random() * 6;

            return (myScore * attackWeight) + (oppScore * defenseWeight) + centerBonus + jitter;
        }

        getPatternScore(r, c, p) {
            const directions = [
                [[0, 1], [0, -1]],
                [[1, 0], [-1, 0]],
                [[1, 1], [-1, -1]],
                [[1, -1], [-1, 1]]
            ];

            let total = 0;
            for (let pair of directions) {
                let count = 1;
                let openEnds = 0;

                for (let [dr, dc] of pair) {
                    let nr = r + dr;
                    let nc = c + dc;
                    while (nr >= 0 && nr < this.boardSize && nc >= 0 && nc < this.boardSize && this.boardState[nr][nc] === p) {
                        count++;
                        nr += dr;
                        nc += dc;
                    }
                    if (nr >= 0 && nr < this.boardSize && nc >= 0 && nc < this.boardSize && this.boardState[nr][nc] === 0) {
                        openEnds++;
                    }
                }

                if (count >= 5) total += 100000;
                else if (count === 4) {
                    if (openEnds === 2) total += 30000;
                    else if (openEnds === 1) total += 6000;
                } else if (count === 3) {
                    if (openEnds === 2) total += 5000;
                    else if (openEnds === 1) total += 700;
                } else if (count === 2) {
                    if (openEnds === 2) total += 250;
                    else if (openEnds === 1) total += 35;
                }
            }
            return total;
        }

        // Chuyển giai đoạn giải đấu
        setStage(stageName) {
            const self = this;
            const origin = window.location.origin;
            const pathParts = window.location.pathname.split('/');
            const appRoot = '/' + (pathParts[1] && !pathParts[1].includes('.') ? pathParts[1] + '/' : '');
            const apiUrl = origin + appRoot + 'api_esports_tournament.php';

            $.post(apiUrl, { action: 'set_stage', stage: stageName }, function (res) {
                if (res && res.success) {
                    self.currentData.tournament = res.tournament;
                    self.renderAll();

                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2500,
                        timerProgressBar: true,
                        background: '#0f172a',
                        color: '#f8fafc'
                    });
                    Toast.fire({
                        icon: 'success',
                        title: res.message
                    });
                }
            }, 'json');
        }

        // Chuyển mùa giải: Xuân - Hạ - Thu - Đông
        setSeason(seasonName) {
            const self = this;
            const origin = window.location.origin;
            const pathParts = window.location.pathname.split('/');
            const appRoot = '/' + (pathParts[1] && !pathParts[1].includes('.') ? pathParts[1] + '/' : '');
            const apiUrl = origin + appRoot + 'api_esports_tournament.php';

            $.post(apiUrl, { action: 'set_season', season: seasonName }, function (res) {
                if (res && res.success) {
                    self.currentData.tournament = res.tournament;
                    self.renderAll();

                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true,
                        background: '#0f172a',
                        color: '#f8fafc'
                    });
                    Toast.fire({
                        icon: 'success',
                        title: res.message
                    });
                }
            }, 'json');
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

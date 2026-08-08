$(document).ready(function () {
    let slotsData = {};
    let config = null;
    let counterInterval = null;
    let totalAccumulated = 0;
    let totalRate = 0;
    let MAX_LEVEL = 50;

    let MAX_HOURS = 24;
    const TOTAL_SLOTS = 5;

    // Tabs
    $('.tab-btn').click(function () {
        $('.tab-btn').removeClass('active');
        $(this).addClass('active');

        $('.tab-content').removeClass('active');
        $('#' + $(this).data('target')).addClass('active');

        if ($(this).data('target') === 'raidPvp') {
            loadVulnerableList();
        }
    });

    // Tải thông tin Mỏ của tôi
    function loadInfo() {
        $.get('../api_mining.php?action=info', function (res) {
            if (res.success) {
                config = res.config;
                slotsData = res.slots;
                totalAccumulated = res.total_accumulated;
                totalRate = res.total_rate;
                MAX_LEVEL = res.max_level;
                MAX_HOURS = res.storage_config[res.storage_level].hours;

                $('#totalRate').text(totalRate.toLocaleString('vi-VN'));

                // Storage Render
                $('#storageLvlTxt').text(res.storage_level);
                $('#storageMaxTxt').text(MAX_HOURS + ' Giờ');

                const nextStorageLvl = res.storage_level + 1;
                if (res.storage_config[nextStorageLvl]) {
                    $('#btnUpgradeStorage').prop('disabled', false).text('Nâng Cấp (' + (res.storage_config[nextStorageLvl].cost / 1000000) + 'M)');
                } else {
                    $('#btnUpgradeStorage').prop('disabled', true).text('CẤP TỐI ĐA');
                }

                // Boost Render
                if (res.boost && res.boost.active) {
                    $('#boostStatusTxt').text(`Tốc độ X2 đang kích hoạt. Hết hạn sau ${res.boost.remaining_hours} giờ.`);
                    $('#btnBuyBoost').prop('disabled', true).text('ĐANG DÙNG');
                } else {
                    $('#boostStatusTxt').text('Tốc độ x2 trong 12 Giờ');
                    $('#btnBuyBoost').prop('disabled', false).text('Mua (10M)');
                }

                // Guard Status
                if (res.guard) {
                    $('#guardStatus').text(`Đang được bảo vệ. Hết hạn sau ${res.guard.remaining_hours} giờ.`);
                    $('#btnBuyGuard').prop('disabled', true).text('ĐÃ THUÊ');
                    $('.guard-icon').css('color', '#2ecc71');
                } else {
                    $('#guardStatus').text('Chưa thuê. Mỏ có nguy cơ bị cướp 15% nếu AFK quá 24h!');
                    $('#btnBuyGuard').prop('disabled', false).text('Thuê (500K / 24h)');
                    $('.guard-icon').css('color', 'var(--mining-primary)');
                }

                renderSlotsGrid();
                startCounter();
            } else {
                Swal.fire('Lỗi', res.message || 'Không thể lấy dữ liệu', 'error');
            }
        });
    }

    let upgradeMultiplier = '1';

    // Multiplier Toggle
    $('.btn-multi').click(function () {
        $('.btn-multi').removeClass('active');
        $(this).addClass('active');
        upgradeMultiplier = $(this).data('val').toString();
        renderSlotsGrid();
    });

    function getUpgradeCost(currentLevel) {
        if (currentLevel >= MAX_LEVEL) return { levels: 0, cost: 0 };

        let targetLevel = currentLevel;
        if (upgradeMultiplier === '1') targetLevel = currentLevel + 1;
        else if (upgradeMultiplier === '10') targetLevel = Math.min(MAX_LEVEL, currentLevel + 10);
        else if (upgradeMultiplier === 'max') targetLevel = MAX_LEVEL;

        let totalCost = 0;
        let userMoneyText = $('#userMoney').text();
        let userMoney = parseInt(userMoneyText.replace(/[\.,]/g, '')) || 0;
        let affordableLevel = currentLevel;

        for (let i = currentLevel + 1; i <= targetLevel; i++) {
            if (totalCost + config[i].cost <= userMoney || upgradeMultiplier !== 'max') {
                totalCost += config[i].cost;
                affordableLevel = i;
            } else {
                break; // Hết GTLM khi dùng MAX
            }
        }

        // Nếu chọn MAX nhưng ko đủ GTLM mua cả level 1 thì cho nâng 1 cấp (sẽ báo lỗi backend)
        if (upgradeMultiplier === 'max' && affordableLevel === currentLevel && currentLevel < MAX_LEVEL) {
            totalCost = config[currentLevel + 1].cost;
            affordableLevel = currentLevel + 1;
        }

        return {
            levelsToAdd: affordableLevel - currentLevel,
            targetLevel: affordableLevel,
            cost: totalCost
        };
    }

    function renderSlotsGrid() {
        const grid = $('#slotsGrid');
        grid.empty();

        for (let i = 1; i <= TOTAL_SLOTS; i++) {
            const slot = slotsData[i];

            if (slot.empty) {
                const nextCost = config[1].cost;
                grid.append(`
                    <div class="miner-slot">
                        <div class="slot-header">
                            <h3>Khe Số ${i}</h3>
                            <span class="slot-badge">Trống</span>
                        </div>
                        <div class="slot-empty">
                            <i class="fas fa-plus-circle slot-icon"></i>
                            <div>Bấm để thuê thợ mỏ Gỗ</div>
                        </div>
                        <button class="btn-upgrade" onclick="upgradeSlot(${i}, 0, 1)">THUÊ (${nextCost.toLocaleString('vi-VN')} GTLM)</button>
                    </div>
                `);
            } else {
                const lvl = slot.level;
                const pct = slot.capacity_percent;
                const isMaxLvl = (lvl >= MAX_LEVEL);

                let upgradeBtn = '';
                if (!isMaxLvl) {
                    const upg = getUpgradeCost(lvl);
                    upgradeBtn = `<button class="btn-upgrade" onclick="upgradeSlot(${i}, ${lvl}, ${upg.levelsToAdd})">NÂNG +${upg.levelsToAdd} CẤP (${upg.cost.toLocaleString('vi-VN')} GTLM)</button>`;
                } else {
                    upgradeBtn = `<button class="btn-upgrade" disabled style="opacity: 0.5;">CẤP TỐI ĐA</button>`;
                }

                grid.append(`
                    <div class="miner-slot" id="slotCard_${i}">
                        <div class="slot-header">
                            <h3 style="color: var(--mining-primary); margin:0;">${slot.name}</h3>
                            <span class="slot-badge" style="background: rgba(46, 204, 113, 0.2); color: var(--success);">Cấp ${lvl}</span>
                        </div>
                        <div style="font-size: 0.9rem; margin-bottom: 5px;">Tốc độ: <b>${slot.rate.toLocaleString('vi-VN')} / h</b></div>
                        
                        <div class="capacity-bar-bg">
                            <div class="capacity-bar-fill" id="capFill_${i}" style="width: ${pct}%;"></div>
                        </div>
                        <div class="capacity-text">Sức chứa: <span id="capText_${i}">${pct}</span>%</div>
                        
                        ${upgradeBtn}
                    </div>
                `);
            }
        }
    }

    function startCounter() {
        if (counterInterval) clearInterval(counterInterval);
        if (totalRate <= 0) return;

        const ratePerSecond = totalRate / 3600;
        const tickRate = 100;
        const addPerTick = ratePerSecond * (tickRate / 1000);

        counterInterval = setInterval(() => {
            let isAllFull = true;
            let tickAdded = 0;

            for (let i = 1; i <= TOTAL_SLOTS; i++) {
                const slot = slotsData[i];
                if (!slot.empty) {
                    const pEl = $('#capText_' + i);
                    const fEl = $('#capFill_' + i);

                    let curPct = parseFloat(pEl.text());
                    if (curPct < 100) {
                        isAllFull = false;
                        const addPct = (100 / (24 * 3600)) * (tickRate / 1000);
                        curPct = Math.min(100, curPct + addPct);

                        pEl.text(curPct.toFixed(4));
                        fEl.css('width', curPct + '%');

                        tickAdded += (slot.rate / 3600) * (tickRate / 1000);
                    } else {
                        pEl.text('100.00');
                    }
                }
            }

            if (tickAdded > 0) {
                totalAccumulated += tickAdded;
                $('#totalAccumulatedVal').text(Math.floor(totalAccumulated).toLocaleString('vi-VN'));
            }

            if (isAllFull) {
                clearInterval(counterInterval);
            }
        }, tickRate);
    }

    window.upgradeSlot = function (slotIndex, currentLevel, levelsToAdd) {
        if (levelsToAdd <= 0) return;

        Swal.fire({
            title: 'Xác nhận?',
            text: `Bạn muốn nâng cấp khe số ${slotIndex} thêm ${levelsToAdd} cấp độ?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Đồng ý',
            cancelButtonText: 'Hủy'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Đang xử lý...', allowOutsideClick: false, showConfirmButton: false });

                $.post('../api_mining.php?action=upgrade', { slot: slotIndex, levels_to_add: levelsToAdd }, function (res) {
                    if (res.success) {
                        $('#userMoney').text(res.new_money);
                        Swal.fire('Thành công', res.message, 'success');
                        loadInfo();
                    } else {
                        Swal.fire('Lỗi', res.message, 'error');
                    }
                });
            }
        });
    };

    $('#btnClaimAll').click(function () {
        if (totalAccumulated < 1) return;

        const btn = $(this);
        btn.prop('disabled', true).text('ĐANG THU HOẠCH...');

        $.post('../api_mining.php?action=claim_all', function (res) {
            btn.prop('disabled', false).text('THU HOẠCH TẤT CẢ');

            if (res.success) {
                $('#userMoney').text(res.new_money);
                if (window.GameEffects) {
                    window.GameEffects.showWin(res.claimed);
                }
                Swal.fire({
                    title: 'Bội Thu!',
                    html: res.message,
                    icon: 'success'
                });
                loadInfo();
            } else {
                Swal.fire('Lỗi', res.message, 'error');
            }
        });
    });

    // Mua Chó
    $('#btnBuyGuard').click(function () {
        Swal.fire({
            title: 'Thuê Chó Canh Gác?',
            text: 'Mất 500.000 GTLM để bảo vệ mỏ khỏi bọn trộm trong 24 tiếng. Thuê?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74c3c'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('../api_mining_pvp.php?action=buy_guard', function (res) {
                    if (res.success) {
                        Swal.fire('Hoàn tất!', res.message, 'success');
                        loadInfo();
                    } else {
                        Swal.fire('Lỗi', res.message, 'error');
                    }
                });
            }
        });
    });

    // Mua Nâng Cấp Kho
    $('#btnUpgradeStorage').click(function () {
        Swal.fire({
            title: 'Nâng Cấp Kho?',
            text: 'Bạn có muốn dùng GTLM để mở rộng giới hạn lưu trữ AFK của kho không?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3498db'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('../api_mining.php?action=buy_storage', function (res) {
                    if (res.success) {
                        $('#userMoney').text(res.new_money);
                        Swal.fire('Hoàn tất!', res.message, 'success');
                        loadInfo();
                    } else {
                        Swal.fire('Lỗi', res.message, 'error');
                    }
                });
            }
        });
    });

    // Mua Boost x2
    $('#btnBuyBoost').click(function () {
        Swal.fire({
            title: 'Kích Hoạt X2?',
            text: 'Sử dụng 10,000,000 GTLM để nhân đôi tốc độ đào trong vòng 12 tiếng?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#9b59b6'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('../api_mining.php?action=buy_boost', function (res) {
                    if (res.success) {
                        $('#userMoney').text(res.new_money);
                        Swal.fire('Kích hoạt thành công!', res.message, 'success');
                        loadInfo();
                    } else {
                        Swal.fire('Lỗi', res.message, 'error');
                    }
                });
            }
        });
    });

    // ===== RAID PVP =====
    window.loadVulnerableList = function () {
        const listEl = $('#raidList');
        listEl.html('<div style="text-align:center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Đang dò tìm...</div>');

        $.get('../api_mining_pvp.php?action=vulnerable_list', function (res) {
            listEl.empty();
            if (res.success) {
                if (res.list.length === 0) {
                    listEl.html('<div style="text-align:center; padding: 20px; opacity: 0.5;">Hiện tại không có mỏ nào sơ hở. Hãy quay lại sau!</div>');
                } else {
                    res.list.forEach(target => {
                        listEl.append(`
                            <div class="raid-target">
                                <div>
                                    <h3 style="margin: 0 0 5px 0;">${target.name}</h3>
                                    <span style="font-size: 0.8rem; opacity: 0.7;">Có thợ mỏ cấp tối đa: ${target.level}</span>
                                </div>
                                <button class="btn-steal" onclick="raidTarget(${target.id}, '${target.name}')">
                                    <i class="fas fa-mask"></i> CƯỚP
                                </button>
                            </div>
                        `);
                    });
                }
            }
        });
    };

    window.raidTarget = function (targetId, targetName) {
        Swal.fire({
            title: 'Tiến hành Đột Nhập?',
            text: `Bạn sẽ cướp mỏ của ${targetName}. Nếu họ có chó canh gác, bạn sẽ bị cắn chạy té khói!`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Đột Nhập',
            confirmButtonColor: '#e74c3c'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('../api_mining_pvp.php?action=raid', { target_id: targetId }, function (res) {
                    if (res.success) {
                        $('#userMoney').text(res.new_money);
                        Swal.fire('Thành công', res.message, 'success');
                        loadVulnerableList(); // Refresh list
                    } else {
                        Swal.fire('Thất bại', res.message, 'error');
                        loadVulnerableList();
                    }
                });
            }
        });
    };

    // Run Initial
    loadInfo();
});

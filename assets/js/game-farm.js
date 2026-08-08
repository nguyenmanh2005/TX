let farmData = [];
let inventory = {};
let currentNow = new Date();
let selectedPlotIndex = -1;

function fetchFarm() {
    $.post('../api_farm.php', { action: 'get_farm' }, function (res) {
        if (res.success) {
            $('#userMoney').text(Number(res.money).toLocaleString());
            farmData = res.plots;
            inventory = res.inventory;
            currentNow = new Date(res.now);
            updateInventoryUI();
            renderGrid();
        }
    }, 'json');
}

function updateInventoryUI() {
    $('#inv_seed_wheat').text(inventory.seed_wheat);
    $('#inv_seed_corn').text(inventory.seed_corn);
    $('#inv_seed_tomato').text(inventory.seed_tomato);
    $('#inv_seed_apple').text(inventory.seed_apple);
    $('#inv_seed_watermelon').text(inventory.seed_watermelon);
    $('#inv_seed_strawberry').text(inventory.seed_strawberry);
    $('#inv_seed_grape').text(inventory.seed_grape);
    $('#inv_seed_peach').text(inventory.seed_peach);
    $('#inv_seed_cherry').text(inventory.seed_cherry);
    $('#inv_seed_lemon').text(inventory.seed_lemon);
    $('#inv_seed_banana').text(inventory.seed_banana);
    $('#inv_seed_kiwi').text(inventory.seed_kiwi);
    $('#inv_seed_mango').text(inventory.seed_mango);
    $('#inv_seed_pineapple').text(inventory.seed_pineapple);
    $('#inv_seed_coconut').text(inventory.seed_coconut);
    $('#inv_seed_melon').text(inventory.seed_melon);
    $('#inv_seed_orange').text(inventory.seed_orange);
    $('#inv_seed_avocado').text(inventory.seed_avocado);
    $('#inv_seed_pear').text(inventory.seed_pear);
    $('#inv_seed_pomegranate').text(inventory.seed_pomegranate);
    $('#inv_fertilizer').text(inventory.fertilizer);

    $('#inv_crop_wheat').text(inventory.crop_wheat);
    $('#inv_crop_corn').text(inventory.crop_corn);
    $('#inv_crop_tomato').text(inventory.crop_tomato);
    $('#inv_crop_apple').text(inventory.crop_apple);
    $('#inv_crop_watermelon').text(inventory.crop_watermelon);
    $('#inv_crop_strawberry').text(inventory.crop_strawberry);
    $('#inv_crop_grape').text(inventory.crop_grape);
    $('#inv_crop_peach').text(inventory.crop_peach);
    $('#inv_crop_cherry').text(inventory.crop_cherry);
    $('#inv_crop_lemon').text(inventory.crop_lemon);
    $('#inv_crop_banana').text(inventory.crop_banana);
    $('#inv_crop_kiwi').text(inventory.crop_kiwi);
    $('#inv_crop_mango').text(inventory.crop_mango);
    $('#inv_crop_pineapple').text(inventory.crop_pineapple);
    $('#inv_crop_coconut').text(inventory.crop_coconut);
    $('#inv_crop_melon').text(inventory.crop_melon);
    $('#inv_crop_orange').text(inventory.crop_orange);
    $('#inv_crop_avocado').text(inventory.crop_avocado);
    $('#inv_crop_pear').text(inventory.crop_pear);
    $('#inv_crop_pomegranate').text(inventory.crop_pomegranate);

    $('#modal_seed_wheat').text(inventory.seed_wheat);
    $('#modal_seed_corn').text(inventory.seed_corn);
    $('#modal_seed_tomato').text(inventory.seed_tomato);
    $('#modal_seed_apple').text(inventory.seed_apple);
    $('#modal_seed_watermelon').text(inventory.seed_watermelon);
    $('#modal_seed_strawberry').text(inventory.seed_strawberry);
    $('#modal_seed_grape').text(inventory.seed_grape);
    $('#modal_seed_peach').text(inventory.seed_peach);
    $('#modal_seed_cherry').text(inventory.seed_cherry);
    $('#modal_seed_lemon').text(inventory.seed_lemon);
    $('#modal_seed_banana').text(inventory.seed_banana);
    $('#modal_seed_kiwi').text(inventory.seed_kiwi);
    $('#modal_seed_mango').text(inventory.seed_mango);
    $('#modal_seed_pineapple').text(inventory.seed_pineapple);
    $('#modal_seed_coconut').text(inventory.seed_coconut);
    $('#modal_seed_melon').text(inventory.seed_melon);
    $('#modal_seed_orange').text(inventory.seed_orange);
    $('#modal_seed_avocado').text(inventory.seed_avocado);
    $('#modal_seed_pear').text(inventory.seed_pear);
    $('#modal_seed_pomegranate').text(inventory.seed_pomegranate);
    $('#modal_fertilizer').text(inventory.fertilizer);
}

function renderGrid() {
    let html = '';
    for (let i = 0; i < 9; i++) {
        let plot = farmData[i];
        if (!plot || !plot.seed_code) {
            // Đất trống
            html += `<div class="plot" onclick="openModal(${i}, 'empty')">
                        <div class="plot-icon" style="opacity: 0.2;">🟫</div>
                        <div class="plot-timer">Trống</div>
                     </div>`;
        } else {
            let harvestTime = new Date(plot.harvest_time).getTime();
            let nowTime = currentNow.getTime();
            let timeLeft = Math.floor((harvestTime - nowTime) / 1000);

            if (timeLeft <= 0) {
                // Chín
                let icon = '🌱';
                if (plot.seed_code === 'WHEAT') icon = '🌾';
                else if (plot.seed_code === 'CORN') icon = '🌽';
                else if (plot.seed_code === 'TOMATO') icon = '🍅';
                else if (plot.seed_code === 'APPLE') icon = '🍎';
                else if (plot.seed_code === 'WATERMELON') icon = '🍉';
                else if (plot.seed_code === 'STRAWBERRY') icon = '🍓';
                else if (plot.seed_code === 'GRAPE') icon = '🍇';
                else if (plot.seed_code === 'PEACH') icon = '🍑';
                else if (plot.seed_code === 'CHERRY') icon = '🍒';
                else if (plot.seed_code === 'LEMON') icon = '🍋';
                else if (plot.seed_code === 'BANANA') icon = '🍌';
                else if (plot.seed_code === 'KIWI') icon = '🥝';
                else if (plot.seed_code === 'MANGO') icon = '🥭';
                else if (plot.seed_code === 'PINEAPPLE') icon = '🍍';
                else if (plot.seed_code === 'COCONUT') icon = '🥥';
                else if (plot.seed_code === 'MELON') icon = '🍈';
                else if (plot.seed_code === 'ORANGE') icon = '🍊';
                else if (plot.seed_code === 'AVOCADO') icon = '🥑';
                else if (plot.seed_code === 'PEAR') icon = '🍐';
                else if (plot.seed_code === 'POMEGRANATE') icon = '🍎';
                html += `<div class="plot ready" onclick="harvest(${i})">
                            <div class="plot-status">CHÍN!</div>
                            <div class="plot-icon">${icon}</div>
                            <div class="plot-timer" style="color:#4ade80;">Thu hoạch</div>
                         </div>`;
            } else {
                // Đang lớn
                let icon = timeLeft > 60 ? '🌱' : '🌿';
                html += `<div class="plot" onclick="openModal(${i}, 'growing')" data-harvest="${harvestTime}">
                            <div class="plot-icon">${icon}</div>
                            <div class="plot-timer timer-text">${formatTime(timeLeft)}</div>
                         </div>`;
            }
        }
    }
    $('#farmGrid').html(html);
}

function formatTime(sec) {
    if (sec <= 0) return '00:00';
    let m = Math.floor(sec / 60).toString().padStart(2, '0');
    let s = (sec % 60).toString().padStart(2, '0');
    return `${m}:${s}`;
}

// Tick mỗi giây để cập nhật UI mượt mà
setInterval(() => {
    currentNow = new Date(currentNow.getTime() + 1000);
    $('.plot[data-harvest]').each(function () {
        let hTime = parseInt($(this).attr('data-harvest'));
        let tLeft = Math.floor((hTime - currentNow.getTime()) / 1000);
        if (tLeft <= 0) {
            fetchFarm(); // Gọi DB lấy cục diện mới khi có cây chín
        } else {
            $(this).find('.timer-text').text(formatTime(tLeft));
        }
    });
}, 1000);

function buyItem(itemCode) {
    Swal.fire({
        title: 'Mua Vật Phẩm',
        text: 'Nhập số lượng bạn muốn mua:',
        input: 'number',
        inputAttributes: { min: 1, step: 1 },
        inputValue: 1,
        showCancelButton: true,
        confirmButtonText: 'Mua Ngay',
        cancelButtonText: 'Hủy Bỏ',
        background: '#1e293b',
        color: '#f8fafc'
    }).then((result) => {
        if (result.isConfirmed) {
            let amount = parseInt(result.value);
            if (amount > 0) {
                $.post('../api_farm.php', { action: 'buy_item', item: itemCode, amount: amount }, function (res) {
                    if (res.success) {
                        Swal.fire({ title: 'Thành công!', text: res.message, icon: 'success', timer: 1500, showConfirmButton: false });
                        fetchFarm();
                    } else {
                        Swal.fire({ title: 'Lỗi', text: res.message, icon: 'error', background: '#111', color: '#fff' });
                    }
                }, 'json');
            }
        }
    });
}

function openModal(index, status) {
    selectedPlotIndex = index;
    if (status === 'empty') {
        $('.modal-title').text('Gieo Hạt');
        $('.seed-btn[onclick^="plantSeed"]').show();
        $('.seed-btn[onclick^="fertilizePlot"]').hide();
    } else if (status === 'growing') {
        $('.modal-title').text('Bón Phân');
        $('.seed-btn[onclick^="plantSeed"]').hide();
        $('.seed-btn[onclick^="fertilizePlot"]').show();
    }
    $('#seedModal').css('display', 'flex');
}

function plantSeed(seedCode) {
    $.post('../api_farm.php', { action: 'plant', plot_index: selectedPlotIndex, seed_code: seedCode }, function (res) {
        if (res.success) {
            $('#seedModal').hide();
            fetchFarm();
        } else {
            Swal.fire('Lỗi', res.message, 'error');
        }
    }, 'json');
}

function fertilizePlot() {
    Swal.fire({
        title: 'Bón Phân',
        text: 'Bạn muốn bón phân như thế nào?',
        icon: 'question',
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: 'Bón 1 Bao (-50% TG)',
        denyButtonText: 'Ép Chín Luôn',
        cancelButtonText: 'Hủy',
        background: '#1e293b',
        color: '#fff'
    }).then((result) => {
        if (result.isConfirmed) {
            executeFertilize('single');
        } else if (result.isDenied) {
            executeFertilize('max');
        }
    });
}

function executeFertilize(mode) {
    $.post('../api_farm.php', { action: 'fertilize', plot_index: selectedPlotIndex, mode: mode }, function (res) {
        if (res.success) {
            $('#seedModal').hide();
            Swal.fire({ title: 'Thành công!', text: res.message, icon: 'success', timer: 2000, showConfirmButton: false });
            fetchFarm();
        } else {
            Swal.fire('Lỗi', res.message, 'error');
        }
    }, 'json');
}

function harvest(index) {
    $.post('../api_farm.php', { action: 'harvest', plot_index: index }, function (res) {
        if (res.success) {
            fetchFarm();
            // Có thể thêm hiệu ứng bay GTLM
        } else {
            Swal.fire('Lỗi', res.message, 'error');
        }
    }, 'json');
}

$(document).ready(function () {
    fetchFarm();
    setInterval(fetchFarm, 10000); // Sync DB mỗi 10 giây
});

function harvestAll() {
    $.post('../api_farm.php', { action: 'harvest_all' }, function (res) {
        if (res.success) {
            Swal.fire({ title: 'Thu hoạch!', text: res.message, icon: 'success', timer: 1500, showConfirmButton: false });
            fetchFarm();
        } else {
            Swal.fire('Thất bại', res.message, 'error');
        }
    }, 'json');
}

let botActive = false;
let botConfig = { seed: '', limit: 0, autoFertilize: false, planted: 0 };
let botInterval = null;

function openBotConfigModal() {
    if (botActive) {
        Swal.fire('Bot đang chạy!', 'Bạn cần dừng Bot hiện tại trước khi cài đặt lại.', 'warning');
        return;
    }
    let options = '';
    const seeds = [
        { code: 'WHEAT', name: 'Lúa Mì' }, { code: 'CORN', name: 'Ngô' }, { code: 'TOMATO', name: 'Cà Chua' },
        { code: 'APPLE', name: 'Táo' }, { code: 'WATERMELON', name: 'Dưa Hấu' }, { code: 'STRAWBERRY', name: 'Dâu Tây' },
        { code: 'GRAPE', name: 'Nho' }, { code: 'PEACH', name: 'Đào Tiên' }, { code: 'CHERRY', name: 'Cherry' },
        { code: 'LEMON', name: 'Chanh' }, { code: 'BANANA', name: 'Chuối' }, { code: 'KIWI', name: 'Kiwi' },
        { code: 'MANGO', name: 'Xoài' }, { code: 'PINEAPPLE', name: 'Dứa' }, { code: 'COCONUT', name: 'Dừa' },
        { code: 'MELON', name: 'Dưa Lưới' }, { code: 'ORANGE', name: 'Cam' }, { code: 'AVOCADO', name: 'Bơ' },
        { code: 'PEAR', name: 'Lê' }, { code: 'POMEGRANATE', name: 'Lựu' }
    ];

    seeds.forEach(s => {
        let count = parseInt($('#inv_seed_' + s.code.toLowerCase()).text()) || 0;
        if (count > 0) {
            options += `<option value="${s.code}">${s.name} (Có sẵn: ${count})</option>`;
        }
    });

    if (!options) {
        Swal.fire('Kho rỗng!', 'Bạn không có hạt giống nào để Bot tự trồng.', 'error');
        return;
    }

    Swal.fire({
        title: '🤖 CÀI ĐẶT BOT AFK',
        html: `
            <div style="text-align: left; font-size: 0.9rem; margin-bottom: 15px; color: #94a3b8;">
                Bot sẽ tự động <b>Thu Hoạch -> Bón Phân -> Gieo Hạt</b> trên 9 ô đất của bạn mỗi giây.
            </div>
            <select id="botSeed" class="swal2-input" style="width: 80%; font-size: 1rem;">
                ${options}
            </select>
            <input id="botLimit" type="number" class="swal2-input" placeholder="Số lượng mục tiêu (VD: 100)" style="width: 80%;" min="1">
            <div style="margin-top: 15px; text-align: left; padding-left: 10%;">
                <label style="cursor: pointer; display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" id="botFertilize" style="width: 20px; height: 20px;">
                    Cho phép Bot dùng Phân bón
                </label>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Khởi Động Bot',
        cancelButtonText: 'Hủy',
        background: '#1e293b',
        color: '#fff',
        preConfirm: () => {
            const seed = document.getElementById('botSeed').value;
            const limit = parseInt(document.getElementById('botLimit').value);
            const useFert = document.getElementById('botFertilize').checked;
            if (!limit || limit <= 0) {
                Swal.showValidationMessage('Vui lòng nhập số lượng hợp lệ!');
                return false;
            }
            return { seed: seed, limit: limit, autoFertilize: useFert };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            startBot(result.value);
        }
    });
}

function startBot(config) {
    botConfig = config;
    botConfig.planted = 0;
    botActive = true;

    if ($('#bot-ui').length === 0) {
        $('body').append(`
            <div id="bot-ui" style="position: fixed; bottom: 20px; right: 20px; background: rgba(16, 185, 129, 0.95); padding: 15px; border-radius: 12px; color: #fff; z-index: 1000; box-shadow: 0 5px 20px rgba(0,0,0,0.6); border: 2px solid #34d399; width: 250px;">
                <h4 style="margin: 0 0 10px; font-size: 1.1rem; text-align: center;">🤖 BOT ĐANG CHẠY</h4>
                <div id="bot-status" style="font-size: 0.9rem; margin-bottom: 10px; min-height: 40px;">Khởi động...</div>
                <button onclick="stopBot()" style="background: #ef4444; border: none; padding: 8px; border-radius: 6px; color: #fff; font-weight: bold; cursor: pointer; width: 100%; transition: 0.2s;">🛑 Dừng Bot</button>
            </div>
        `);
    } else {
        $('#bot-ui').show();
    }

    botInterval = setInterval(botTick, 1000);
}

function stopBot() {
    botActive = false;
    clearInterval(botInterval);
    $('#bot-ui').hide();
    Swal.fire('Đã Dừng', 'Bot nông trại đã được tắt.', 'info');
}

function botTick() {
    if (!botActive) return;

    let readyPlots = farmData.map((p, i) => (p && p.seed_code && new Date(p.harvest_time).getTime() - currentNow.getTime() <= 0) ? i : -1).filter(i => i !== -1);
    if (readyPlots.length > 0) {
        $('#bot-status').text(`Đang gặt ${readyPlots.length} cây...`);
        $.post('../api_farm.php', { action: 'harvest_all' }, function (res) {
            if (res.success) fetchFarm();
        }, 'json');
        return;
    }

    let growingPlots = farmData.map((p, i) => (p && p.seed_code && new Date(p.harvest_time).getTime() - currentNow.getTime() > 0) ? i : -1).filter(i => i !== -1);
    if (botConfig.autoFertilize && growingPlots.length > 0) {
        let hasFert = parseInt($('#inv_fertilizer').text()) || 0;
        if (hasFert > 0) {
            $('#bot-status').text(`Đang bón phân ${Math.min(growingPlots.length, hasFert)} cây...`);
            $.post('../api_farm.php', { action: 'fertilize_all' }, function (res) {
                if (res.success) fetchFarm();
            }, 'json');
            return;
        }
    }

    let emptyPlots = farmData.map((p, i) => (!p || !p.seed_code) ? i : -1).filter(i => i !== -1);
    if (emptyPlots.length > 0 && botConfig.planted < botConfig.limit) {
        let remaining = botConfig.limit - botConfig.planted;
        let toPlant = Math.min(emptyPlots.length, remaining);
        $('#bot-status').html(`Mục tiêu: <b>${botConfig.planted}/${botConfig.limit}</b><br>Đang gieo ${toPlant} hạt...`);

        $.post('../api_farm.php', { action: 'plant_all', seed_code: botConfig.seed, limit: toPlant }, function (res) {
            if (res.success) {
                botConfig.planted += res.planted;
                fetchFarm();
                if (botConfig.planted >= botConfig.limit) {
                    stopBot();
                    Swal.fire('Hoàn thành', `Bot đã gieo đủ ${botConfig.limit} hạt!`, 'success');
                }
            } else {
                stopBot();
                Swal.fire('Lỗi Bot', res.message, 'error');
            }
        }, 'json');
        return;
    }

    if (botConfig.planted >= botConfig.limit) {
        stopBot();
        Swal.fire('Hoàn thành', `Bot đã gieo đủ ${botConfig.limit} hạt!`, 'success');
    } else {
        $('#bot-status').html(`Mục tiêu: <b>${botConfig.planted}/${botConfig.limit}</b><br>Đang chờ cây chín...`);
    }
}

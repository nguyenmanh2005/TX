$(document).ready(function() {
    let marketData = [];
    let inventoryData = {};
    let selectedCommodity = null;
    let chartInstance = null;

    function getIcon(code) {
        switch(code) {
            case 'STONE': return '<i class="fas fa-cubes text-gray-400"></i>';
            case 'IRON': return '<i class="fas fa-hammer text-gray-300"></i>';
            case 'GOLD': return '<i class="fas fa-coins text-yellow-400"></i>';
            case 'DIAMOND': return '<i class="far fa-gem text-blue-400"></i>';
            case 'WHEAT': return '🌾';
            case 'CORN': return '🌽';
            case 'TOMATO': return '🍅';
            case 'APPLE': return '🍎';
            case 'WATERMELON': return '🍉';
            case 'STRAWBERRY': return '🍓';
            case 'GRAPE': return '🍇';
            case 'PEACH': return '🍑';
            case 'CHERRY': return '🍒';
            case 'LEMON': return '🍋';
            case 'BANANA': return '🍌';
            case 'KIWI': return '🥝';
            case 'MANGO': return '🥭';
            case 'PINEAPPLE': return '🍍';
            case 'COCONUT': return '🥥';
            case 'MELON': return '🍈';
            case 'ORANGE': return '🍊';
            case 'AVOCADO': return '🥑';
            case 'PEAR': return '🍐';
            case 'POMEGRANATE': return '🍎'; // Using apple emoji for pomegranate as generic or 🍑
            default: return '<i class="fas fa-box text-gray-500"></i>';
        }
    }

    function loadMarket() {
        $.get('../api_market.php?action=info', function(res) {
            if (res.success) {
                marketData = res.market;
                inventoryData = res.inventory;
                renderTicker();
                renderCommodities();
                
                if (!selectedCommodity && marketData.length > 0) {
                    selectCommodity(marketData[0]);
                } else if (selectedCommodity) {
                    let updated = marketData.find(c => c.commodity_code === selectedCommodity.commodity_code);
                    if (updated) selectCommodity(updated);
                }
            }
        }, 'json');
    }

    function renderTicker() {
        let html = '';
        marketData.forEach(c => {
            let history = c.history_prices ? JSON.parse(c.history_prices) : [];
            let price = parseFloat(c.current_price);
            let prevPrice = history.length > 1 ? parseFloat(history[history.length - 2]) : parseFloat(c.base_price);
            
            let isDown = price < prevPrice;
            let arrow = isDown ? '▼' : '▲';
            let color = isDown ? '#ef4444' : '#10b981';
            let pct = prevPrice > 0 ? (((price - prevPrice) / prevPrice) * 100).toFixed(2) : 0;

            html += `<span class="ticker-item">${c.commodity_code} <span style="color:${color}">${price.toLocaleString('vi-VN')} ${arrow} ${Math.abs(pct)}%</span></span>`;
        });
        
        // Clone for infinite scroll
        $('#tickerContent').html(html + html + html);
    }

    function renderCommodities() {
        let html = '';
        marketData.forEach(c => {
            let history = c.history_prices ? JSON.parse(c.history_prices) : [];
            let price = parseFloat(c.current_price);
            let prevPrice = history.length > 1 ? parseFloat(history[history.length - 2]) : parseFloat(c.base_price);
            
            let isDown = price < prevPrice;
            let pClass = isDown ? 'price-down' : 'price-up';
            let isActive = selectedCommodity && selectedCommodity.commodity_code === c.commodity_code ? 'active' : '';

            let invQty = inventoryData[c.commodity_code.toLowerCase()] || 0;

            html += `
                <div class="stock-item ${isActive}" data-code="${c.commodity_code}">
                    <div style="display:flex; align-items:center; gap: 10px;">
                        <div style="font-size:1.5rem;">${getIcon(c.commodity_code)}</div>
                        <div>
                            <div style="font-weight:bold; font-size:1.1rem; color:#fff;">${c.commodity_name}</div>
                            <div style="font-size:0.8rem; color:#888;">SL: <b style="color:#fbbf24">${invQty}</b></div>
                        </div>
                    </div>
                    <div class="${pClass}" style="font-size:1.2rem;">${price.toLocaleString('vi-VN')}</div>
                </div>
            `;
        });
        $('#commoditiesList').html(html);

        $('.stock-item').click(function() {
            let code = $(this).data('code');
            let item = marketData.find(c => c.commodity_code === code);
            if (item) selectCommodity(item);
        });
    }

    function selectCommodity(item) {
        selectedCommodity = item;
        $('.stock-item').removeClass('active');
        $(`.stock-item[data-code="${item.commodity_code}"]`).addClass('active');
        
        $('#tradePanel').show();
        $('#tradeTitle').html(`${getIcon(item.commodity_code)} Giao dịch ${item.commodity_name}`);
        
        let invQty = inventoryData[item.commodity_code.toLowerCase()] || 0;
        $('#inventoryQty').text(invQty);

        let history = item.history_prices ? JSON.parse(item.history_prices) : [];
        if (history.length === 0) history.push(item.current_price);
        
        let price = parseFloat(item.current_price);
        let prevPrice = history.length > 1 ? parseFloat(history[history.length - 2]) : parseFloat(item.base_price);
        let isDown = price < prevPrice;
        let color = isDown ? '#ef4444' : '#10b981';

        $('#chartTitle').html(`${item.commodity_name} / GTLM`);
        $('#chartPrice').html(`${price.toLocaleString('vi-VN')}`).css('color', color);

        calcTotal();

        // Draw Chart
        let labels = history.map((_, i) => `Phiên ${i+1}`);
        let dataPrices = history.map(p => parseFloat(p));

        const ctx = document.getElementById('marketChart').getContext('2d');
        if (chartInstance) chartInstance.destroy();

        // Gradient for chart
        let gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, isDown ? 'rgba(239, 68, 68, 0.5)' : 'rgba(16, 185, 129, 0.5)');
        gradient.addColorStop(1, 'rgba(0, 0, 0, 0)');

        chartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: `Giá ${item.commodity_code}`,
                    data: dataPrices,
                    borderColor: color,
                    backgroundColor: gradient,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: color,
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleFont: { family: 'Space Grotesk', size: 14 },
                        bodyFont: { family: 'Space Grotesk', size: 16 }
                    }
                },
                scales: {
                    x: { ticks: { color: '#888' }, grid: { color: '#222', drawBorder: false } },
                    y: { ticks: { color: '#888', font: {family: 'Space Grotesk'} }, grid: { color: '#222', drawBorder: false } }
                },
                interaction: { mode: 'nearest', axis: 'x', intersect: false }
            }
        });
    }

    function calcTotal() {
        if (!selectedCommodity) return;
        let qty = parseInt($('#tradeQty').val()) || 0;
        let total = qty * parseFloat(selectedCommodity.current_price);
        $('#totalCost').text(total.toLocaleString('vi-VN') + ' GTLM');
    }

    $('#tradeQty').on('input', calcTotal);

    function trade(type) {
        if (!selectedCommodity) return;
        let qty = parseInt($('#tradeQty').val()) || 0;
        if (qty <= 0) return;

        let btn = type === 'buy' ? $('#btnBuy') : $('#btnSell');
        let oldHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> XỬ LÝ...');

        $.post('../api_market.php', {
            action: 'trade',
            type: type,
            code: selectedCommodity.commodity_code,
            amount: qty
        }, function(res) {
            btn.prop('disabled', false).html(oldHtml);
            if (res.success) {
                $('#userMoney').text(parseInt(res.new_money).toLocaleString('vi-VN'));
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: res.message,
                    showConfirmButton: false,
                    timer: 2000
                });
                loadMarket();
            } else {
                Swal.fire({ title: 'Thất Bại', text: res.message, icon: 'error', background: '#111', color: '#fff' });
            }
        }, 'json');
    }

    $('#btnBuy').click(() => trade('buy'));
    $('#btnSell').click(() => trade('sell'));

    loadMarket();
    setInterval(loadMarket, 20000); // Tự động load lại giá mỗi 20 giây
});

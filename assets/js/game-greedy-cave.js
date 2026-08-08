$(document).ready(function () {
    let currentStep = 0;

    // Định dạng số
    $('#betAmount').on('input', function () {
        let value = $(this).val().replace(/[^0-9]/g, '');
        if (value) {
            $(this).val(parseInt(value, 10).toLocaleString('vi-VN'));
        }
    });

    function loadStatus() {
        $.post('../api_greedy_cave.php', { action: 'status' }, function (res) {
            if (res.success && res.has_session) {
                let cave = res.session;
                if (cave.status === 'playing') {
                    $('#setupPanel').hide();
                    $('#actionPanel').show();
                    updateUI(cave.current_step, cave.accumulated_prize);
                    $('#statusTitle').text('Đang ở trong hang...').css('color', '#3b82f6');
                } else {
                    $('#setupPanel').show();
                    $('#actionPanel').hide();
                    updateUI(0, 0);
                    $('#statusTitle').text('Chuẩn bị thám hiểm').css('color', '#eab308');
                    $('#characterIcon').removeClass('crashed').html('<i class="fas fa-user-astronaut"></i>');
                }
            } else {
                $('#setupPanel').show();
                $('#actionPanel').hide();
                updateUI(0, 0);
                $('#statusTitle').text('Chuẩn bị thám hiểm').css('color', '#eab308');
                $('#characterIcon').removeClass('crashed').html('<i class="fas fa-user-astronaut"></i>');
            }
        }, 'json');
    }

    function updateUI(step, prize) {
        currentStep = step;
        $('#txtStep').text(step);
        $('#txtPrize').text(parseInt(prize).toLocaleString('vi-VN') + ' GTLM');

        let risk = Math.max(15, 100 - ((step + 1) * 5));
        let crashChance = 100 - risk;
        $('#txtRisk').text(crashChance + '%');
        $('#riskBar').css('width', crashChance + '%');

        if (crashChance > 50) {
            $('#txtRisk').css('color', '#ef4444');
            $('#riskBar').css('background', '#ef4444');
        } else if (crashChance > 20) {
            $('#txtRisk').css('color', '#eab308');
            $('#riskBar').css('background', '#eab308');
        } else {
            $('#txtRisk').css('color', '#22c55e');
            $('#riskBar').css('background', '#22c55e');
        }
    }

    $('#btnStart').click(function () {
        let bet = $('#betAmount').val();
        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> ĐANG VÀO...');

        $.post('../api_greedy_cave.php', { action: 'start', bet: bet }, function (res) {
            $('#btnStart').prop('disabled', false).html('<i class="fas fa-play"></i> TIẾN VÀO HANG');
            if (res.success) {
                loadStatus();
                // Update money text temporarily
                let currentMoney = parseInt($('#userMoney').text().replace(/\./g, ''));
                let betValue = parseInt(bet.replace(/\./g, ''));
                $('#userMoney').text((currentMoney - betValue).toLocaleString('vi-VN'));
            } else {
                Swal.fire('Lỗi', res.message, 'error');
            }
        }, 'json');
    });

    $('#btnStep').click(function () {
        $('#btnStep, #btnCashout').prop('disabled', true);

        // Animation bước đi
        $('#characterIcon').addClass('walking');

        setTimeout(() => {
            $.post('../api_greedy_cave.php', { action: 'step' }, function (res) {
                $('#characterIcon').removeClass('walking');
                $('#btnStep, #btnCashout').prop('disabled', false);

                if (res.success) {
                    if (res.survived) {
                        updateUI(res.step, res.prize);
                        // Show subtle toast
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: 'An toàn! x' + res.multiplier.toFixed(2),
                            showConfirmButton: false,
                            timer: 1500
                        });
                    } else {
                        // Sập hầm
                        $('#characterIcon').addClass('crashed').html('<i class="fas fa-skull"></i>');
                        $('#txtPrize').text('0 GTLM').removeClass('highlight-money').css('color', '#ef4444');
                        $('#statusTitle').text('BẠN ĐÃ CHẾT!').css('color', '#ef4444');

                        Swal.fire({
                            title: 'SẬP HẦM!',
                            text: 'Tham thì thâm! Bạn đã chết và mất trắng toàn bộ GTLM!',
                            icon: 'error',
                            confirmButtonColor: '#ef4444'
                        }).then(() => {
                            loadStatus();
                        });
                    }
                } else {
                    Swal.fire('Lỗi', res.message, 'error');
                }
            }, 'json');
        }, 600); // 600ms delay for suspense
    });

    $('#btnCashout').click(function () {
        if (currentStep === 0) {
            Swal.fire('Thông báo', 'Bạn phải bước ít nhất 1 bước mới được rút GTLM!', 'info');
            return;
        }

        $('#btnStep, #btnCashout').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> XỬ LÝ...');

        $.post('../api_greedy_cave.php', { action: 'cashout' }, function (res) {
            $('#btnStep').html('<i class="fas fa-shoe-prints"></i> BƯỚC TIẾP');
            $('#btnCashout').html('<i class="fas fa-hand-holding-usd"></i> CHẠY TRỐN');
            $('#btnStep, #btnCashout').prop('disabled', false);

            if (res.success) {
                $('#userMoney').text(parseInt(res.new_money).toLocaleString('vi-VN'));
                Swal.fire({
                    title: 'Sống sót!',
                    text: res.message,
                    icon: 'success',
                    confirmButtonColor: '#22c55e'
                }).then(() => {
                    loadStatus();
                });
            } else {
                Swal.fire('Lỗi', res.message, 'error');
            }
        }, 'json');
    });

    loadStatus();
});

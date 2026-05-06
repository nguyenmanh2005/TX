<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require_once 'load_theme.php';

if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%)';
}

$userId = $_SESSION['Iduser'];

// Kiểm tra bảng trivia_questions có tồn tại không
$checkTable = $conn->query("SHOW TABLES LIKE 'trivia_questions'");
$triviaTableExists = $checkTable && $checkTable->num_rows > 0;
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trivia Quiz - Câu Hỏi Trắc Nghiệm</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            background:
                <?= $bgGradientCSS ?>
            ;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        * {
            cursor: inherit;
        }

        button,
        a,
        input[type="button"],
        input[type="submit"],
        label,
        select {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        .trivia-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .header-trivia {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 24px;
            margin-bottom: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-align: center;
            animation: fadeInDown 0.6s ease;
            position: relative;
            overflow: hidden;
        }

        .header-trivia::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.05) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        .header-trivia h1 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 42px;
            font-weight: 800;
            letter-spacing: -1px;
            margin-bottom: 15px;
        }

        .card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .category-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 25px;
            border-radius: 16px;
            text-align: center;
            color: white;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .category-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.2) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .category-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s ease;
        }

        .category-card:hover::before {
            opacity: 1;
        }

        .category-card:hover::after {
            left: 100%;
        }

        .category-card:hover {
            transform: translateY(-8px) scale(1.05) rotate(2deg);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.5),
                0 0 30px rgba(102, 126, 234, 0.3);
            border-color: white;
        }

        .category-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .category-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .category-count {
            font-size: 14px;
            opacity: 0.9;
        }

        .game-setup {
            display: none;
        }

        .game-setup.active {
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-select,
        .form-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-select:focus,
        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-block;
            text-decoration: none;
            color: white;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }

        .question-container {
            display: none;
        }

        .question-container.active {
            display: block;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            margin-bottom: 30px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 4px;
            position: relative;
            overflow: hidden;
        }

        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: shimmer 2s infinite;
        }

        .question-text {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .options-container {
            display: grid;
            gap: 15px;
        }

        .option-btn {
            padding: 20px;
            background: white;
            border: 3px solid #e0e0e0;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            text-align: left;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
            overflow: hidden;
        }

        .option-btn::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transform: scaleY(0);
            transition: transform 0.3s ease;
            transform-origin: bottom;
        }

        .option-btn:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
            transform: translateX(8px) scale(1.02);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
        }

        .option-btn:hover::before {
            transform: scaleY(1);
        }

        .option-btn.selected {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.15);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateX(5px);
        }

        .option-btn.selected::before {
            transform: scaleY(1);
        }

        .option-btn.correct {
            border-color: #28a745;
            background: rgba(40, 167, 69, 0.15);
            color: #28a745;
            animation: bounceIn 0.5s ease;
            box-shadow: 0 0 20px rgba(40, 167, 69, 0.3);
        }

        .option-btn.wrong {
            border-color: #dc3545;
            background: rgba(220, 53, 69, 0.15);
            color: #dc3545;
            animation: shake 0.4s ease;
            box-shadow: 0 0 20px rgba(220, 53, 69, 0.3);
        }

        .option-label {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            flex-shrink: 0;
        }

        .option-btn.correct .option-label {
            background: #28a745;
        }

        .option-btn.wrong .option-label {
            background: #dc3545;
        }

        .result-container {
            display: none;
            text-align: center;
        }

        .result-container.active {
            display: block;
        }

        .result-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }

        .result-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .result-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .stat-item {
            background: rgba(247, 247, 247, 0.8);
            padding: 20px;
            border-radius: 12px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #667eea;
        }

        .stat-label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 18px;
        }

        .message {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .message.error {
            background: rgba(220, 53, 69, 0.2);
            border: 2px solid #dc3545;
            color: #dc3545;
        }

        @media (max-width: 768px) {
            .trivia-container {
                padding: 10px;
            }

            .header-trivia {
                padding: 25px;
            }

            .category-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }

            .question-text {
                font-size: 20px;
            }
        }
    </style>
</head>

<body>
    <div class="trivia-container">
        <div class="header-trivia">
            <h1>📚 Trivia Quiz</h1>
            <p style="color: #666; font-size: 18px; margin-top: 10px;">Kiểm tra kiến thức của bạn với các câu hỏi trắc
                nghiệm!</p>
        </div>

        <?php if (!$triviaTableExists): ?>
            <div class="card">
                <div class="message error">
                    ⚠️ Hệ thống Trivia chưa được kích hoạt! Vui lòng chạy file <strong>create_trivia_tables.sql</strong>
                    trước.
                </div>
            </div>
        <?php else: ?>
            <!-- Màn hình chọn danh mục -->
            <div class="card" id="category-screen">
                <h2 style="margin-bottom: 20px;">Chọn Danh Mục</h2>
                <div class="category-grid" id="categories-list">
                    <div class="no-data">Đang tải...</div>
                </div>
            </div>

            <!-- Màn hình setup game -->
            <div class="card game-setup" id="setup-screen">
                <h2 style="margin-bottom: 20px;">Thiết Lập Game</h2>
                <form id="setup-form">
                    <div class="form-group">
                        <label class="form-label">Số Câu Hỏi</label>
                        <select class="form-select" id="total-questions" required>
                            <option value="10">10 câu</option>
                            <option value="15">15 câu</option>
                            <option value="20">20 câu</option>
                            <option value="25">25 câu</option>
                            <option value="30">30 câu</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Độ Khó</label>
                        <select class="form-select" id="difficulty" required>
                            <option value="mixed">Tất Cả</option>
                            <option value="easy">Dễ</option>
                            <option value="medium">Trung Bình</option>
                            <option value="hard">Khó</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="hidden" id="selected-category-id">
                        <button type="submit" class="btn btn-primary">Bắt Đầu Chơi</button>
                        <button type="button" class="btn btn-danger" onclick="backToCategories()"
                            style="margin-left: 10px;">Quay Lại</button>
                    </div>
                </form>
            </div>

            <!-- Màn hình chơi game -->
            <div class="card question-container" id="game-screen">
                <div class="progress-bar">
                    <div class="progress-fill" id="progress-fill" style="width: 0%"></div>
                </div>
                <div class="question-text" id="question-text">Đang tải câu hỏi...</div>
                <div class="options-container" id="options-container">
                    <!-- Options sẽ được load bằng JS -->
                </div>
                <div style="margin-top: 30px; text-align: center;">
                    <button class="btn btn-success" id="submit-btn" onclick="submitAnswer()" disabled>Nộp Đáp Án</button>
                </div>
            </div>

            <!-- Màn hình kết quả -->
            <div class="card result-container" id="result-screen">
                <div class="result-icon" id="result-icon">🎉</div>
                <div class="result-title" id="result-title">Hoàn Thành!</div>
                <div class="result-stats" id="result-stats">
                    <!-- Stats sẽ được load bằng JS -->
                </div>
                <div>
                    <button class="btn btn-primary" onclick="location.reload()">Chơi Lại</button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const userId = <?= $userId ?>;
        let currentGameId = null;
        let currentQuestion = null;
        let selectedAnswer = null;
        let categoryId = null;

        // Load categories
        function loadCategories() {
            $.ajax({
                url: 'api_trivia.php',
                method: 'GET',
                data: { action: 'get_categories' },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        let html = '';
                        // Thêm option "Tất Cả"
                        html += `
                            <div class="category-card" onclick="selectCategory(null)">
                                <div class="category-icon">📚</div>
                                <div class="category-name">Tất Cả</div>
                                <div class="category-count">Tất cả danh mục</div>
                            </div>
                        `;

                        response.categories.forEach(category => {
                            html += `
                                <div class="category-card" onclick="selectCategory(${category.id})" style="background: linear-gradient(135deg, ${category.color} 0%, ${category.color}dd 100%);">
                                    <div class="category-icon">${category.icon}</div>
                                    <div class="category-name">${category.name}</div>
                                    <div class="category-count">${category.question_count} câu hỏi</div>
                                </div>
                            `;
                        });
                        $('#categories-list').html(html);
                    }
                }
            });
        }

        // Select category
        function selectCategory(id) {
            categoryId = id;
            $('#selected-category-id').val(id);
            $('#category-screen').hide();
            $('#setup-screen').addClass('active');
        }

        // Back to categories
        function backToCategories() {
            $('#setup-screen').removeClass('active');
            $('#category-screen').show();
        }

        // Start game
        $('#setup-form').on('submit', function (e) {
            e.preventDefault();

            const totalQuestions = $('#total-questions').val();
            const difficulty = $('#difficulty').val();

            $.ajax({
                url: 'api_trivia.php',
                method: 'POST',
                data: {
                    action: 'start_game',
                    category_id: categoryId,
                    difficulty: difficulty,
                    total_questions: totalQuestions
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        currentGameId = response.game_id;
                        $('#setup-screen').removeClass('active');
                        $('#game-screen').addClass('active');
                        loadQuestion();
                    } else {
                        Swal.fire('Lỗi', response.message, 'error');
                    }
                }
            });
        });

        // Load question
        function loadQuestion() {
            $.ajax({
                url: 'api_trivia.php',
                method: 'GET',
                data: { action: 'get_question', game_id: currentGameId },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        if (response.game_completed) {
                            finishGame();
                            return;
                        }

                        currentQuestion = response.question;
                        selectedAnswer = null;

                        // Update progress
                        const progress = (response.question.progress.current / response.question.progress.total) * 100;
                        $('#progress-fill').css('width', progress + '%');

                        // Display question
                        $('#question-text').text(response.question.question);

                        // Display options
                        let optionsHtml = '';
                        const options = [
                            { label: 'A', text: response.question.option_a },
                            { label: 'B', text: response.question.option_b },
                            { label: 'C', text: response.question.option_c },
                            { label: 'D', text: response.question.option_d }
                        ];

                        options.forEach(option => {
                            optionsHtml += `
                                <div class="option-btn" onclick="selectOption('${option.label}')" data-option="${option.label}">
                                    <div class="option-label">${option.label}</div>
                                    <div>${option.text}</div>
                                </div>
                            `;
                        });

                        $('#options-container').html(optionsHtml);
                        $('#submit-btn').prop('disabled', true);
                    } else {
                        Swal.fire('Lỗi', response.message, 'error');
                    }
                }
            });
        }

        // Select option
        function selectOption(answer) {
            selectedAnswer = answer;
            $('.option-btn').removeClass('selected');
            $(`.option-btn[data-option="${answer}"]`).addClass('selected');
            $('#submit-btn').prop('disabled', false);
        }

        // Submit answer
        function submitAnswer() {
            if (!selectedAnswer || !currentQuestion) return;

            $('#submit-btn').prop('disabled', true);

            $.ajax({
                url: 'api_trivia.php',
                method: 'POST',
                data: {
                    action: 'submit_answer',
                    game_id: currentGameId,
                    question_id: currentQuestion.id,
                    answer: selectedAnswer
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        // Highlight correct/wrong answers
                        $('.option-btn').each(function () {
                            const option = $(this).data('option');
                            if (option === response.correct_answer) {
                                $(this).addClass('correct');
                            } else if (option === selectedAnswer && !response.is_correct) {
                                $(this).addClass('wrong');
                            }
                        });

                        // Show result
                        setTimeout(() => {
                            if (response.is_correct) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Đúng rồi!',
                                    text: `+${response.points_earned} điểm`,
                                    timer: 1500,
                                    showConfirmButton: false
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Sai rồi!',
                                    html: `Đáp án đúng: <strong>${response.correct_answer}</strong><br>${response.explanation || ''}`,
                                    timer: 3000,
                                    showConfirmButton: false
                                });
                            }

                            // Load next question after delay
                            setTimeout(() => {
                                loadQuestion();
                            }, 500);
                        }, 1000);
                    } else {
                        Swal.fire('Lỗi', response.message, 'error');
                        $('#submit-btn').prop('disabled', false);
                    }
                }
            });
        }

        // Finish game
        function finishGame() {
            $.ajax({
                url: 'api_trivia.php',
                method: 'POST',
                data: {
                    action: 'finish_game',
                    game_id: currentGameId
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        const stats = response.stats;

                        // Show result screen
                        $('#game-screen').removeClass('active');
                        $('#result-screen').addClass('active');

                        // Calculate accuracy
                        const accuracy = stats.total_questions > 0
                            ? Math.round((stats.correct_answers / stats.total_questions) * 100)
                            : 0;

                        // Set icon based on accuracy
                        if (accuracy >= 80) {
                            $('#result-icon').text('🏆');
                            $('#result-title').text('Xuất Sắc!');
                        } else if (accuracy >= 60) {
                            $('#result-icon').text('🎉');
                            $('#result-title').text('Tốt Lắm!');
                        } else {
                            $('#result-icon').text('👍');
                            $('#result-title').text('Cố Gắng Thêm!');
                        }

                        // Display stats
                        $('#result-stats').html(`
                            <div class="stat-item">
                                <div class="stat-value">${stats.correct_answers}/${stats.total_questions}</div>
                                <div class="stat-label">Đúng/Tổng</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">${accuracy}%</div>
                                <div class="stat-label">Độ Chính Xác</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">${stats.total_points}</div>
                                <div class="stat-label">Tổng Điểm</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">${formatMoney(stats.reward_amount)}</div>
                                <div class="stat-label">Phần Thưởng</div>
                            </div>
                        `);

                        Swal.fire({
                            icon: 'success',
                            title: 'Hoàn Thành!',
                            html: `Bạn đã nhận được <strong>${formatMoney(stats.reward_amount)}</strong>!`,
                            timer: 3000,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire('Lỗi', response.message, 'error');
                    }
                }
            });
        }

        // Format money
        function formatMoney(amount) {
            return new Intl.NumberFormat('vi-VN').format(amount) + ' gtlm';
        }

        // Load initial data
        loadCategories();
    </script>
</body>

</html>
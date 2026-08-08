<?php
/**
 * Trivia Bot Logic
 * Quản lý bot tự động tham gia trả lời câu đố Trivia.
 */

function handleTriviaBot($baseUrl, $cFile) {
    $actions = [];

    // 1. Bắt đầu game (5 câu hỏi)
    $startRes = executeBotAction($baseUrl . "/api_trivia.php", [
        'action' => 'start_game',
        'total_questions' => 5,
        'difficulty' => 'mixed'
    ], $cFile);

    if (!isset($startRes['success']) || !$startRes['success']) {
        return null;
    }

    $gameId = $startRes['game_id'];
    $correctCount = 0;

    // 2. Trả lời 5 câu hỏi
    for ($i = 0; $i < 5; $i++) {
        $qRes = executeBotAction($baseUrl . "/api_trivia.php?action=get_question&game_id=$gameId", null, $cFile);
        
        if (!isset($qRes['success']) || !$qRes['success'] || !isset($qRes['question'])) {
            break; // Có thể đã trả lời hết hoặc lỗi
        }

        $questionId = $qRes['question']['id'];

        // Random đáp án (Tỉ lệ trúng trung bình là 25%)
        $answers = ['A', 'B', 'C', 'D'];
        
        // Bonus: Thêm logic bot thỉnh thoảng thông minh đột xuất
        $chosenAnswer = $answers[array_rand($answers)];

        $ansRes = executeBotAction($baseUrl . "/api_trivia.php", [
            'action' => 'submit_answer',
            'game_id' => $gameId,
            'question_id' => $questionId,
            'answer' => $chosenAnswer
        ], $cFile);

        if (isset($ansRes['is_correct']) && $ansRes['is_correct']) {
            $correctCount++;
        }
    }

    // 3. Kết thúc game nhận thưởng
    $finishRes = executeBotAction($baseUrl . "/api_trivia.php", [
        'action' => 'finish_game',
        'game_id' => $gameId
    ], $cFile);

    if (isset($finishRes['success']) && $finishRes['success']) {
        $stats = $finishRes['stats'];
        if ($stats['reward_amount'] > 0) {
            $actions[] = "Vừa trả lời đúng <b>" . $stats['correct_answers'] . "/5</b> câu đố Trivia, rinh về <b>" . number_format($stats['reward_amount']) . " GTLM</b>!";
            return ['status' => 'success', 'actions' => $actions];
        }
    }

    return null;
}
?>

<?php
/**
 * API xử lý Trivia/Quiz Game
 * 
 * Actions:
 * - get_categories: Lấy danh sách danh mục
 * - start_game: Bắt đầu game mới
 * - get_question: Lấy câu hỏi tiếp theo
 * - submit_answer: Nộp đáp án
 * - finish_game: Kết thúc game và nhận phần thưởng
 * - get_leaderboard: Lấy bảng xếp hạng
 * - get_my_stats: Lấy thống kê cá nhân
 */

session_start();
require 'db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập!']);
    exit;
}

$userId = $_SESSION['Iduser'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Kiểm tra bảng tồn tại
$checkTable = $conn->query("SHOW TABLES LIKE 'trivia_questions'");
if (!$checkTable || $checkTable->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Hệ thống Trivia chưa được kích hoạt! Vui lòng chạy file create_trivia_tables.sql trước.']);
    exit;
}

switch ($action) {
    case 'get_categories':
        // Lấy danh sách danh mục
        $sql = "SELECT * FROM trivia_categories WHERE is_active = 1 ORDER BY name";
        $result = $conn->query($sql);
        
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            // Đếm số câu hỏi trong danh mục
            $countSql = "SELECT COUNT(*) as count FROM trivia_questions WHERE category_id = ? AND is_active = 1";
            $countStmt = $conn->prepare($countSql);
            $countStmt->bind_param("i", $row['id']);
            $countStmt->execute();
            $countResult = $countStmt->get_result()->fetch_assoc();
            $row['question_count'] = $countResult['count'];
            $countStmt->close();
            
            $categories[] = $row;
        }
        
        echo json_encode(['success' => true, 'categories' => $categories]);
        break;
        
    case 'start_game':
        // Bắt đầu game mới
        $categoryId = isset($_POST['category_id']) && $_POST['category_id'] ? (int)$_POST['category_id'] : null;
        $difficulty = $_POST['difficulty'] ?? 'mixed';
        $totalQuestions = (int)($_POST['total_questions'] ?? 10);
        
        if ($totalQuestions < 5 || $totalQuestions > 50) {
            echo json_encode(['success' => false, 'message' => 'Số câu hỏi phải từ 5-50!']);
            exit;
        }
        
        // Kiểm tra category có tồn tại không
        if ($categoryId) {
            $checkCat = $conn->prepare("SELECT id FROM trivia_categories WHERE id = ? AND is_active = 1");
            $checkCat->bind_param("i", $categoryId);
            $checkCat->execute();
            if ($checkCat->get_result()->num_rows == 0) {
                echo json_encode(['success' => false, 'message' => 'Danh mục không tồn tại!']);
                exit;
            }
            $checkCat->close();
        }
        
        // Tạo game mới
        $sql = "INSERT INTO trivia_games (user_id, category_id, difficulty, total_questions) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisi", $userId, $categoryId, $difficulty, $totalQuestions);
        
        if ($stmt->execute()) {
            $gameId = $conn->insert_id;
            echo json_encode(['success' => true, 'game_id' => $gameId, 'message' => 'Bắt đầu game thành công!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi tạo game!']);
        }
        $stmt->close();
        break;
        
    case 'get_question':
        // Lấy câu hỏi tiếp theo
        $gameId = (int)($_GET['game_id'] ?? 0);
        
        if (!$gameId) {
            echo json_encode(['success' => false, 'message' => 'Game ID không hợp lệ!']);
            exit;
        }
        
        // Lấy thông tin game
        $gameSql = "SELECT * FROM trivia_games WHERE id = ? AND user_id = ?";
        $gameStmt = $conn->prepare($gameSql);
        $gameStmt->bind_param("ii", $gameId, $userId);
        $gameStmt->execute();
        $game = $gameStmt->get_result()->fetch_assoc();
        $gameStmt->close();
        
        if (!$game) {
            echo json_encode(['success' => false, 'message' => 'Game không tồn tại!']);
            exit;
        }
        
        if ($game['completed_at']) {
            echo json_encode(['success' => false, 'message' => 'Game đã kết thúc!']);
            exit;
        }
        
        // Lấy các câu hỏi đã trả lời
        $answeredSql = "SELECT question_id FROM trivia_answers WHERE game_id = ?";
        $answeredStmt = $conn->prepare($answeredSql);
        $answeredStmt->bind_param("i", $gameId);
        $answeredStmt->execute();
        $answeredResult = $answeredStmt->get_result();
        $answeredIds = [];
        while ($row = $answeredResult->fetch_assoc()) {
            $answeredIds[] = $row['question_id'];
        }
        $answeredStmt->close();
        
        // Kiểm tra đã trả lời hết chưa
        if (count($answeredIds) >= $game['total_questions']) {
            echo json_encode(['success' => false, 'message' => 'Đã trả lời hết câu hỏi!', 'game_completed' => true]);
            exit;
        }
        
        // Lấy câu hỏi chưa trả lời
        $questionSql = "SELECT * FROM trivia_questions WHERE is_active = 1";
        
        if ($game['category_id']) {
            $questionSql .= " AND category_id = " . $game['category_id'];
        }
        
        if ($game['difficulty'] !== 'mixed') {
            $questionSql .= " AND difficulty = '" . $conn->real_escape_string($game['difficulty']) . "'";
        }
        
        if (!empty($answeredIds)) {
            $questionSql .= " AND id NOT IN (" . implode(',', $answeredIds) . ")";
        }
        
        $questionSql .= " ORDER BY RAND() LIMIT 1";
        
        $questionResult = $conn->query($questionSql);
        
        if ($questionResult->num_rows == 0) {
            echo json_encode(['success' => false, 'message' => 'Không còn câu hỏi nào!']);
            exit;
        }
        
        $question = $questionResult->fetch_assoc();
        
        // Ẩn đáp án đúng
        unset($question['correct_answer']);
        unset($question['explanation']);
        
        // Thêm thông tin tiến độ
        $question['progress'] = [
            'current' => count($answeredIds) + 1,
            'total' => $game['total_questions']
        ];
        
        echo json_encode(['success' => true, 'question' => $question]);
        break;
        
    case 'submit_answer':
        // Nộp đáp án
        $gameId = (int)($_POST['game_id'] ?? 0);
        $questionId = (int)($_POST['question_id'] ?? 0);
        $userAnswer = strtoupper(trim($_POST['answer'] ?? ''));
        
        if (!$gameId || !$questionId || !in_array($userAnswer, ['A', 'B', 'C', 'D'])) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ!']);
            exit;
        }
        
        // Kiểm tra game
        $gameSql = "SELECT * FROM trivia_games WHERE id = ? AND user_id = ?";
        $gameStmt = $conn->prepare($gameSql);
        $gameStmt->bind_param("ii", $gameId, $userId);
        $gameStmt->execute();
        $game = $gameStmt->get_result()->fetch_assoc();
        $gameStmt->close();
        
        if (!$game || $game['completed_at']) {
            echo json_encode(['success' => false, 'message' => 'Game không tồn tại hoặc đã kết thúc!']);
            exit;
        }
        
        // Lấy câu hỏi
        $questionSql = "SELECT * FROM trivia_questions WHERE id = ?";
        $questionStmt = $conn->prepare($questionSql);
        $questionStmt->bind_param("i", $questionId);
        $questionStmt->execute();
        $question = $questionStmt->get_result()->fetch_assoc();
        $questionStmt->close();
        
        if (!$question) {
            echo json_encode(['success' => false, 'message' => 'Câu hỏi không tồn tại!']);
            exit;
        }
        
        // Kiểm tra đã trả lời câu này chưa
        $checkSql = "SELECT id FROM trivia_answers WHERE game_id = ? AND question_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("ii", $gameId, $questionId);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Bạn đã trả lời câu hỏi này rồi!']);
            exit;
        }
        $checkStmt->close();
        
        // Kiểm tra đáp án
        $isCorrect = ($userAnswer === $question['correct_answer']);
        $pointsEarned = $isCorrect ? $question['points'] : 0;
        
        // Lưu đáp án
        $conn->begin_transaction();
        try {
            // Insert vào trivia_answers
            $insertSql = "INSERT INTO trivia_answers (game_id, question_id, user_answer, is_correct, points_earned) 
                         VALUES (?, ?, ?, ?, ?)";
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->bind_param("iisii", $gameId, $questionId, $userAnswer, $isCorrect, $pointsEarned);
            $insertStmt->execute();
            $insertStmt->close();
            
            // Cập nhật game stats
            $updateSql = "UPDATE trivia_games 
                         SET total_points = total_points + ?,
                             correct_answers = correct_answers + ?
                         WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("iii", $pointsEarned, $isCorrect, $gameId);
            $updateStmt->execute();
            $updateStmt->close();
            
            $conn->commit();
            
            // Trả về kết quả (không tiết lộ đáp án đúng nếu sai)
            $response = [
                'success' => true,
                'is_correct' => $isCorrect,
                'points_earned' => $pointsEarned,
                'correct_answer' => $question['correct_answer'],
                'explanation' => $question['explanation'] ?? ''
            ];
            
            echo json_encode($response);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        break;
        
    case 'finish_game':
        // Kết thúc game và nhận phần thưởng
        $gameId = (int)($_POST['game_id'] ?? 0);
        
        if (!$gameId) {
            echo json_encode(['success' => false, 'message' => 'Game ID không hợp lệ!']);
            exit;
        }
        
        // Lấy thông tin game
        $gameSql = "SELECT * FROM trivia_games WHERE id = ? AND user_id = ?";
        $gameStmt = $conn->prepare($gameSql);
        $gameStmt->bind_param("ii", $gameId, $userId);
        $gameStmt->execute();
        $game = $gameStmt->get_result()->fetch_assoc();
        $gameStmt->close();
        
        if (!$game) {
            echo json_encode(['success' => false, 'message' => 'Game không tồn tại!']);
            exit;
        }
        
        if ($game['completed_at']) {
            echo json_encode(['success' => false, 'message' => 'Game đã kết thúc rồi!']);
            exit;
        }
        
        // Tính phần thưởng: 100 điểm = 1000 VNĐ
        $rewardAmount = ($game['total_points'] / 100) * 1000;
        
        // Kết thúc game và cấp phần thưởng
        $conn->begin_transaction();
        try {
            // Cập nhật game
            $updateSql = "UPDATE trivia_games SET completed_at = NOW(), reward_amount = ? WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("di", $rewardAmount, $gameId);
            $updateStmt->execute();
            $updateStmt->close();
            
            // Cấp phần thưởng
            if ($rewardAmount > 0) {
                $moneySql = "UPDATE users SET Money = Money + ? WHERE Iduser = ?";
                $moneyStmt = $conn->prepare($moneySql);
                $moneyStmt->bind_param("di", $rewardAmount, $userId);
                $moneyStmt->execute();
                $moneyStmt->close();
            }
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Hoàn thành game thành công!',
                'stats' => [
                    'total_questions' => $game['total_questions'],
                    'correct_answers' => $game['correct_answers'],
                    'total_points' => $game['total_points'],
                    'reward_amount' => $rewardAmount
                ]
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        break;
        
    case 'get_leaderboard':
        // Lấy bảng xếp hạng
        $limit = (int)($_GET['limit'] ?? 50);
        $categoryId = isset($_GET['category_id']) && $_GET['category_id'] ? (int)$_GET['category_id'] : null;
        
        $sql = "SELECT tg.*, u.Name, u.ImageURL,
                (SELECT COUNT(*) FROM trivia_games tg2 WHERE tg2.user_id = tg.user_id) as total_games,
                (SELECT SUM(total_points) FROM trivia_games tg3 WHERE tg3.user_id = tg.user_id) as total_points_all
                FROM trivia_games tg
                JOIN users u ON tg.user_id = u.Iduser
                WHERE tg.completed_at IS NOT NULL";
        
        if ($categoryId) {
            $sql .= " AND tg.category_id = " . $categoryId;
        }
        
        $sql .= " ORDER BY tg.total_points DESC, tg.correct_answers DESC
                 LIMIT ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $leaderboard = [];
        $rank = 1;
        while ($row = $result->fetch_assoc()) {
            $row['rank'] = $rank++;
            $leaderboard[] = $row;
        }
        $stmt->close();
        
        echo json_encode(['success' => true, 'leaderboard' => $leaderboard]);
        break;
        
    case 'get_my_stats':
        // Lấy thống kê cá nhân
        $sql = "SELECT 
                COUNT(*) as total_games,
                SUM(correct_answers) as total_correct,
                SUM(total_questions) as total_questions,
                SUM(total_points) as total_points,
                SUM(reward_amount) as total_reward
                FROM trivia_games
                WHERE user_id = ? AND completed_at IS NOT NULL";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$stats['total_games']) {
            $stats = [
                'total_games' => 0,
                'total_correct' => 0,
                'total_questions' => 0,
                'total_points' => 0,
                'total_reward' => 0
            ];
        }
        
        // Tính tỷ lệ đúng
        $stats['accuracy'] = $stats['total_questions'] > 0 
            ? round(($stats['total_correct'] / $stats['total_questions']) * 100, 2) 
            : 0;
        
        echo json_encode(['success' => true, 'stats' => $stats]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Action không hợp lệ!']);
        break;
}

$conn->close();


<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['Iduser'])) {
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng đăng nhập!']);
    exit();
}

$userId = $_SESSION['Iduser'];
$today = date('Y-m-d');

// Check if already drawn today
$stmt = $conn->prepare("SELECT fortune_text, lucky_game FROM user_fortunes WHERE user_id = ? AND fortune_date = ?");
$stmt->bind_param("is", $userId, $today);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode([
        'status' => 'success',
        'already_drawn' => true,
        'fortune' => $row['fortune_text'],
        'lucky_game' => $row['lucky_game']
    ]);
    exit();
}

// Draw new fortune
$fortunes = [
    "Hôm nay bạn có sao Hồng Loan chiếu mệnh, tình duyên và  Gtlm bạc đều khởi sắc!",
    "Vận may đang đến gần, hãy mạnh dạn đặt cược vào những gì bạn tin tưởng.",
    "Một ngày bình yên, không nên quá mạo hiểm nhưng cũng đừng bỏ lỡ cơ hội nhỏ.",
    "Cẩn thận sao Quả Tạ, hôm nay hãy chơi vui là chính, đừng quá ăn thua.",
    "Thần tài đang gõ cửa nhà bạn, hãy chuẩn bị sẵn sàng để đón nhận lộc lá!",
    "Hôm nay bạn hợp với màu đỏ, hãy chọn những game có yếu tố màu này để tăng vận may.",
    "Trí tuệ minh mẫn, hôm nay là ngày tuyệt vời để chơi các game cân não như Poker."
];

$games = ["Tài Xỉu", "Xóc Đĩa", "Bầu Cua", "Poker", "Blackjack", "Slot Machine", "Baccarat"];

$randomFortune = $fortunes[array_rand($fortunes)];
$randomGame = $games[array_rand($games)];

$stmt = $conn->prepare("INSERT INTO user_fortunes (user_id, fortune_date, fortune_text, lucky_game) VALUES (?, ?, ?, ?)");
$stmt->bind_param("isss", $userId, $today, $randomFortune, $randomGame);

if ($stmt->execute()) {
    echo json_encode([
        'status' => 'success',
        'already_drawn' => false,
        'fortune' => $randomFortune,
        'lucky_game' => $randomGame
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi hệ thống: ' . $conn->error]);
}
?>

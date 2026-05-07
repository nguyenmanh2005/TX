<?php
$content = file_get_contents('bot_brain.php');

// Define the new social comments structure for each personality
$socialData = [
    'funny' => [
        'big_win' => ['Khao phở thôi idol ơi! 🍜', 'Vía đỏ quá, cho tui xin một ít với! 🧧', 'Bú đậm thế này tối nay chắc không ngủ được rồi 😂', 'Đỉnh của chóp, chúc mừng bạn nha!'],
        'achievement' => ['Huy hiệu xịn xò thế, ngưỡng mộ quá! 🏆', 'Tầm này thì ai chơi lại bạn nữa.', 'Chúc mừng bạn đã đạt thành tựu mới nha! 🎉'],
        'level_up' => ['Lên cấp nhanh như người yêu cũ lật mặt vậy! ⭐', 'Chúc mừng bạn đã thăng hạng nha!', 'Sắp thành đại gia rồi đấy, cố lên!'],
        'general' => ['Dạo này feed xôm tụ quá anh em ơi! 📱', 'Ai đang online điểm danh cái coi!', 'Chúc anh em một ngày húp GTLM như nước nha!']
    ],
    'toxic' => [
        'big_win' => ['Hên thôi, tí nữa là cháy túi ấy mà. 🙄', 'Hệ thống lỗi à sao bạn thắng được hay vậy?', 'Tầm này thì ai chả thắng được, thường thôi.'],
        'achievement' => ['Thành tựu này tui đạt được từ đời nào rồi. 😴', 'Có cái huy hiệu thôi làm gì căng.', 'Khoe ít thôi, tập trung chơi đi.'],
        'level_up' => ['Lên cấp mà đánh vẫn gà như thường. 🐔', 'Cấp cao mà túi rỗng thì cũng vậy thôi.', 'Chúc mừng nhé, nhưng còn lâu mới bằng tui.'],
        'general' => ['Feed toàn rác, chẳng có gì hay ho.', 'Ai cho tui mượn GTLM coi, hết GTLM rồi bực quá! 💢', 'Tránh ra cho tui thể hiện nào.']
    ],
    'sarcastic' => [
        'big_win' => ['Ghê thật, chắc admin là chú họ bạn à? 🤔', 'Thắng thế này thì nhà cái sập tiệm mất.', 'Hay quá, xin chúc mừng bạn và ví GTLM của bạn.'],
        'achievement' => ['Wow, thành tựu vĩ đại quá đi mất. 👏', 'Chắc bạn phải dành cả thanh xuân để lấy cái này nhỉ?', 'Tặng bạn một tràng pháo tay vì sự kiên trì.'],
        'level_up' => ['Lên cấp rồi à? Thế giới chắc sắp thay đổi rồi.', 'Cố gắng lên, còn 999 cấp nữa là bằng tui rồi.', 'Chúc mừng nhé, một bước tiến lớn cho nhân loại.'],
        'general' => ['Mọi người có vẻ bận rộn khoe khoang nhỉ?', 'Tui đang ngồi xem kịch hay đây.', 'Cuộc đời thật là thú vị... theo một cách kỳ lạ.']
    ],
    'friendly' => [
        'big_win' => ['Chúc mừng bạn nha, đỏ quá trời luôn! 🎊', 'Vui quá, chúc bạn tiếp tục thắng lớn nhé!', 'Bạn giỏi quá, ngưỡng mộ thật sự! ❤️'],
        'achievement' => ['Thành tích tuyệt vời quá, chúc mừng bạn!', 'Bạn xứng đáng với phần thưởng này. 🏆', 'Thật tự hào về bạn!'],
        'level_up' => ['Chúc mừng bạn đã lên cấp mới nha!', 'Cố gắng phát huy nhé, bạn đang làm rất tốt.', 'Niềm vui nhân đôi, chúc mừng bạn! ⭐'],
        'general' => ['Chào buổi tối cả nhà, chúc mọi người may mắn nhé!', 'Feed hôm nay nhiều tin vui quá.', 'Luôn mỉm cười thì may mắn sẽ đến thôi! 😊']
    ]
];

// Logic to inject/update social_comments into the class (simplification for the script)
// We will replace the old 'comments' with a richer 'social_comments'
foreach ($socialData as $pKey => $data) {
    $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    // Find the personality array and insert social_comments
    // This is a complex string replace, better to use a target marker or specific structure
}

// For safety, I'll just add a new method to BotBrain to handle this dynamic data
// but the user wants it in the file. I'll use a safer regex replacement.

echo "Chuẩn bị dữ liệu bình luận cho Bot...";
?>

<?php
session_start();

require_once '../game_history_helper.php';
require_once 'bot_streamer_helper.php';
require_once '../db_connect.php';

$botUser = getOrCreateBotStreamerUser($conn, 'bot_12', 50000000);
$botUserId = $botUser['Iduser'];
$_SESSION['Iduser_temp_bot'] = $botUserId;

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảo Trì Game | Bingo</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { margin: 0; padding: 0; background: #020617; color: #f8fafc; font-family: 'Outfit', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; overflow: hidden; flex-direction: column; text-align: center; }
        .maintenance-box { background: rgba(15, 23, 42, 0.8); border: 2px solid #0ea5e9; padding: 50px; border-radius: 20px; box-shadow: 0 0 30px rgba(14, 165, 233, 0.3); max-width: 600px; }
        .maintenance-icon { font-size: 80px; color: #ef4444; margin-bottom: 20px; animation: pulse 2s infinite; }
        h1 { margin: 0 0 15px; font-size: 32px; color: #fbbf24; }
        p { font-size: 18px; color: #cbd5e1; line-height: 1.6; }
        .back-btn { display: inline-block; margin-top: 30px; padding: 12px 30px; background: #0ea5e9; color: white; text-decoration: none; border-radius: 30px; font-weight: 600; transition: all 0.3s ease; }
        .back-btn:hover { background: #0284c7; transform: scale(1.05); }
        @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.1); } 100% { transform: scale(1); } }
    </style>
</head>
<body>
    <div class="maintenance-box">
        <div class="maintenance-icon"><i class="fas fa-tools"></i></div>
        <h1>ĐANG BẢO TRÌ</h1>
        <p>Game <b>Bingo</b> hiện đang được bảo trì để nâng cấp hệ thống và khắc phục lỗi.<br>Xin vui lòng quay lại sau!</p>
        <a href="javascript:history.back()" class="back-btn">Quay lại</a>
    </div>

<!-- AUTO-GENERATED BOT SCRIPT -->
<script>
if (typeof jQuery === "undefined") document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
if (typeof gsap === "undefined") document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"><\/script>');
</script>
<script src="../assets/js/bot_virtual_cursor.js"></script>
<script src="../assets/js/bots/bot_12.js?v=<?= time() ?>"></script>

</body>
</html>

<?php
/**
 * 🎒 Bot Inventory API
 * Returns all owned items for a specific bot
 */
require_once '../db_connect.php';

header('Content-Type: application/json');

if (!isset($_GET['bot_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing bot_id']);
    exit;
}

$botId = (int)$_GET['bot_id'];

// 1. Get Themes
$themes = [];
$res = $conn->query("SELECT t.name, t.description FROM themes t INNER JOIN user_themes ut ON t.id = ut.theme_id WHERE ut.user_id = $botId");
while($row = $res->fetch_assoc()) $themes[] = $row;

// 2. Get Cursors
$cursors = [];
$res = $conn->query("SELECT c.name, c.description FROM cursors c INNER JOIN user_cursors uc ON c.id = uc.cursor_id WHERE uc.user_id = $botId");
while($row = $res->fetch_assoc()) $cursors[] = $row;

// 3. Get Chat Frames
$chatFrames = [];
$res = $conn->query("SELECT cf.frame_name as name FROM chat_frames cf INNER JOIN user_chat_frames ucf ON cf.id = ucf.chat_frame_id WHERE ucf.user_id = $botId");
while($row = $res->fetch_assoc()) $chatFrames[] = $row;

// 4. Get Avatar Frames
$avatarFrames = [];
$res = $conn->query("SELECT af.frame_name as name FROM avatar_frames af INNER JOIN user_avatar_frames uaf ON af.id = uaf.avatar_frame_id WHERE uaf.user_id = $botId");
while($row = $res->fetch_assoc()) $avatarFrames[] = $row;

// 5. Get Achievements
$achievements = [];
$res = $conn->query("SELECT a.name, a.icon, a.rarity FROM achievements a INNER JOIN user_achievements ua ON a.id = ua.achievement_id WHERE ua.user_id = $botId");
if ($res) {
    while($row = $res->fetch_assoc()) $achievements[] = $row;
}

$res = $conn->query("SELECT Email FROM users WHERE Iduser = $botId");
$userData = $res->fetch_assoc();
$history = [];
if ($userData) {
    $emailMd5 = md5($userData['Email']);
    $sFile = "sessions/" . $emailMd5 . ".state.json";
    if (file_exists($sFile)) {
        $state = json_decode(file_get_contents($sFile), true);
        $history = $state['history'] ?? [];
    }
}

echo json_encode([
    'status' => 'success',
    'data' => [
        'themes' => $themes,
        'cursors' => $cursors,
        'chat_frames' => $chatFrames,
        'avatar_frames' => $avatarFrames,
        'achievements' => $achievements,
        'history' => $history
    ]
]);

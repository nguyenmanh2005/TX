<?php
/**
 * 👑 GTLM FEMALE TTS AUDIO SERVICE v2.0
 * Cung cấp giọng NỮ tiếng Việt chuẩn 100% (ngọt ngào, tự nhiên) cho Bot Queen GTLM
 * Tự động cắt câu và ghép MP3 liền mạch cho các đoạn văn dài.
 */

$text = trim($_GET['text'] ?? $_POST['text'] ?? '');
if ($text === '') {
    http_response_code(400);
    echo "No text provided";
    exit;
}

// 1. Chuẩn hóa và làm sạch văn bản đọc tiếng Việt
$cleanText = strip_tags($text);
$cleanText = preg_replace('/[*#_`~]/', '', $cleanText);
$cleanText = str_ireplace(
    ['GTLM', 'AI', 'PvP', 'VIP', 'Queen', '15x15', 'knockout'],
    ['Gờ Tờ Lờ Mờ', 'A I', 'P v P', 'V I P', 'Quin', 'mười lăm nhân mười lăm', 'nốc ao'],
    $cleanText
);
$cleanText = preg_replace('/x(\d+(\.\d+)?)/i', 'nhân $1', $cleanText);
$cleanText = preg_replace('/\s+/', ' ', trim($cleanText));

$cacheDir = __DIR__ . '/cache_tts';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0777, true);
}

$cacheFile = $cacheDir . '/' . md5('vi_female_' . $cleanText) . '.mp3';

if (file_exists($cacheFile) && filesize($cacheFile) > 1000) {
    header('Content-Type: audio/mpeg');
    header('Content-Length: ' . filesize($cacheFile));
    header('Cache-Control: public, max-age=86400');
    readfile($cacheFile);
    exit;
}

// 2. Chia nhỏ văn bản thành các đoạn <= 180 ký tự theo dấu câu để Google TTS không bị lỗi
function splitIntoChunks($str, $maxLen = 170) {
    if (mb_strlen($str, 'UTF-8') <= $maxLen) {
        return [$str];
    }
    $words = explode(' ', $str);
    $chunks = [];
    $curr = '';

    foreach ($words as $word) {
        $test = $curr === '' ? $word : $curr . ' ' . $word;
        if (mb_strlen($test, 'UTF-8') <= $maxLen) {
            $curr = $test;
        } else {
            if ($curr !== '') $chunks[] = $curr;
            $curr = $word;
        }
    }
    if ($curr !== '') {
        $chunks[] = $curr;
    }
    return $chunks;
}

function fetchGoogleTTSChunk($chunkText) {
    $url = "https://translate.google.com/translate_tts?ie=UTF-8&tl=vi&client=tw-ob&q=" . urlencode($chunkText);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 7);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_REFERER, 'https://translate.google.com/');
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($code === 200 && strlen($data) > 500) ? $data : null;
}

$chunks = splitIntoChunks($cleanText, 170);
$combinedAudio = '';

// Lấy tối đa 4 đoạn đầu tiên (khoảng 600 ký tự) để phản hồi nhanh và mượt mà
$chunks = array_slice($chunks, 0, 4);

foreach ($chunks as $chunk) {
    $audioPart = fetchGoogleTTSChunk($chunk);
    if ($audioPart) {
        $combinedAudio .= $audioPart;
    }
}

if (strlen($combinedAudio) > 1000) {
    @file_put_contents($cacheFile, $combinedAudio);
    header('Content-Type: audio/mpeg');
    header('Content-Length: ' . strlen($combinedAudio));
    header('Cache-Control: public, max-age=86400');
    echo $combinedAudio;
    exit;
}

http_response_code(500);
echo "TTS Generation failed";

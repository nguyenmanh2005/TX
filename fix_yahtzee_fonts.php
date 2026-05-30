<?php
$file = 'c:/xampp/htdocs/1/games/yahtzee.php';
$content = file_get_contents($file);

$replacements = [
    'cÆ°á»£c' => 'cược',
    'khÃ´ng' => 'không',
    'Ä‘á»§' => 'đủ',
    'hoáº·c' => 'hoặc',
    'há»£p lá»‡' => 'hợp lệ',
    'PhiÃªn chÆ¡i' => 'Phiên chơi',
    'Ä‘Ã£' => 'đã',
    'káº¿t thÃºc' => 'kết thúc',
    'Cao Cáº¥p' => 'Cao Cấp',
    'XÃºc xáº¯c' => 'Xúc xắc',
    'THOÃ T' => 'THOÁT',
    'CÆ¯á»¢C' => 'CƯỢC',
    'Láº¦N Láº®C' => 'LẦN LẮC',
    'Láº®C XÃšC Xáº®C' => 'LẮC XÚC XẮC',
    'Báº£ng' => 'Bảng',
    'Ä iá»ƒm' => 'Điểm',
    'Tá»• Há»£p' => 'Tổ Hợp',
    'Bá»™' => 'Bộ',
    'Tá»© QuÃ½' => 'Tứ Quý',
    'CÃ¹ LÅ©' => 'Cù Lũ',
    'GIá»®' => 'GIỮ'
];

foreach ($replacements as $bad => $good) {
    $content = str_replace($bad, $good, $content);
}

file_put_contents($file, $content);
echo "Fixed font in yahtzee.php\n";

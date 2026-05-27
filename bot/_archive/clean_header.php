<?php
$file = 'bot_engine.php';
$content = file_get_contents($file);

// Strip all non-ASCII characters from the first 500 bytes
$head = substr($content, 0, 500);
$tail = substr($content, 500);

$cleanHead = preg_replace('/[^\x20-\x7E\r\n\t]/', '', $head);

file_put_contents($file, $cleanHead . $tail);
echo "Cleaned non-ASCII from header of bot_engine.php\n";
?>

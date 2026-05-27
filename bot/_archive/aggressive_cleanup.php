<?php
$file = 'bot_engine.php';
$lines = file($file);
$newLines = [];
foreach ($lines as $line) {
    if (strpos($line, '::-webkit-scrollbar') !== false && strpos($line, 'echo') === false && strpos($line, '"') === false) {
        continue; // Skip orphan CSS lines
    }
    if (trim($line) === '</style>";' && isset($lastLine) && trim($lastLine) === '</style>";') {
        continue; // Skip double closing tags
    }
    $newLines[] = $line;
    $lastLine = $line;
}
file_put_contents($file, implode("", $newLines));
echo "Aggressively stripped orphan CSS lines\n";
?>

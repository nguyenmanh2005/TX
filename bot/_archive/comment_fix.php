<?php
$file = 'bot_engine.php';
$lines = file($file);

// Find the start of the broken comment
$start = 0;
for ($i = 0; $i < count($lines); $i++) {
    if (strpos($lines[$i], '/**') !== false && $i > 120) {
        $start = $i;
        break;
    }
}

// Find where it should end (before executeBotCycle)
$end = 0;
for ($i = $start; $i < count($lines); $i++) {
    if (strpos($lines[$i], 'function executeBotCycle') !== false) {
        $end = $i;
        break;
    }
}

if ($start > 0 && $end > $start) {
    // Replace everything between start and end with a clean version
    $clean = array_slice($lines, 0, $start);
    $clean[] = "/**\n * Thực hiện hành động bot qua cURL\n */\n";
    // Copy the executeBotAction and handleSicboBot properly
    // This is risky, so I'll just ensure the comment is CLOSED before any function
    $lines[$end - 1] = " */\n";
    file_put_contents($file, implode("", $lines));
    echo "Fixed unterminated comment at $start\n";
}
?>

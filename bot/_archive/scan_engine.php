<?php
$c = file_get_contents('bot_engine.php');
if (strpos($c, '?>') !== false) {
    echo "FOUND TAG at " . strpos($c, '?>');
} else {
    echo "NO TAG";
}
?>

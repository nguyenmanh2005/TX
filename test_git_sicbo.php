<?php
$c = shell_exec('git show 54e3def:games/sicbo.php');
echo "cược: " . substr_count($c, 'cược') . "\n";
echo "cÆ°á»£c: " . substr_count($c, 'cÆ°á»£c') . "\n";

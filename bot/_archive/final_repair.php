<?php
$file = 'bot_engine.php';
$content = file_get_contents($file);

// Fix the specific broken block around Sicbo and executeBotCycle
$bad = '    $res = executeBotAction($baseUrl . "/games/sicbo_v2.php?action=roll", [\'bets\' => json_encode($bets)], $cFile);
 */';
$good = '    $res = executeBotAction($baseUrl . "/games/sicbo_v2.php?action=roll", [\'bets\' => json_encode($bets)], $cFile);
}'; // Close handleSicboBot

$content = str_replace($bad, $good, $content);

// Fix broken CSS start
$bad2 = '    echo "<style>";
            clear: both;';
$good2 = '    echo "<style>
        .bot-card {
            clear: both;';

$content = str_replace($bad2, $good2, $content);

file_put_contents($file, $content);
echo "Fixed orphan */ and CSS start in bot_engine.php\n";
?>

<?php
$file = 'bot_engine.php';
$lines = file($file);

// Ensure the start is perfect
$lines[0] = "<?php\n";

// Fix the problematic block
$newLines = array_slice($lines, 0, 200);
$newLines[] = " */\n";
$newLines[] = "function executeBotCycle(mysqli \$conn, array \$config, string \$cookieDir, string \$baseUrl, BotBrain \$brain, array \$botNameMap, array \$availableGames) {\n";
$newLines[] = "    header('X-Accel-Buffering: no'); \n";
$newLines[] = "    echo \"<style>\";\n";
$tail = array_slice($lines, 230);
$final = array_merge($newLines, $tail);

file_put_contents($file, implode("", $final));
echo "Surgically repaired bot_engine.php\n";
?>

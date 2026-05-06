<?php
// Script to add INSERT statements for history tables to all game files

$games = ['bj', 'coinflip', 'cs', 'dice', 'duangua', 'hopmu', 'minesweeper', 'number', 'poker', 'roulette', 'rps', 'ruttham', 'slot', 'vietlott', 'xocdia', 'vq'];

$gameDir = __DIR__ . '/games';

foreach ($games as $game) {
    $filePath = "$gameDir/{$game}.php";
    
    if (!file_exists($filePath)) {
        echo "⚠️  File not found: $filePath\n";
        continue;
    }
    
    $content = file_get_contents($filePath);
    
    // Skip if already has the insert history
    if (strpos($content, "INSERT INTO history_") !== false) {
        echo "✓ $game.php already has INSERT statement\n";
        continue;
    }
    
    // Find logGameHistory call and add insert after it
    if (preg_match("/logGameHistory\s*\(\s*\\\$conn,\s*\\\$.*?,\s*['\"]([^'\"]+)['\"]/", $content, $matches)) {
        $insertCode = "\n        
        // Insert vào history_" . $game . " table
        \$historyStmt = \$conn->prepare(\"INSERT INTO history_" . $game . " (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())\");
        if (\$historyStmt) {
            \$result = \$_POST['result'] ?? 'Unknown';
            \$bet = (int)(\$_POST['bet'] ?? 0);
            \$winAmount = (int)(\$_POST['win'] ?? \$reward ?? 0);
            \$userId = \$_SESSION['Iduser'] ?? 0;
            \$historyStmt->bind_param(\"iisi\", \$userId, \$bet, \$result, \$winAmount);
            \$historyStmt->execute();
            \$historyStmt->close();
        }";
        
        // Find where to insert (after logGameHistory line)
        $logPattern = "/logGameHistory\s*\([^)]+\);/";
        $content = preg_replace($logPattern, "$0" . $insertCode, $content, 1);
        
        if (file_put_contents($filePath, $content)) {
            echo "✓ Added INSERT statement to $game.php\n";
        } else {
            echo "✗ Failed to update $game.php\n";
        }
    } else {
        echo "⚠️  Could not find logGameHistory in $game.php\n";
    }
}

echo "\n✅ Done!\n";
?>

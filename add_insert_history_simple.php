<?php
// Simpler script - add history INSERT after UPDATE users Money

$games = [
    'coinflip', 'cs', 'dice', 'number', 'poker', 'roulette', 'rps', 'slot', 'vietlott', 'xocdia', 'vq'
];

$gameDir = __DIR__ . '/games';

foreach ($games as $game) {
    $filePath = "$gameDir/{$game}.php";
    
    if (!file_exists($filePath)) {
        echo "⚠️  File not found: $filePath\n";
        continue;
    }
    
    $content = file_get_contents($filePath);
    
    // Skip if already has INSERT INTO history_
    if (strpos($content, "INSERT INTO history_") !== false) {
        echo "✓ $game.php already has history insert\n";
        continue;
    }
    
    // Simple approach: add history insert in POST handler
    // Find the POST handler and add insert
    $insertCode = "
        
        // Insert vào history_{$game} table
        if (\$_SERVER['REQUEST_METHOD'] === 'POST' && isset(\$_SESSION['Iduser'])) {
            \$userId = \$_SESSION['Iduser'];
            \$betAmount = (int)(\$_POST['bet'] ?? 0);
            \$resultStr = \$_POST['result'] ?? 'Unknown';
            \$winAmount = (int)(\$reward ?? 0);
            
            \$historyStmt = \$conn->prepare(\"INSERT INTO history_{$game} (Iduser, Bet, Result, WinAmount, Time) VALUES (?, ?, ?, ?, NOW())\");
            if (\$historyStmt) {
                \$historyStmt->bind_param(\"iisi\", \$userId, \$betAmount, \$resultStr, \$winAmount);
                \$historyStmt->execute();
                \$historyStmt->close();
            }
        }";
    
    // Find first UPDATE users ... Money and add insert after it
    if (preg_match("/(UPDATE users SET Money[^;]+;)/", $content, $matches)) {
        $position = strpos($content, $matches[0]) + strlen($matches[0]);
        $content = substr_replace($content, $insertCode, $position, 0);
        
        if (file_put_contents($filePath, $content)) {
            echo "✓ Added history insert to $game.php\n";
        } else {
            echo "✗ Failed to update $game.php\n";
        }
    } else {
        echo "⚠️  Could not find UPDATE users in $game.php\n";
    }
}

echo "\n✅ Done!\n";
?>

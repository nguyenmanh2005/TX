<?php
// Script to add history functionality to all game files

$games = ['bj', 'coinflip', 'cs', 'dice', 'duangua', 'hopmu', 'minesweeper', 'number', 'poker', 'roulette', 'rps', 'ruttham', 'slot', 'vietlott', 'xocdia', 'trivia', 'vq'];

$gameDir = __DIR__ . '/games';

foreach ($games as $game) {
    $filePath = "$gameDir/{$game}.php";
    
    if (!file_exists($filePath)) {
        echo "⚠️  File not found: $filePath\n";
        continue;
    }
    
    $content = file_get_contents($filePath);
    
    // Check if already has history
    if (strpos($content, 'loadGameHistory') !== false || strpos($content, "history_{$game}") !== false) {
        echo "✓ $game.php already has history\n";
        continue;
    }
    
    // Add AJAX endpoint after initial session checks (after db_connect)
    $ajaxCode = "
// AJAX history endpoint
\$isAjax = !empty(\$_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower(\$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (\$isAjax && \$_SERVER['REQUEST_METHOD'] === 'GET' && isset(\$_GET['action']) && \$_GET['action'] === 'get_history') {
    header('Content-Type: application/json; charset=utf-8');
    
    \$id = \$_SESSION['Iduser'] ?? 0;
    \$sql = \"SELECT * FROM history_{$game} WHERE Iduser = ? ORDER BY Time DESC LIMIT 20\";
    \$stmt = \$conn->prepare(\$sql);
    \$stmt->bind_param(\"i\", \$id);
    \$stmt->execute();
    \$result = \$stmt->get_result();
    
    \$history = [];
    while (\$row = \$result->fetch_assoc()) {
        \$history[] = \$row;
    }
    \$stmt->close();
    
    echo json_encode([
        'success' => true,
        'history' => \$history
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
";
    
    // Find where to insert AJAX code (after db_connect require)
    if (preg_match("/(require.*?db_connect\.php.*?;)/", $content, $matches)) {
        $insertPos = strpos($content, $matches[0]) + strlen($matches[0]);
        $content = substr_replace($content, "\n\n" . $ajaxCode, $insertPos, 0);
    }
    
    // Add CSS for history box if <style> tag exists
    $cssCode = "
        /* History Box Styles */
        .bottom-section {
            margin-top: 50px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            max-width: 1000px;
            margin-left: auto;
            margin-right: auto;
        }

        .history-box, .chart-box {
            background: rgba(0, 121, 107, 0.9);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            color: white;
        }

        .history-box h3, .chart-box h3 {
            margin-top: 0;
            font-size: 20px;
            color: #ffd700;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .history-box table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .history-box table tr {
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideIn 0.5s ease-out forwards;
        }

        .history-box table td, .history-box table th {
            padding: 10px;
            text-align: center;
        }

        .history-box table th {
            background: rgba(255, 255, 255, 0.1);
            font-weight: 700;
            color: #ffd700;
        }

        .history-box table tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        @media (max-width: 768px) {
            .bottom-section {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }
";
    
    if (strpos($content, '</style>') !== false) {
        $content = str_replace('</style>', $cssCode . "\n    </style>", $content);
    }
    
    // Add HTML for history box before closing body
    $htmlCode = "
<div class=\"bottom-section\">
    <div class=\"history-box\">
        <h3>Lịch sử chơi</h3>
        <table border=\"1\" cellpadding=\"10\" id=\"historyTable\">
            <thead>
                <tr>
                    <th>Khoá</th>
                    <th>Cược</th>
                    <th>Kết quả</th>
                    <th>Thắng</th>
                </tr>
            </thead>
            <tbody id=\"historyBody\">
                <tr><td colspan=\"4\">Chưa có lượt chơi nào.</td></tr>
            </tbody>
        </table>
    </div>
</div>

";
    
    if (strpos($content, '</body>') !== false) {
        $content = str_replace('</body>', $htmlCode . "</body>", $content);
    }
    
    // Add JavaScript for loading history
    $jsCode = "
    // Load game history for {$game}
    async function load" . ucfirst($game) . "History() {
        try {
            const response = await fetch('{$game}.php?action=get_history', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();
            
            if (data.success && data.history.length > 0) {
                const tbody = document.getElementById('historyBody');
                tbody.innerHTML = '';
                
                data.history.forEach((record, index) => {
                    const row = document.createElement('tr');
                    row.style.animation = \`slideIn 0.5s ease-out forwards\`;
                    row.style.animationDelay = (index * 0.05) + 's';
                    row.innerHTML = \`
                        <td>\${record.Result}</td>
                        <td>\${Number(record.Bet).toLocaleString('vi-VN')}</td>
                        <td>\${record.Result}</td>
                        <td style=\"color: \${record.WinAmount > 0 ? '#28a745' : '#dc3545'}\">
                            \${record.WinAmount > 0 ? '+' : ''}\${Number(record.WinAmount).toLocaleString('vi-VN')}
                        </td>
                    \`;
                    tbody.appendChild(row);
                });
            }
        } catch (error) {
            console.error('Lỗi load history:', error);
        }
    }
    
    // Auto load history when page loads
    window.addEventListener('load', function() {
        load" . ucfirst($game) . "History();
    });
";
    
    if (strpos($content, '</script>') !== false) {
        $lastScriptPos = strrpos($content, '</script>');
        $content = substr_replace($content, $jsCode . "\n</script>", $lastScriptPos, 8);
    }
    
    // Write updated content
    if (file_put_contents($filePath, $content)) {
        echo "✓ Added history to $game.php\n";
    } else {
        echo "✗ Failed to update $game.php\n";
    }
}

echo "\n✅ Done!\n";
?>

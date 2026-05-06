<?php
/**
 * Script to make all game history match Bingo.php structure
 * Adds:
 * - Statistics from database (wins/losses)
 * - Doughnut chart
 * - Better history table styling
 * - Better history loading function
 */

$games = ['ac', 'baucua', 'bj', 'coinflip', 'cs', 'dice', 'duangua', 'hopmu', 'minesweeper', 'number', 'poker', 'roulette', 'rps', 'ruttham', 'slot', 'vietlott', 'xocdia', 'vq'];

$gameDir = __DIR__ . '/games';

foreach ($games as $game) {
    if ($game === 'bingo') continue; // Skip bingo, it's already perfect
    
    $filePath = "$gameDir/{$game}.php";
    
    if (!file_exists($filePath)) {
        echo "⚠️  File not found: $filePath\n";
        continue;
    }
    
    $content = file_get_contents($filePath);
    
    // 1. Add statistics queries after user fetch
    $statsCode = "
// Get statistics from database for chart
\$gameThang = 0;
\$gameThua = 0;
\$sqlStats = \"SELECT COUNT(*) as total, SUM(CASE WHEN WinAmount > 0 THEN 1 ELSE 0 END) as wins FROM history_{$game} WHERE Iduser = ?\";
\$stmtStats = \$conn->prepare(\$sqlStats);
\$stmtStats->bind_param(\"i\", \$userId);
\$stmtStats->execute();
\$resultStats = \$stmtStats->get_result();
if (\$rowStats = \$resultStats->fetch_assoc()) {
    \$gameThang = \$rowStats['wins'] ?? 0;
    \$gameThua = (\$rowStats['total'] ?? 0) - \$gameThang;
}
\$stmtStats->close();
";
    
    // Find where to insert stats (after user fetch, before AJAX check)
    if (preg_match("/(SELECT Money.*?fetch_assoc\(\);)/s", $content)) {
        // Find position after user fetch
        if (preg_match_all("/(fetch_assoc\(\);)/", $content, $matches, PREG_OFFSET_CAPTURE)) {
            // Use first fetch_assoc for user data
            if (count($matches[0]) > 0) {
                $insertPos = $matches[0][0][1] + strlen($matches[0][0][0]);
                
                // Check if stats already exist
                if (strpos($content, "\$gameThang") === false && strpos($content, "\$" . $game . "Thang") === false) {
                    $content = substr_replace($content, "\n\n" . $statsCode, $insertPos, 0);
                }
            }
        }
    }
    
    // 2. Update history box HTML to match bingo style (with Id, Bet, Result, WinAmount, Time)
    $betterHistoryHTML = "
<div class=\"bottom-section\">
    <div class=\"history-box\">
        <h3>📋 Lịch sử chơi (10 lần gần nhất)</h3>
        <table border=\"1\" cellpadding=\"10\" id=\"historyTable\">
            <thead>
                <tr style=\"background: rgba(255, 255, 255, 0.1);\">
                    <th style=\"padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); color: #ffd700;\">ID</th>
                    <th style=\"padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); color: #ffd700;\">Cược</th>
                    <th style=\"padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); color: #ffd700;\">Kết quả</th>
                    <th style=\"padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); color: #ffd700;\">Thắng</th>
                    <th style=\"padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); color: #ffd700;\">Thời gian</th>
                </tr>
            </thead>
            <tbody id=\"historyBody\">
                <tr><td colspan=\"5\" style=\"text-align: center; padding: 15px; color: #aaa;\">Chưa có lượt chơi nào</td></tr>
            </tbody>
        </table>
    </div>
    
    <div class=\"chart-box\">
        <h3>📊 Thống kê</h3>
        <div class=\"stats-container\">
            <div class=\"stat-item wins\">
                <div class=\"label\">Lần Thắng</div>
                <div class=\"value\"><?= \$gameThang ?></div>
            </div>
            <div class=\"stat-item losses\">
                <div class=\"label\">Lần Thua</div>
                <div class=\"value\"><?= \$gameThua ?></div>
            </div>
        </div>
        <canvas id=\"gameChart\" style=\"max-height: 300px;\"></canvas>
    </div>
</div>
";
    
    // Replace old history box with new one
    if (preg_match("/<div[^>]*class=\"bottom-section\"[^>]*>.*?<\\/div>\\s*<\\/div>/s", $content)) {
        // Replace existing bottom-section
        $content = preg_replace("/<div[^>]*class=\"bottom-section\"[^>]*>.*?<\\/div>\\s*<\\/div>/s", $betterHistoryHTML, $content);
    } elseif (preg_match("/<\\/body>/", $content)) {
        // Insert before </body>
        $content = preg_replace("/<\\/body>/", $betterHistoryHTML . "\n</body>", $content);
    }
    
    // 3. Update history loading JavaScript function
    $gameName = ucfirst($game);
    $improvedLoadFunction = "
    // Improved history loading function
    async function load{$gameName}History() {
        try {
            const response = await fetch('{$game}.php?action=get_history', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (!response.ok) return;
            
            const data = await response.json();
            
            if (data.success && data.history && data.history.length > 0) {
                const historyTable = document.querySelector('.history-box table');
                
                if (historyTable) {
                    let tbody = historyTable.querySelector('tbody');
                    if (!tbody) {
                        tbody = document.createElement('tbody');
                        historyTable.appendChild(tbody);
                    }
                    
                    // Clear existing rows except if they have data
                    if (tbody.children.length === 1 && tbody.children[0].cells[0].colSpan === 5) {
                        tbody.innerHTML = '';
                    }
                    
                    // Add up to 10 most recent records
                    data.history.slice(0, 10).forEach((record, index) => {
                        const newRow = document.createElement('tr');
                        newRow.style.borderBottom = '1px solid rgba(255, 255, 255, 0.1)';
                        newRow.style.animation = 'slideIn 0.5s ease-out forwards';
                        newRow.style.animationDelay = (index * 0.05) + 's';
                        newRow.innerHTML = \`
                            <td style=\"padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;\">\${record.Id}</td>
                            <td style=\"padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right;\">\${parseInt(record.Bet).toLocaleString('vi-VN')}</td>
                            <td style=\"padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1);\">
                                \${record.Result || '-'}
                            </td>
                            <td style=\"padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; color: \${parseInt(record.WinAmount) > 0 ? '#4ade80' : '#ff6b6b'};\">\${parseInt(record.WinAmount).toLocaleString('vi-VN')}</td>
                            <td style=\"padding: 8px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: right; font-size: 12px;\">\${record.Time}</td>
                        \`;
                        tbody.appendChild(newRow);
                    });
                    
                    // Hide empty message
                    const emptyMsg = document.querySelector('.history-box p');
                    if (emptyMsg && data.history.length > 0) {
                        emptyMsg.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Load history error:', error);
        }
    }
    
    // Chart.js for {$game} game
    const ctx{$gameName} = document.getElementById('gameChart');
    if (ctx{$gameName}) {
        const gameChart = new Chart(ctx{$gameName}.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Lần Thắng', 'Lần Thua'],
                datasets: [{
                    label: 'Kết quả',
                    data: [<?= \$gameThang ?>, <?= \$gameThua ?>],
                    backgroundColor: [
                        'rgba(74, 222, 128, 0.7)',
                        'rgba(255, 107, 107, 0.7)'
                    ],
                    borderColor: [
                        'rgba(74, 222, 128, 1)',
                        'rgba(255, 107, 107, 1)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.8)',
                            font: { size: 13, weight: '600' },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) label += ': ';
                                label += context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                label += ' (' + percentage + '%)';
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Auto-load history on page load
    window.addEventListener('load', load{$gameName}History);
";
    
    // Replace old history loading function with improved one
    $oldLoadPattern = "/async\\s+function\\s+load" . ucfirst($game) . "History\\s*\\(\\)\\s*\\{[^}]*\\}/s";
    if (preg_match($oldLoadPattern, $content)) {
        $content = preg_replace($oldLoadPattern, "", $content);
    }
    
    // Remove old addEventListener if exists
    $oldEventListener = "/window\\.addEventListener\\s*\\(\\s*['\"]load['\"]\s*,\\s*function\\s*\\(\\)\\s*\\{[^}]*load" . ucfirst($game) . "History[^}]*\\}\\s*\\);/s";
    if (preg_match($oldEventListener, $content)) {
        $content = preg_replace($oldEventListener, "", $content);
    }
    
    // Find where to add the improved functions (before </script> or at end of script section)
    if (preg_match_all("/<\\/script>/", $content, $matches, PREG_OFFSET_CAPTURE)) {
        if (count($matches[0]) > 0) {
            // Use last </script> before </body>
            $lastScriptPos = $matches[0][count($matches[0]) - 1][1];
            $content = substr_replace($content, "\n" . $improvedLoadFunction . "\n</script>", $lastScriptPos, 9);
        }
    }
    
    // 4. Add CSS for statistics display if not present
    if (strpos($content, '.stats-container') === false) {
        $cssCode = "
        /* Statistics Container */
        .stats-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-item:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
        }
        
        .stat-item.wins {
            border-left: 4px solid #4ade80;
        }
        
        .stat-item.losses {
            border-left: 4px solid #ff6b6b;
        }
        
        .stat-item .label {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        
        .stat-item .value {
            font-size: 28px;
            font-weight: 700;
            color: #ffd700;
        }
        
        .chart-box {
            display: flex;
            flex-direction: column;
        }
        
        .chart-box canvas {
            margin-top: 20px;
        }
";
        
        if (preg_match("/<\\/style>/", $content)) {
            $content = preg_replace("/<\\/style>/", $cssCode . "\n    </style>", $content);
        }
    }
    
    // Save updated content
    if (file_put_contents($filePath, $content)) {
        echo "✓ Updated $game.php with bingo-style history\n";
    } else {
        echo "✗ Failed to update $game.php\n";
    }
}

echo "\n✅ Done! All games now have bingo-style history\n";
?>

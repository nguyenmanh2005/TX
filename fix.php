<?php
$file = "c:/xampp/htdocs/1/games/number.php";
$content = file_get_contents($file);

// Find the first instance of '</body>'
$bodyPos = strpos($content, '</body>');
if ($bodyPos !== false) {
    // We want to keep everything from the beginning of the file, up to the end of the <script> block, OR up to the first </div></div>\n</body>.
    // Let's find the FIRST <canvas id="gameChart" style="max-height: 300px;"></canvas>
    $canvasPos = strpos($content, '<canvas id="gameChart"');
    
    if ($canvasPos !== false) {
        $div1 = strpos($content, '</div>', $canvasPos);
        $div2 = strpos($content, '</div>', $div1 + 1);
        
        $validHtml = substr($content, 0, $div2 + 6);
        $newContent = $validHtml . "\n</body>\n</html>";
        file_put_contents($file, $newContent);
        echo "Fixed HTML.\n";
    }
}

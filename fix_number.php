<?php
$file = "c:/xampp/htdocs/1/games/number.php";
$content = file_get_contents($file);

// Find the string that marks the start of the duplicated blocks
$startDupe = '<div class="stat-item losses">';
$posFirst = strpos($content, $startDupe);

if ($posFirst !== false) {
    // Find the second occurrence of this string, because the first one is the legitimate one inside .stats-container!
    $posSecond = strpos($content, $startDupe, $posFirst + strlen($startDupe));
    
    if ($posSecond !== false) {
        // Now, we need to cut the file at exactly where the duplication starts.
        // Wait, the duplication starts BEFORE `<div class="stat-item losses">` because of the `</div>` tags.
        // Let's look at the pattern:
        //             <div class="stat-item losses">
        //                 <div class="label">Lần Thua</div>
        //                 <div class="value"><?= $gameThua ?></div>
        //             </div>
        //         </div>
        //         <canvas id="gameChart" style="max-height: 300px;"></canvas>
        //     </div>
        // </div>
        // 
        //             <div class="stat-item losses">
        
        // Let's find the second '<div class="stat-item losses">'
        // Then walk backwards to the '</div></div>' before it.
        $cutPos = strrpos(substr($content, 0, $posSecond), '</div>');
        $cutPos = strrpos(substr($content, 0, $cutPos), '</div>'); // backwards to </div>
        $cutPos = strrpos(substr($content, 0, $cutPos), '</div>'); // backwards to </div>?
        // Actually, it's easier. We know the exact valid end of the file should be the end of the <script> block which is at the very bottom, OR the </body></html> tag.
        // Let's just find the last valid HTML tag before the duplication, which is the <script> at the bottom.
        // Wait, in the git diff, the <script> block for `threejs-background` was added AT THE VERY END in the same commit.
        // So I can just do a regex replacement that matches everything from the second `stat-item losses` down to the end of the file, EXCLUDING the </body></html>.
        
        // Let's just split by the duplicate pattern!
        $pattern = "/(\s*<div class=\"stat-item losses\">\s*<div class=\"label\">Lần Thua<\/div>\s*<div class=\"value\"><\?= \\\$gameThua \?><\/div>\s*<\/div>\s*<\/div>\s*<canvas id=\"gameChart\" style=\"max-height: 300px;\"><\/canvas>\s*<\/div>\s*<\/div>){2,}/";
        $content = preg_replace($pattern, "$1", $content);
        
        file_put_contents($file, $content);
        echo "Fixed number.php using regex!";
    }
}

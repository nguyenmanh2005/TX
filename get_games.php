<?php
$html = file_get_contents("c:/xampp/htdocs/1/index.php");
preg_match_all('/<a href="([^"]+)" class="game-card"[^>]*>.*?<div class="game-info">\s*<h3>([^<]+)<\/h3>/s', $html, $matches);
$results = [];
for ($i=0; $i<count($matches[0]); $i++) {
    $results[] = $matches[1][$i] . " - " . trim($matches[2][$i]);
}
echo implode("\n", $results);
?>

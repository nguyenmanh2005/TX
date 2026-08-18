<?php
$html = file_get_contents("c:/xampp/htdocs/1/index.php");
preg_match_all('/<a href="([^"]+)" class="game-card"/', $html, $matches);
echo implode("\n", $matches[1]);
?>

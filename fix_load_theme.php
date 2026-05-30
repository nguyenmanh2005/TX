<?php
$file = 'c:/xampp/htdocs/1/load_theme.php';
$content = file_get_contents($file);

// Find the appended script
$pos = strpos($content, '<script>' . "\n" . 'document.addEventListener(\'DOMContentLoaded\', function() {');

if ($pos !== false) {
    // Add ?> right before it
    $newContent = substr($content, 0, $pos) . "?>\n" . substr($content, $pos);
    file_put_contents($file, $newContent);
    echo "Fixed PHP syntax error in load_theme.php";
} else {
    echo "Could not find the script block.";
}

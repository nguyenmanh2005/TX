<?php
$file = 'bot_engine.php';
$content = file_get_contents($file);

// Remove any premature closing tags
$content = str_replace('?>', '', $content);
// Add one back at the very end if needed (though not required for pure PHP)
$content .= "\n?>";

file_put_contents($file, $content);
echo "Stripped all internal closing tags from bot_engine.php\n";
?>

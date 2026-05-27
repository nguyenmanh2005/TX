<?php
$file = 'bot_engine.php';
$content = file_get_contents($file);

// Remove all PHP start and end tags
$content = str_replace('<?php', '', $content);
$content = str_replace('?>', '', $content);

// Ensure it starts with <?php and no other tags
$content = "<?php\n" . trim($content);

file_put_contents($file, $content);
echo "Hard-reset PHP tags in bot_engine.php\n";
?>

<?php
$content = file_get_contents('shop.php');

// Refined Card Pattern to handle line breaks and spaces
$cardPattern = '/class="item-card[^"]*cursor[^"]*active[^"]*"/s';
$newCard = 'class="item-card <?= $cursor[\'owned\'] > 0 ? \'owned\' : \'\' ?> <?= $current[\'current_cursor_id\'] == $cursor[\'id\'] ? \'active\' : \'\' ?>"
                        onmouseenter="previewCursor(this, \'<?= htmlspecialchars($cursor[\'cursor_image\']) ?>\', \'<?= htmlspecialchars($cursor[\'pointer_image\'] ?? $cursor[\'cursor_image\']) ?>\')"
                        onmouseleave="resetCursor()"';

$content = preg_replace($cardPattern, $newCard, $content);

file_put_contents('shop.php', $content);
echo "Cập nhật thẻ vật phẩm thành công!";
?>

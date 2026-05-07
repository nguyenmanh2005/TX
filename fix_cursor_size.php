<?php
$content = file_get_contents('shop.php');

// 1. Xóa đoạn CSS 80px gây lỗi hiển thị minh họa
$content = preg_replace('/\.cursor-preview img\s*{\s*max-width:\s*80px;.*?}/s', '', $content);

// 2. Nâng cấp hàm previewCursor để tự động RESIZE ảnh về 32x32
$newJs = '        function previewCursor(element, cursorUrl, pointerUrl) {
            // Hàm xử lý resize ảnh bằng Canvas để con trỏ không bị to
            function getResizedCursor(url, callback) {
                const img = new Image();
                img.crossOrigin = "Anonymous";
                img.onload = function() {
                    const canvas = document.createElement("canvas");
                    canvas.width = 32;
                    canvas.height = 32;
                    const ctx = canvas.getContext("2d");
                    ctx.drawImage(img, 0, 0, 32, 32);
                    callback(canvas.toDataURL("image/png"));
                };
                img.src = url;
            }

            getResizedCursor(cursorUrl, function(resizedCursor) {
                document.body.style.cursor = `url(\'${resizedCursor}\'), auto`;
            });

            getResizedCursor(pointerUrl, function(resizedPointer) {
                const interactives = document.querySelectorAll(\'button, a, .item-card, input, select\');
                interactives.forEach(el => {
                    el.style.cursor = `url(\'${resizedPointer}\'), pointer`;
                });
            });

            element.style.background = "rgba(102, 126, 234, 0.1)";
        }';

$content = preg_replace('/function previewCursor.*?element\.style\.background = "rgba\(102, 126, 234, 0.1\)";\s*}/s', $newJs, $content);

file_put_contents('shop.php', $content);
echo "Đã khắc phục lỗi kích thước con trỏ thành công!";
?>

<?php
$content = file_get_contents('shop.php');

// 1. Thêm CSS hỗ trợ xem thử chuyên nghiệp
$customCss = '
        /* CSS hỗ trợ xem thử con trỏ */
        body.previewing-cursor {
            cursor: var(--preview-default), auto !important;
        }
        body.previewing-cursor button, 
        body.previewing-cursor a, 
        body.previewing-cursor input, 
        body.previewing-cursor select,
        body.previewing-cursor .buy-button {
            cursor: var(--preview-pointer), pointer !important;
        }
';

// Chèn CSS vào trước thẻ đóng </style>
$content = str_replace('</style>', $customCss . '</style>', $content);

// 2. Nâng cấp JavaScript xử lý Logic mới
$newJs = '        function previewCursor(element, cursorUrl, pointerUrl) {
            function getResizedCursor(url, callback) {
                const img = new Image();
                img.crossOrigin = "Anonymous";
                img.onload = function() {
                    const canvas = document.createElement("canvas");
                    canvas.width = 32; canvas.height = 32;
                    const ctx = canvas.getContext("2d");
                    ctx.drawImage(img, 0, 0, 32, 32);
                    callback(canvas.toDataURL("image/png"));
                };
                img.src = url;
            }

            // Lấy cả 2 ảnh đã resize
            getResizedCursor(cursorUrl, function(resDefault) {
                getResizedCursor(pointerUrl, function(resPointer) {
                    document.body.style.setProperty(\'--preview-default\', `url(\'${resDefault}\')`);
                    document.body.style.setProperty(\'--preview-pointer\', `url(\'${resPointer}\')`);
                    document.body.classList.add(\'previewing-cursor\');
                });
            });

            element.style.background = "rgba(102, 126, 234, 0.1)";
        }

        function resetCursor() {
            document.body.classList.remove(\'previewing-cursor\');
            document.body.style.removeProperty(\'--preview-default\');
            document.body.style.removeProperty(\'--preview-pointer\');
            document.querySelectorAll(\'.item-card\').forEach(el => el.style.background = "");
        }';

// Thay thế hàm cũ bằng hàm mới thông minh hơn
$content = preg_replace('/function previewCursor.*?document\.querySelectorAll\(\'\.item-card\'\)\.forEach\(el => el\.style\.background = ""\);\s*}/s', $newJs, $content);

file_put_contents('shop.php', $content);
echo "Đã nâng cấp logic dùng thử con trỏ thông minh!";
?>

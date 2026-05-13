<?php
session_start();
if (!isset($_SESSION['Iduser'])) {
    header("Location: login.php");
    exit();
}

require 'db_connect.php';

// Kiểm tra kết nối database
if (!$conn || $conn->connect_error) {
    die("Lỗi kết nối database: " . ($conn ? $conn->connect_error : "Không thể kết nối"));
}

// Load theme
require_once 'load_theme.php';
// Đảm bảo $bgGradientCSS có giá trị
if (!isset($bgGradientCSS) || empty($bgGradientCSS)) {
    $bgGradientCSS = 'linear-gradient(135deg, #667eea 0%, #764ba2 50%, #4facfe 100%)';
}

$userId = $_SESSION['Iduser'];
$uploadSuccess = false;
$error = '';
$maxFileSize = 5 * 1024 * 1024; // 5MB
$uploadDirRelative = "game/uploads/";
$uploadDirAbsolute = __DIR__ . '/' . $uploadDirRelative;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["avatar"])) {
    if (!is_dir($uploadDirAbsolute)) {
        if (!mkdir($uploadDirAbsolute, 0755, true) && !is_dir($uploadDirAbsolute)) {
            $error = "Không thể tạo thư mục lưu ảnh trên máy chủ.";
        }
    }

    if (!$error) {
        $file = $_FILES["avatar"];
        
        // Validate file size
        if ($file["size"] > $maxFileSize) {
            $error = "Kích thước file quá lớn. Tối đa 5MB.";
        } elseif ($file["error"] !== UPLOAD_ERR_OK) {
            $error = "Lỗi khi tải file lên. Mã lỗi: " . $file["error"];
        } else {
            $sanitizedName = preg_replace('/[^a-zA-Z0-9._-]/', '', basename($file["name"]));
            $uniqueName = time() . "_" . ($sanitizedName ?: 'avatar');
            $targetFilePath = $uploadDirAbsolute . $uniqueName;
            $relativeFilePath = $uploadDirRelative . $uniqueName;
            $imageFileType = strtolower(pathinfo($sanitizedName, PATHINFO_EXTENSION));
            $allowedTypes = ["jpg", "jpeg", "png", "gif", "webp"];

            // Validate file type
            if (!in_array($imageFileType, $allowedTypes)) {
                $error = "Chỉ cho phép ảnh JPG, JPEG, PNG, GIF, WEBP.";
            } else {
                // Validate MIME type
                $mimeType = null;
                if (function_exists('finfo_open')) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    if ($finfo) {
                        $mimeType = finfo_file($finfo, $file["tmp_name"]);
                        finfo_close($finfo);
                    }
                }

                if (!$mimeType && function_exists('mime_content_type')) {
                    $mimeType = @mime_content_type($file["tmp_name"]);
                }

                if (!$mimeType) {
                    $imageInfo = @getimagesize($file["tmp_name"]);
                    if ($imageInfo && isset($imageInfo['mime'])) {
                        $mimeType = $imageInfo['mime'];
                    }
                }

                $allowedMimes = ["image/jpeg", "image/png", "image/gif", "image/webp"];
                if (!$mimeType || !in_array($mimeType, $allowedMimes)) {
                    $error = "File không phải là ảnh hợp lệ.";
                } elseif (move_uploaded_file($file["tmp_name"], $targetFilePath)) {
                    // Update database
                    $stmt = $conn->prepare("UPDATE users SET ImageURL = ? WHERE Iduser = ?");
                    $stmt->bind_param("si", $relativeFilePath, $userId);
                    if ($stmt->execute()) {
                        $uploadSuccess = true;
                    } else {
                        $error = "Cập nhật database thất bại.";
                        // Remove uploaded file if DB update fails
                        if (file_exists($targetFilePath)) {
                            unlink($targetFilePath);
                        }
                    }
                    $stmt->close();
                } else {
                    $error = "Tải ảnh lên thất bại. Vui lòng thử lại.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm ảnh đại diện - Giải Trí Lành Mạnh</title>
        <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/loading.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <style>
        body {
            cursor: url('chuot.png'), url('../chuot.png'), auto !important;
            background: <?= $bgGradientCSS ?>;
            background-attachment: fixed;
            min-height: 100vh;
            position: relative;
            padding: 20px;
        }
        
        * {
            cursor: inherit;
        }

        button, a, input[type="button"], input[type="submit"], label, select {
            cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;
        }

        .upload-container {
            text-align: center;
            padding: 40px 20px;
            max-width: 800px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.98);
            border-radius: var(--border-radius-lg);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.5);
        }
        
        .upload-area {
            border: 3px dashed var(--border-color);
            border-radius: var(--border-radius-lg);
            padding: 50px 40px;
            margin: 25px 0;
            background: rgba(249, 249, 249, 0.95);
            transition: border-color 0.2s ease, background 0.2s ease, transform 0.2s ease;
            cursor: pointer;
        }
        
        .upload-area:hover {
            border-color: var(--secondary-color);
            background: rgba(240, 248, 255, 0.95);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .upload-area.dragover {
            border-color: var(--success-color);
            background: rgba(232, 245, 233, 0.95);
        }
        
        .upload-icon {
            font-size: 64px;
            margin-bottom: 20px;
            color: var(--secondary-color);
        }
        
        .file-info {
            margin-top: 20px;
            padding: 15px;
            background: rgba(232, 244, 248, 0.95);
            border-radius: var(--border-radius);
            display: none;
            border: 2px solid var(--secondary-color);
        }
        
        .preview-image {
            max-width: 350px;
            max-height: 350px;
            margin: 25px auto;
            border-radius: var(--border-radius-lg);
            border: 4px solid var(--secondary-color);
            box-shadow: var(--shadow-lg);
            display: none;
            object-fit: cover;
        }
        
        .upload-container h2 {
            color: var(--primary-color);
            margin-bottom: 15px;
            font-size: 28px;
            font-weight: 700;
        }
            /* Three.js canvas background */
        #threejs-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }

    </style>
</head>
<body>
    <canvas id="threejs-background"></canvas>

    <div style="background: rgba(255, 255, 255, 0.1); padding: 20px; margin-bottom: 20px; border-radius: var(--border-radius-lg); text-align: center;">
        <h1 style="color: white; margin: 0 0 15px 0; font-size: 28px; font-weight: 700; text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);">📸 Cập nhật ảnh đại diện</h1>
        <a href="index.php" style="display: inline-block; padding: 10px 20px; background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%); color: white; text-decoration: none; border-radius: var(--border-radius); font-weight: 600; cursor: url('img/tay.png'), url('../img/tay.png'), pointer !important;">⬅ Về trang chủ</a>
    </div>

    <div class="upload-container">
        <div class="form-wrapper">
            <h2>✨ Thêm / Cập nhật ảnh đại diện</h2>
            <p style="color: var(--text-light); margin-bottom: 30px; font-size: 16px;">
                Chọn ảnh từ máy tính của bạn (JPG, PNG, GIF, WEBP - Tối đa 5MB)
            </p>
            
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="upload-area" id="uploadArea">
                    <div class="upload-icon">📁</div>
                    <p style="font-size: 18px; margin: 15px 0; color: var(--text-dark); font-weight: 500;">
                        Kéo thả ảnh vào đây hoặc click để chọn
                    </p>
                    <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/gif,image/webp" required style="display: none;">
                    <button type="button" class="btn" onclick="document.getElementById('avatarInput').click()" style="margin-top: 10px;">
                        📂 Chọn ảnh
                    </button>
                </div>
                
                <div class="file-info" id="fileInfo"></div>
                <img id="previewImage" class="preview-image" alt="Preview">
                
                <input type="submit" value="📤 Tải ảnh lên" class="btn btn-success" style="width: 100%; margin-top: 25px; padding: 16px; font-size: 18px;">
            </form>

            <?php if ($uploadSuccess): ?>
                <div class="message success" style="margin-top: 25px; padding: 20px; background: rgba(40, 167, 69, 0.1); border: 2px solid #28a745; border-radius: var(--border-radius); color: #28a745; font-weight: 600; animation: slideIn 0.5s ease;">
                    ✅ Tải ảnh thành công! Ảnh đại diện của bạn đã được cập nhật.
                    <div style="margin-top: 15px;">
                        <a href="index.php" style="display: inline-block; padding: 10px 20px; background: var(--secondary-color); color: white; text-decoration: none; border-radius: var(--border-radius); margin-right: 10px; font-weight: 600;">🏠 Về trang chủ</a>
                        <a href="addimg.php" style="display: inline-block; padding: 10px 20px; background: var(--primary-color); color: white; text-decoration: none; border-radius: var(--border-radius); font-weight: 600;">📸 Tải ảnh khác</a>
                    </div>
                </div>
            <?php elseif (!empty($error)): ?>
                <div class="message error" style="margin-top: 25px; animation: slideIn 0.5s ease;">
                    ❌ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const uploadArea = document.getElementById('uploadArea');
        const avatarInput = document.getElementById('avatarInput');
        const fileInfo = document.getElementById('fileInfo');
        const previewImage = document.getElementById('previewImage');
        const uploadForm = document.getElementById('uploadForm');

        // Click to select file
        uploadArea.addEventListener('click', () => {
            avatarInput.click();
        });

        // Drag and drop
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                avatarInput.files = files;
                handleFileSelect(files[0]);
            }
        });

        // File input change
        avatarInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileSelect(e.target.files[0]);
            }
        });

        function handleFileSelect(file) {
            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                alert('Chỉ cho phép file ảnh (JPG, PNG, GIF, WEBP)');
                return;
            }

            // Validate file size (5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('Kích thước file quá lớn. Tối đa 5MB.');
                return;
            }

            // Show file info
            fileInfo.style.display = 'block';
            fileInfo.innerHTML = `
                <strong>📄 Tên file:</strong> ${file.name}<br>
                <strong>📊 Kích thước:</strong> ${(file.size / 1024 / 1024).toFixed(2)} MB
            `;

            // Show preview
            const reader = new FileReader();
            reader.onload = (e) => {
                previewImage.src = e.target.result;
                previewImage.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.cursor = "url('chuot.png'), url('../chuot.png'), auto";
            
            const interactiveElements = document.querySelectorAll('button, a, input, label, select');
            interactiveElements.forEach(el => {
                el.style.cursor = "url('img/tay.png'), url('../img/tay.png'), pointer";
            });
        });
    </script>

<script>
    // Initialize Three.js Background
    (function() {
        // Pass theme config từ PHP sang JavaScript
        window.themeConfig = {
            particleCount: <?= $particleCount ?? 800 ?>,
            particleSize: <?= $particleSize ?? 0.05 ?>,
            particleColor: '<?= $particleColor ?? "#ffffff" ?>',
            particleOpacity: <?= $particleOpacity ?? 0.6 ?>,
            shapeCount: <?= $shapeCount ?? 10 ?>,
            shapeColors: <?= json_encode($shapeColors ?? ["#667eea", "#764ba2", "#4facfe", "#00f2fe"]) ?>,
            shapeOpacity: <?= $shapeOpacity ?? 0.3 ?>,
            bgGradient: <?= json_encode($bgGradient ?? ["#667eea", "#764ba2", "#4facfe"]) ?>
        };
        
        // Load Three.js background script với đường dẫn chính xác
        const isInGames = window.location.pathname.includes('/games/');
        const script = document.createElement('script');
        script.src = isInGames ? '../threejs-background.js' : 'threejs-background.js';
        script.onload = function() {
            console.log('Three.js background loaded');
        };
        document.head.appendChild(script);
    })();
</script>
</body>
</html>

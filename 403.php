<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Truy cập bị từ chối</title>
    <style>
        body {
            background: #0f172a;
            color: #f8fafc;
            font-family: 'Outfit', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            text-align: center;
        }
        .container {
            padding: 40px;
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            max-width: 500px;
        }
        h1 { font-size: 80px; margin: 0; color: #ef4444; }
        p { font-size: 18px; color: #94a3b8; margin: 20px 0; }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #3b82f6;
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn:hover { background: #2563eb; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="container">
        <h1>403</h1>
        <h2>Truy cập bị từ chối</h2>
        <p>Bạn không có quyền truy cập vào trang này. Vui lòng quay lại hoặc đăng nhập bằng tài khoản có quyền admin.</p>
        <a href="index.php" class="btn">Quay lại trang chủ</a>
    </div>
</body>
</html>

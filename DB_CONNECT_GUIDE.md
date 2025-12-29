# 📘 Hướng Dẫn Sử Dụng db_connect.php

## ✅ Mục Tiêu
Đảm bảo **TẤT CẢ** các trang trong website đều sử dụng **DUY NHẤT** file `db_connect.php` để kết nối database.

## 📁 File Quan Trọng

### 1. `db_connect.php` - File chính
- ✅ **File này là file DUY NHẤT** được sử dụng để kết nối database
- Tất cả các trang phải sử dụng: `require 'db_connect.php';`
- File này chứa thông tin kết nối database production

### 2. `db_connect_backup_local.php` - File backup
- ⚠️ **KHÔNG được sử dụng** trong production
- Chỉ để backup cho môi trường local (localhost)
- **KHÔNG** được require trong bất kỳ file nào

## 🔧 Cách Sử Dụng

### Trong mỗi file PHP cần kết nối database:

```php
<?php
session_start(); // Nếu cần
require 'db_connect.php'; // ✅ BẮT BUỘC

// Sau đó có thể sử dụng $conn
$sql = "SELECT * FROM users WHERE Iduser = ?";
$stmt = $conn->prepare($sql);
// ...
?>
```

## ✅ Kiểm Tra

### Cách 1: Sử dụng script kiểm tra (Web)
1. Mở trình duyệt
2. Truy cập: `http://localhost/a/check_db_connect_usage.php`
3. Xem kết quả và tự động sửa nếu cần

### Cách 2: Sử dụng script tự động sửa (Command Line)
```bash
php ensure_db_connect.php
```

Script này sẽ:
- ✅ Tự động tìm tất cả file PHP
- ✅ Thay thế `db_connect_backup_local.php` thành `db_connect.php`
- ✅ Thêm `require 'db_connect.php';` vào các file thiếu
- ✅ Chuẩn hóa format require

## 📋 Quy Tắc

### ✅ ĐÚNG:
```php
require 'db_connect.php';
require_once 'db_connect.php';
```

### ❌ SAI:
```php
require 'db_connect_backup_local.php'; // ❌ KHÔNG được dùng
require 'db_connect_local.php'; // ❌ KHÔNG được dùng
new mysqli(...); // ❌ KHÔNG tạo kết nối trực tiếp
mysqli_connect(...); // ❌ KHÔNG tạo kết nối trực tiếp
```

## 🔍 Kiểm Tra Thủ Công

Để kiểm tra một file có sử dụng đúng không:

1. Mở file PHP
2. Tìm dòng: `require 'db_connect.php';` hoặc `require_once 'db_connect.php';`
3. Đảm bảo KHÔNG có:
   - `db_connect_backup_local.php`
   - `new mysqli(...)`
   - `mysqli_connect(...)`

## 🚀 Chạy Script Tự Động Sửa

```bash
# Từ thư mục gốc của project
php ensure_db_connect.php
```

Script sẽ:
- Quét tất cả file PHP
- Tự động sửa các file chưa đúng
- Báo cáo kết quả

## 📝 Lưu Ý

1. **File `db_connect.php` là file DUY NHẤT** được sử dụng
2. **KHÔNG** tạo kết nối database trực tiếp trong các file khác
3. **KHÔNG** sử dụng `db_connect_backup_local.php` trong production
4. Luôn sử dụng `require 'db_connect.php';` ở đầu file (sau `<?php` hoặc `session_start()`)

## 🎯 Kết Quả Mong Đợi

Sau khi chạy script, **TẤT CẢ** file PHP sẽ:
- ✅ Sử dụng `require 'db_connect.php';`
- ✅ KHÔNG có kết nối database trực tiếp
- ✅ KHÔNG sử dụng `db_connect_backup_local.php`

## 🔄 Cập Nhật Database Connection

Nếu cần thay đổi thông tin kết nối database:
1. **CHỈ** sửa file `db_connect.php`
2. Tất cả các trang sẽ tự động sử dụng thông tin mới
3. **KHÔNG** cần sửa từng file một

---

**✅ Đảm bảo tất cả trang đều sử dụng db_connect.php để dễ quản lý và bảo trì!**


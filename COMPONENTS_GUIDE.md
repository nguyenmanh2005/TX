# 🎨 Hướng Dẫn Sử Dụng Components UI/UX

## 📋 Tổng Quan

Hệ thống UI/UX mới bao gồm các component tái sử dụng, responsive design, loading states và animations mượt mà.

## 📁 Cấu Trúc Files

```
assets/css/
├── main.css          # CSS chính với các component cơ bản
├── components.css    # Components tái sử dụng
├── responsive.css    # Responsive design
├── loading.css      # Loading states & skeleton screens
├── animations.css    # Animations
├── game-effects.css # Game-specific effects
└── master.css       # Tất cả trong một (optional)
```

## 🚀 Cách Include CSS

### Option 1: Include từng file (Recommended)
```html
<link rel="stylesheet" href="assets/css/main.css">
<link rel="stylesheet" href="assets/css/components.css">
<link rel="stylesheet" href="assets/css/responsive.css">
<link rel="stylesheet" href="assets/css/loading.css">
<link rel="stylesheet" href="assets/css/animations.css">
```

### Option 2: Sử dụng master.css
```html
<link rel="stylesheet" href="assets/css/master.css">
```

### Option 3: Sử dụng PHP Helper
```php
require_once 'include_css.php';
echo getCSSIncludes(); // Cho trang thường
echo getGameCSSIncludes(); // Cho trang game
echo getAdminCSSIncludes(); // Cho trang admin
```

## 🎯 Components Có Sẵn

### 1. Cards

#### Card Modern
```html
<div class="card-modern">
    <h3>Tiêu đề</h3>
    <p>Nội dung card...</p>
</div>
```

### 2. Buttons

#### Button Modern
```html
<button class="btn-modern">Click me</button>
<button class="btn-modern btn-success">Success</button>
<button class="btn-modern btn-danger">Danger</button>
<button class="btn-modern btn-warning">Warning</button>
```

#### Button Loading State
```html
<button class="btn-modern btn-loading">Loading...</button>
```

### 3. Forms

#### Input Modern
```html
<input type="text" class="input-modern" placeholder="Nhập text...">
<input type="email" class="input-modern" placeholder="Email">
<input type="password" class="input-modern" placeholder="Password">
```

### 4. Tables

#### Table Modern
```html
<table class="table-modern">
    <thead>
        <tr>
            <th>Column 1</th>
            <th>Column 2</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Data 1</td>
            <td>Data 2</td>
        </tr>
    </tbody>
</table>
```

### 5. Badges

```html
<span class="badge badge-success">Success</span>
<span class="badge badge-danger">Danger</span>
<span class="badge badge-warning">Warning</span>
<span class="badge badge-info">Info</span>
```

### 6. Alerts

```html
<div class="alert alert-success">
    <i class="fa-solid fa-check-circle"></i>
    Thành công!
</div>

<div class="alert alert-danger">
    <i class="fa-solid fa-exclamation-circle"></i>
    Có lỗi xảy ra!
</div>

<div class="alert alert-warning">
    <i class="fa-solid fa-exclamation-triangle"></i>
    Cảnh báo!
</div>

<div class="alert alert-info">
    <i class="fa-solid fa-info-circle"></i>
    Thông tin!
</div>
```

### 7. Loading States

#### Spinner
```html
<div class="spinner"></div>
<div class="spinner spinner-small"></div>
```

#### Skeleton Loading
```html
<div class="skeleton skeleton-text"></div>
<div class="skeleton skeleton-title"></div>
<div class="skeleton skeleton-avatar"></div>
<div class="skeleton skeleton-image"></div>
<div class="skeleton skeleton-button"></div>
```

#### Full Page Loader
```html
<div class="page-loader">
    <div class="loader-content">
        <div class="loader-spinner"></div>
        <div class="loader-text">Đang tải...</div>
        <div class="loader-progress">
            <div class="loader-progress-bar"></div>
        </div>
    </div>
</div>
```

### 8. Progress Bar

```html
<div class="progress-bar">
    <div class="progress-bar-fill" style="width: 60%;"></div>
</div>
```

### 9. Modals

```html
<div class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Tiêu đề Modal</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            Nội dung modal...
        </div>
    </div>
</div>
```

### 10. Stats Grid

```html
<div class="stats-grid">
    <div class="stat-box">
        <span class="stat-box-icon">💰</span>
        <span class="stat-box-value">1,000,000</span>
        <span class="stat-box-label">VNĐ</span>
    </div>
    <div class="stat-box">
        <span class="stat-box-icon">🎮</span>
        <span class="stat-box-value">150</span>
        <span class="stat-box-label">Game đã chơi</span>
    </div>
</div>
```

### 11. Tabs

```html
<div class="tabs">
    <button class="tab active">Tab 1</button>
    <button class="tab">Tab 2</button>
    <button class="tab">Tab 3</button>
</div>

<div class="tab-content active">
    Nội dung tab 1
</div>
<div class="tab-content">
    Nội dung tab 2
</div>
```

### 12. Pagination

```html
<div class="pagination">
    <a href="?page=1">&laquo;</a>
    <a href="?page=1">1</a>
    <span class="active">2</span>
    <a href="?page=3">3</a>
    <a href="?page=3">&raquo;</a>
</div>
```

### 13. Search Box

```html
<div class="search-box">
    <input type="text" placeholder="Tìm kiếm...">
    <i class="fa-solid fa-search search-box-icon"></i>
</div>
```

### 14. Empty State

```html
<div class="empty-state">
    <div class="empty-state-icon">📭</div>
    <h3 class="empty-state-title">Không có dữ liệu</h3>
    <p class="empty-state-description">Chưa có dữ liệu để hiển thị.</p>
</div>
```

### 15. Tooltip

```html
<span class="tooltip" data-tooltip="Đây là tooltip">Hover me</span>
```

## 📱 Responsive Utilities

### Grid System
```html
<div class="grid grid-2">2 columns</div>
<div class="grid grid-3">3 columns</div>
<div class="grid grid-4">4 columns</div>
<div class="grid grid-auto">Auto columns</div>
```

### Container Sizes
```html
<div class="container-sm">Small container</div>
<div class="container-md">Medium container</div>
<div class="container-lg">Large container</div>
<div class="container-xl">XL container</div>
```

## 🎨 Typography

### Text Gradient
```html
<h1 class="text-gradient">Gradient Text</h1>
```

### Text Shadow
```html
<h1 class="text-shadow">Shadow Text</h1>
```

## 🌙 Dark Mode

Dark mode được hỗ trợ tự động. Thêm class `dark-mode` vào body:

```html
<body class="dark-mode">
```

## ♿ Accessibility

- Tất cả components đều hỗ trợ keyboard navigation
- Focus states rõ ràng
- Screen reader friendly
- ARIA attributes được thêm tự động

## 🎭 Animations

Tất cả animations đều tôn trọng `prefers-reduced-motion`. Nếu user bật reduced motion, animations sẽ bị tắt.

## 📝 Best Practices

1. **Luôn sử dụng semantic HTML**
2. **Sử dụng các class có sẵn thay vì viết CSS mới**
3. **Test trên nhiều thiết bị khác nhau**
4. **Sử dụng skeleton loading khi fetch data**
5. **Thêm loading states cho buttons khi submit**

## 🔧 Customization

Tất cả colors và spacing có thể customize qua CSS variables trong `:root`:

```css
:root {
    --primary-color: #00796b;
    --secondary-color: #3498db;
    --border-radius: 12px;
    /* ... */
}
```

## 📚 Examples

Xem các trang đã được cập nhật:
- `index.php` - Trang chủ với stats grid
- `shop.php` - Cửa hàng với card modern
- `profile.php` - Profile với tabs
- `leaderboard.php` - Bảng xếp hạng với table modern

## 🐛 Troubleshooting

### CSS không load?
- Kiểm tra đường dẫn file CSS
- Clear browser cache
- Kiểm tra console để xem lỗi

### Component không hiển thị đúng?
- Kiểm tra đã include đủ CSS files chưa
- Kiểm tra có conflict với CSS cũ không
- Xem browser console để debug

## 📞 Support

Nếu có vấn đề, hãy kiểm tra:
1. File CSS đã được include chưa
2. Class names có đúng không
3. HTML structure có đúng không

---

**Happy Coding! 🚀**


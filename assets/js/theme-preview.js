/**
 * Theme Preview System
 * Xem trước theme trước khi mua
 */

function initThemePreview() {
    const previewButtons = document.querySelectorAll('[data-theme-preview]');

    previewButtons.forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const themeId = this.getAttribute('data-theme-preview');
            showThemePreview(themeId);
        });
    });
}

function showThemePreview(themeId) {
    // Fetch theme data
    fetch(`api_profile.php?action=get_theme&theme_id=${themeId}`, {
        credentials: 'same-origin'
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderThemePreviewModal(data.theme);
            } else {
                if (typeof QuickActions !== 'undefined' && QuickActions.showToast) {
                    QuickActions.showToast('Không thể tải thông tin theme', 'error');
                }
            }
        })
        .catch(err => {
            console.error('Theme preview error:', err);
            if (typeof QuickActions !== 'undefined' && QuickActions.showToast) {
                QuickActions.showToast('Lỗi khi tải theme', 'error');
            }
        });
}

function renderThemePreviewModal(theme) {
    // Remove existing modal
    const existing = document.getElementById('themePreviewModal');
    if (existing) {
        existing.remove();
    }

    const modal = document.createElement('div');
    modal.id = 'themePreviewModal';
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal" style="max-width: 800px;">
            <div class="modal-header">
                <h3 class="modal-title">🎨 Xem Trước Theme: ${theme.name || 'Theme'}</h3>
                <button class="modal-close" onclick="closeThemePreview()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="theme-preview-container">
                    <div class="theme-preview-background" style="background: ${theme.background_gradient || 'linear-gradient(135deg, #667eea, #764ba2)'};">
                        <div class="theme-preview-content">
                            <h4>${theme.name || 'Theme Name'}</h4>
                            <p>${theme.description || 'Theme description'}</p>
                            <div class="theme-preview-stats">
                                <div class="stat">💰 Giá: ${(theme.price || 0).toLocaleString('vi-VN')} GTLM</div>
                                <div class="stat">⭐ Rating: ${theme.rating || 'N/A'}</div>
                            </div>
                        </div>
                    </div>
                    <div class="theme-preview-actions">
                        <button class="btn-modern btn-primary" onclick="buyTheme(${theme.id})">Mua Ngay</button>
                        <button class="btn-modern" onclick="closeThemePreview()">Đóng</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    // Show modal
    setTimeout(() => {
        modal.style.display = 'flex';
    }, 10);

    // Close on overlay click
    modal.addEventListener('click', function (e) {
        if (e.target === modal) {
            closeThemePreview();
        }
    });
}

function closeThemePreview() {
    const modal = document.getElementById('themePreviewModal');
    if (modal) {
        modal.style.display = 'none';
        setTimeout(() => modal.remove(), 300);
    }
}

function buyTheme(themeId) {
    // Redirect to shop or handle purchase
    window.location.href = `shop.php?buy_theme=${themeId}`;
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', initThemePreview);

// Export
window.ThemePreview = {
    showThemePreview,
    closeThemePreview
};


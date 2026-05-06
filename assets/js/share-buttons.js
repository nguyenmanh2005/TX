/**
 * Share Buttons System
 * Chia sẻ nội dung lên social media
 */

function initShareButtons() {
    const shareButtons = document.querySelectorAll('[data-share]');
    
    shareButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const platform = this.getAttribute('data-share');
            const url = this.getAttribute('data-url') || window.location.href;
            const title = this.getAttribute('data-title') || document.title;
            const text = this.getAttribute('data-text') || '';
            
            shareToPlatform(platform, { url, title, text });
        });
    });
}

function shareToPlatform(platform, { url, title, text }) {
    const encodedUrl = encodeURIComponent(url);
    const encodedTitle = encodeURIComponent(title);
    const encodedText = encodeURIComponent(text);
    
    let shareUrl = '';
    
    switch(platform) {
        case 'facebook':
            shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}`;
            break;
        case 'twitter':
            shareUrl = `https://twitter.com/intent/tweet?url=${encodedUrl}&text=${encodedTitle}`;
            break;
        case 'whatsapp':
            shareUrl = `https://wa.me/?text=${encodedTitle}%20${encodedUrl}`;
            break;
        case 'telegram':
            shareUrl = `https://t.me/share/url?url=${encodedUrl}&text=${encodedTitle}`;
            break;
        case 'copy':
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(() => {
                    if (typeof QuickActions !== 'undefined' && QuickActions.showToast) {
                        QuickActions.showToast('✅ Đã sao chép link!', 'success');
                    }
                });
                return;
            }
            break;
        case 'native':
            if (navigator.share) {
                navigator.share({
                    title: title,
                    text: text,
                    url: url
                }).catch(err => {
                    console.log('Share cancelled:', err);
                });
                return;
            }
            break;
    }
    
    if (shareUrl) {
        window.open(shareUrl, '_blank', 'width=600,height=400');
    }
}

// Create share buttons dynamically
function createShareButtons(container, options = {}) {
    const {
        url = window.location.href,
        title = document.title,
        text = '',
        platforms = ['facebook', 'twitter', 'whatsapp', 'copy']
    } = options;
    
    const buttonsContainer = document.createElement('div');
    buttonsContainer.className = 'share-buttons';
    
    const platformIcons = {
        facebook: '📘',
        twitter: '🐦',
        whatsapp: '💬',
        telegram: '✈️',
        copy: '📋',
        native: '🔗'
    };
    
    const platformLabels = {
        facebook: 'Facebook',
        twitter: 'Twitter',
        whatsapp: 'WhatsApp',
        telegram: 'Telegram',
        copy: 'Sao chép',
        native: 'Chia sẻ'
    };
    
    platforms.forEach(platform => {
        const btn = document.createElement('button');
        btn.className = 'share-btn';
        btn.setAttribute('data-share', platform);
        btn.setAttribute('data-url', url);
        btn.setAttribute('data-title', title);
        btn.setAttribute('data-text', text);
        btn.innerHTML = `
            <span class="share-icon">${platformIcons[platform] || '🔗'}</span>
            <span class="share-label">${platformLabels[platform] || platform}</span>
        `;
        buttonsContainer.appendChild(btn);
    });
    
    container.appendChild(buttonsContainer);
    initShareButtons(); // Re-initialize
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', initShareButtons);

// Export
window.ShareButtons = {
    initShareButtons,
    shareToPlatform,
    createShareButtons
};


/**
 * User Feedback System
 * Thu thập feedback từ người dùng
 */

const UserFeedback = {
    init: function() {
        // Create feedback button
        this.createFeedbackButton();
        
        // Listen for feedback form submissions
        document.addEventListener('submit', (e) => {
            if (e.target.classList.contains('feedback-form')) {
                e.preventDefault();
                this.handleFeedbackSubmit(e.target);
            }
        });
    },
    
    createFeedbackButton: function() {
        const btn = document.createElement('button');
        btn.id = 'feedbackButton';
        btn.className = 'feedback-btn';
        btn.innerHTML = '💬 Feedback';
        btn.title = 'Gửi phản hồi';
        btn.onclick = () => this.showFeedbackModal();
        document.body.appendChild(btn);
    },
    
    showFeedbackModal: function() {
        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.id = 'feedbackModal';
        modal.innerHTML = `
            <div class="modal" style="max-width: 500px;">
                <div class="modal-header">
                    <h3 class="modal-title">💬 Gửi Phản Hồi</h3>
                    <button class="modal-close" onclick="UserFeedback.closeFeedbackModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <form class="feedback-form">
                        <div class="form-group">
                            <label>Loại phản hồi</label>
                            <select name="type" class="input-modern" required>
                                <option value="bug">🐛 Báo lỗi</option>
                                <option value="suggestion">💡 Đề xuất</option>
                                <option value="question">❓ Câu hỏi</option>
                                <option value="other">📝 Khác</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Nội dung</label>
                            <textarea name="message" class="input-modern" rows="5" required placeholder="Nhập phản hồi của bạn..."></textarea>
                        </div>
                        <div class="form-group">
                            <label>Email (tùy chọn)</label>
                            <input type="email" name="email" class="input-modern" placeholder="email@example.com">
                        </div>
                        <button type="submit" class="btn-modern btn-primary">Gửi Phản Hồi</button>
                    </form>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        setTimeout(() => modal.style.display = 'flex', 10);
        
        modal.addEventListener('click', (e) => {
            if (e.target === modal) this.closeFeedbackModal();
        });
    },
    
    closeFeedbackModal: function() {
        const modal = document.getElementById('feedbackModal');
        if (modal) {
            modal.style.display = 'none';
            setTimeout(() => modal.remove(), 300);
        }
    },
    
    handleFeedbackSubmit: function(form) {
        const formData = new FormData(form);
        const data = {
            type: formData.get('type'),
            message: formData.get('message'),
            email: formData.get('email'),
            url: window.location.href,
            userAgent: navigator.userAgent,
            timestamp: new Date().toISOString()
        };
        
        // Send to server
        fetch('api_save_feedback.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    if (typeof QuickActions !== 'undefined' && QuickActions.showToast) {
                        QuickActions.showToast('✅ Cảm ơn phản hồi của bạn!', 'success');
                    }
                    this.closeFeedbackModal();
                } else {
                    throw new Error(result.message || 'Failed to submit feedback');
                }
            })
            .catch(err => {
                console.error('Feedback error:', err);
                // Save to localStorage as backup
                const feedbacks = JSON.parse(localStorage.getItem('pendingFeedbacks') || '[]');
                feedbacks.push(data);
                localStorage.setItem('pendingFeedbacks', JSON.stringify(feedbacks));
                
                if (typeof QuickActions !== 'undefined' && QuickActions.showToast) {
                    QuickActions.showToast('⚠️ Đã lưu phản hồi, sẽ gửi khi online', 'info');
                }
                this.closeFeedbackModal();
            });
    }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => UserFeedback.init());

// Export
window.UserFeedback = UserFeedback;

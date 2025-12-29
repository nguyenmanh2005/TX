/**
 * Game Lucky Wheel - JavaScript Enhanced
 * Nâng cấp UI/UX cho Lucky Wheel
 */

class LuckyWheelEnhanced {
    constructor() {
        this.rewards = [];
        this.canSpin = false;
        this.isSpinning = false;
        this.wheelElement = null;
        this.init();
    }
    
    init() {
            this.wheelElement = document.getElementById('wheel');
        this.setupEventListeners();
                this.checkSpinStatus();
                this.loadRewards();
                this.loadHistory();
    }
    
    setupEventListeners() {
        const spinButton = document.getElementById('spinButton');
        if (spinButton) {
            spinButton.addEventListener('click', () => this.spinWheel());
        }

        // Close popup khi click outside
        document.addEventListener('click', (e) => {
            const popup = document.getElementById('rewardPopup');
            if (popup && e.target === popup) {
                this.closeRewardPopup();
            }
        });
        
        // Keyboard shortcut: Space để quay
        document.addEventListener('keydown', (e) => {
            if (e.code === 'Space' && this.canSpin && !this.isSpinning) {
                e.preventDefault();
                this.spinWheel();
            }
        });
    }
    
    checkSpinStatus() {
        fetch('api_lucky_wheel.php?action=check_spin')
            .then(response => response.json())
            .then(data => {
            if (data.status === 'success') {
                this.canSpin = !data.has_spun;
                this.updateSpinStatus(data.has_spun);
            }
            })
            .catch(error => {
            console.error('Error checking spin status:', error);
                this.updateSpinStatus(true, 'error');
            });
    }
    
    updateSpinStatus(hasSpun, error = false) {
        const spinButton = document.getElementById('spinButton');
        const spinStatus = document.getElementById('spinStatus');
        
        if (!spinButton || !spinStatus) return;

        // Remove all status classes
        spinStatus.classList.remove('status-ready', 'status-spinning', 'status-used');

        if (error) {
            spinButton.disabled = true;
            spinStatus.innerHTML = '❌ Có lỗi xảy ra!';
            spinStatus.classList.add('status-used');
            return;
        }
        
        if (hasSpun) {
            spinButton.disabled = true;
            spinStatus.innerHTML = '❌ Bạn đã quay wheel hôm nay rồi! Quay lại vào ngày mai nhé.';
            spinStatus.classList.add('status-used');
        } else {
            spinButton.disabled = false;
            spinStatus.innerHTML = '✅ Bạn có thể quay wheel ngay bây giờ!';
            spinStatus.classList.add('status-ready');
        }
    }
    
    loadRewards() {
        fetch('api_lucky_wheel.php?action=get_rewards')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
            if (data.status === 'success') {
                this.rewards = data.rewards || [];
                this.drawWheel();
            } else {
                    console.error('Error loading rewards:', data.message);
                    this.showError('Không thể tải phần thưởng');
            }
            })
            .catch(error => {
            console.error('Error loading rewards:', error);
                this.showError('Có lỗi xảy ra khi tải phần thưởng');
            });
    }
    
    drawWheel() {
        const canvas = this.wheelElement;
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        if (!ctx) return;
        
        const centerX = canvas.width / 2;
        const centerY = canvas.height / 2;
        const radius = canvas.width / 2 - 10;
        
        if (!this.rewards || this.rewards.length === 0) {
            // Vẽ wheel mặc định
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.beginPath();
            ctx.arc(centerX, centerY, radius, 0, 2 * Math.PI);
            ctx.fillStyle = '#3498db';
            ctx.fill();
            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 5;
            ctx.stroke();
            ctx.fillStyle = '#fff';
            ctx.font = 'bold 20px Arial';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.strokeStyle = '#000';
            ctx.lineWidth = 3;
            ctx.strokeText('Đang tải...', centerX, centerY);
            ctx.fillText('Đang tải...', centerX, centerY);
            return;
        }
        
        // Clear canvas
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        const anglePerSector = (2 * Math.PI) / this.rewards.length;
        
        this.rewards.forEach((reward, index) => {
            const startAngle = index * anglePerSector - Math.PI / 2;
            const endAngle = (index + 1) * anglePerSector - Math.PI / 2;
            
            // Vẽ sector
            ctx.beginPath();
            ctx.moveTo(centerX, centerY);
            ctx.arc(centerX, centerY, radius, startAngle, endAngle);
            ctx.closePath();
            ctx.fillStyle = reward.color || '#3498db';
            ctx.fill();
            
            // Vẽ border
            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 2;
            ctx.stroke();
            
            // Vẽ đường phân cách
            ctx.beginPath();
            ctx.moveTo(centerX, centerY);
            ctx.lineTo(
                centerX + Math.cos(startAngle) * radius,
                centerY + Math.sin(startAngle) * radius
            );
            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 2;
            ctx.stroke();
            
            // Tính góc giữa của sector
            const middleAngle = startAngle + anglePerSector / 2;
            const angleDegrees = (middleAngle * 180 / Math.PI + 360) % 360;
            
            // Vị trí text
            let textDistance = radius * 0.68;
            
            // Điều chỉnh text nếu ở phía trên (gần pointer)
            if ((angleDegrees >= 255 && angleDegrees <= 285)) {
                textDistance = radius * 0.50;
            } else if ((angleDegrees >= 240 && angleDegrees <= 300)) {
                textDistance = radius * 0.58;
            }
            
            const textX = centerX + Math.cos(middleAngle) * textDistance;
            const textY = centerY + Math.sin(middleAngle) * textDistance;
            
            // Chuẩn bị text
            let text = reward.reward_name || 'N/A';
            const maxLength = 18;
            if (text.length > maxLength) {
                text = text.substring(0, maxLength - 3) + '...';
            }
            
            // Vẽ text
            ctx.save();
            ctx.translate(textX, textY);
            
            let textAngle = middleAngle;
            if (angleDegrees > 90 && angleDegrees < 270) {
                textAngle += Math.PI;
            }
            ctx.rotate(textAngle + Math.PI / 2);
            
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.font = 'bold 11px Arial, sans-serif';
            
            // Shadow
            ctx.strokeStyle = '#000';
            ctx.lineWidth = 4;
            ctx.lineJoin = 'round';
            ctx.miterLimit = 2;
            ctx.strokeText(text, 0, 0);
            
            // Text chính
            ctx.fillStyle = '#ffffff';
            ctx.fillText(text, 0, 0);
            
            ctx.restore();
        });
    }
    
    spinWheel() {
        if (this.isSpinning || !this.canSpin) return;
        
        this.isSpinning = true;
        const spinButton = document.getElementById('spinButton');
        const spinStatus = document.getElementById('spinStatus');
        
        if (spinButton) spinButton.disabled = true;
        if (spinStatus) {
            spinStatus.innerHTML = '⏳ Đang quay...';
            spinStatus.classList.remove('status-ready', 'status-used');
            spinStatus.classList.add('status-spinning');
        }
        
        fetch('api_lucky_wheel.php?action=spin', {
                method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const currentRotation = this.getCurrentRotation(this.wheelElement);
                const newRotation = currentRotation + data.angle;
                
                if (this.wheelElement) {
                    this.wheelElement.style.transform = `rotate(${newRotation}deg)`;
                }
                
                setTimeout(() => {
                    this.showRewardPopup(data.reward, data.message);
                    this.checkSpinStatus();
                    this.loadHistory();
                    
                    // Reload page after 3 seconds to update balance
                    if (data.reward_given) {
                        setTimeout(() => {
                            window.location.reload();
                        }, 3000);
                    }
                    
                    this.isSpinning = false;
                }, 4000);
            } else {
                this.showError(data.message || 'Có lỗi xảy ra!');
                this.isSpinning = false;
                if (spinButton) spinButton.disabled = false;
                this.checkSpinStatus();
            }
        })
        .catch(error => {
            console.error('Error spinning wheel:', error);
            this.showError('Có lỗi xảy ra khi quay wheel!');
            this.isSpinning = false;
            if (spinButton) spinButton.disabled = false;
            this.checkSpinStatus();
        });
    }
    
    getCurrentRotation(element) {
        if (!element) return 0;
        
        const style = window.getComputedStyle(element);
        const matrix = style.transform || style.webkitTransform || style.mozTransform;
        if (matrix && matrix !== 'none') {
            try {
                const values = matrix.split('(')[1].split(')')[0].split(',');
                const a = parseFloat(values[0]);
                const b = parseFloat(values[1]);
                const angle = Math.round(Math.atan2(b, a) * (180/Math.PI));
                return angle < 0 ? angle + 360 : angle;
            } catch (e) {
                console.error('Error parsing transform:', e);
                return 0;
            }
        }
        return 0;
    }
    
    showRewardPopup(reward, message) {
        const popup = document.getElementById('rewardPopup');
        const icon = document.getElementById('rewardIcon');
        const rewardMessage = document.getElementById('rewardMessage');
        const rewardDetails = document.getElementById('rewardDetails');
        
        if (!popup) return;
        
        if (icon) icon.textContent = reward.icon || '🎁';
        if (rewardMessage) {
            rewardMessage.textContent = reward.reward_value > 0 ? '🎉 Chúc mừng!' : '😢';
        }
        if (rewardDetails) rewardDetails.textContent = message;
            
            popup.style.display = 'block';
            
        // Auto close sau 5 giây nếu không click
        setTimeout(() => {
            if (popup.style.display === 'block') {
                this.closeRewardPopup();
                }
        }, 5000);
    }
    
    closeRewardPopup() {
        const popup = document.getElementById('rewardPopup');
        if (popup) {
            popup.style.display = 'none';
        }
    }
    
    loadHistory() {
        fetch('api_lucky_wheel.php?action=get_history')
            .then(response => response.json())
            .then(data => {
            if (data.status === 'success') {
                const historyList = document.getElementById('historyList');
                    
                if (!historyList) return;
                
                if (data.history.length === 0) {
                        historyList.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--text-light);">Chưa có lịch sử quay</div>';
                    return;
                }
                
                    historyList.innerHTML = data.history.map((item, index) => {
                    const date = new Date(item.spun_at);
                        const dateStr = date.toLocaleDateString('vi-VN') + ' ' + date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
                    
                    return `
                            <div class="history-item-enhanced" style="animation-delay: ${index * 0.1}s">
                            <div class="history-icon-enhanced">${item.icon || '🎁'}</div>
                            <div class="history-details-enhanced">
                                <div class="history-name-enhanced">${item.reward_name}</div>
                                <div class="history-date-enhanced">${dateStr}</div>
                            </div>
                        </div>
                    `;
                }).join('');
            }
            })
            .catch(error => {
            console.error('Error loading history:', error);
            });
    }
    
    showError(message) {
        const spinStatus = document.getElementById('spinStatus');
        if (spinStatus) {
            spinStatus.innerHTML = '❌ ' + message;
            spinStatus.classList.remove('status-ready', 'status-spinning');
            spinStatus.classList.add('status-used');
        }
    }
}

// Initialize khi DOM ready
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('wheel')) {
        window.luckyWheelEnhanced = new LuckyWheelEnhanced();
        }
});

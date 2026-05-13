/**
 * 🌊 Bắn Cá Arcade Engine v1.0
 * Pure JS Canvas Logic
 */

const canvas = document.getElementById('gameCanvas');
const ctx = canvas.getContext('2d');

canvas.width = window.innerWidth;
canvas.height = window.innerHeight;

window.addEventListener('resize', () => {
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
});

// --- Config ---
const FISH_TYPES = {
    small: { name: 'Cá Xanh', multiplier: 2, speed: 2, hp: 1, size: 30, color: '#38bdf8', frequency: 0.05 },
    medium: { name: 'Cá Vàng', multiplier: 5, speed: 1.5, hp: 3, size: 50, color: '#fbbf24', frequency: 0.02 },
    large: { name: 'Cá Đỏ', multiplier: 10, speed: 1, hp: 5, size: 70, color: '#f87171', frequency: 0.01 },
    shark: { name: 'Cá Mập', multiplier: 20, speed: 0.8, hp: 15, size: 120, color: '#94a3b8', frequency: 0.005 },
    octopus: { name: 'Bạch Tuộc', multiplier: 50, speed: 1.2, hp: 30, size: 90, color: '#a855f7', frequency: 0.002 },
    gold_crab: { name: 'Cua Vàng', multiplier: 100, speed: 0.5, hp: 50, size: 80, color: '#fbbf24', frequency: 0.001 },
    dragon: { name: 'Rồng Biển', multiplier: 500, speed: 0.4, hp: 200, size: 200, color: '#22c55e', frequency: 0.0005 }
};

class Fish {
    constructor(typeKey) {
        const type = FISH_TYPES[typeKey];
        this.typeKey = typeKey;
        this.name = type.name;
        this.multiplier = type.multiplier;
        this.speed = type.speed + (Math.random() * 0.5);
        this.hp = type.hp;
        this.maxHp = type.hp;
        this.size = type.size;
        this.color = type.color;
        
        // Vị trí xuất hiện ngẫu nhiên bên trái hoặc phải
        this.direction = Math.random() > 0.5 ? 1 : -1;
        this.x = this.direction === 1 ? -this.size : canvas.width + this.size;
        this.y = Math.random() * (canvas.height - 200) + 100;
        
        // Di chuyển hơi zigzag
        this.angle = 0;
        this.isDead = false;
    }

    update() {
        this.x += this.speed * this.direction;
        this.y += Math.sin(this.angle) * 1;
        this.angle += 0.05;

        // Xóa nếu ra khỏi màn hình
        if (this.direction === 1 && this.x > canvas.width + this.size) this.isDead = true;
        if (this.direction === -1 && this.x < -this.size) this.isDead = true;
    }

    draw() {
        ctx.save();
        ctx.translate(this.x, this.y);
        if (this.direction === -1) ctx.scale(-1, 1);

        // Vẽ thân cá đơn giản (Có thể thay bằng Sprite sau)
        ctx.beginPath();
        ctx.ellipse(0, 0, this.size / 2, this.size / 4, 0, 0, Math.PI * 2);
        ctx.fillStyle = this.color;
        ctx.fill();
        ctx.strokeStyle = 'white';
        ctx.lineWidth = 2;
        ctx.stroke();

        // Đuôi
        ctx.beginPath();
        ctx.moveTo(-this.size / 2, 0);
        ctx.lineTo(-this.size / 2 - 15, -15);
        ctx.lineTo(-this.size / 2 - 15, 15);
        ctx.closePath();
        ctx.fillStyle = this.color;
        ctx.fill();

        // Mắt
        ctx.beginPath();
        ctx.arc(this.size / 4, -5, 4, 0, Math.PI * 2);
        ctx.fillStyle = 'white';
        ctx.fill();
        ctx.beginPath();
        ctx.arc(this.size / 4 + 2, -5, 2, 0, Math.PI * 2);
        ctx.fillStyle = 'black';
        ctx.fill();

        // Thanh máu nếu là cá lớn
        if (this.maxHp > 1) {
            const healthBarWidth = this.size;
            ctx.fillStyle = '#333';
            ctx.fillRect(-healthBarWidth/2, -this.size/2 - 10, healthBarWidth, 4);
            ctx.fillStyle = '#4ade80';
            ctx.fillRect(-healthBarWidth/2, -this.size/2 - 10, healthBarWidth * (this.hp / this.maxHp), 4);
        }

        ctx.restore();
    }
}

class Bullet {
    constructor(x, y, targetX, targetY, price) {
        this.x = x;
        this.y = y;
        this.price = price;
        this.radius = 8;
        this.speed = 10;
        this.isDead = false;

        const angle = Math.atan2(targetY - y, targetX - x);
        this.vx = Math.cos(angle) * this.speed;
        this.vy = Math.sin(angle) * this.speed;
    }

    update() {
        this.x += this.vx;
        this.y += this.vy;

        if (this.x < 0 || this.x > canvas.width || this.y < 0 || this.y > canvas.height) {
            this.isDead = true;
        }
    }

    draw() {
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
        ctx.fillStyle = '#0ea5e9';
        ctx.shadowBlur = 15;
        ctx.shadowColor = '#0ea5e9';
        ctx.fill();
        ctx.shadowBlur = 0;
    }
}

class Particle {
    constructor(x, y, color) {
        this.x = x;
        this.y = y;
        this.color = color;
        this.vx = (Math.random() - 0.5) * 10;
        this.vy = (Math.random() - 0.5) * 10;
        this.alpha = 1;
        this.isDead = false;
    }
    update() {
        this.x += this.vx;
        this.y += this.vy;
        this.alpha -= 0.02;
        if (this.alpha <= 0) this.isDead = true;
    }
    draw() {
        ctx.globalAlpha = this.alpha;
        ctx.beginPath();
        ctx.arc(this.x, this.y, 3, 0, Math.PI * 2);
        ctx.fillStyle = this.color;
        ctx.fill();
        ctx.globalAlpha = 1;
    }
}

// --- Game Engine ---
const fishes = [];
const bullets = [];
const particles = [];
let lastShotTime = 0;

function spawnFish() {
    for (const key in FISH_TYPES) {
        if (Math.random() < FISH_TYPES[key].frequency) {
            fishes.push(new Fish(key));
        }
    }
}

// catchFish has been merged into shoot action in api_banharc.php

function showScorePopup(x, y, score) {
    const el = $('<div class="score-popup">+' + Number(score).toLocaleString() + '</div>');
    el.css({ left: x, top: y });
    $('body').append(el);
    setTimeout(() => el.remove(), 1000);
}

function gameLoop() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    spawnFish();

    // Update & Draw Fish
    for (let i = fishes.length - 1; i >= 0; i--) {
        fishes[i].update();
        fishes[i].draw();
        if (fishes[i].isDead) fishes.splice(i, 1);
    }

    // Update & Draw Bullets
    for (let i = bullets.length - 1; i >= 0; i--) {
        bullets[i].update();
        bullets[i].draw();
        if (bullets[i].isDead) bullets.splice(i, 1);
    }

    // Update & Draw Particles
    for (let i = particles.length - 1; i >= 0; i--) {
        particles[i].update();
        particles[i].draw();
        if (particles[i].isDead) particles.splice(i, 1);
    }

    checkCollision();
    requestAnimationFrame(gameLoop);
}

// Bắt sự kiện click để bắn
canvas.addEventListener('mousedown', (e) => {
    const now = Date.now();
    if (now - lastShotTime < 200) return; // Tốc độ bắn tối đa 5 viên/s

    const price = window.currentBulletPrice || 500;
    
    // Kiểm tra xem có nhắm trúng con cá nào ngay lúc click không (Aiming)
    let targetedFish = null;
    for (const fish of fishes) {
        const dist = Math.hypot(e.clientX - fish.x, e.clientY - fish.y);
        if (dist < fish.size) {
            targetedFish = fish;
            break;
        }
    }

    // Gọi API duy nhất: Trừ tiền đạn + Roll kết quả bắt cá luôn (Security fix)
    $.post('../api_banharc.php', { 
        action: 'shoot', 
        bullet_price: price,
        fish_type: targetedFish ? targetedFish.typeKey : ''
    }, function(res) {
        if (res.success) {
            const bullet = new Bullet(canvas.width / 2, canvas.height - 50, e.clientX, e.clientY, price);
            
            // Lưu kết quả server đã trả về vào viên đạn
            if (res.caught) {
                bullet.serverCaught = true;
                bullet.reward = res.reward;
                bullet.fishName = res.fish_name;
                bullet.targetFishId = targetedFish ? targetedFish.id : null; // Giả định có ID
            }
            
            bullets.push(bullet);
            lastShotTime = now;
            $('#userBalance').text(Number(res.new_balance).toLocaleString());
        } else {
            Swal.fire({ title: 'Lỗi', text: res.message, icon: 'error', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
        }
    }, 'json');
});

// Cập nhật lại checkCollision để dùng kết quả từ Server
function checkCollision() {
    bullets.forEach(bullet => {
        fishes.forEach(fish => {
            const dist = Math.hypot(bullet.x - fish.x, bullet.y - fish.y);
            if (dist < fish.size / 2 + bullet.radius) {
                bullet.isDead = true;
                
                // Nếu viên đạn này đã được Server xác nhận là "trúng" cá này
                if (bullet.serverCaught && fish.typeKey === targetedFishType(bullet)) {
                    showScorePopup(fish.x, fish.y, bullet.reward);
                    fish.isDead = true;
                    for(let i=0; i<20; i++) particles.push(new Particle(fish.x, fish.y, fish.color));
                } else {
                    // Trúng nhưng không chết (hoặc server roll trượt)
                    for(let i=0; i<5; i++) particles.push(new Particle(bullet.x, bullet.y, '#fff'));
                }
            }
        });
    });
}

function targetedFishType(bullet) {
    // Logic phụ trợ để map lại loại cá
    return bullet.fishName ? Object.keys(FISH_TYPES).find(k => FISH_TYPES[k].name === bullet.fishName) : null;
}

// Khởi chạy
gameLoop();

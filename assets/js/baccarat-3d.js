/**
 * Baccarat 3D Engine
 * Handles 3D table, cards, and animations
 */

class Baccarat3D {
    constructor() {
        this.scene = new THREE.Scene();
        this.camera = new THREE.PerspectiveCamera(45, window.innerWidth / window.innerHeight, 0.1, 1000);
        this.renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        this.cards = [];
        
        this.init();
    }

    init() {
        this.renderer.setSize(window.innerWidth, window.innerHeight);
        this.renderer.setPixelRatio(window.devicePixelRatio);
        this.renderer.shadowMap.enabled = true;
        document.getElementById('threejs-canvas').appendChild(this.renderer.domElement);

        // Camera positioning - Top down angled view
        this.camera.position.set(0, 15, 10);
        this.camera.lookAt(0, 0, -2);

        // Lighting
        const ambientLight = new THREE.AmbientLight(0xffffff, 0.4);
        this.scene.add(ambientLight);

        const spotLight = new THREE.SpotLight(0xffffff, 1);
        spotLight.position.set(0, 20, 10);
        spotLight.castShadow = true;
        this.scene.add(spotLight);

        this.createTable();
        this.animate();

        window.addEventListener('resize', () => this.onWindowResize());
    }

    createTable() {
        // Table Top (Oval shape simplified as a rounded box)
        const geometry = new THREE.BoxGeometry(20, 0.5, 12);
        const material = new THREE.MeshStandardMaterial({ 
            color: 0x0a3d2e,
            roughness: 0.8,
            metalness: 0.2
        });
        const tableTop = new THREE.Mesh(geometry, material);
        tableTop.position.y = -0.25;
        tableTop.receiveShadow = true;
        this.scene.add(tableTop);

        // Table Rim (Gold/Wood)
        const rimGeometry = new THREE.BoxGeometry(20.5, 0.8, 12.5);
        const rimMaterial = new THREE.MeshStandardMaterial({ color: 0x221100 });
        const tableRim = new THREE.Mesh(rimGeometry, rimMaterial);
        tableRim.position.y = -0.5;
        this.scene.add(tableRim);
    }

    /**
     * SIÊU PHẨM: Tự vẽ mặt quân bài bằng Canvas (Không cần ảnh, không bao giờ lỗi)
     */
    createCardFace(suit, value) {
        const canvas = document.createElement('canvas');
        canvas.width = 256;
        canvas.height = 384;
        const ctx = canvas.getContext('2d');

        // 1. Nền trắng với Gradient nhẹ cho sang trọng
        const grad = ctx.createLinearGradient(0, 0, 0, canvas.height);
        grad.addColorStop(0, '#ffffff');
        grad.addColorStop(1, '#f0f0f0');
        ctx.fillStyle = grad;
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        // 2. Viền bài
        ctx.strokeStyle = '#d4af37'; // Màu vàng Gold
        ctx.lineWidth = 8;
        ctx.strokeRect(4, 4, canvas.width - 8, canvas.height - 8);

        // 3. Xác định màu sắc và biểu tượng
        const isRed = (suit === 'hearts' || suit === 'diamonds');
        const color = isRed ? '#d63031' : '#2d3436';
        const icons = { 'hearts': '♥', 'diamonds': '♦', 'clubs': '♣', 'spades': '♠' };
        const icon = icons[suit];
        const valTxt = value === 1 ? 'A' : (value === 11 ? 'J' : (value === 12 ? 'Q' : (value === 13 ? 'K' : value)));

        // 4. Vẽ số ở góc
        ctx.fillStyle = color;
        ctx.font = 'bold 50px Arial';
        ctx.fillText(valTxt, 25, 60);
        ctx.font = '40px Arial';
        ctx.fillText(icon, 25, 100);

        // Vẽ đối xứng ở góc dưới
        ctx.save();
        ctx.translate(canvas.width, canvas.height);
        ctx.rotate(Math.PI);
        ctx.font = 'bold 50px Arial';
        ctx.fillText(valTxt, 25, 60);
        ctx.font = '40px Arial';
        ctx.fillText(icon, 25, 100);
        ctx.restore();

        // 5. Vẽ biểu tượng lớn ở giữa
        ctx.font = '160px Arial';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(icon, canvas.width / 2, canvas.height / 2);

        return new THREE.CanvasTexture(canvas);
    }

    createCardMesh(suit, value) {
        const geometry = new THREE.BoxGeometry(1, 0.02, 1.4);
        const faceTexture = this.createCardFace(suit, value);
        faceTexture.needsUpdate = true;
        
        // Mặt sau màu Royale Blue với họa tiết kim cương
        const backCanvas = document.createElement('canvas');
        backCanvas.width = 256;
        backCanvas.height = 384;
        const bCtx = backCanvas.getContext('2d');
        bCtx.fillStyle = '#0a3d62';
        bCtx.fillRect(0, 0, 256, 384);
        bCtx.strokeStyle = '#d4af37';
        bCtx.lineWidth = 5;
        bCtx.strokeRect(10, 10, 236, 364);
        // Vẽ chữ R (Royale) ở giữa
        bCtx.fillStyle = '#d4af37';
        bCtx.font = 'bold 120px serif';
        bCtx.textAlign = 'center';
        bCtx.textBaseline = 'middle';
        bCtx.fillText('R', 128, 192);
        const backTexture = new THREE.CanvasTexture(backCanvas);
        backTexture.needsUpdate = true;

        const materials = [
            new THREE.MeshBasicMaterial({ color: 0xffffff }), // 0: Cạnh phải
            new THREE.MeshBasicMaterial({ color: 0xffffff }), // 1: Cạnh trái
            new THREE.MeshBasicMaterial({ map: faceTexture }), // 2: MẶT TRÊN (Mặt bài)
            new THREE.MeshBasicMaterial({ map: backTexture }), // 3: MẶT DƯỚI (Lưng bài)
            new THREE.MeshBasicMaterial({ color: 0xffffff }), // 4: Cạnh trước
            new THREE.MeshBasicMaterial({ color: 0xffffff })  // 5: Cạnh sau
        ];

        const card = new THREE.Mesh(geometry, materials);
        return card;
    }

    /**
     * Deal animation
     */
    dealCard(side, index, value, suit, isThird = false) {
        const card = this.createCardMesh(suit, value);
        card.position.set(8, 2, -5); // Start at "Shoe" position
        card.rotation.x = Math.PI; // Face down
        this.scene.add(card);
        this.cards.push(card);

        const targetX = (side === 'player' ? -3 : 3) + (isThird ? (side === 'player' ? -1.2 : 1.2) : (index * 1.1 - 0.5));
        const targetZ = -2;
        const targetRotationY = isThird ? Math.PI / 2 : 0;

        gsap.to(card.position, {
            x: targetX,
            y: 0.1,
            z: targetZ,
            duration: 0.6,
            ease: "power2.out",
            delay: (isThird ? 0 : index * 0.4)
        });

        // Flip animation
        gsap.to(card.rotation, {
            x: 0,
            y: targetRotationY,
            duration: 0.5,
            delay: (isThird ? 0.6 : index * 0.4 + 0.6),
            ease: "back.out(1.7)"
        });

        return card;
    }

    clearCards() {
        this.cards.forEach(card => {
            gsap.to(card.position, {
                y: 5,
                opacity: 0,
                duration: 0.5,
                onComplete: () => this.scene.remove(card)
            });
        });
        this.cards = [];
    }

    onWindowResize() {
        this.camera.aspect = window.innerWidth / window.innerHeight;
        this.camera.updateProjectionMatrix();
        this.renderer.setSize(window.innerWidth, window.innerHeight);
    }

    animate() {
        requestAnimationFrame(() => this.animate());
        this.renderer.render(this.scene, this.camera);
    }
}

const baccarat3D = new Baccarat3D();

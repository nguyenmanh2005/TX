class Blackjack3D {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        this.cards = [];
        this.init();
        this.animate();
    }

    init() {
        this.scene = new THREE.Scene();
        this.camera = new THREE.PerspectiveCamera(45, window.innerWidth / window.innerHeight, 0.1, 1000);
        this.camera.position.set(0, 12, 10); // Đưa camera lên cao và lùi ra sau một chút
        this.camera.lookAt(0, 0, -1); // Nhìn vào trung tâm bàn bài

        this.renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        this.renderer.setClearColor(0x000000, 0); // Đảm bảo nền WebGL trong suốt hoàn toàn
        this.renderer.setSize(window.innerWidth, window.innerHeight);
        this.renderer.setPixelRatio(window.devicePixelRatio);
        this.container.appendChild(this.renderer.domElement);

        // Lighting
        const ambientLight = new THREE.AmbientLight(0xffffff, 0.9);
        this.scene.add(ambientLight);
        const pointLight = new THREE.PointLight(0xffffff, 0.6);
        pointLight.position.set(0, 10, 5);
        this.scene.add(pointLight);

        window.addEventListener('resize', () => this.onWindowResize());
    }

    createCardFace(suit, value) {
        const canvas = document.createElement('canvas');
        canvas.width = 256;
        canvas.height = 384;
        const ctx = canvas.getContext('2d');

        const grad = ctx.createLinearGradient(0, 0, 0, canvas.height);
        grad.addColorStop(0, '#ffffff');
        grad.addColorStop(1, '#f0f0f0');
        ctx.fillStyle = grad;
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        ctx.strokeStyle = '#d4af37';
        ctx.lineWidth = 8;
        ctx.strokeRect(4, 4, canvas.width - 8, canvas.height - 8);

        const isRed = (suit === 'hearts' || suit === 'diamonds');
        const color = isRed ? '#d63031' : '#2d3436';
        const icons = { 'hearts': '♥', 'diamonds': '♦', 'clubs': '♣', 'spades': '♠' };
        const icon = icons[suit];
        const valTxt = value === 1 ? 'A' : (value === 11 ? 'J' : (value === 12 ? 'Q' : (value === 13 ? 'K' : value)));

        ctx.fillStyle = color;
        ctx.font = 'bold 50px Arial';
        ctx.fillText(valTxt, 25, 60);
        ctx.font = '40px Arial';
        ctx.fillText(icon, 25, 100);

        ctx.save();
        ctx.translate(canvas.width, canvas.height);
        ctx.rotate(Math.PI);
        ctx.font = 'bold 50px Arial';
        ctx.fillText(valTxt, 25, 60);
        ctx.font = '40px Arial';
        ctx.fillText(icon, 25, 100);
        ctx.restore();

        ctx.font = '160px Arial';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(icon, canvas.width / 2, canvas.height / 2);

        const texture = new THREE.CanvasTexture(canvas);
        texture.needsUpdate = true;
        return texture;
    }

    createCardMesh(suit, value) {
        const geometry = new THREE.BoxGeometry(1, 0.02, 1.4);
        const faceTexture = this.createCardFace(suit, value);
        
        const backCanvas = document.createElement('canvas');
        backCanvas.width = 256;
        backCanvas.height = 384;
        const bCtx = backCanvas.getContext('2d');
        bCtx.fillStyle = '#0a3d62';
        bCtx.fillRect(0, 0, 256, 384);
        bCtx.strokeStyle = '#d4af37';
        bCtx.lineWidth = 5;
        bCtx.strokeRect(10, 10, 236, 364);
        bCtx.fillStyle = '#d4af37';
        bCtx.font = 'bold 120px serif';
        bCtx.textAlign = 'center';
        bCtx.textBaseline = 'middle';
        bCtx.fillText('R', 128, 192);
        const backTexture = new THREE.CanvasTexture(backCanvas);
        backTexture.needsUpdate = true;

        const materials = [
            new THREE.MeshBasicMaterial({ color: 0xffffff }),
            new THREE.MeshBasicMaterial({ color: 0xffffff }),
            new THREE.MeshBasicMaterial({ map: faceTexture }), // Top
            new THREE.MeshBasicMaterial({ map: backTexture }), // Bottom
            new THREE.MeshBasicMaterial({ color: 0xffffff }),
            new THREE.MeshBasicMaterial({ color: 0xffffff })
        ];

        return new THREE.Mesh(geometry, materials);
    }

    dealCard(side, index, value, suit, faceUp = true) {
        const card = this.createCardMesh(suit, value);
        card.position.set(8, 2, -5); 
        card.rotation.x = Math.PI; 
        this.scene.add(card);
        this.cards.push(card);

        // Position logic: Player (side 0), King (side 1)
        const targetX = (index * 1.2) - 1.5;
        const targetZ = (side === 'player' ? 0.5 : -3.5);

        gsap.to(card.position, {
            x: targetX,
            y: 0.05 + (index * 0.01),
            z: targetZ,
            duration: 0.6,
            ease: "power2.out"
        });

        if (faceUp) {
            gsap.to(card.rotation, {
                x: 0,
                duration: 0.5,
                delay: 0.4,
                ease: "back.out(1.7)"
            });
        }

        return card;
    }

    flipCard(card) {
        gsap.to(card.rotation, {
            x: 0,
            duration: 0.5,
            ease: "back.out(1.7)"
        });
    }

    clearCards() {
        this.cards.forEach(card => {
            gsap.to(card.position, {
                y: 5,
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

const blackjack3D = new Blackjack3D('blackjack-canvas');

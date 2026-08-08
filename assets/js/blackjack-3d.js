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
        let valStr = value;
        if (value === 1) valStr = 'A';
        else if (value === 11) valStr = 'J';
        else if (value === 12) valStr = 'Q';
        else if (value === 13) valStr = 'K';
        else if (value < 10) valStr = '0' + value;
        
        const url = `../games/img/anh-bai/PNG/Cards (large)/card_${suit}_${valStr}.png`;
        const texture = new THREE.TextureLoader().load(url);
        
        // Ensure proper color space (optional, helps with sRGB)
        texture.colorSpace = THREE.SRGBColorSpace || THREE.sRGBEncoding;
        
        return texture;
    }

    createCardMesh(suit, value) {
        const geometry = new THREE.BoxGeometry(1, 0.02, 1.4);
        const faceTexture = this.createCardFace(suit, value);
        
        const backUrl = '../games/img/anh-bai/PNG/Cards (large)/card_back.png';
        const backTexture = new THREE.TextureLoader().load(backUrl);
        backTexture.colorSpace = THREE.SRGBColorSpace || THREE.sRGBEncoding;

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

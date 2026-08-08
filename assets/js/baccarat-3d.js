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

    createCardFace(suit, value) {
        let valStr = value;
        if (value === 1) valStr = 'A';
        else if (value === 11) valStr = 'J';
        else if (value === 12) valStr = 'Q';
        else if (value === 13) valStr = 'K';
        else if (value < 10) valStr = '0' + value;
        
        const url = `../games/img/anh-bai/PNG/Cards (large)/card_${suit}_${valStr}.png`;
        const texture = new THREE.TextureLoader().load(url);
        texture.colorSpace = THREE.SRGBColorSpace || THREE.sRGBEncoding;
        return texture;
    }

    createCardMesh(suit, value) {
        const geometry = new THREE.BoxGeometry(1, 0.02, 1.4);
        const faceTexture = this.createCardFace(suit, value);
        faceTexture.needsUpdate = true;
        
        const backUrl = '../games/img/anh-bai/PNG/Cards (large)/card_back.png';
        const backTexture = new THREE.TextureLoader().load(backUrl);
        backTexture.colorSpace = THREE.SRGBColorSpace || THREE.sRGBEncoding;

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

/**
 * Crash Premium 3D Engine — Visible & Synchronized
 * High-visibility ship with diagonal flight path
 */

class Crash3D {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        if (!this.container) return;

        this.scene = new THREE.Scene();
        this.camera = new THREE.PerspectiveCamera(45, this.container.offsetWidth / this.container.offsetHeight, 0.1, 2000);
        
        this.renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        this.renderer.setSize(this.container.offsetWidth, this.container.offsetHeight);
        this.renderer.setPixelRatio(window.devicePixelRatio);
        this.container.appendChild(this.renderer.domElement);

        this.bloomPass = new THREE.UnrealBloomPass(new THREE.Vector2(this.container.offsetWidth, this.container.offsetHeight), 1.5, 0.4, 0.85);
        this.bloomPass.threshold = 0.1;
        this.bloomPass.strength = 1.2;
        
        this.composer = new THREE.EffectComposer(this.renderer);
        this.composer.addPass(new THREE.RenderPass(this.scene, this.camera));
        this.composer.addPass(this.bloomPass);

        this.speed = 0;
        this.targetSpeed = 0.05;
        this.isCrashed = false;
        
        this.init();
        this.animate();
        window.addEventListener('resize', () => this.onWindowResize());
    }

    init() {
        this.createNebula();
        this.createStars();
        this.createPlanets();
        this.createShip();
        
        this.scene.add(new THREE.AmbientLight(0xffffff, 0.8));
        const sun = new THREE.DirectionalLight(0xffffff, 1.5);
        sun.position.set(10, 10, 20);
        this.scene.add(sun);

        this.camera.position.set(0, 0, 30); // Lùi xa để nhìn rộng hơn
    }

    createNebula() {
        const geo = new THREE.SphereGeometry(1, 16, 16);
        this.nebulaGroup = new THREE.Group();
        const colors = [0x4a1080, 0x0a4a90, 0x5a1060];
        for (let i = 0; i < 15; i++) {
            const mat = new THREE.MeshBasicMaterial({ color: colors[i % 3], transparent: true, opacity: 0.05, blending: THREE.AdditiveBlending });
            const cloud = new THREE.Mesh(geo, mat);
            cloud.position.set((Math.random()-0.5)*150, (Math.random()-0.5)*150, -100 - Math.random()*100);
            cloud.scale.set(40 + Math.random()*50, 40 + Math.random()*50, 10);
            this.nebulaGroup.add(cloud);
        }
        this.scene.add(this.nebulaGroup);
    }

    createPlanets() {
        const planetGeo = new THREE.SphereGeometry(15, 32, 32);
        const planetMat = new THREE.MeshStandardMaterial({ color: 0x4a90e2, metalness: 0.5, roughness: 0.5 });
        this.planet = new THREE.Mesh(planetGeo, planetMat);
        this.planet.position.set(60, 40, -150);
        this.scene.add(this.planet);
    }

    createStars() {
        this.starLayers = [];
        const layerConfigs = [{ count: 2000, size: 0.1, color: 0xffffff, speed: 1 }, { count: 1000, size: 0.15, color: 0xaaaaff, speed: 2 }];
        layerConfigs.forEach(config => {
            const geo = new THREE.BufferGeometry();
            const pos = new Float32Array(config.count * 3);
            for(let i=0; i<config.count*3; i++) pos[i] = (Math.random()-0.5)*300;
            geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
            const points = new THREE.Points(geo, new THREE.PointsMaterial({color: config.color, size: config.size, transparent: true, opacity: 0.8}));
            this.scene.add(points);
            this.starLayers.push({ mesh: points, speed: config.speed });
        });
    }

    createShip() {
        const ship = new THREE.Group();
        
        // Body - Bright Silver
        const body = new THREE.Mesh(
            new THREE.CylinderGeometry(0.4, 0.3, 2.5, 12),
            new THREE.MeshStandardMaterial({ color: 0xffffff, metalness: 1, roughness: 0.1, emissive: 0x444444 })
        );
        body.rotation.x = Math.PI/2;
        ship.add(body);

        // Wings - Glowing Cyan
        const wing = new THREE.Mesh(
            new THREE.BoxGeometry(2.5, 0.1, 0.8),
            new THREE.MeshStandardMaterial({ color: 0x00f2fe, emissive: 0x00f2fe, emissiveIntensity: 2 })
        );
        wing.position.z = -0.5;
        ship.add(wing);

        // Engine - Fire
        const fireGeo = new THREE.ConeGeometry(0.4, 2, 16);
        const fireMat = new THREE.MeshBasicMaterial({ color: 0xff4757, transparent: true, opacity: 0.8, blending: THREE.AdditiveBlending });
        this.fire = new THREE.Mesh(fireGeo, fireMat);
        this.fire.position.z = -2;
        this.fire.rotation.x = -Math.PI/2;
        ship.add(this.fire);

        this.engineLight = new THREE.PointLight(0xff4757, 10, 10);
        this.engineLight.position.z = -2;
        ship.add(this.engineLight);

        this.rocket = ship;
        this.rocket.scale.set(1.2, 1.2, 1.2); // Tăng kích thước lên cho dễ nhìn
        this.scene.add(this.rocket);
    }

    setSpeed(multiplier) {
        if (this.isCrashed) return;
        this.targetSpeed = 0.05 + Math.min(2, (multiplier - 1) * 0.1);
        
        const progress = Math.min(1, (multiplier - 1) / 15);
        if (this.rocket) {
            // Tọa độ hiện tại
            const posX = -12 + progress * 24;
            const posY = -8 + Math.pow(progress, 1.2) * 16;
            const posZ = -progress * 40;

            // Tính toán điểm mục tiêu phía trước để "nhìn" theo
            const nextProgress = Math.min(1, progress + 0.01);
            const targetX = -12 + nextProgress * 24;
            const targetY = -8 + Math.pow(nextProgress, 1.2) * 16;
            const targetZ = -nextProgress * 40;

            this.rocket.position.set(posX, posY, posZ);
            this.rocket.lookAt(targetX, targetY, targetZ);
            
            // Thêm độ nghiêng cánh khi lượn chéo
            this.rocket.rotation.z += Math.PI / 8; // Nghiêng nhẹ sang bên
            
            if (this.fire) this.fire.scale.set(1.5 + progress, 1.5 + progress * 2, 1.5 + progress);
        }

        this.bloomPass.strength = 1.2 + progress * 3;
        
        if (this.rocket) {
            gsap.to(this.camera.position, {
                x: this.rocket.position.x * 0.7,
                y: this.rocket.position.y * 0.7,
                z: 30 + this.rocket.position.z * 0.3,
                duration: 0.8,
                ease: "power2.out"
            });
            this.camera.lookAt(this.rocket.position);
        }
    }

    getScreenPosition() {
        if (!this.rocket) return { x: 0, y: 0 };
        const vector = new THREE.Vector3();
        this.rocket.getWorldPosition(vector);
        vector.project(this.camera);
        const widthHalf = this.container.offsetWidth / 2;
        const heightHalf = this.container.offsetHeight / 2;
        return { x: (vector.x * widthHalf) + widthHalf, y: -(vector.y * heightHalf) + heightHalf };
    }

    onStart() {
        this.isCrashed = false;
        this.rocket.visible = true;
        this.rocket.scale.set(1.5, 1.5, 1.5);
        this.rocket.position.set(-12, -8, 0);
        this.targetSpeed = 0.05;
    }

    onCrash() {
        this.isCrashed = true;
        this.targetSpeed = 0;
        gsap.to(this.rocket.scale, { x: 0, y: 0, z: 0, duration: 0.1 });
    }

    onCashout() {
        gsap.to(this.rocket.position, { z: -100, x: 50, y: 30, duration: 1.5, ease: "power4.in" });
    }

    onWindowResize() {
        this.camera.aspect = this.container.offsetWidth / this.container.offsetHeight;
        this.camera.updateProjectionMatrix();
        this.renderer.setSize(this.container.offsetWidth, this.container.offsetHeight);
        this.composer.setSize(this.container.offsetWidth, this.container.offsetHeight);
    }

    animate() {
        requestAnimationFrame(() => this.animate());
        this.speed += (this.targetSpeed - this.speed) * 0.1;
        if (this.starLayers) {
            this.starLayers.forEach(layer => {
                const pos = layer.mesh.geometry.attributes.position.array;
                for (let i = 0; i < pos.length; i += 3) {
                    pos[i + 2] += this.speed * 20 * layer.speed;
                    if (pos[i + 2] > 100) pos[i + 2] = -150;
                }
                layer.mesh.geometry.attributes.position.needsUpdate = true;
            });
        }
        if (this.nebulaGroup) this.nebulaGroup.rotation.z += 0.0005;
        if (this.engineLight) this.engineLight.intensity = 5 + Math.sin(Date.now() * 0.02) * 5;
        this.composer.render();
    }
}
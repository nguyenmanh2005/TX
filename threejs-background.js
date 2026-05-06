/**
 * Three.js Background Script
 * File này tạo background động với particles và shapes dựa trên theme config
 * Sử dụng cho tất cả các trang (trừ login/auth)
 */

(function() {
    // Chờ DOM load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initThreeJSBackground);
    } else {
        initThreeJSBackground();
    }

    function initThreeJSBackground() {
        const canvas = document.getElementById('threejs-background');
        if (!canvas) {
            console.warn('Canvas #threejs-background not found');
            return;
        }

        // Đảm bảo canvas phủ toàn màn hình và nằm dưới cùng
        canvas.style.position = 'fixed';
        canvas.style.top = '0';
        canvas.style.left = '0';
        canvas.style.width = '100%';
        canvas.style.height = '100%';
        canvas.style.zIndex = '-1';
        canvas.style.pointerEvents = 'none';

        // Kiểm tra Three.js đã load chưa
        if (typeof THREE === 'undefined') {
            console.error('Three.js library not loaded');
            return;
        }

        // Tối ưu: Nếu có canvas, làm body transparent để thấy background phía sau
        // và chuyển background của body sang canvas
        const bodyBg = window.getComputedStyle(document.body).backgroundImage;
        if (bodyBg && bodyBg !== 'none') {
            canvas.style.backgroundImage = bodyBg;
            canvas.style.backgroundAttachment = 'fixed';
            canvas.style.backgroundSize = 'cover';
            document.body.style.background = 'transparent';
        }

        // Lấy theme config từ global variable (được set từ PHP)
        if (typeof window.themeConfig === 'undefined') {
            console.warn('Theme config not found, using default');
            window.themeConfig = {
                particleCount: 1000,
                particleSize: 0.05,
                particleColor: '#ffffff',
                particleOpacity: 0.6,
                shapeCount: 15,
                shapeColors: ['#667eea', '#764ba2', '#4facfe', '#00f2fe'],
                shapeOpacity: 0.3,
                bgGradient: ['#667eea', '#764ba2', '#4facfe']
            };
        }

        const themeConfig = window.themeConfig;
        let scene, particlesMaterial, shapes, particlesMesh, camera, renderer;

        // Initialize Three.js với tối ưu performance
        scene = new THREE.Scene();
        camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
        renderer = new THREE.WebGLRenderer({
            canvas: canvas,
            alpha: true,
            antialias: false,
            powerPreference: "low-power"
        });
        renderer.setSize(window.innerWidth, window.innerHeight);
        // Pixel ratio tối đa 1 để tiết kiệm GPU
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1));

        const particlesGeometry = new THREE.BufferGeometry();
        // Giới hạn 400 particles để giảm lag
        const particlesCount = Math.min(themeConfig.particleCount || 1000, 400);
        const posArray = new Float32Array(particlesCount * 3);

        for (let i = 0; i < particlesCount * 3; i++) {
            posArray[i] = (Math.random() - 0.5) * 20;
        }

        particlesGeometry.setAttribute('position', new THREE.BufferAttribute(posArray, 3));

        // Convert hex color to number
        const particleColorNum = parseInt((themeConfig.particleColor || '#ffffff').replace('#', ''), 16);

        particlesMaterial = new THREE.PointsMaterial({
            size: themeConfig.particleSize || 0.05,
            color: particleColorNum,
            transparent: true,
            opacity: themeConfig.particleOpacity || 0.6
        });

        particlesMesh = new THREE.Points(particlesGeometry, particlesMaterial);
        scene.add(particlesMesh);

        // Tạo các hình dạng 3D với giới hạn để tránh lag
        shapes = [];
        const shapeColors = themeConfig.shapeColors || ['#667eea', '#764ba2', '#4facfe', '#00f2fe'];
        const colors = shapeColors.map(c => parseInt(c.replace('#', ''), 16));
        // Giới hạn 6 shapes để giảm GPU load
        const shapeCount = Math.min(themeConfig.shapeCount || 15, 6);

        for (let i = 0; i < shapeCount; i++) {
            const geometry = new THREE.IcosahedronGeometry(Math.random() * 0.5 + 0.3, 0);
            const material = new THREE.MeshStandardMaterial({
                color: colors[Math.floor(Math.random() * colors.length)],
                transparent: true,
                opacity: themeConfig.shapeOpacity || 0.3,
                wireframe: Math.random() > 0.5
            });
            const mesh = new THREE.Mesh(geometry, material);
            mesh.position.set(
                (Math.random() - 0.5) * 15,
                (Math.random() - 0.5) * 15,
                (Math.random() - 0.5) * 15
            );
            mesh.rotation.set(
                Math.random() * Math.PI,
                Math.random() * Math.PI,
                Math.random() * Math.PI
            );
            shapes.push(mesh);
            scene.add(mesh);
        }

        // Ánh sáng
        const ambientLight = new THREE.AmbientLight(0xffffff, 0.5);
        scene.add(ambientLight);

        const pointLight = new THREE.PointLight(0xffffff, 1);
        pointLight.position.set(5, 5, 5);
        scene.add(pointLight);

        camera.position.z = 5;

        // Animation loop – giới hạn 30 FPS, dừng khi tab ẩn
        let lastTime = 0;
        const frameInterval = 1000 / 30; // 30 FPS
        let rafId = null;
        let paused = false;

        function animate(currentTime) {
            rafId = requestAnimationFrame(animate);
            if (paused) return;

            const deltaTime = currentTime - lastTime;
            if (deltaTime < frameInterval) return;
            lastTime = currentTime - (deltaTime % frameInterval);

            if (particlesMesh) {
                particlesMesh.rotation.y += 0.0006;
                particlesMesh.rotation.x += 0.0003;
            }
            if (shapes) {
                shapes.forEach((shape, index) => {
                    if (shape) {
                        shape.rotation.x += 0.005 * ((index % 3) + 1);
                        shape.rotation.y += 0.005 * ((index % 2) + 1);
                    }
                });
            }
            if (renderer && scene && camera) {
                renderer.render(scene, camera);
            }
        }

        // Pause khi tab bị ẩn – tiết kiệm CPU/GPU hoàn toàn
        document.addEventListener('visibilitychange', () => {
            paused = document.hidden;
        });

        // Debounce resize để không gọi liên tục
        let resizeTimer = null;
        function handleResize() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                if (!camera || !renderer) return;
                camera.aspect = window.innerWidth / window.innerHeight;
                camera.updateProjectionMatrix();
                renderer.setSize(window.innerWidth, window.innerHeight);
            }, 200);
        }
        window.addEventListener('resize', handleResize);

        animate();

        // Export để có thể update từ bên ngoài (nếu cần)
        window.threejsBackground = {
            scene: scene,
            particlesMaterial: particlesMaterial,
            shapes: shapes,
            updateTheme: function(newConfig) {
                if (!newConfig) return;
                
                // Update particles
                if (particlesMaterial && newConfig.particleColor) {
                    const colorNum = parseInt(newConfig.particleColor.replace('#', ''), 16);
                    particlesMaterial.color.setHex(colorNum);
                }
                if (particlesMaterial && newConfig.particleOpacity !== undefined) {
                    particlesMaterial.opacity = newConfig.particleOpacity;
                }
                if (particlesMaterial && newConfig.particleSize !== undefined) {
                    particlesMaterial.size = newConfig.particleSize;
                }

                // Update shapes
                if (shapes && newConfig.shapeColors) {
                    const newColors = newConfig.shapeColors.map(c => parseInt(c.replace('#', ''), 16));
                    shapes.forEach((shape, index) => {
                        if (shape.material) {
                            shape.material.color.setHex(newColors[index % newColors.length]);
                        }
                        if (shape.material && newConfig.shapeOpacity !== undefined) {
                            shape.material.opacity = newConfig.shapeOpacity;
                        }
                    });
                }
            }
        };
    }

    // Tự động chèn nút Trang chủ nếu không phải trang index.php
    function injectHomeButton() {
        const isIndex = window.location.pathname.endsWith('index.php') || 
                      window.location.pathname.endsWith('/') || 
                      window.location.pathname === '';
        
        if (isIndex) return;

        // Tránh chèn trùng
        if (document.querySelector('.home-fab')) return;

        const isInGames = window.location.pathname.includes('/games/');
        const homeButton = document.createElement('a');
        homeButton.href = isInGames ? '../index.php' : 'index.php';
        homeButton.className = 'home-fab fade-in';
        homeButton.innerHTML = '<span class="home-fab-icon">🏠</span> <span class="home-fab-text">Trang chủ</span>';
        
        // Đảm bảo CSS cho nút đã sẵn sàng
        document.body.appendChild(homeButton);
    }

    // Chạy inject sau khi DOM đã sẵn sàng
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectHomeButton);
    } else {
        injectHomeButton();
    }
})();


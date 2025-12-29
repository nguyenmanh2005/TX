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

        // Kiểm tra Three.js đã load chưa
        if (typeof THREE === 'undefined') {
            console.error('Three.js library not loaded');
            return;
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
            antialias: false, // Tắt antialias để tăng performance
            powerPreference: "high-performance" // Ưu tiên performance
        });
        renderer.setSize(window.innerWidth, window.innerHeight);
        // Giảm pixel ratio để tăng performance (tối đa 1.5 để tránh lag)
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5));

        // Tạo particles với giới hạn để tránh lag
        const particlesGeometry = new THREE.BufferGeometry();
        // Giới hạn số particles tối đa để tránh lag (tối đa 800 particles)
        const particlesCount = Math.min(themeConfig.particleCount || 1000, 800);
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
        // Giới hạn số shapes tối đa (tối đa 10 shapes)
        const shapeCount = Math.min(themeConfig.shapeCount || 15, 10);

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

        // Animation loop với tối ưu performance
        let lastTime = 0;
        const targetFPS = 30; // Giảm FPS xuống 30 để tiết kiệm tài nguyên
        const frameInterval = 1000 / targetFPS;
        
        function animate(currentTime) {
            requestAnimationFrame(animate);
            
            // Throttle animation để giảm lag
            const deltaTime = currentTime - lastTime;
            if (deltaTime < frameInterval) {
                return;
            }
            lastTime = currentTime - (deltaTime % frameInterval);

            if (particlesMesh) {
                particlesMesh.rotation.y += 0.001;
                particlesMesh.rotation.x += 0.0005;
            }

            if (shapes) {
                // Giảm số lượng shapes được update mỗi frame
                const updateCount = Math.min(shapes.length, 5); // Chỉ update 5 shapes mỗi frame
                for (let i = 0; i < updateCount; i++) {
                    const index = (Math.floor(currentTime / 100) + i) % shapes.length;
                    const shape = shapes[index];
                    if (shape) {
                        shape.rotation.x += 0.01 * ((index % 3) + 1);
                        shape.rotation.y += 0.01 * ((index % 2) + 1);
                        shape.position.y += Math.sin(currentTime * 0.001 + index) * 0.001;
                    }
                }
            }

            if (renderer && scene && camera) {
                renderer.render(scene, camera);
            }
        }

        // Resize handler
        function handleResize() {
            if (!camera || !renderer) return;
            
            camera.aspect = window.innerWidth / window.innerHeight;
            camera.updateProjectionMatrix();
            renderer.setSize(window.innerWidth, window.innerHeight);
        }

        window.addEventListener('resize', handleResize);

        // Start animation
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
})();


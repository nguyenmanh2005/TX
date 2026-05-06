<?php
/**
 * Template để thêm Three.js background vào các trang
 * Copy code này vào các file PHP cần thiết
 */

/*
 * BƯỚC 1: Thêm Three.js library vào <head>
 * Tìm: <head>
 * Thêm sau: <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
 */

/*
 * BƯỚC 2: Thêm canvas vào <body>
 * Tìm: <body>
 * Thêm sau: <canvas id="threejs-background"></canvas>
 */

/*
 * BƯỚC 3: Thêm CSS vào <style>
 * Thêm vào cuối style tag:
 */
?>
<style>
    /* Three.js canvas background */
    #threejs-background {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: -1;
        pointer-events: none;
    }
    
    body {
        position: relative; /* Thêm nếu chưa có */
    }
</style>

<?php
/*
 * BƯỚC 4: Thêm script khởi tạo trước </body>
 * Thêm trước </body>:
 */
?>
<script>
    // Initialize Three.js Background
    (function() {
        window.themeConfig = {
            particleCount: <?= $particleCount ?>,
            particleSize: <?= $particleSize ?>,
            particleColor: '<?= $particleColor ?>',
            particleOpacity: <?= $particleOpacity ?>,
            shapeCount: <?= $shapeCount ?>,
            shapeColors: <?= json_encode($shapeColors) ?>,
            shapeOpacity: <?= $shapeOpacity ?>,
            bgGradient: <?= json_encode($bgGradient) ?>
        };
        const script = document.createElement('script');
        script.src = 'threejs-background.js';
        script.onload = function() { console.log('Three.js background loaded'); };
        document.head.appendChild(script);
    })();
</script>


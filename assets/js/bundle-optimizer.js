/**
 * Bundle Optimizer - Tối ưu bundle size và loading
 */

class BundleOptimizer {
    constructor() {
        this.loadedModules = new Set();
        this.init();
    }

    init() {
        this.setupDynamicImports();
        this.setupCodeSplitting();
        this.setupTreeShaking();
    }

    setupDynamicImports() {
        // Load modules on demand
        this.loadModule = async (moduleName) => {
            if (this.loadedModules.has(moduleName)) {
                return this.loadedModules.get(moduleName);
            }
            
            try {
                const module = await import(`./modules/${moduleName}.js`);
                this.loadedModules.set(moduleName, module);
                return module;
            } catch (error) {
                console.error(`Error loading module ${moduleName}:`, error);
                return null;
            }
        };
    }

    setupCodeSplitting() {
        // Split code into chunks
        this.chunks = {
            critical: ['game-ui-enhanced.js', 'game-animations-enhanced.js'],
            game: ['game-roulette.js', 'game-slot.js', 'game-dice.js'],
            utils: ['performance-optimizer.js', 'database-optimizer.js']
        };
        
        // Load critical chunks first
        this.loadChunk('critical').then(() => {
            // Load other chunks after critical
            this.scheduleChunkLoad('game');
            this.scheduleChunkLoad('utils');
        });
    }

    async loadChunk(chunkName) {
        const scripts = this.chunks[chunkName] || [];
        const promises = scripts.map(script => this.loadScript(script));
        return Promise.all(promises);
    }

    loadScript(src) {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = `assets/js/${src}`;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    scheduleChunkLoad(chunkName) {
        if ('requestIdleCallback' in window) {
            requestIdleCallback(() => {
                this.loadChunk(chunkName);
            });
        } else {
            setTimeout(() => {
                this.loadChunk(chunkName);
            }, 1000);
        }
    }

    setupTreeShaking() {
        // Remove unused code (this is typically done at build time)
        // But we can optimize by only loading what's needed
        this.requiredModules = new Set();
        
        // Track which modules are actually used
        this.trackModuleUsage = (moduleName) => {
            this.requiredModules.add(moduleName);
        };
    }

    // Minify inline scripts
    minifyInlineScripts() {
        const scripts = document.querySelectorAll('script:not([src])');
        scripts.forEach(script => {
            // Remove comments and whitespace
            const minified = script.textContent
                .replace(/\/\*[\s\S]*?\*\//g, '')
                .replace(/\/\/.*/g, '')
                .replace(/\s+/g, ' ')
                .trim();
            script.textContent = minified;
        });
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    window.bundleOptimizer = new BundleOptimizer();
});


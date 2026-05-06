/**
 * Sound Effects - Thêm âm thanh cho games
 */

class SoundEffects {
    constructor() {
        this.sounds = {};
        this.enabled = localStorage.getItem('soundEnabled') !== 'false';
        this.volume = parseFloat(localStorage.getItem('soundVolume')) || 0.5;
        this.init();
    }

    init() {
        this.createAudioContext();
        this.setupSoundToggle();
        this.loadSounds();
    }

    createAudioContext() {
        try {
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
        } catch (e) {
            console.warn('Web Audio API not supported');
        }
    }

    setupSoundToggle() {
        // Create sound control UI
        const soundControl = document.createElement('div');
        soundControl.className = 'sound-control';
        soundControl.innerHTML = `
            <button id="soundToggle" class="sound-toggle-btn">
                ${this.enabled ? '🔊' : '🔇'}
            </button>
            <input type="range" id="soundVolume" min="0" max="1" step="0.1" value="${this.volume}">
        `;
        document.body.appendChild(soundControl);

        const toggleBtn = document.getElementById('soundToggle');
        const volumeSlider = document.getElementById('soundVolume');

        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                this.enabled = !this.enabled;
                localStorage.setItem('soundEnabled', this.enabled);
                toggleBtn.textContent = this.enabled ? '🔊' : '🔇';
            });
        }

        if (volumeSlider) {
            volumeSlider.addEventListener('input', (e) => {
                this.volume = parseFloat(e.target.value);
                localStorage.setItem('soundVolume', this.volume);
            });
        }
    }

    loadSounds() {
        // Generate sounds using Web Audio API
        this.sounds = {
            win: () => this.playTone(440, 0.3, 'sine'),
            lose: () => this.playTone(220, 0.3, 'sawtooth'),
            click: () => this.playTone(800, 0.1, 'square'),
            spin: () => this.playTone(300, 0.5, 'sine'),
            coin: () => this.playTone(600, 0.2, 'sine'),
            card: () => this.playTone(500, 0.15, 'sine'),
            crash: () => this.playTone(150, 0.8, 'sawtooth')
        };
    }

    playTone(frequency, duration, type = 'sine') {
        if (!this.enabled || !this.audioContext) return;

        const oscillator = this.audioContext.createOscillator();
        const gainNode = this.audioContext.createGain();

        oscillator.connect(gainNode);
        gainNode.connect(this.audioContext.destination);

        oscillator.frequency.value = frequency;
        oscillator.type = type;

        gainNode.gain.setValueAtTime(0, this.audioContext.currentTime);
        gainNode.gain.linearRampToValueAtTime(this.volume, this.audioContext.currentTime + 0.01);
        gainNode.gain.exponentialRampToValueAtTime(0.01, this.audioContext.currentTime + duration);

        oscillator.start(this.audioContext.currentTime);
        oscillator.stop(this.audioContext.currentTime + duration);
    }

    play(soundName) {
        if (this.sounds[soundName]) {
            this.sounds[soundName]();
        }
    }

    // Predefined sound effects
    playWin() {
        this.play('win');
    }

    playLose() {
        this.play('lose');
    }

    playClick() {
        this.play('click');
    }

    playSpin() {
        this.play('spin');
    }

    playCoin() {
        this.play('coin');
    }

    playCard() {
        this.play('card');
    }

    playCrash() {
        this.play('crash');
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    window.soundEffects = new SoundEffects();
    
    // Add click sounds to buttons
    document.querySelectorAll('button, .btn, .game-link').forEach(btn => {
        btn.addEventListener('click', () => {
            if (window.soundEffects) {
                window.soundEffects.playClick();
            }
        });
    });
});


/**
 * SoundManager - Quản lý âm thanh toàn diện cho Casino Royale
 */
const SoundManager = {
    isEnabled: true,
    volume: 0.5,
    bgMusic: null,

    sounds: {
        hover: 'https://assets.mixkit.co/active_storage/sfx/2571/2571-preview.mp3',
        click: 'https://assets.mixkit.co/active_storage/sfx/2568/2568-preview.mp3',
        tab: 'https://assets.mixkit.co/active_storage/sfx/2572/2572-preview.mp3',
        win: 'https://assets.mixkit.co/active_storage/sfx/2019/2019-preview.mp3',
        loss: 'https://assets.mixkit.co/active_storage/sfx/2573/2573-preview.mp3',
        dice: 'https://assets.mixkit.co/active_storage/sfx/2006/2006-preview.mp3',
        card: 'https://assets.mixkit.co/active_storage/sfx/1126/1126-preview.mp3',
        chip: 'https://assets.mixkit.co/active_storage/sfx/2569/2569-preview.mp3',
        lobbyBg: 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-15.mp3'
    },

    init() {
        // Khôi phục cài đặt từ localStorage
        const savedVolume = localStorage.getItem('casino_volume');
        if (savedVolume !== null) this.volume = parseFloat(savedVolume);
        
        const savedEnabled = localStorage.getItem('casino_sound_enabled');
        if (savedEnabled !== null) this.isEnabled = savedEnabled === 'true';

        console.log('🔊 SoundManager initialized. Volume:', this.volume);
        this.createUI();
    },

    createUI() {
        const div = document.createElement('div');
        div.className = 'sound-control';
        div.innerHTML = `
            <button class="sound-toggle-btn" id="soundToggle">
                <i class="fas ${this.isEnabled ? 'fa-volume-up' : 'fa-volume-mute'}"></i>
            </button>
            <input type="range" class="volume-slider" id="volumeSlider" min="0" max="1" step="0.1" value="${this.volume}">
        `;
        document.body.appendChild(div);

        document.getElementById('soundToggle').addEventListener('click', () => {
            const enabled = this.toggle();
            document.querySelector('#soundToggle i').className = `fas ${enabled ? 'fa-volume-up' : 'fa-volume-mute'}`;
        });

        document.getElementById('volumeSlider').addEventListener('input', (e) => {
            this.setVolume(e.target.value);
        });
    },

    play(soundName) {
        if (!this.isEnabled) return;
        const url = this.sounds[soundName];
        if (!url) return;
        
        const audio = new Audio(url);
        audio.volume = this.volume;
        audio.play().catch(e => {});
    },

    startBgMusic() {
        if (!this.isEnabled || this.bgMusic) return;
        
        this.bgMusic = new Audio(this.sounds.lobbyBg);
        this.bgMusic.volume = this.volume * 0.4;
        this.bgMusic.loop = true;
        this.bgMusic.play().then(() => {
            console.log('🎵 Music started successfully');
        }).catch(e => {
            console.log('Waiting for user interaction to play music...');
        });
    },

    setVolume(val) {
        this.volume = val;
        if (this.bgMusic) this.bgMusic.volume = val * 0.4;
        localStorage.setItem('casino_volume', val);
    },

    toggle() {
        this.isEnabled = !this.isEnabled;
        if (!this.isEnabled && this.bgMusic) {
            this.bgMusic.pause();
        } else if (this.isEnabled && this.bgMusic) {
            this.bgMusic.play();
        }
        localStorage.setItem('casino_sound_enabled', this.isEnabled);
        return this.isEnabled;
    }
};

// Khởi tạo
SoundManager.init();

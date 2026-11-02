# 🎰 HỆ THỐNG CASINO & GIẢI TRÍ TRỰC TUYẾN TOÀN DIỆN — GTLM GAMING PLATFORM (TX)

[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![Database](https://img.shields.io/badge/MySQL-MariaDB-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Frontend](https://img.shields.io/badge/Three.js-WebGL-black?style=for-the-badge&logo=three.js&logoColor=white)](https://threejs.org/)
[![CSS Architecture](https://img.shields.io/badge/Glassmorphism-Cyberpunk%20Neon-8A2BE2?style=for-the-badge)](https://developer.mozilla.org/en-US/docs/Web/CSS)
[![Live Stream Tables](https://img.shields.io/badge/LiveStream%20Channels-61%20B%C3%A0n%2024%2F7-10B981?style=for-the-badge)](LiveStream/spectator.php)
[![PvP Matchmaking](https://img.shields.io/badge/PvP%20Radar-1vs1%20Realtime-EF4444?style=for-the-badge)](pvp_caro.php)

> **GTLM Gaming Platform (TX)** là hệ sinh thái nền tảng sòng bạc trực tuyến, giải trí mini-game, mạng xã hội bang hội và đài truyền hình LiveStream tương tác ảo 24/7 quy mô lớn. Dự án được xây dựng từ nền tảng nguyên bản (Zero-Framework Vanilla Core) kết hợp công nghệ đồ họa Three.js, CSS3 Glassmorphism, âm thanh Web Audio API Synthesizer và trí tuệ nhân tạo Bot AI Turbo thích ứng theo chuỗi thắng thua.

---

## 📑 MỤC LỤC CHI TIẾT

1. [🌟 Giới Thiệu Tổng Quan & Triết Lý Thiết Kế](#1--giới-thiệu-tổng-quan--triết-lý-thiết-kế)
2. [🏗️ Kiến Trúc Công Nghệ & Tech Stack](#2-️-kiến-trúc-công-nghệ--tech-stack)
3. [📂 Sơ Đồ Cấu Trúc Thư Mục Dự Án](#3--sơ-đồ-cấu-trúc-thư-mục-dự-án)
4. [🎮 Danh Mục 60+ Trò Chơi Độc Quyền](#4--danh-mục-60-trò-chơi-độc-quyền)
5. [📺 Đài Truyền Hình LiveStream Casino 24/7 (Bàn 1 Đến Bàn 61)](#5--đài-truyền-hình-livestream-casino-247-bàn-1-đến-bàn-61)
6. [⚔️ Hệ Thống Đối Kháng PvP & Sân Đấu Cờ Ca-rô AI](#6-️-hệ-thống-đối-kháng-pvp--sân-đấu-cờ-ca-rô-ai)
7. [🏰 Hệ Thống Xã Hội, Bang Hội & Đánh Boss Thế Giới](#7--hệ-thống-xã-hội-bang-hội--đánh-boss-thế-giới)
8. [💎 Hệ Thống VIP, Kinh Nghiệm (XP) & Vòng Quay May Mắn](#8--hệ-thống-vip-kinh-nghiệm-xp--vòng-quay-may-mắn)
9. [🛡️ Cơ Chế Bảo Mật, Chống Gian Lận & Bộ Lọc Casino](#9-️-cơ-chế-bảo-mật-chống-gian-lận--bộ-lọc-casino)
10. [⚙️ Cấu Trúc Cơ Sở Dữ Liệu (Database Schema)](#10-️-cấu-trúc-cơ-sở-dữ-liệu-database-schema)
11. [🚀 Hướng Dẫn Cài Đặt & Triển Khai Môi Trường](#11--hướng-dẫn-cài-đặt--triển-khai-môi-trường)
12. [📊 Hệ Thống Quản Trị Sới Bạc (Admin Control Center)](#12--hệ-thống-quản-trị-sới-bạc-admin-control-center)
13. [📜 Bản Quyền, Tài Nguyên Bên Thứ 3 & Tuyên Bố Phi Lợi Nhuận](#13--bản-quyền-tài-nguyên-bên-thứ-3--tuyên-bố-phi-lợi-nhuận)
    - [13.1 🚫 Tuyên Bố Phi Lợi Nhuận & Miễn Trừ Thương Mại](#131--tuyên-bố-phi-lợi-nhuận--miễn-trừ-thương-mại)
    - [13.2 🖼️ Ghi Nhận Tài Nguyên Hình Ảnh & Đồ Họa Bên Thứ 3](#132-️-ghi-nhận-tài-nguyên-hình-ảnh--đồ-họa-bên-thứ-3-third-party-visual-assets)
    - [13.3 🎵 Tài Nguyên Âm Thanh & Hiệu Ứng (Audio & SFX Credits)](#133--tài-nguyên-âm-thanh--hiệu-ứng-audio--sfx-credits)
    - [13.4 📚 Thư Viện & Công Nghệ Mã Nguồn Mở (Open Source)](#134--thư-viện--công-nghệ-mã-nguồn-mở-open-source-libraries)
    - [13.5 🤖 Sự Hỗ Trợ Từ Các Mô Hình Trí Tuệ Nhân Tạo (AI Collaboration)](#135--sự-hỗ-trợ-từ-các-mô-hình-trí-tuệ-nhân-tạo-ai-collaboration)
    - [13.6 ⚖️ Chính Sách Bảo Hộ Quyền Tác Giả & Quy Trình Gỡ Bỏ Tài Nguyên (DMCA / Takedown)](#136-️-chính-sách-bảo-hộ-quyền-tác-giả--quy-trình-gỡ-bỏ-tài-nguyên-dmca--takedown-policy)
    - [13.7 📜 Bản Quyền Mã Nguồn & Giấy Phép Dự Án](#137--bản-quyền-mã-nguồn--giấy-phép-dự-án)

---

## 1. 🌟 GIỚI THIỆU TỔNG QUAN & TRIẾT LÝ THIẾT KẾ

Nền tảng được thiết kế xoay quanh đơn vị tiền tệ trung tâm **GTLM (Gia Tài Lộc Mệnh)** — tượng trưng cho phúc khí, tài vận và địa vị của người chơi trong thế giới cờ bạc thượng lưu. 

### Các Điểm Nổi Bật Chính:
- **Trải Nghiệm Thượng Lưu (Cyberpunk Casino):** Giao diện tối màu (Dark Luxe) phối hợp các dải đèn Neon Emerald, Gold Cyber, Rose Red và dải hạt động 3D Three.js bay lượn ngẫu nhiên trên nền không gian vũ trụ.
- **Tương Tác Con Trỏ Độc Quyền:** Thay thế hoàn toàn con trỏ mặc định bằng bộ biểu tượng bàn tay và mũi tên ma thuật chuẩn game (`img/chuot.png`, `img/tay.png`).
- **Hệ Thống LiveStream 61 Bàn Liền Mạch:** Mô phỏng phòng live casino tương tác 24/7 với Bot Streamer tự động ra chiêu, tự động gửi bình luận gáy khét theo chuẩn văn hóa Casino Rule 5.3 (*"Húp trọn", "Bay màu", "GTLM rực rỡ", "Ra chiêu"*).
- **Âm Thanh Tự Nhiên Không Cần File Ngoài:** Tích hợp bộ tổng hợp âm thanh Web Audio API (Synthesizer Tones) trực tiếp sinh sóng sine, square, triangle để tạo tiếng lách cách đặt quân, gieo xúc xắc và nhạc hiệu chiến thắng mà không lo lỗi tải file tĩnh.

---

## 2. 🏗️ KIẾN TRÚC CÔNG NGHỆ & TECH STACK

| Lớp (Layer) | Công Nghệ Sử Dụng | Mục Đích Triển Khai |
| :--- | :--- | :--- |
| **Backend Core** | PHP 8.0+ / Native Procedural & OOP | Xử lý logic cược, thanh toán, session, thuật toán cờ bạc công bằng (Provably Fair) |
| **Cơ Sở Dữ Liệu** | MySQL / MariaDB (MySQLi Prepared Statements) | Lưu trữ người dùng, ví tiền, lịch sử cược `game_history`, phòng PvP, bảng xếp hạng |
| **Frontend UI** | HTML5, CSS3 Glassmorphism, CSS Grid / Flexbox | Giao diện đáp ứng (Responsive), khóa khung chat không giãn layout, hiệu ứng mờ kính |
| **Hiệu Ứng 3D** | Three.js (r128), WebGL, Canvas API | Nền hạt không gian ThreeJS ma trận, bánh xe quay 3D, xúc xắc 3D xoay góc |
| **Chuyển Động** | GSAP (GreenSock 3.12.2), CSS Keyframes | Hiệu ứng rơi quà tặng TikTok Gift FX, thẻ bài lật 3D, sóng radar quét đối thủ |
| **Tương Tác DOM** | jQuery 3.6.0, SweetAlert2 v11 | Xử lý AJAX không tải lại trang, modal xác nhận cược, thông báo chiến thắng |
| **Âm Thanh** | HTML5 Web Audio API Synthesizer | Tạo hiệu ứng âm thanh động (Sound FX) không cần asset tĩnh |
| **Phông Chữ** | Google Fonts (Outfit, Orbitron, JetBrains Mono, Inter) | Kiểu chữ số công nghệ số hóa, đồng bộ tiếng Việt không bao giờ lỗi font |

---

## 3. 📂 SƠ ĐỒ CẤU TRÚC THƯ MỤC DỰ ÁN

```bash
./
├── index.php                      # Trang chủ Sảnh Casino Trung Tâm (Lobby & Game Grid Modern)
├── login.php / auth.php           # Hệ thống xác thực đăng nhập, đăng ký tài khoản bảo mật
├── db_connect.php                 # Kết nối CSDL MySQL, bộ lọc Anti-Exploit, cấu hình Hosting
├── game_history_helper.php        # Trình ghi log lịch sử cược đa năng (Win/Loss, GTLM, Game Type)
├── load_theme.php                 # Trình nạp giao diện động, bảng màu, hạt Three.js theo vai trò
├── pvp_caro.php                   # Sảnh Đấu Cờ Ca-rô (Đấu Đơn AI Heuristic + Radar Ghép Trận 1vs1)
├── pvp_challenge.php / pvp_play   # Đấu trường thách đấu cờ bạc giữa người với người
├── tower_of_gods.php              # Siêu phẩm Tháp Thần Bài 100 Tầng (3v3 RPG Turn-Based Auto-Battler)
├── plinko_royale_v3.php           # Game Thả Bóng Plinko Vương Triều V3 (Jackpot Siêu Khủng x1000)
├── watch.php                      # Proxy điều hướng chuẩn về phòng xem LiveStream
│
├── LiveStream/                    # 📺 HỆ THỐNG ĐÀI TRUYỀN HÌNH LIVE CASINO 24/7
│   ├── spectator.php              # Sảnh tổng quan duyệt 61 kênh phát sóng trực tiếp
│   ├── watch.php                  # Phòng xem chi tiết (Khung video + Live Chat khóa cố định + Dock FX)
│   ├── api_spectator.php          # Backend RESTful API đồng bộ nhịp cược 30 giây thời gian thực
│   ├── bot_streamer_helper.php    # Quản lý khởi tạo tài khoản và ví GTLM dồi dào cho Bot Streamer
│   ├── live_1.php -> live_61.php  # 61 Màn đấu game riêng biệt chạy độc lập 24/7
│   └── bots/                      # 🤖 TRÍ TUỆ NHÂN TẠO BOT STREAMER
│       ├── bot_1.js -> bot_61.js  # Script điều khiển chuột ảo, giải thuật đặt cược và chat Casino 5.3
│
├── games/                         # 🎮 THƯ MỤC CHỨA TOÀN BỘ 60+ GAME CASINO NGUYÊN BẢN
│   ├── baccarat.php / craps.php   # Sòng bài quốc tế cổ điển
│   ├── baucua.php / xocdia.php    # Trò chơi dân gian Việt Nam truyền thống
│   ├── crash.php / limbo.php      # Thể loại tăng hệ số nhân theo thời gian
│   ├── plinko.php / plinko_v2.php # Game rơi bóng giải trí vật lý
│   ├── samloc.php / tusac.php     # Đánh bài dân tộc 52 lá & 112 lá
│   └── ...                        # Hơn 50 tựa game phong phú khác
│
├── assets/ / img/                 # Tài nguyên hình ảnh, biểu tượng chuột, âm thanh, CSS dùng chung
├── admin_dashboard.php            # Bảng điều khiển quản trị viên tối cao (Admin Control Center)
└── graphify-out/                  # Bản đồ tri thức tự động trích xuất cấu trúc mã nguồn AST
```

---

## 4. 🎮 DANH MỤC 60+ TRÒ CHƠI ĐỘC QUYỀN

Toàn bộ trò chơi đều được tính toán toán học chuẩn sòng bạc, có tỷ lệ RTP (Return to Player) minh bạch và hỗ trợ giao diện cược mượt mà:

### Nhóm 1: Dân Gian & Truyền Thống Việt Nam
- **Bầu Cua Tôm Cá 3D (`games/baucua.php`):** Lắc 3 viên xúc xắc Linh Thú Bầu - Cua - Tôm - Cá - Gà - Hươu với hiệu ứng mở bát chân thực.
- **Trận Địa Xóc Đĩa (`games/xocdia.php`):** 4 đồng xu 2 mặt đỏ trắng, phân định Chẵn - Lẻ, Tứ Đỏ, Tứ Trắng, Ba Đỏ, Ba Trắng.
- **Sâm Lốc Kinh Điển (`games/samloc.php`):** Đánh bài miền Bắc, chặn bài theo bộ, cơ chế Báo Sâm, đền làng kịch tính.
- **Tứ Sắc Hoàng Gia (`games/tusac.php`):** Trò chơi 112 lá truyền thống 4 màu Tướng - Sĩ - Tượng - Xe - Pháo - Mã - Tốt.
- **Bài Cào Ba Cây (`games/threecard.php`):** Đếm nút chia 10, phân định Sáp, Liêng, Ba Tây, tính điểm tốc độ cao.
- **Đá Gà Thần Kê (`games/daga.php`):** Trận thư hùng giữa Xích Thần Kê (Meron) và Thanh Thần Kê (Wala).

### Nhóm 2: Sòng Bạc Quốc Tế (Las Vegas & Macau Standard)
- **Baccarat Hoàng Gia 3D (`games/baccarat.php`):** Cược Player - Banker - Tie, luật rút lá thứ 3 chuẩn quốc tế.
- **Blackjack Multi-Seat (`games/blackjack_multi.php`):** Bàn chơi Xì Dách nhiều tụ, tính toán rút thêm, dằn dơ, bảo hiểm cược.
- **Roulette Cò Quay Châu Âu (`games/roulette.php`):** Vòng xoay 37 số (0-36), cược màu, cược chẵn lẻ, cược tá, cược góc.
- **Poker Texas Hold'em (`games/poker.php`):** Đấu trí chia bài tẩy, flop, turn, river, tố tất tay (All-in).
- **Caribbean Stud Poker (`games/caribbean.php`):** Đấu bài 5 lá trực tiếp với nhà cái, săn thưởng Progressive Jackpot.
- **Pai Gow Poker (`games/paigow.php`):** Xếp 7 lá bài thành 2 chi (chi 5 lá và chi 2 lá) đánh bại Banker.
- **Craps Xúc Xắc Đường Phố (`games/craps.php`):** Đặt cược cửa Pass Line, Don't Pass, Come, Field kịch tính.
- **Sicbo Tài Xỉu Cổ Điển (`games/sicbo.php`):** Lắc 3 hạt xúc xắc tính điểm 3-18, cược bão, cược tổng nút.

### Nhóm 3: Fast-Paced, Arcade & Crash Tốc Độ Cao
- **Tiên Tri Vũ Trụ Crash (`games/crash.php`):** Tên lửa bay lên vũ trụ với hệ số nhân x1.01 đến x100.00+, người chơi phải chốt lời trước khi nổ.
- **Plinko Royale V1, V2, V3 (`plinko_royale_v3.php`):** Thả bóng rơi qua ma trận đinh tán kim cương, húp thưởng đáy chậu lên đến **x1000 lần vốn**.
- **Limbo Siêu Tốc (`games/limbo.php`):** Chọn hệ số mong muốn, quay số ngẫu nhiên chỉ trong 0.2 giây.
- **Minesweeper & Mines Casino (`games/mines.php`):** Lật ô dò kho báu kim cương, né tránh bom mìn để nhân tiền lũy tiến.
- **Bắn Cá Arcade Neon (`games/banharc.php`):** Ngắm bắn đàn cá thần tài dưới đáy biển sâu với đạn laser tăng cấp.
- **Đua Thú Cyberpunk 2077 (`games/cyber_racing.php`):** Trường đua thú máy tương lai giữa Sói Cyber, Thỏ Neon, Báo Máy.

### Nhóm 4: Logic & Đối Kháng Trí Tuệ (PvP)
- **Đại Chiến Cờ Ca-rô AI & PvP (`pvp_caro.php`):** Bàn cờ chuẩn 15x15, tích hợp giải thuật Minimax cắt tỉa Alpha-Beta và Radar ghép trận 1vs1.
- **Đua Ngựa Thời Gian Thực (`games/horserace_pvp.php`):** Các kỵ mã tranh tài tốc độ với tỷ lệ cược biến động theo phong độ.
- **Battle Royale Sống Còn (`games/battleroyale.php`):** 30 kỳ thủ cùng nhảy dù thu thập trang bị sinh tồn trên một bàn đấu casino.

### Nhóm 5: Nhập Vai (RPG) & Đời Sống Sòng Bạc
- **Tháp Thần Bài 100 Tầng (`tower_of_gods.php`):** Đấu tướng theo lượt 3v3 Turn-based RPG, kích hoạt Tuyệt Kỹ Lãnh Tụ, leo tháp chinh phục boss.
- **Hang Động Tham Lam (`games/greedy_cave.php`):** Khám phá hầm ngục tìm báu vật, cân nhắc thoát ra bảo toàn chiến lợi phẩm.
- **Nông Trại Lộc Mệnh (`games/farm.php`):** Trồng trọt, thu hoạch nông sản quy đổi ra GTLM.
- **Đào Mỏ Tinh Thể (`games/mining.php`):** Khai thác quặng quý và chế tạo trang sức gia tăng chỉ số may mắn.

---

## 5. 📺 ĐÀI TRUYỀN HÌNH LIVESTREAM CASINO 24/7 (BÀN 1 ĐẾN BÀN 61)

Hệ thống đài truyền hình trực tiếp 24/7 gồm đúng **61 bàn live liên tục** không có bất kỳ khoảng trống hay kênh rác nào:

```
[Khán Giả Truy Cập] ───> [LiveStream/spectator.php] ───> Duyệt 61 Kênh Sôi Động
                                  │
                                  ▼
                         [LiveStream/watch.php?id=X]
                   ┌──────────────┴──────────────┐
                   ▼                             ▼
       [Iframe Game 24/7: live_X.php]   [Khung Live Chat & Tương Tác]
       - Bot Streamer Tự Chơi           - Khóa cứng cố định chiều cao
       - Chuột Ảo Di Chuyển Mượt        - BotChat.send bình luận gáy khét
       - Web Audio FX chân thực         - Tặng TikTok Gift FX rơi toàn màn hình
       - Khóa can thiệp người xem       - Tip GTLM vinh danh Streamer
```

### 📋 Danh Sách Đầy Đủ 61 Bàn LiveStream Hiện Hữu:

| Bàn ID | Tên Kênh Phát Sóng | Mã Game Gốc | Streamer AI Đại Diện | Điểm Nhấn Nổi Bật |
| :---: | :--- | :--- | :--- | :--- |
| **1** | Thế Giới Linh Thú | `baucua.php` | Thánh Nữ Tiên Tri | Lắc xúc xắc Bầu Cua 3D Linh Vật |
| **2** | Trận Địa Trắng Đỏ | `xocdia.php` | Thần Bài Bịp | Đấu đĩa đỏ trắng Chẵn Lẻ kịch tính |
| **3** | Tiên Tri Vũ Trụ | `crash.php` | Cơ Trưởng Đẹp Trai | Đón tên lửa nhân x100 |
| **4** | Đại Chiến Thần Kê | `daga.php` | Chủ Sới Gà | Gà chọi đẫm máu Meron vs Wala |
| **5** | Chiến Trường Rồng Hổ | `dragontiger.php` | Long Vương & Hổ Tướng | Rút 2 lá bài phân định thắng bại |
| **6** | Đua Thú Cyberpunk | `cyber_racing.php` | Thánh Cược Đua Thú | Trường đua thú máy neon 2077 |
| **7** | Plinko Royale | `plinko.php` | Đại Gia Thả Bóng | Rơi bóng qua ma trận kim cương |
| **8** | Slot Machine Premium | `slot.php` | Thánh Nổ Hũ | Máy xèng 3x3 nổ hũ Jackpot liên hoàn |
| **9** | Baccarat Đỉnh Cao | `baccarat.php` | Cao Thủ Baccarat | So điểm Player vs Banker đẳng cấp |
| **10** | Bắn Cá Arcade Neon | `banharc.php` | Vua Thủy Tề | Bắn cá laser húp ngọc đại dương |
| **11** | Battle Royale Sống Còn | `battleroyale.php` | Chiến Thần Bo | Đấu trường sinh tử 30 người |
| **12** | Bingo Hoàng Kim | `bingo.php` | Tiên Tri Bingo | Quay cầu bốc số nổ dòng may mắn |
| **13** | Bj Thần Thánh | `bj.php` | Thần Bài Blackjack | Bốc bài 21 điểm lật kèo chớp nhoáng |
| **14** | Bjo Đấu Trí | `bjo.php` | Lão Đại Bjo | Đấu bài cược nhanh phong cách cổ điển |
| **15** | Blackjack Las Vegas | `blackjack.php` | Master Blackjack | Đấu trí nhà cái chuẩn luật Mỹ |
| **16** | Blackjack Multi-Seat | `blackjack_multi.php` | Đại Gia 5 Tụ | Một mình ôm trọn cả bàn cược |
| **17** | Caribbean Stud Poker | `caribbean.php` | Thuyền Trưởng Caribbean | Tố bài 5 lá săn Progressive Pot |
| **18** | Coinflip Đồng Xu | `coinflip.php` | Thánh Đoán Mặt | Lật đồng xu ngửa xấp 50/50 |
| **19** | Xạ Thủ Xúc Xắc Craps | `craps.php` | Bậc Thầy Xạ Thủ | Gieo xúc xắc Craps đường phố |
| **20** | Dice Tài Lộc | `dice.php` | Thần Xúc Xắc | Đoán điểm Tài Xỉu xúc xắc siêu tốc |
| **21** | Đua Ngựa Trường Đua 3D | `duangua.php` | Nài Ngựa Số 1 | Kỵ mã số 8 phi nước đại về đích |
| **22** | Cờ Bạc Fantan | `fantan.php` | Lão Sư Fantan | Đếm cúc gạt số dư 1, 2, 3, 4 |
| **23** | Nông Trại Triệu Phú | `farm.php` | Nông Dân Bạc Tỷ | Thu hoạch vàng rực rỡ từ đất lộc |
| **24** | Thẻ Bài Gacha Cards | `gacha_cards.php` | Nhà Sưu Tập | Mở túi mù gacha thẻ bài huyền thoại |
| **25** | Hang Động Tham Lam | `greedy_cave.php` | Nhà Thám Hiểm | Đào sâu nhặt ngọc né quái vật |
| **26** | Hilo Cực Tốc | `hilo.php` | Thánh Cao Thấp | Đoán lá tiếp theo Cao hơn hay Thấp hơn |
| **27** | Texas Hold'em VIP | `holdem.php` | Vua Tố All-In | Cân não từng vòng cược Flop/River |
| **28** | Hộp Mù May Mắn | `hopmu.php` | Thần Mở Hộp | Khui hộp bí ẩn nhận giải độc đắc |
| **29** | Đua Ngựa Tốc Độ | `horserace.php` | Chiến Mã Vô Địch | Bắt kèo chú ngựa đen vượt lên |
| **30** | Đua Ngựa PvP Đối Kháng | `horserace_pvp.php` | Kỵ Sĩ Đấu Trường | Đua ngựa solo tay đôi trực tuyến |
| **31** | Jojo Battle Sàn Đấu | `jojo_battle.php` | Stand Master | Đấu Stand chiêu thức ảo diệu |
| **32** | Keno Xổ Số Vũ Trụ | `keno.php` | Thánh Keno | Bốc 20 số trên bảng 80 số |
| **33** | Let It Ride | `letitride.php` | Thần Giữ Cược | Rút bớt hay để nguyên cược bài |
| **34** | Limbo Tên Lửa | `limbo.php` | Cơ Trưởng Limbo | Săn nhân x100 trong 1 cái chớp mắt |
| **35** | Xổ Số Truyền Thống | `lottery.php` | Thần Quay Số | Lồng cầu quay thưởng minh bạch |
| **36** | Mạt Chược Thập Tam Thái Bảo | `mahjong.php` | Thần Bài Mạt Chược | Ù khang, ù thông, tứ đại thiên vương |
| **37** | Vòng Quay Megaspin | `megaspin.php` | Nữ Hoàng Bánh Xe | Quay trục vòng lớn húp hệ số x50 |
| **38** | Dò Mìn Minesweeper | `mines.php` | Chuyên Gia Rà Phá | Lật hết ô sạch né bom ăn đậm |
| **39** | Gỡ Bom Cảm Tử | `minesweeper.php` | Đội Trưởng Gỡ Bom | Căng não từng bước di chuyển |
| **40** | Number Đoán Số Vận Mệnh | `number.php` | Thần Số Học | Bắt trúng con số định mệnh đổi đời |
| **41** | Pai Gow 7 Lá | `paigow.php` | Bậc Thầy Xếp Bài | Tách 2 chi khắc chế nhà cái |
| **42** | Poker Sàn Đấu Macau | `poker.php` | Vua Sòng Bạc | Đấu tẩy xì phé gay cấn |
| **43** | Pontoon Thượng Lưu | `pontoon.php` | Quý Tộc Pontoon | Đánh bại 21 điểm phong cách Anh |
| **44** | Red Dog Lá Bài Bí Mật | `reddog.php` | Thánh Bắt Kẹp | Lá bài thứ 3 nằm lọt khe giữa |
| **45** | Roulette Châu Âu | `roulette.php` | Nữ Thần Vòng Quay | Quả bóng ngà lăn trên 37 ô số |
| **46** | Kéo Búa Bao Siêu Cấp | `rps.php` | Vua Oẳn Tù Tì | Đấu phản xạ tay đôi ăn tiền |
| **47** | Rút Thăm May Mắn | `ruttham.php` | Chủ Lễ Hội | Rút thăm trúng thưởng hũ chung |
| **48** | Sâm Lốc Đệ Nhất Bắc Kỳ | `samloc.php` | Thần Sâm Hà Thành | Chặn bài đền làng thót tim |
| **49** | Vé Cào Trúng Liền | `scratch.php` | Thợ Cào Thẻ | Cào 3 ô đồng nhất nhận quà ngay |
| **50** | Tài Xỉu Lắc Đĩa Sicbo | `sicbo.php` | Lão Đại Sicbo | Lắc 3 hột xí ngầu nắp đậy trong suốt |
| **51** | Bài Cào 3 Lá Miền Tây | `threecard.php` | Anh Ba Bài Cào | Đọ 9 nút húp trọn chiếu bạc |
| **52** | Leo Tháp Tower | `tower.php` | Kẻ Chinh Phục | Leo từng tầng tháp nhân tiền cao dần |
| **53** | Bài Tứ Sắc Cung Đình | `tusac.php` | Quan Lớn Tứ Sắc | Chiếu tướng, khui bài, ăn chẵn |
| **54** | Video Poker Đổi Thưởng | `videopoker.php` | Tay Chơi Máy Điện Tử | Xếp sảnh chúa Royal Flush x800 |
| **55** | Vietlott Mega 6/45 | `vietlott.php` | Triệu Phú Vietlott | Săn giải Jackpot hàng chục tỷ GTLM |
| **56** | Chiến Tranh Bài Casino War | `war.php` | Đại Tướng Chiến Trận | Rút bài cao hơn là lụm tiền |
| **57** | Xúc Xắc Yahtzee | `yahtzee.php` | Nghệ Nhân Yahtzee | Đổ cù lũ, sảnh lớn, 5 hạt giống nhau |
| **58** | Plinko V2 Pro | `plinko_v2.php` | Thần Bóng Neon | Đường rơi bóng đổi hướng bất ngờ |
| **59** | Plinko Royale V3 Hoàng Gia | `plinko_royale_v3.php` | Hoàng Đế Plinko | Ma trận kim cương hũ x1000 |
| **60** | Tháp Thần Bài 100 Tầng | `tower_of_gods.php` | Chủ Tháp Thần Bài | Đấu tướng 3v3 RPG Turn-based |
| **61** | Đại Chiến Cờ Ca-rô AI | `pvp_caro.php` | AlphaX vs DeepO | Đấu trí siêu AI 15x15 Ngũ Tử Liền Hàng |

---

## 6. ⚔️ HỆ THỐNG ĐỐI KHÁNG PvP & SÂN ĐẤU CỜ CA-RÔ AI

Sảnh Đấu Cờ Ca-rô (`pvp_caro.php`) được thiết kế theo mô hình tách biệt 2 trạng thái rõ ràng:

### A. Chế Độ Đấu Đơn Với Siêu AI Heuristic (Singleplayer Arena)
- **Độc Lập Hoàn Toàn:** Không ghi nhận vào bảng `pvp_challenges`, ngăn ngừa triệt để tình trạng bắn popup thông báo thách đấu làm phiền người chơi toàn hệ thống.
- **Trừ Cược & Trả Thưởng Tự Động:** Đặt cược hợp lệ (từ 10.000 GTLM đến hàng triệu GTLM), khi thắng nhận ngay **1.95x** tiền thưởng kèm ghi log chuẩn mực vào `game_history`.
- **Động Cơ Heuristic Minimax 15x15:**
  - Tự động nhận diện thế trận: *Ngũ tử liền hàng (Win ngay), 4 quân mở 2 đầu, 4 quân chặn 1 đầu, Tam hoa đôi, 3 quân mở 2 đầu*.
  - Tự động chặn các nước hiểm của đối thủ và mở các thế trận tấn công chéo/dọc/ngang ảo diệu.

### B. Cơ Chế Radar Tìm Trận 1vs1 (Realtime Matchmaking Radar)
- Hiển thị giao diện quét sóng Radar Neon công nghệ cao khi bấm *"⚔️ Tìm Trận Đấu Ngay"*.
- Tự động rà soát các phòng thách đấu đang chờ người tiếp chiêu khớp mức cược.
- Nếu tìm thấy phòng: Tự động ghép đôi và mở bàn đấu trực tuyến ngay lập tức.
- Nếu chưa có phòng: Tự động khởi tạo bàn đấu chờ trong sảnh mà không bị chuyển hướng thô sang trang khác.

---

## 7. 🏰 HỆ THỐNG XÃ HỘI, BANG HỘI & ĐÁNH BOSS THẾ GIỚI

### Bang Hội & Bang Chiến (`guilds.php`, `guild_war_helper.php`)
- **Gia Nhập & Thành Lập Bang:** Mở bang hội, bổ nhiệm Bang Chủ, Phó Bang Chủ, Trưởng Lão.
- **Quỹ Bang Hội:** Thành viên đóng góp GTLM để nâng cấp cấp độ Bang, mở khóa các đặc quyền tăng tỷ lệ nhận thưởng casino cho toàn bang.
- **Bang Chiến Trực Tiếp:** Các bang hội so tài tổng điểm thắng cược trong tuần để tranh đoạt Ngai Vàng Casino.

### Boss Thế Giới Hắc Long (`world_boss.php`)
- Sự kiện xuất hiện vào khung giờ vàng mỗi ngày.
- Toàn bộ người chơi trên server cùng tham gia tấn công Boss bằng điểm cược GTLM.
- Phần thưởng rớt đồ cực phẩm: Rương Thần Thoại, Danh Hiệu Phát Sáng, Kim Cương quý hiếm.

### Thần Thú Đồng Hành (`pets.php`, `pet_helper.php`)
- Ấp trứng, tiến hóa Thần Thú (Kỳ Lân, Phượng Hoàng, Huyền Vũ, Thanh Long).
- Thần Thú mang lại kỹ năng nội tại: *Tăng 5% tỷ lệ nổ hũ, Giảm 3% tiền phế bàn, Tự động nhặt lì xì*.

### Chợ Đen & Sàn Đấu Giá (`marketplace.php`, `auction.php`)
- Người chơi tự do giao thương vật phẩm, nguyên liệu cường hóa, thú cưỡi, danh hiệu giới hạn.
- Đấu giá công khai thời gian thực theo từng bước giá GTLM.

---

## 8. 💎 HỆ THỐNG VIP, KINH NGHIỆM (XP) & VÒNG QUAY MAY MẮN

### Hệ Thống Cấp Bậc VIP
Hệ thống tính lũy tiến dựa trên tổng tiền cược hợp lệ:
1. **VIP 1 - Tân Thủ:** Khởi đầu hành trình.
2. **VIP 2 - Tay Chơi:** Tặng 50.000 GTLM hàng tuần, hoàn trả 0.2%.
3. **VIP 3 - Cao Thủ:** Tặng 200.000 GTLM hàng tuần, hoàn trả 0.4%, khung avatar Bạc.
4. **VIP 4 - Đại Gia:** Tặng 1.000.000 GTLM, hoàn trả 0.8%, quà sinh nhật riêng.
5. **VIP 5 - Thần Bài:** Tặng 5.000.000 GTLM, hoàn trả 1.2%, kênh hỗ trợ ưu tiên riêng.
6. **VIP 6 - Chí Tôn Vô Thượng:** Tặng 20.000.000 GTLM, hoàn trả 2.0%, biểu tượng Rồng Vàng bay quanh tên.

### Phúc Lợi Hàng Ngày
- **Điểm Danh 7 Ngày (`daily_login.php`):** Nhận thưởng lũy tiến theo chuỗi ngày đăng nhập liên tục.
- **Vòng Quay May Mắn (`lucky_wheel.php`):** Tặng lượt quay miễn phí mỗi ngày, cơ hội trúng Jackpot 100.000.000 GTLM.
- **Cơn Mưa Lì Xì (`RedEnvelopeManager`):** Phát lì xì ngẫu nhiên trên toàn sảnh chat lúc 12:00 và 20:00 mỗi ngày.

---

## 9. 🛡️ CƠ CHẾ BẢO MẬT, CHỐNG GIAN LẬN & BỘ LỌC CASINO

Dự án áp dụng các lớp phòng vệ nghiêm ngặt:

1. **Bộ Lọc Số Âm Toàn Cục (Global Anti-Exploit in `db_connect.php`):**
   ```php
   if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
       array_walk_recursive($_POST, function(&$value) {
           if (is_numeric($value) && $value < 0) {
               $value = 0; // Chặn triệt để lỗ hổng cược số âm hack tiền
           }
       });
   }
   ```
2. **Ngăn Ngừa SQL Injection Tuyệt Đối:**
   - 100% các câu truy vấn tài chính và người dùng đều sử dụng `$stmt = $conn->prepare(...)` và `$stmt->bind_param(...)`.
3. **Bảo Mật Phiên Làm Việc (Session Hardening):**
   - Thiết lập cờ `cookie_httponly = 1` và `cookie_samesite = 'Strict'` ngăn ngừa tấn công XSS đánh cắp Session ID.
4. **Từ Điển Thay Thế Từ Khóa Nhạy Cảm (`vocabulary_helper.php`):**
   - Tự động thay thế các từ ngữ thô thiển thành thuật ngữ chuẩn mỹ cảm sòng bạc thông qua `ob_start(['VocabularyHelper', 'mask'])`.

---

## 10. ⚙️ CẤU TRÚC CƠ SỞ DỮ LIỆU (DATABASE SCHEMA)

Cơ sở dữ liệu trung tâm: `vdkmnuaahosting_taixiu` (hoặc cấu hình tùy chỉnh qua `.env.php`).

```
                ┌──────────────────┐
                │      users       │
                │──────────────────│
                │ Iduser (PK)      │
                │ Name, Email      │
                │ Money, Diamond   │
                │ Role, VIP, Level │
                └────────┬─────────┘
                         │ 1:N
        ┌────────────────┼────────────────┬────────────────┐
        ▼                ▼                ▼                ▼
┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
│ game_history │ │livestream_...│ │pvp_challenges│ │    guilds    │
│──────────────│ │──────────────│ │──────────────│ │──────────────│
│ id (PK)      │ │ id (PK)      │ │ id (PK)      │ │ id (PK)      │
│ user_id (FK) │ │ table_id     │ │ creator_id   │ │ leader_id    │
│ game_type    │ │ user_name    │ │ opponent_id  │ │ guild_name   │
│ bet_amount   │ │ message      │ │ bet_amount   │ │ fund_balance │
│ win_amount   │ │ created_at   │ │ status       │ │ level        │
└──────────────┘ └──────────────┘ └──────────────┘ └──────────────┘
```

### Các Bảng Dữ Liệu Cốt Lõi:
- `users`: Tài khoản, mật khẩu băm, số dư GTLM (`Money`), Kim Cương, Cấp độ VIP, kinh nghiệm.
- `game_history`: Nhật ký mọi phiên đặt cược của người chơi và bot (Game, Cược, Thắng/Thua, Số dư sau cược, Thời gian).
- `livestream_chats`: Lưu trữ lịch sử tin nhắn phòng live của khán giả và bot streamer theo từng `table_id`.
- `livestream_reactions`: Lưu các lượt thả tim, vỗ tay, icon nổi bay trên màn hình live.
- `pvp_challenges`: Danh sách các bàn cờ thách đấu người đấu người đang chờ đối thủ tiếp chiêu.
- `guilds` & `guild_members`: Thông tin bang hội, quỹ bang, cấp bậc và danh sách thành viên.
- `pets`: Thần thú nuôi dưỡng, cấp độ, kinh nghiệm và kỹ năng bổ trợ cờ bạc.
- `quests` & `user_quests`: Hệ thống nhiệm vụ hàng ngày và tiến độ hoàn thành nhận thưởng.

---

## 11. 🚀 HƯỚNG DẪN CÀI ĐẶT & TRIỂN KHAI MÔI TRƯỜNG

### Bước 1: Chuẩn Bị Môi Trường
- Cài đặt **XAMPP** (hỗ trợ PHP 8.0 trở lên và MySQL/MariaDB).
- Đảm bảo các tiện ích mở rộng PHP sau đã được bật trong `php.ini`:
  - `extension=mysqli`
  - `extension=mbstring`
  - `extension=curl`
  - `extension=fileinfo`

### Bước 2: Đặt Mã Nguồn Vào Thư Mục Web Server
Đặt thư mục mã nguồn vào thư mục phục vụ web của bạn (ví dụ thư mục `htdocs/` của máy chủ):
```bash
htdocs/
```

### Bước 3: Cấu Hình Cơ Sở Dữ Liệu
1. Mở công cụ quản lý cơ sở dữ liệu MySQL (ví dụ: giao diện phpMyAdmin hoặc MySQL Workbench / DBeaver / MySQL CLI).
2. Tạo cơ sở dữ liệu mới có tên: `vdkmnuaahosting_taixiu` (hoặc tên tùy chọn của bạn).
3. Import các tệp cơ sở dữ liệu nền tảng và nạp dữ liệu bot:
   - Import file: `insert_live_bots.sql` (Tự động khởi tạo đầy đủ 61 Bot Streamer dồi dào GTLM).
4. Mở file [db_connect.php](db_connect.php) kiểm tra cấu hình kết nối:
   ```php
   'DB_SERVER' => 'localhost',
   'DB_NAME'   => 'vdkmnuaahosting_taixiu',
   'DB_USER'   => 'root',
   'DB_PASS'   => '',
   ```

### Bước 4: Khởi Chạy Nền Tảng
Khởi động máy chủ Web (Apache/Nginx) & MySQL, sau đó truy cập các đường dẫn tương ứng trong dự án:
- **Trang Chủ Casino Tổng:** `index.php`
- **Sảnh LiveStream 61 Bàn:** `LiveStream/spectator.php`
- **Phòng Xem Live Trực Tiếp (Bàn 61 - Cờ Ca-rô AI):** `LiveStream/watch.php?id=61`
- **Sảnh Đấu Cờ Ca-rô (Đấu Đơn & Radar PvP):** `pvp_caro.php`
- **Trung Tâm Quản Trị Admin:** `admin_dashboard.php`

---

## 12. 📊 HỆ THỐNG QUẢN TRỊ SỚI BẠC (ADMIN CONTROL CENTER)

Truy cập tại [admin_dashboard.php](admin_dashboard.php) với quyền tài khoản Quản Trị Viên (`Role = 1`):

- **Biểu Đồ Tài Chính Thời Gian Thực:** Thống kê tổng tiền GTLM lưu thông, dòng tiền nạp/rút, tỷ lệ thắng thua của nhà cái (House Edge).
- **Quản Lý Hội Viên:** Tra cứu thông tin, lịch sử cược, khóa tài khoản vi phạm, điều chỉnh số dư GTLM trực tiếp.
- **Giám Sát Bot Streamer:** Kiểm tra tình trạng hoạt động của 61 bot tự động, theo dõi số dư của bot để tự động bơm vốn khi cần.
- **Thiết Lập Giải Đấu & Hũ Jackpot:** Tùy biến giá trị Jackpot khởi điểm, tỷ lệ tích lũy hũ và thời gian nổ hũ sự kiện.
- **Nhật Ký Hệ Thống (System Logs):** Ghi nhận các hành vi bất thường, phát hiện dấu hiệu gian lận tự động.

---

## 13. 📜 BẢN QUYỀN, TÀI NGUYÊN BÊN THỨ 3 & TUYÊN BỐ PHI LỢI NHUẬN

> [!IMPORTANT]
> ### 🚫 TUYÊN BỐ ĐẶC BIỆT: DỰ ÁN HOÀN TOÀN PHI LỢI NHUẬN (100% NON-PROFIT & EDUCATIONAL PURPOSES)
> **Dự án GTLM Gaming Platform (nguyenmanh2005/TX) được phát triển và vận hành với mục đích DUY NHẤT là nghiên cứu học thuật, giáo dục công nghệ lập trình web và thử nghiệm giải thuật phần mềm.**
> - **TUYỆT ĐỐI KHÔNG CÓ Ý ĐỊNH THU VỀ BẤT KỲ LỢI NHUẬN THƯƠNG MẠI NÀO DƯỚI BẤT KỲ HÌNH THỨC NÀO.**
> - **KHÔNG THU TIỀN THẬT, KHÔNG CÓ CỔNG NẠP/RÚT TIỀN MẶT, KHÔNG QUY ĐỔI THẺ CÀO, KHÔNG BÁN VẬT PHẨM LẤY TIỀN THẬT.**
> - **ĐỒNG TIỀN ẢO GTLM VÀ KIM CƯƠNG HOÀN TOÀN LÀ ĐIỂM SỐ MÔ PHỎNG VÔ GIÁ TRỊ NGOÀI ĐỜI THỰC.**
> - Mọi tài nguyên hình ảnh, âm thanh từ bên thứ ba được sử dụng trên tinh thần **Học hỏi - Tôn trọng bản quyền - Fair Use phi thương mại**.

---

### 13.1 🚫 Tuyên Bố Phi Lợi Nhuận, Miễn Trừ Thương Mại & Cảnh Báo Pháp Lý

1. **Mục Đích Nghiên Cứu Kỹ Thuật & Giáo Dục Lập Trình:**
   - Dự án là sản phẩm đồ án thực nghiệm Full-stack chuyên sâu (PHP 8.0+ kết hợp MySQLi đa luồng, Canvas 2D/3D WebGL, Three.js, CSS Glassmorphism hiện đại, Web Audio API Synthesizer và giải thuật Minimax cờ Ca-rô AI).
   - Tác giả và đội ngũ phát triển cam kết **không kinh doanh**, **không thu lợi nhuận**, không thu phí dịch vụ, không chèn banner quảng cáo kiếm tiền và không có bất kỳ hành vi thương mại hóa nào.

2. **Quy Định Tiền Ảo GTLM & Điểm Số Mô Phỏng:**
   - Đơn vị tiền tệ **GTLM (Gia Tài Lộc Mệnh)** và **Kim Cương** chỉ đóng vai trò là các biến số toán học (numeric variables) phục vụ việc kiểm thử logic cân bằng sòng bạc (RTP/House Edge), kiểm thử tải trọng luồng dữ liệu thời gian thực và mang lại trải nghiệm giải trí mô phỏng lành mạnh.
   - GTLM **tuyệt đối không thể quy đổi thành tiền mặt, thẻ cào điện thoại, tiền mã hóa (crypto) hay bất kỳ hiện kim / tài sản có giá trị thực tế nào**.
   - Ban quản trị nghiêm cấm triệt để mọi hành vi mua bán, sang nhượng hoặc giao dịch điểm GTLM bằng tiền thật giữa các người chơi ngoài đời thực. Mọi tài khoản vi phạm sẽ bị khóa vĩnh viễn không cần báo trước.

3. **Cảnh Báo Tuân Thủ Pháp Luật & Trách Nhiệm Người Dùng:**
   - Dự án nghiêm cấm tuyệt đối việc sử dụng mã nguồn này để tổ chức đánh bạc ăn tiền thật hoặc bất kỳ hoạt động trái pháp luật hiện hành của nước Cộng hòa Xã hội Chủ nghĩa Việt Nam cũng như luật pháp quốc tế.
   - Người sử dụng tự chịu hoàn toàn trách nhiệm pháp lý nếu tự ý triển khai mã nguồn vào các mục đích phi giáo dục hoặc trái pháp luật.

---

### 13.2 🖼️ Ghi Nhận Bản Quyền Tài Nguyên Hình Ảnh & Đồ Họa Bên Thứ 3 (Visual Assets Credits)

Dự án cam kết tôn trọng tuyệt đối quyền tác giả và quyền sở hữu trí tuệ của tất cả các nghệ sĩ, studio và cộng đồng sáng tạo trên thế giới. Các tài nguyên đồ họa được sử dụng theo giấy phép mã nguồn mở (Creative Commons Zero CC0, Open Source, Personal / Non-commercial Use) hoặc theo nguyên tắc **Sử Dụng Hợp Lý (Fair Use)** cho mục đích nghiên cứu học thuật phi lợi nhuận:

| Tài Nguyên / Nền Tảng | Hạng Mục Sử Dụng Trong Nền Tảng | Tác Giả / Nhà Cung Cấp | Giấy Phép & Ghi Nhận Bản Quyền (Credits) |
| :--- | :--- | :--- | :--- |
| **Kenney Assets** | Bộ bài tây hoàn chỉnh (Playing Cards Pack 52 lá + Jokers), xúc xắc xúc xắc và thẻ bài giao diện (`games/img/anh-bai/`, `LiveStream/img/anh-bai/`) | **Kenney** ([kenney.nl](https://kenney.nl)) | **Creative Commons Zero (CC0 1.0 Universal - Public Domain)**. Trân trọng cảm ơn tác giả Kenney vì kho tài nguyên mở vô giá cho cộng đồng lập trình game! |
| **Freepik & Unsplash** | Hình nền các phòng game, banner sự kiện, ảnh nền sảnh VIP và hoa văn texture | Các nghệ sĩ cộng tác trên [Freepik](https://freepik.com) & [Unsplash](https://unsplash.com) | Giấy phép sử dụng miễn phí cho mục đích học tập và dự án phi thương mại. |
| **Sweezy Cursors, RW-Designer, Custom Cursor** | Bộ con trỏ chuột nghệ thuật độc quyền (`img/chuot.png`, `img/tay.png`) | [Sweezy Cursors](https://sweezy-cursors.com), [RW-Designer](https://www.rw-designer.com), [Custom Cursor](https://custom-cursor.com) | Tài nguyên con trỏ chuột web miễn phí phục vụ trải nghiệm giao diện người dùng. |
| **Itch.io Indie Creators** | Sprite quái vật hầm ngục (`greedy_cave.php`), hình ảnh Hắc Long Boss Thế Giới (`world_boss.php`) | Các nghệ sĩ độc lập trên [Itch.io](https://itch.io) | Sử dụng theo giấy phép tài nguyên indie phi thương mại. |
| **Transparent Textures** | Họa tiết vi lưới nền tối, hoa văn sòng bạc sang trọng | [Transparent Textures](https://www.transparenttextures.com) | Họa tiết nền web mở miễn phí cho cộng đồng. |
| **AI Image Generation** | Hình nền các màn đấu Battle Royale, Sới Gà Thần Kê, Bài Tứ Sắc, Thần Thú Pet | Được sinh bởi Midjourney, Stable Diffusion, DALL-E, Gemini & Grok | Bản quyền hình ảnh sinh bởi AI phi thương mại phục vụ minh họa ý tưởng game. |
| **DiceBear Avatars API** | Ảnh đại diện vector SVG tự động sinh ngẫu nhiên cho hội viên và 61 Bot Streamer | [DiceBear API](https://api.dicebear.com) | Giấy phép mã nguồn mở (CC BY 4.0 / MIT License). |

---

### 13.3 🎵 Tài Nguyên Âm Thanh & Hiệu Ứng (Audio & SFX Credits)

- **YouTube Audio Library ([youtube.com/audiolibrary](https://www.youtube.com/audiolibrary)):** Nhạc nền sảnh chờ, nhạc nền thư giãn cho các kênh phát sóng LiveStream 24/7 (Royalty-Free Music).
- **Pixabay Music & Mixkit ([pixabay.com](https://pixabay.com/music/), [mixkit.co](https://mixkit.co)):** Hiệu ứng âm thanh (SFX) mở bát, lắc xí ngầu, nổ hũ Jackpot, tiếng reo hò chiến thắng.
- **Bensound & Zapsplat ([bensound.com](https://www.bensound.com), [zapsplat.com](https://www.zapsplat.com)):** Tài nguyên âm thanh chất lượng cao phục vụ các màn đấu kịch tính.
- **HTML5 Web Audio API Synthesizer:** Hệ thống âm thanh độc quyền tự động sinh sóng dao động (Sine, Square, Triangle wave) trực tiếp bằng mã JavaScript trên trình duyệt (trong các game Cờ Ca-rô, Slot, Crash, Xúc xắc) giúp tối ưu hiệu năng và không phụ thuộc file âm thanh tĩnh bên ngoài.

---

### 13.4 📚 Thư Viện & Công Nghệ Mã Nguồn Mở (Open Source Libraries)

Dự án sử dụng và ghi nhận sự đóng góp to lớn của cộng đồng phát triển mã nguồn mở toàn cầu:
- **Three.js ([threejs.org](https://threejs.org)):** Giấy phép MIT — Thư viện đồ họa 3D WebGL render hạt không gian vũ trụ.
- **GSAP - GreenSock ([greensock.com](https://greensock.com/gsap/)):** Thư viện chuyển động hoạt hình chuyên nghiệp cho quà tặng rơi và thẻ bài lật 3D.
- **Chart.js ([chartjs.org](https://chartjs.org)):** Giấy phép MIT — Biểu đồ phân tích tài chính dòng tiền trên Admin Dashboard.
- **SweetAlert2 ([sweetalert2.github.io](https://sweetalert2.github.io)):** Giấy phép MIT — Hộp thoại thông báo pop-up thông minh.
- **Font Awesome ([fontawesome.com](https://fontawesome.com)):** Giấy phép SIL OFL & CC BY 4.0 — Bộ biểu tượng icon giao diện.
- **Google Fonts ([fonts.google.com](https://fonts.google.com)):** Các phông chữ Inter, Roboto, Outfit, Orbitron, JetBrains Mono theo giấy phép Open Font License (OFL).
- **jQuery ([jquery.com](https://jquery.com)):** Giấy phép MIT — Nền tảng điều khiển DOM và kết nối AJAX không đồng bộ.

---

### 13.5 🤖 Sự Hỗ Trợ Từ Các Mô Hình Trí Tuệ Nhân Tạo (AI Collaboration)

Dự án có sự đồng hành và hỗ trợ phát triển logic thuật toán từ các mô hình trí tuệ nhân tạo tiên tiến:
- **Google DeepMind / Gemini / Antigravity Agent:** Hỗ trợ tối ưu hóa thuật toán cờ Ca-rô Minimax cắt tỉa Alpha-Beta, kiến trúc đồng bộ thời gian thực cho 61 bàn LiveStream, cơ chế bảo vệ phiên làm việc và cấu trúc sơ đồ tri thức Graphify.
- **Anthropic Claude / OpenAI ChatGPT / xAI Grok:** Hỗ trợ thiết kế kịch bản toán học xác suất hoàn vốn (RTP), hệ thống âm thanh Web Audio API, và các thuật toán phân bổ xúc xắc chuẩn Provably Fair.

---

### 13.6 ⚖️ Chính Sách Bảo Hộ Quyền Tác Giả & Quy Trình Gỡ Bỏ Tài Nguyên (DMCA / Takedown Policy)

> [!NOTE]
> **Cam Kết Tôn Trọng Tuyệt Đối Quyền Sở Hữu Trí Tuệ:**
> Dự án GTLM Gaming Platform được xây dựng dựa trên tinh thần học hỏi, chia sẻ kiến thức kỹ thuật và phụng sự cộng đồng nghiên cứu mã nguồn mở.
> 
> Nếu bạn là tác giả, nghệ sĩ hoặc chủ sở hữu hợp pháp của bất kỳ hình ảnh, âm thanh, biểu tượng hay tài sản trí tuệ nào xuất hiện trong dự án này và **không mong muốn tài nguyên của mình được sử dụng** trong dự án nghiên cứu phi lợi nhuận này, hoặc muốn bổ sung/thay đổi thông tin ghi nhận công lao (Attribution):
> 
> 📬 **Vui lòng liên hệ với ban quản trị:**
> - **Email quản trị:** `cumanhpt@gmail.com`
> - **Kênh tiếp nhận GitHub Issues:** [https://github.com/nguyenmanh2005/TX/issues](https://github.com/nguyenmanh2005/TX/issues)
> 
> Ban quản trị cam kết sẽ phản hồi, tiếp nhận và **tiến hành gỡ bỏ hoặc cập nhật thông tin tài nguyên theo đúng yêu cầu của tác giả trong vòng 24 đến 48 giờ làm việc**, trên tinh thần tôn trọng tối đa quyền tác giả và quy chuẩn quốc tế.

---

### 13.7 📜 Bản Quyền Mã Nguồn & Giấy Phép Dự Án

- **Đơn Vị Phát Triển:** Đội ngũ phát triển độc quyền GTLM Core Team (nguyenmanh2005/TX).
- **Phân Loại Dự Án:** **Dự án Nghiên Cứu Giáo Dục, Học Tập, Giải Trí Thuần Túy & PHI LỢI NHUẬN (100% Non-Profit Educational / Academic / Research Prototype)**.
- **Giấy Phép:** Dự án nhằm mục đích giải trí, học tập VÀ PHI LỢI NHUẬN.
- **Ràng Buộc Sử Dụng:** Nghiêm cấm mọi hành vi sao chép, đóng gói, phân phối lại hoặc sử dụng mã nguồn của dự án này nhằm mục đích thương mại, thu phí, trục lợi tài chính hoặc tổ chức các hoạt động đánh bạc tiền thật trái pháp luật.

---
*⭐ Chúc xếp và cộng đồng người chơi có những giây phút trải nghiệm giải trí thăng hoa cùng GTLM Gaming Platform! ⭐*

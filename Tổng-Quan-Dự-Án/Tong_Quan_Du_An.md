# TỔNG QUAN CHI TIẾT DỰ ÁN GIẢI TRÍ LÀNH MẠNH (GTLM)

Dưới đây là Tài liệu Thiết kế Hệ thống (System Design & Overview) phiên bản Đầy đủ và Chi tiết nhất của siêu dự án Web-Game "Giải Trí Lành Mạnh". Dự án không chỉ là một cổng game thông thường, mà được xây dựng như một "Vũ Trụ Ảo" (Micro-Universe) với hệ thống Kinh tế phức tạp, Xã hội tương tác sâu và một Quân đoàn AI (Bot Army) thông minh tự hoạt động 24/7.

---

## PHẦN 1: HỆ THỐNG VAI TRÒ VÀ NGƯỜI DÙNG (ROLES & ENTITIES)

Hệ thống xoay quanh sự tương tác liên tục giữa Người chơi thật và Trí tuệ nhân tạo (Bot), tạo ra một cộng đồng luôn nhộn nhịp.

### 1. Người Chơi Thật (Real Users)
*   **Role 0 (Thường dân/Dân chơi)**:
    *   Sở hữu tài khoản cá nhân, cày cuốc GTLM (Đơn vị GTLM tệ chính).
    *   Tự do tham gia hơn 40 trò chơi, gia nhập Bang hội, kết bái Sư - Đồ.
    *   Được vinh danh trên Bảng Xếp Hạng (Leaderboard) hoặc Biên Niên Sử Server.
*   **Role 1 (Quản trị viên / Admin)**:
    *   Kiểm soát vĩ mô: Bật/tắt Sự kiện mùa giải, thiết lập Lời Tiên Tri.
    *   Giám sát qua `admin_dashboard.php` và `bot_intelligence.php` (Phòng phân tích trí tuệ Bot).
    *   Sử dụng hệ thống "Tester Bot" để tự động rà quét và kiểm toán lỗ hổng bảo mật.

### 2. Quân Đoàn AI - Bot Army (Hệ sinh thái tự vận hành)
Hệ thống sở hữu **136 Bot AI** được lập trình tinh vi qua `bot_engine.php` và `bot_brain.php`. Chúng không chỉ chơi game mà còn "sống" trong server:
*   **A. Phân cấp Tài sản (Wealth Tiers)**:
    *   `Whales (Đại gia)`: Sở hữu hàng tỷ GTLM. Thường xuyên cược lớn (All-in), chơi Baccarat, Poker. Thích khoe khoang sự giàu có trên Chat.
    *   `Commoners (Dân cày)`: Chơi an toàn, vốn nhỏ. Thường chơi Xí ngầu, Chiến Trường Linh Thú, tích tiểu thành đại.
*   **B. Phân cấp Trách nhiệm Xã hội (Social Roles)**:
    *   `Reporters (Phóng viên)`: Khi có sự kiện chấn động (Ví dụ: Ai đó trúng Jackpot chục tỷ, Boss Hắc Long Thần bị tiêu diệt), Phóng viên sẽ tự động viết bài đưa tin lên Bảng tin (Social Feed).
    *   `Influencers (Người tạo Trend)`: Chuyên đi thả tim, bình luận dạo, khích bác hoặc chúc mừng người chơi thật để tăng độ tương tác.
*   **C. Trí Tuệ Cảm Xúc & Ký Ức (Mood & Rivalry System)**:
    *   `Mood (Tâm trạng)`: Cập nhật liên tục. Nếu bot thua liên tiếp 5 ván, trạng thái chuyển sang "Cay cú" (Tilted) hoặc "Trầm cảm" (Depressed), dẫn đến việc cược Chiến mạng hơn. Nếu thắng, trạng thái là "Excited" (Hưng phấn).
    *   `Rivalry (Kẻ thù)`: Nếu một người chơi thật liên tục đánh bại Bot trong game PvP (như Đua ngựa PvP), Bot sẽ nhớ mặt (lưu ID) và ghim thù, sẵn sàng khịa trên kênh Chat.

---

## PHẦN 2: CÁC TÍNH NĂNG CỐT LÕI (CORE SYSTEMS)

### 1. Hệ thống Kinh Tế & Cửa Hàng (Economy & Shop)
*   **GTLM tệ kép**: `GTLM` dùng để cược và mua sắm cơ bản. `Xu Sự Kiện` chỉ xuất hiện và có giá trị trong mùa event hiện tại.
*   **Cửa hàng Tùy chỉnh (Vanity Shop)**: Nơi "đốt GTLM" của đại gia. Mua các vật phẩm trang trí như: `Danh hiệu (Titles)` hiển thị trên đầu, `Khung Avatar (Avatar Frames)`, `Khung Chat` rực rỡ, `Giao diện nền (Themes)` và `Con trỏ chuột (Cursors)`.
*   **Chợ Giao Dịch (Marketplace)**: Chợ đen tự do. Người chơi treo bán vật phẩm không dùng đến. Các Bot thi thoảng sẽ đóng vai trò "Market Maker" (Tạo thanh khoản) bằng cách thu mua đồ ế hoặc bán rẻ đồ hiếm.

### 2. Hệ thống Tương Tác Xã Hội (Social Hub)
*   **Mạng Xã Hội Thu Nhỏ (Social Feed)**: Tính năng giống Facebook. Post status, up ảnh, thả tim, và bình luận. Bot sẽ vào tương tác liên tục.
*   **Trung tâm Sư Đồ (Mentor Center)**: Người cấp cao (Mentor) nhận người mới (Apprentice). Đệ tử cày level, Sư phụ nhận hoa hồng XP và GTLM.
*   **Kênh Chat Toàn Cầu & Chat Bang Hội**: Tích hợp tag tên (@mention), chống spam (Rate limit) và bộ lọc từ khóa.

### 3. Hệ thống Thăng Tiến (Progression & BP)
*   **Battle Pass (Thẻ Mùa Giải)**: Chia làm nhánh Free (Miễn phí) và Premium (Trả phí). Cày level từ 1 đến 100 thông qua việc làm Nhiệm vụ (Ngày/Tuần) để mở khóa hòm đồ, VIP, Danh hiệu độc quyền.
*   **Nhiệm vụ Động (Quests)**: "Chơi 5 ván Baccarat", "Nổ hũ 1 lần", "Thắng PvP". Cập nhật mới mỗi ngày.

### 4. Hệ thống Sự Kiện & Giải Đấu Mùa (Events & Tournaments)
*   **Đại Sảnh Sự Kiện (Event Hub)**: Dashboard quản lý tổng hợp. Tự động đổi màu sắc giao diện (Theme_Config) tuỳ theo dịp (Halloween, Tết, Valentine).
*   **Chuỗi Nhiệm vụ Mùa (Event Mission Chains)**: Các nhiệm vụ liên hoàn. Làm xong mốc 1 mới mở mốc 2, phần thưởng tăng dần độ hiếm.
*   **Lời Tiên Tri (Oracle Prophecy)**: Một dạng "Đảo Luật chơi". Đầu tuần, NPC Tiên Tri đưa ra 3 lời sấm truyền (VD: "Tuần sau XP x2" hoặc "Tuần sau Jackpot dễ nổ hơn"). Toàn server cùng bỏ GTLM Vote. Lời tiên tri nào thắng sẽ thành hiện thực.
*   **Biên Niên Sử Server (Server Chronicles)**: Cuốn sách lưu danh lịch sử. Kẻ nào diệt Boss, kẻ nào nổ hũ to nhất lịch sử đều được khắc tên mãi mãi.

### 5. Boss Thế Giới & Xổ Số Cộng Đồng (Co-op PVE)
*   **World Boss (Hắc Long Thần)**: Sự kiện "Triệu hồi Boss". Boss có 1 tỷ HP. Mọi người chơi và Bot cùng ném GTLM vào rỉa máu. Có tính sát thương chí mạng. Hệ thống xếp hạng "Top Sát Thương" để trao thưởng đồ Đỏ (Đồ cực hiếm).
*   **Mục Tiêu Cộng Đồng (Community Goal)**: Thanh tiến độ chung toàn server. Ví dụ: "Cả server đạt 1 triệu lượt cược hôm nay sẽ kích hoạt X2 kinh nghiệm toàn máy chủ vào ngày mai".

---

## PHẦN 3: BÁCH KHOA TOÀN THƯ TRÒ CHƠI (>40 GAMES)

Thư viện game khổng lồ phục vụ mọi sở thích, từ tính toán chiến thuật đến thử thách nhân phẩm.

### A. Game Bài Casino & Thể Thức Lớn (Card Games)
Trọng tâm của những ván cược tỷ GTLM:
1.  **Sâm Lốc Tốc Độ & Tứ Sắc**: Đậm chất dân gian Việt Nam.
2.  **Baccarat Premium**: Player vs Banker, thống kê cầu chi tiết.
3.  **Xì Dách (Blackjack)**: 3 phiên bản: Classic, Royale (đồ họa VIP) và Multiplayer (Nhiều người ngồi chung bàn).
4.  **Poker Texas Hold'em**: Tố (Raise), Theo (Call), Bỏ (Fold) - Đấu trí đỉnh cao.
5.  **Long Hổ (Dragon Tiger)**: Game nhịp độ cực nhanh, 1 lá phân định thắng thua.
6.  **Hệ Sinh Thái Poker Khác**: Three Card Poker, Let It Ride, Pai Gow, Caribbean Stud, Casino Hold'em, Red Dog, Video Poker.
7.  **Casino War & Pontoon**.

### B. Slots & Vòng Quay May Mắn (Luck & Lottery)
Hệ thống rủi ro cao - Phần thưởng khủng:
1.  **Slot Machine (Nổ Hũ)**: Các line trúng thưởng chéo/ngang. Có cơ chế Nổ Hũ Rồng Thần (Jackpot chung toàn server).
2.  **Roulette 3D**: Bàn quay Cò quay phong cách Châu Âu.
3.  **Hệ thống Xổ Số**: Xổ Số Cộng Đồng (Mua chung vé), Vietlott, Keno Premium, Bingo Club.
4.  **Vòng Quay (Lucky Wheel)**: Vòng quay miễn phí hàng ngày (Tích hợp bản Vòng Quay Tết đặc biệt).
5.  **Rút Thăm (Raffle)**: Bỏ GTLM mua Ticket chờ quay số cuối tuần.

### C. Mini Games, Cược Nhanh & Arcade (Fast-Paced Games)
Những trò chơi đánh nhanh rút gọn, có tính gây nghiện cao:
1.  **Crash Flight (Tên lửa / Máy bay)**: Tên lửa bay, hệ số x1, x2, x10... tăng liên tục. Phải "Chốt Lời" trước khi tên lửa nổ tung. Trò chơi cân não nhất.
2.  **Mines (Dò Mìn Premium) & Minesweeper**: Có 25 ô, chọn số lượng mìn. Càng bước trúng nhiều kim cương, hệ số nhân càng to.
3.  **Limbo Rocket**: Tự đặt mục tiêu (Ví dụ x100). Nếu hệ thống quay ra số > 100 thì thắng đậm.
4.  **Plinko Royale**: Thả bóng vật lý từ trên chóp tháp. Bóng rơi đập vào đinh (Pegs) rồi lọt vào lỗ x0.5, x2 hay x100.
5.  **Hệ Đua Thú (Racing)**: Đá Gà Premium, Đua Thú, Đua Ngựa Pari-Mutuel. Đặc biệt có **Đua Ngựa PvP** (Người chơi tự đặt cược thi với nhau).
6.  **Hệ Xúc Xắc (Dice)**: Dice (Lắc Xí Ngầu), Sicbo (Xanh Đỏ), Craps, Yahtzee.
7.  **Dân Gian & Giải Trí Khác**: Chiến Trường Linh Thú (Cyber Pets), Xóc Đĩa (Quantum Pulse), Oẳn Tù Tì (RPS), Tung Đồng Xu (Coinflip), Đoán Số, Cào Thẻ, Đoán Màu Bài.
8.  **Thách Đấu PvP (1vs1)**: Cho phép 2 người chơi tự gạ kèo nhau, server làm trọng tài ăn phần trăm (Rake).
9.  **Arcade Nhập Vai**: Đại Chiến JoJo, Hộp Mù (Gacha Mở Hộp), Tower Climb (Leo tháp sinh tồn).

---

## PHẦN 4: BẢO MẬT & VẬN HÀNH (SECURITY & ENGINE)

Để duy trì một hệ thống có kinh tế và hàng trăm Bot chạy liên tục, dự án trang bị cơ sở hạ tầng cực kỳ vững chắc:

1.  **Bot Kiểm Toán Chuyên Sâu (`tester_bot.php`)**:
    *   Tool độc quyền của Admin. Khi kích hoạt, Bot Tester sẽ "tấn công" (Audit) trực tiếp vào hệ thống của chính nó để tìm bug.
    *   **Anti-Negative Balance**: Quét và ngăn chặn lỗi người chơi nhập GTLM cược là số âm (`-5000`) hoặc số thập phân.
    *   **Anti-Integer Overflow**: Ngăn lỗi nhập số cược quá lớn (`999999999999999999`) làm sập tràn RAM.
    *   **Logic Integrity**: Cố tình hack vượt cấp Battle Pass, mua số lượng âm trong Shop Sự Kiện, cố tình chèn XSS (`<script>`) vào lời Tiên tri. Bot sẽ báo cáo nếu phát hiện rò rỉ.
2.  **Bảo vệ Giao dịch (Database Transactions)**:
    *   Tất cả giao dịch chuyển GTLM (Chợ, PvP, Trúng thưởng) đều được bao bọc bởi cấu trúc `BEGIN TRANSACTION` và `COMMIT` của MySQL, đảm bảo không bao giờ bị nhân đôi GTLM khi mạng lag (Race conditions).
3.  **Hoạt động Xuyên Suốt (CLI & Cronjob)**:
    *   Tích hợp sẵn `.env.php` và mã nguồn nhận dạng môi trường (CLI/CGI) để Admin dễ dàng quăng lên VPS (Ubuntu/CentOS), thiết lập Cronjob cho Bot tự chạy mà không cần mở trình duyệt.

## TỔNG KẾT & TẦM NHÌN
Dự án "Giải Trí Lành Mạnh" đã vượt qua ranh giới của một Website giải trí thông thường. Với **hệ sinh thái Kinh tế khép kín**, **hàng chục Game đồ sộ**, **tương tác Xã hội có chiều sâu** và đặc biệt là **Mô hình AI Bot Army thao túng tâm lý**, dự án đủ khả năng mang lại một trải nghiệm giữ chân người dùng (Retention) vô cùng mạnh mẽ, sống động và hấp dẫn từng giây từng phút.

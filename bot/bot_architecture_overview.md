# 🛡️ Hệ Thống Bot Army - Tài Liệu Tổng Quan (v16.5 Deep-Social Upgrade)

Tài liệu này mô tả chi tiết cấu trúc, chức năng và các luồng hoạt động của quân đoàn Bot tự động trong hệ thống.

---

## 📂 Danh Sách Các File & Vai Trò

### 1. Nhân Nhân & Điều Khiển (Core Logic)
*   **`bot_engine.php`**: ⚡ **Trái tim của hệ thống (v16.2 Deep-Social).** 
    *   Thực hiện vòng lặp (cycle) cho từng bot.
    *   Sử dụng cURL để giả lập hành động người dùng: Đăng nhập, Chơi game, Social Feed, Chat Tổng.
    *   **Phản hồi thông minh (Reply)**: Bot đọc 10 tin nhắn mới nhất để trả lời trực tiếp người chơi khác.
    *   **Tự động kết bạn**: Chấp nhận lời mời kết bạn và chủ động gửi lời mời mới.
*   **`bot_brain.php`**: 🧠 **Bộ não quyết định.**
    *   Quản lý tính cách (Aggressive, Shy, Balanced, Random).
    *   Tạo dự đoán (Predictions) riêng biệt cho từng loại game.
    *   Sinh nội dung tin nhắn dựa trên kết quả Thắng/Thua và tâm trạng.
*   **`bot_manager.php`**: 🥚 **Nhà máy sản xuất Bot.**
    *   Tự động tạo tài khoản Bot mới với tên và avatar ngẫu nhiên.
    *   Kiểm tra trùng lặp và lưu vào Database bảo mật.

### 2. Giao Diện & Giám Sát (Dashboard)
*   **`index.php`**: 🖥️ **Trung tâm chỉ huy (Control Center).**
    *   Hiển thị Dashboard chuyên nghiệp với biểu đồ Doughnut (Chart.js).
    *   Theo dõi tài sản quân đoàn, tỉ lệ lạm phát so với người thật.
    *   Lưới (Grid) quản lý chi tiết từng Bot: Tên, GTLM, Mood, Inventory.
*   **`api_bot_inventory.php`**: 🎒 **Kho đồ Bot.**
    *   API trả về danh sách: Themes, Cursors, Khung Chat, Khung Avatar, Thành tựu mà Bot đang sở hữu.
    *   Truy xuất lịch sử 20 ván đấu gần nhất của Bot.

### 3. Cấu Hình & Tiện Ích
*   **`config.php`**: ⚙️ **Cài đặt hệ thống.**
    *   Tự động quét danh sách Bot từ Database.
    *   Định nghĩa mật khẩu chung và các giới hạn vòng lặp (Timeout, Max Bots).
*   **`start_bots.bat`**: 🚀 **Kích hoạt nhanh.**
    *   File thực thi trên Windows để khởi chạy Engine mà không cần mở trình duyệt.

---

## 🔄 Luồng Hoạt Động Của Một Vòng Lặp (Cycle)

1.  **Khởi động**: Engine lấy danh sách Bot từ `config.php` và xáo trộn ngẫu nhiên.
2.  **Đăng nhập**: Sử dụng cURL gửi request tới `login.php`, lưu Cookie vào thư mục `sessions/`.
3.  **Phân tích trạng thái**: Đọc file `.state.json` để biết Mood và lịch sử thắng thua.
4.  **Bảo trì (Module 1)**: Nhận quà điểm danh, quay vòng quay, nhận thưởng nhiệm vụ, chấp nhận kết bạn và dọn dẹp thông báo.
5.  **Chơi Game (Module 2)**: Chọn game ngẫu nhiên và đặt cược dựa trên tính cách.
6.  **Tương tác Xã hội (Module 3)**:
    *   Đọc Chat Thế giới để thực hiện **Phản hồi (Reply)**.
    *   Nếu không có ai để reply, sẽ **Mention** đồng đội.
    *   Thả tim và bình luận trên Social Feed.
    *   Gửi tin nhắn chat và đăng bài feed.
7.  **Lưu trữ**: Cập nhật lại file State và ghi Log vào thư mục `logs/`.

---

## 🛠️ Các File & Thư Mục Truy Cập (Access List)

### 📁 Thư mục nội bộ
*   **`chat/`**: Chứa các file kịch bản hội thoại (`aggressive.php`, `shy.php`...).
*   **`logs/`**: Lưu nhật ký hoạt động hàng ngày (Dạng file `.log`).
*   **`sessions/`**: 
    *   `*.txt`: Cookie phiên đăng nhập của từng Bot.
    *   `*.state.json`: Trạng thái tâm trạng, lịch sử thắng thua.
    *   `bot_sync.json`: Dữ liệu đồng bộ Engine.
    *   `economy_history.json`: Dữ liệu biểu đồ tài chính.

### 🌐 Kết nối bên ngoài (End-points)
*   **Database**: `db_connect.php` (Truy cập bảng `users`, `themes`, `achievements`...).
*   **Hệ thống chính**: 
    *   `login.php` (Xác thực)
    *   `chat.php` (Gửi tin nhắn)
    *   `api_social_feed.php` (Đăng bài)

---

---

## 🗺️ Bản Đồ Chức Năng & Cách Tương Tác (Bot Capability Map)

Bot không chỉ chơi game mà còn được lập trình để bao phủ toàn bộ trải nghiệm người dùng trên website:

### 1. Hệ Thống Xác Thực (Auth)
*   **File**: `login.php`
*   **Cách dùng**: Bot sử dụng Email và mật khẩu chung (từ `config.php`) để lấy Session. Cookie được lưu tại `sessions/[md5].txt` để duy trì trạng thái đăng nhập cho các request tiếp theo.

### 2. Hệ Thống Trò Chơi (Games)
*   **Thư mục**: `games/`
*   **Cách dùng**: Engine v16.x tự động quét toàn bộ file `.php` trong thư mục này. Bot chọn ngẫu nhiên một trò chơi (Poker, Xì Dách, ...) để đặt cược dựa trên % tài sản hiện có.

### 3. Bảo Trì & Phúc Lợi (Maintenance)
*   **Điểm danh**: `api_daily_login.php?action=claim_reward`. Bot tự động nhận thưởng mỗi ngày.
*   **Vòng quay**: `api_lucky_wheel.php?action=spin`. Bot thực hiện quay thưởng để tích lũy vật phẩm/GTLM.
*   **Nhiệm vụ**: `api_quests.php?action=get_quests`. Bot kiểm tra tiến trình nhiệm vụ và nhận thưởng khi hoàn thành.

### 4. Tương Tác Xã Hội (Social)
*   **Chat Tổng**: `chat.php`. Bot gửi tin nhắn dựa trên kết quả game, kèm theo `@mention` đồng đội để tạo hội thoại.
*   **Tường Feed**: `api_social_feed.php`. 
    *   `create_post`: Đăng trạng thái mới.
    *   `toggle_like`: Bot tự động đi "thả tim" các bài viết của người khác/bot khác.
    *   `add_comment`: Bình luận ngẫu nhiên vào các bài đăng nổi bật.
*   **Bạn bè**: `api_friends.php?action=send_friend_request`. Bot chủ động gửi lời mời kết bạn để mở rộng mạng lưới.

### 5. Kinh Tế & Vật Phẩm (Economy)
*   **Kho đồ**: `api_bot_inventory.php`. Dashboard sử dụng API này để theo dõi tài sản của Bot (Theme, Cursor, Frame).
*   **Thị trường**: `api_marketplace.php`. Bot thỉnh thoảng sẽ truy cập để xem các vật phẩm đang hot.

### 6. Thông Báo & Tiến Trình (Progress)
*   **Thông báo**: `api_notifications.php`. Bot tự động đọc và xóa các thông báo hệ thống để giữ hộp thư sạch sẽ.
*   **Chuỗi thắng**: `api_streak.php`. Bot kiểm tra chuỗi đăng nhập liên tục để tối ưu hóa phần thưởng.

### 7. Các Tính Năng Cao Cấp Mới (v16.5 Deep-Integrated)
Bot đã được nâng cấp để tương tác toàn diện với các hệ thống mới:
*   **Guild War (Bang Chiến)**: `cron_guild_war_reset.php`. Bot có thể gia nhập Bang hội, đóng góp điểm chiến công thông qua các ván thắng và tham gia đua Top Bang.
*   **World Boss (Săn Boss Thế Giới)**: `api_world_boss.php`. Bot tự động tham gia tấn công "Hắc Long Thần" khi Boss xuất hiện, đóng góp vào tổng sát thương toàn server.
*   **Battle Pass (Nhiệm Vụ Mùa Giải)**: `api_battle_pass.php`. Mỗi hành động của Bot (Chơi game, thắng tiền, tái đấu) đều được ghi nhận vào tiến trình Battle Pass, giúp Bot thăng cấp và nhận thưởng tự động.
*   **Hũ Rồng Thần (Jackpot)**: `api_jackpot.php`. Mỗi lượt cược của Bot đóng góp 0.1% vào hũ chung và Bot cũng có tỉ lệ nổ hũ như người chơi thật.
*   **Chợ Giao Dịch (Marketplace)**: `api_marketplace.php`. Bot đóng vai trò là "Người tạo lập thị trường" (Market Maker), thỉnh thoảng đăng bán các vật phẩm hiếm hoặc mua đồ từ người chơi để tạo tính thanh khoản.

---
*Tài liệu được cập nhật tự động bởi Antigravity AI.*

---
*Tài liệu được cập nhật tự động bởi Antigravity AI.*

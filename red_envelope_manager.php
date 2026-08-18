<?php
/**
 * Red Envelope Manager — Quản Lý Lì Xì & Phát Lộc GTLM (4B)
 * [NEW FILE] - Hoạt động độc lập, tuân thủ Rule 1 (Transaction), Rule 5.3 (Từ vựng)
 * Không ghi đè lên bất kỳ file cũ nào của hệ thống
 */

if (!defined('RED_ENVELOPE_LOADED')) {
    define('RED_ENVELOPE_LOADED', true);
}

class RedEnvelopeManager {
    private $conn;

    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
    }

    /**
     * Áp dụng từ vựng chuẩn dự án theo Rule 5.3
     */
    private function applyVocabulary($text) {
        $replacements = [
            '/thắng\s+GTLM/ui' => 'húp GTLM',
            '/thua\s+GTLM/ui' => 'bay màu',
            '/cá\s+cược/ui' => 'giao lưu thử vận',
            '/đặt\s+cược/ui' => 'ra chiêu',
            '/sòng\s+bài/ui' => 'trận địa',
            '/casino/ui' => 'trận địa'
        ];
        foreach ($replacements as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }
        return $text;
    }

    /**
     * Kiểm tra và thông báo nếu bảng DB chưa tạo
     */
    public function checkTables() {
        $res1 = $this->conn->query("SHOW TABLES LIKE 'red_envelopes'");
        $res2 = $this->conn->query("SHOW TABLES LIKE 'red_envelope_claims'");
        return ($res1 && $res1->num_rows > 0 && $res2 && $res2->num_rows > 0);
    }

    /**
     * Tạo bao lì xì mới (Người chơi thật hoặc Bot)
     */
    public function createEnvelope($senderId, $senderName, $senderAvatar, $totalAmount, $totalCount, $message, $type = 'random', $isBot = false) {
        if (!$this->checkTables()) {
            return ['success' => false, 'message' => 'Bảng red_envelopes chưa tồn tại. Vui lòng chạy block SQL!'];
        }

        $totalAmount = floatval($totalAmount);
        $totalCount = intval($totalCount);
        if ($totalAmount <= 0 || $totalCount <= 0) {
            return ['success' => false, 'message' => 'Số GTLM và số lượng bao phải lớn hơn 0!'];
        }

        if ($totalAmount / $totalCount < 100) {
            return ['success' => false, 'message' => 'Mỗi bao lì xì tối thiểu phải chứa 100 GTLM!'];
        }

        $message = $this->applyVocabulary(trim($message));
        if (empty($message)) {
            $message = "Phát lộc rực rỡ, chúc đạo hữu húp đậm GTLM!";
        }

        // Nếu không phải bot, tiến hành trừ GTLM bằng Transaction & row lock
        if (!$isBot) {
            $this->conn->begin_transaction();
            try {
                $stmtLock = $this->conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
                $stmtLock->bind_param("i", $senderId);
                $stmtLock->execute();
                $resLock = $stmtLock->get_result();
                $userRow = $resLock->fetch_assoc();
                $stmtLock->close();

                if (!$userRow || floatval($userRow['Money']) < $totalAmount) {
                    $this->conn->rollback();
                    return ['success' => false, 'message' => 'Số dư GTLM trong nick không đủ để phát lì xì!'];
                }

                $stmtSub = $this->conn->prepare("UPDATE users SET Money = Money - ? WHERE Iduser = ?");
                $stmtSub->bind_param("di", $totalAmount, $senderId);
                $stmtSub->execute();
                $stmtSub->close();
            } catch (Throwable $e) {
                $this->conn->rollback();
                return ['success' => false, 'message' => 'Lỗi giao dịch trừ GTLM: ' . $e->getMessage()];
            }
        } else {
            $this->conn->begin_transaction();
        }

        try {
            $stmtInsert = $this->conn->prepare("
                INSERT INTO red_envelopes (sender_id, sender_name, sender_avatar, total_amount, remaining_amount, total_count, remaining_count, message, type, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            $stmtInsert->bind_param("issddiiss", $senderId, $senderName, $senderAvatar, $totalAmount, $totalAmount, $totalCount, $totalCount, $message, $type);
            $stmtInsert->execute();
            $envelopeId = $stmtInsert->insert_id;
            $stmtInsert->close();

            // Đăng tin nhắn thông báo phát lộc lên Kênh Chat
            $chatMsg = "🧧 Vừa phát Mưa Lì Xì trị giá <b>" . number_format($totalAmount) . " GTLM</b> (" . $totalCount . " bao). Đạo hữu nhanh tay giật lộc nào! [LÌ XÌ #{$envelopeId}]";
            $stmtChat = $this->conn->prepare("INSERT INTO chat_messages (user_id, username, message, avatar, created_at) VALUES (?, ?, ?, ?, NOW())");
            if ($stmtChat) {
                $stmtChat->bind_param("isss", $senderId, $senderName, $chatMsg, $senderAvatar);
                $stmtChat->execute();
                $stmtChat->close();
            }

            $this->conn->commit();
            return [
                'success' => true,
                'envelope_id' => $envelopeId,
                'message' => 'Phát lì xì thành công! Mưa lì xì đã rơi trên toàn Kênh Chat.'
            ];
        } catch (Throwable $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => 'Lỗi tạo lì xì: ' . $e->getMessage()];
        }
    }

    /**
     * Giật Lì Xì (An toàn tuyệt đối với Transaction & FOR UPDATE)
     */
    public function claimEnvelope($envelopeId, $userId, $username, $avatar, $isBot = false) {
        if (!$this->checkTables()) {
            return ['success' => false, 'message' => 'Bảng DB chưa sẵn sàng.'];
        }

        $envelopeId = intval($envelopeId);
        $userId = intval($userId);

        $this->conn->begin_transaction();
        try {
            // Kiểm tra xem người dùng này đã giật bao này chưa
            $stmtCheckClaim = $this->conn->prepare("SELECT id FROM red_envelope_claims WHERE envelope_id = ? AND user_id = ? FOR UPDATE");
            $stmtCheckClaim->bind_param("ii", $envelopeId, $userId);
            $stmtCheckClaim->execute();
            $claimedBefore = $stmtCheckClaim->get_result()->fetch_assoc();
            $stmtCheckClaim->close();

            if ($claimedBefore) {
                $this->conn->rollback();
                return ['success' => false, 'message' => 'Bạn đã giật bao lì xì này rồi! Hãy nhường cho các đạo hữu khác.'];
            }

            // Khóa dòng red_envelopes để tính toán chia GTLM chính xác
            $stmtLockEnv = $this->conn->prepare("SELECT * FROM red_envelopes WHERE id = ? FOR UPDATE");
            $stmtLockEnv->bind_param("i", $envelopeId);
            $stmtLockEnv->execute();
            $env = $stmtLockEnv->get_result()->fetch_assoc();
            $stmtLockEnv->close();

            if (!$env || $env['status'] !== 'active' || $env['remaining_count'] <= 0 || $env['remaining_amount'] <= 0) {
                $this->conn->rollback();
                return ['success' => false, 'message' => 'Bao lì xì này đã bị giật hết sạch! chúc đạo hữu may mắn lần sau.'];
            }

            $remCount = intval($env['remaining_count']);
            $remAmount = floatval($env['remaining_amount']);
            $type = $env['type'];

            // Tính toán số GTLM nhận được
            $claimAmount = 0;
            if ($remCount === 1) {
                $claimAmount = $remAmount;
            } else {
                if ($type === 'equal') {
                    $claimAmount = round($remAmount / $remCount, 2);
                } else {
                    // Thuật toán May Mắn (Random Red Packet): tối đa gấp đôi trung bình
                    $maxPossible = ($remAmount / $remCount) * 2;
                    $minPossible = 100;
                    if ($maxPossible <= $minPossible) {
                        $claimAmount = $remAmount / $remCount;
                    } else {
                        $randFactor = mt_rand(10, 190) / 100.0;
                        $claimAmount = round(($remAmount / $remCount) * $randFactor, 2);
                        if ($claimAmount > $remAmount - (($remCount - 1) * 100)) {
                            $claimAmount = $remAmount - (($remCount - 1) * 100);
                        }
                        if ($claimAmount < 100) $claimAmount = 100;
                    }
                }
            }

            $claimAmount = round($claimAmount, 2);
            $newRemCount = $remCount - 1;
            $newRemAmount = $remAmount - $claimAmount;
            $newStatus = ($newRemCount <= 0 || $newRemAmount <= 0) ? 'empty' : 'active';

            // Cập nhật lại số dư bao lì xì
            $stmtUpEnv = $this->conn->prepare("UPDATE red_envelopes SET remaining_amount = ?, remaining_count = ?, status = ? WHERE id = ?");
            $stmtUpEnv->bind_param("disi", $newRemAmount, $newRemCount, $newStatus, $envelopeId);
            $stmtUpEnv->execute();
            $stmtUpEnv->close();

            // Ghi nhận lịch sử giật
            $stmtInsClaim = $this->conn->prepare("INSERT INTO red_envelope_claims (envelope_id, user_id, username, avatar, amount, claimed_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmtInsClaim->bind_param("iissd", $envelopeId, $userId, $username, $avatar, $claimAmount);
            $stmtInsClaim->execute();
            $stmtInsClaim->close();

            // Cộng GTLM vào tài khoản nếu là người thật
            if (!$isBot) {
                $stmtLockUser = $this->conn->prepare("SELECT Iduser FROM users WHERE Iduser = ? FOR UPDATE");
                $stmtLockUser->bind_param("i", $userId);
                $stmtLockUser->execute();
                $stmtLockUser->close();

                $stmtAddMoney = $this->conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
                $stmtAddMoney->bind_param("di", $claimAmount, $userId);
                $stmtAddMoney->execute();
                $stmtAddMoney->close();
            }

            $isVip = ($env['total_amount'] >= 1000000 || $claimAmount >= 100000);
            $this->conn->commit();
            return [
                'success' => true,
                'amount' => $claimAmount,
                'sender_name' => $env['sender_name'],
                'is_vip' => $isVip,
                'fireworks_fx' => $isVip ? '3d_fireworks_burst' : null,
                'message' => "🎉 Chúc mừng! Bạn vừa giật được bao lì xì" . ($isVip ? " VIP 🎆" : "") . " trị giá " . number_format($claimAmount) . " GTLM từ " . $env['sender_name'] . "!"
            ];
        } catch (Throwable $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => 'Lỗi giao dịch giật lì xì: ' . $e->getMessage()];
        }
    }

    /**
     * Lấy danh sách các bao lì xì đang kích hoạt
     */
    public function getActiveEnvelopes() {
        if (!$this->checkTables()) return [];
        $res = $this->conn->query("
            SELECT * FROM red_envelopes 
            WHERE status = 'active' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            ORDER BY id DESC LIMIT 5
        ");
        $list = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $list[] = $row;
            }
        }
        return $list;
    }
}
?>

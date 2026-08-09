<?php
/**
 * EconomyHelper - Core Security Module
 * 
 * Quản lý toàn bộ giao dịch trừ/cộng GTLM GTLM của dự án.
 * Ngăn chặn Race Condition và Auto-Click Spam bằng Transaction và Row Lock (FOR UPDATE).
 */

class EconomyHelper {
    
    /**
     * Trừ GTLM an toàn.
     * @return bool True nếu đủ GTLM và trừ thành công, False nếu không đủ GTLM hoặc lỗi.
     */
    public static function deductMoney(mysqli $conn, int $userId, float $amount, string $reason = '') {
        if ($amount <= 0) return true; // Hoặc ném Exception nếu muốn
        
        try {
            $conn->begin_transaction();
            
            // Lock dòng của user này
            $stmtLock = $conn->prepare("SELECT Money FROM users WHERE Iduser = ? FOR UPDATE");
            $stmtLock->bind_param("i", $userId);
            $stmtLock->execute();
            $res = $stmtLock->get_result();
            if ($res->num_rows === 0) {
                $conn->rollback();
                return false; // User không tồn tại
            }
            $row = $res->fetch_assoc();
            $currentMoney = (float)$row['Money'];
            
            if ($currentMoney < $amount) {
                $conn->rollback();
                return false; // Không đủ GTLM
            }
            
            // Đủ GTLM -> Trừ
            $stmtUpd = $conn->prepare("UPDATE users SET Money = Money - ? WHERE Iduser = ?");
            $stmtUpd->bind_param("di", $amount, $userId);
            if ($stmtUpd->execute()) {
                $conn->commit();
                return true;
            } else {
                $conn->rollback();
                return false;
            }
        } catch (Exception $e) {
            $conn->rollback();
            return false;
        }
    }

    /**
     * Cộng GTLM an toàn.
     * @return bool
     */
    public static function addMoney(mysqli $conn, int $userId, float $amount, string $reason = '') {
        if ($amount <= 0) return true;
        
        try {
            $conn->begin_transaction();
            
            $stmtLock = $conn->prepare("SELECT Iduser FROM users WHERE Iduser = ? FOR UPDATE");
            $stmtLock->bind_param("i", $userId);
            $stmtLock->execute();
            $res = $stmtLock->get_result();
            if ($res->num_rows === 0) {
                $conn->rollback();
                return false; 
            }
            
            $stmtUpd = $conn->prepare("UPDATE users SET Money = Money + ? WHERE Iduser = ?");
            $stmtUpd->bind_param("di", $amount, $userId);
            if ($stmtUpd->execute()) {
                $conn->commit();
                return true;
            } else {
                $conn->rollback();
                return false;
            }
        } catch (Exception $e) {
            $conn->rollback();
            return false;
        }
    }
}
?>

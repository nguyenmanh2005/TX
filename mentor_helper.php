<?php
/**
 * Mentor System Helper
 */
class MentorHelper {
    /**
     * Checks if a new user has a mentor. If not, automatically assigns one.
     */
    public static function ensureMentor(mysqli $conn, int $userId): ?array {
        // Ensure user progress row exists first
        if (function_exists('up_ensure_row')) {
            up_ensure_row($conn, $userId);
        }

        // Check if user is already assigned a mentor
        $stmt = $conn->prepare("
            SELECT um.*, u.Name as mentor_name 
            FROM user_mentor_relationships um 
            JOIN users u ON um.mentor_id = u.Iduser 
            WHERE um.mentee_id = ?
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $assigned = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($assigned) {
            return $assigned;
        }

        // Check user level. Mentorship is only for new players (Level < 5)
        $stmt = $conn->prepare("SELECT level FROM user_progress WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $prog = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $level = $prog ? (int)$prog['level'] : 1;
        if ($level >= 5) {
            return null; // Not a new player
        }

        // Find experienced mentors (Level >= 5) excluding the user themselves
        $stmt = $conn->prepare("
            SELECT up.user_id, u.Name 
            FROM user_progress up
            JOIN users u ON up.user_id = u.Iduser
            WHERE up.level >= 5 AND up.user_id != ?
            ORDER BY RAND()
            LIMIT 1
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $mentor = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Fallback: If no level 5+ players exist, pick any active player who is not the user
        if (!$mentor) {
            $stmt = $conn->prepare("
                SELECT Iduser as user_id, Name 
                FROM users 
                WHERE Iduser != ? 
                ORDER BY RAND() 
                LIMIT 1
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $mentor = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }

        if (!$mentor) {
            return null; // No available users on the server
        }

        $mentorId = (int)$mentor['user_id'];
        $mentorName = $mentor['Name'];
        $todayStr = date('Y-m-d');

        // Assign mentor
        $stmt = $conn->prepare("
            INSERT INTO user_mentor_relationships (mentor_id, mentee_id, active_days_count, last_active_date)
            VALUES (?, ?, 1, ?)
        ");
        $stmt->bind_param("iis", $mentorId, $userId, $todayStr);
        if ($stmt->execute()) {
            $stmt->close();
            
            // Set flag in session to toast notification once
            $_SESSION['new_mentor_assigned'] = [
                'mentor_id' => $mentorId,
                'mentor_name' => $mentorName
            ];
            
            return [
                'mentor_id' => $mentorId,
                'mentee_id' => $userId,
                'mentor_name' => $mentorName,
                'active_days_count' => 1,
                'last_active_date' => $todayStr
            ];
        }
        $stmt->close();
        return null;
    }

    /**
     * Increments the active days count if a new calendar day has passed.
     */
    public static function trackMenteeActivity(mysqli $conn, int $userId): void {
        // Check if user is a mentee
        $stmt = $conn->prepare("SELECT * FROM user_mentor_relationships WHERE mentee_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $relation = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$relation) {
            return;
        }

        $todayStr = date('Y-m-d');
        $lastActiveDate = $relation['last_active_date'];

        if ($lastActiveDate !== $todayStr) {
            // Increment active days
            $newCount = (int)$relation['active_days_count'] + 1;
            
            $stmt = $conn->prepare("
                UPDATE user_mentor_relationships 
                SET active_days_count = ?, last_active_date = ? 
                WHERE mentee_id = ?
            ");
            $stmt->bind_param("isi", $newCount, $todayStr, $userId);
            $stmt->execute();
            $stmt->close();

            // If reached 7 days, trigger a system log/notification for the mentor
            if ($newCount === 7) {
                $mentorId = (int)$relation['mentor_id'];
                
                $nStmt = $conn->prepare("SELECT Name FROM users WHERE Iduser = ?");
                $nStmt->bind_param("i", $userId);
                $nStmt->execute();
                $nRes = $nStmt->get_result()->fetch_assoc();
                $menteeName = $nRes ? $nRes['Name'] : 'Tân thủ';
                $nStmt->close();

                // Insert system notification
                $msg = "🎉 Đệ tử [{$menteeName}] của bạn đã hoạt động tích cực đủ 7 ngày! Hãy vào Trung Tâm Sư Phụ để nhận thưởng 50.000 GTLM!";
                
                // Check if notifications table exists or has type
                $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())");
                if ($notifStmt) {
                    $notifStmt->bind_param("is", $mentorId, $msg);
                    $notifStmt->execute();
                    $notifStmt->close();
                }
            }
        }
    }
}

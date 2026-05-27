<?php
// SystemLogger.php - Centralized Logging & Monitoring Engine

class SystemLogger {
    private static $initialized = false;
    private static $dbConn = null;

    private static function init() {
        if (self::$initialized) {
            return true;
        }

        global $conn;
        if (isset($conn) && $conn instanceof mysqli) {
            self::$dbConn = $conn;
            self::$initialized = true;
            return true;
        }

        // Try to load db_connect.php
        $dbPath = __DIR__ . '/db_connect.php';
        if (file_exists($dbPath)) {
            require_once $dbPath;
            if (isset($conn) && $conn instanceof mysqli) {
                self::$dbConn = $conn;
                self::$initialized = true;
                return true;
            }
        }

        return false;
    }

    /**
     * Log message to app_logs database table
     */
    public static function log($level, $category, $message, $details = null, $userId = null) {
        if (!self::init()) {
            // Fallback to php error log if DB connection is unavailable
            error_log("[$level][$category] $message - Details: " . json_encode($details));
            return false;
        }

        $userId = $userId ?? ($_SESSION['Iduser'] ?? null);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $pageUrl = $_SERVER['REQUEST_URI'] ?? 'CLI';

        $detailsJson = $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null;

        // Insert into app_logs
        $stmt = self::$dbConn->prepare("INSERT INTO app_logs (log_level, category, message, details, user_id, ip_address, page_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ssssiss", $level, $category, $message, $detailsJson, $userId, $ip, $pageUrl);
            $stmt->execute();
            $stmt->close();
        } else {
            error_log("Failed to prepare app_logs insert: " . self::$dbConn->error);
        }

        // Alert on CRITICAL or ERROR levels
        if ($level === 'CRITICAL' || $level === 'ERROR') {
            self::triggerAlert($level, $message, $userId);
        }

        return true;
    }

    public static function debug($category, $message, $details = null, $userId = null) {
        return self::log('DEBUG', $category, $message, $details, $userId);
    }

    public static function info($category, $message, $details = null, $userId = null) {
        return self::log('INFO', $category, $message, $details, $userId);
    }

    public static function warning($category, $message, $details = null, $userId = null) {
        return self::log('WARNING', $category, $message, $details, $userId);
    }

    public static function error($category, $message, $details = null, $userId = null) {
        return self::log('ERROR', $category, $message, $details, $userId);
    }

    public static function critical($category, $message, $details = null, $userId = null) {
        return self::log('CRITICAL', $category, $message, $details, $userId);
    }

    /**
     * Set custom economy context session variables in MySQL before running update queries.
     * The trigger `trg_users_money_audit` will capture these variables and write them to economy_transaction_logs automatically.
     */
    public static function setEconomyContext($txType, $refId = null, $metadata = null) {
        if (!self::init()) return;
        
        $stmt1 = self::$dbConn->prepare("SET @economy_tx_type = ?");
        if ($stmt1) {
            $stmt1->bind_param("s", $txType);
            $stmt1->execute();
            $stmt1->close();
        }
        
        if ($refId) {
            $stmt2 = self::$dbConn->prepare("SET @economy_ref_id = ?");
            if ($stmt2) {
                $stmt2->bind_param("s", $refId);
                $stmt2->execute();
                $stmt2->close();
            }
        }
        
        if ($metadata) {
            $metaStr = is_array($metadata) ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : $metadata;
            $stmt3 = self::$dbConn->prepare("SET @economy_metadata = ?");
            if ($stmt3) {
                $stmt3->bind_param("s", $metaStr);
                $stmt3->execute();
                $stmt3->close();
            }
        }
    }

    /**
     * Clear economy context variables from current MySQL session
     */
    public static function clearEconomyContext() {
        if (!self::init()) return;
        self::$dbConn->query("SET @economy_tx_type = NULL, @economy_ref_id = NULL, @economy_metadata = NULL");
    }

    /**
     * Trigger real-time alert in admin_alerts
     */
    private static function triggerAlert($level, $message, $userId) {
        $severity = ($level === 'CRITICAL') ? 'critical' : 'warning';
        $type = 'system_error';
        
        $stmt = self::$dbConn->prepare("INSERT INTO admin_alerts (type, user_id, message, severity, is_resolved) VALUES (?, ?, ?, ?, 0)");
        if ($stmt) {
            $stmt->bind_param("siss", $type, $userId, $message, $severity);
            $stmt->execute();
            $stmt->close();
        }

        // Post to Chat 2 for immediate administrative visibility
        self::reportAlertToChat($level, $message);
    }

    /**
     * Send cURL alert to chat2.php for real-time notification
     */
    private static function reportAlertToChat($level, $msg) {
        // Build base URL
        $apiUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        if (strpos($_SERVER['REQUEST_URI'], '/1/') !== false) {
            $apiUrl .= "/1";
        }
        $apiUrl .= "/chat2.php";

        $pageUrl = $_SERVER['REQUEST_URI'] ?? 'CLI';
        $fullMessage = "🚨 [SYSTEM ALERT - $level]\nPage: $pageUrl\nError: $msg\nTime: " . date('Y-m-d H:i:s');
        
        $ch = curl_init($apiUrl);
        if ($ch) {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'message' => $fullMessage
            ]));
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            curl_exec($ch);
            curl_close($ch);
        }
    }
}
?>

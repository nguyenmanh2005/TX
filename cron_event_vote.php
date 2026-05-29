<?php
/**
 * Cron: Event Vote Trigger
 * ─────────────────────────────────────────────────────────────────
 * Chạy tự động để kiểm tra và áp dụng kết quả vote khi có event kết thúc.
 * (Wrapper của cron_event_vote_result.php)
 *
 * Crontab: * * * * * php /path/to/cron_event_vote.php
 */

if (php_sapi_name() !== 'cli' && !isset($_GET['debug'])) {
    http_response_code(403);
    die('Forbidden');
}

// Gọi trực tiếp script xử lý kết quả
require_once __DIR__ . '/cron_event_vote_result.php';

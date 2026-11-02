<?php
/**
 * 🚀 GTLM Realtime Publisher Helper
 * Cho phép các script PHP backend (chat.php, api_pvp_challenge.php...) 
 * bắn sự kiện Realtime 0ms tới WebSocket Server một cách an toàn và tức thì.
 */

class RealtimePublisher {
    const WS_SERVER_HOST = '127.0.0.1';
    const WS_SERVER_PORT = 8088;

    /**
     * Bắn sự kiện tới một kênh WebSocket
     * 
     * @param string $channel Kênh nhận (vd: 'chat', 'live_61', 'pvp_12')
     * @param string $event   Tên sự kiện (vd: 'chat_message', 'caro_move', 'typing')
     * @param array  $data    Dữ liệu payload
     * @return bool
     */
    public static function publish($channel, $event, $data = []) {
        $fp = @stream_socket_client("tcp://" . self::WS_SERVER_HOST . ":" . self::WS_SERVER_PORT, $errno, $errstr, 0.1);
        if (!$fp) {
            // WebSocket Server không chạy (sẽ fallback sang Polling/SSE tự động)
            return false;
        }

        stream_set_timeout($fp, 0, 100000); // 100ms timeout

        $body = json_encode([
            'channel' => $channel,
            'event'   => $event,
            'data'    => $data
        ], JSON_UNESCAPED_UNICODE);

        $request = "POST /publish HTTP/1.1\r\n" .
                   "Host: " . self::WS_SERVER_HOST . ":" . self::WS_SERVER_PORT . "\r\n" .
                   "Content-Type: application/json\r\n" .
                   "Content-Length: " . strlen($body) . "\r\n\r\n" .
                   $body;

        @fwrite($fp, $request);
        $res = @fread($fp, 512);
        @fclose($fp);

        return (strpos($res, '200 OK') !== false);
    }

    /**
     * Kiểm tra trạng thái WebSocket Gateway có đang online hay không
     */
    public static function isOnline() {
        $fp = @stream_socket_client("tcp://" . self::WS_SERVER_HOST . ":" . self::WS_SERVER_PORT, $errno, $errstr, 0.1);
        if ($fp) {
            fclose($fp);
            return true;
        }
        return false;
    }
}

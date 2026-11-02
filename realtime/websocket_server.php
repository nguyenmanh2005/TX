<?php
/**
 * ⚡ GTLM Realtime WebSocket Gateway Server (RFC 6455)
 * Hỗ trợ kênh tin nhắn tức thì 0ms cho Chat, PvP Cờ Ca-rô và LiveStream 61 Bàn.
 * Chạy độc lập qua PHP CLI (C:\xampp\php\php.exe realtime\websocket_server.php)
 */

define('WS_HOST', '0.0.0.0');
define('WS_PORT', 8088);
define('WS_MAGIC_GUID', '258EAFA5-E914-47DA-95CA-C5AB0DC85B11');

class GTLMWebSocketServer {
    private $masterSocket;
    private $clients = []; // socketId => socket resource
    private $handshakes = []; // socketId => bool
    private $clientInfo = []; // socketId => ['channels' => [], 'user_id' => 0, 'username' => '']
    private $channelSubscribers = []; // channel_name => [socketId => true]
    private $startTime;
    private $totalMessagesRouted = 0;

    public function __construct($host = WS_HOST, $port = WS_PORT) {
        $this->startTime = time();
        $context = stream_context_create();
        $address = "tcp://$host:$port";
        $this->masterSocket = @stream_socket_server($address, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);

        if (!$this->masterSocket) {
            die("❌ [WebSocket Server] Không thể khởi động tại $address: $errstr ($errno)\n");
        }

        stream_set_blocking($this->masterSocket, 0);
        echo "==========================================================\n";
        echo " ⚡ GTLM REALTIME WEBSOCKET GATEWAY V1.0 (RFC 6455)\n";
        echo " 🟢 Lắng nghe tại: ws://127.0.0.1:$port\n";
        echo " 📡 Kênh hỗ trợ: Chat Thế Giới, PvP Cờ Ca-rô, LiveStream 61 Bàn\n";
        echo " ⏱️ Khởi chạy lúc: " . date('Y-m-d H:i:s') . "\n";
        echo "==========================================================\n\n";
    }

    public function run() {
        while (true) {
            $read = array_merge([$this->masterSocket], array_values($this->clients));
            $write = null;
            $except = null;

            // Chờ sự kiện socket với timeout 1 giây
            if (@stream_select($read, $write, $except, 1) === false) {
                continue;
            }

            // Có kết nối mới tới Master Socket
            if (in_array($this->masterSocket, $read)) {
                $newClient = @stream_socket_accept($this->masterSocket, 0);
                if ($newClient) {
                    stream_set_blocking($newClient, 0);
                    $socketId = (int)$newClient;
                    $this->clients[$socketId] = $newClient;
                    $this->handshakes[$socketId] = false;
                    $this->clientInfo[$socketId] = [
                        'channels' => [],
                        'user_id' => 0,
                        'username' => 'Khách',
                        'connected_at' => time(),
                        'last_ping' => time()
                    ];
                }
                // Loại master socket khỏi danh sách đọc dữ liệu client
                $read = array_diff($read, [$this->masterSocket]);
            }

            // Đọc dữ liệu từ các client đã kết nối
            foreach ($read as $clientSocket) {
                $socketId = (int)$clientSocket;
                $data = @fread($clientSocket, 65535);

                if ($data === false || strlen($data) === 0) {
                    $this->disconnectClient($socketId);
                    continue;
                }

                // Nếu client chưa handshake: thực hiện bắt tay RFC 6455
                if (empty($this->handshakes[$socketId])) {
                    $this->handleHandshake($socketId, $data);
                } else {
                    // Xử lý frame WebSocket
                    $this->handleFrame($socketId, $data);
                }
            }

            // Dọn dẹp các kết nối stale định kỳ (mỗi 60s)
            $this->cleanStaleConnections();
        }
    }

    private function handleHandshake($socketId, $headers) {
        $socket = $this->clients[$socketId] ?? null;
        if (!$socket) return;

        // Kiểm tra xem có phải là request HTTP thông thường (Publish webhook từ backend)
        if (strpos($headers, 'GET /') === false && strpos($headers, 'POST /publish') !== false) {
            $this->handleHttpPublish($socketId, $headers);
            return;
        }

        // Kiểm tra Sec-WebSocket-Key
        if (preg_match("/Sec-WebSocket-Key: (.*)\r\n/i", $headers, $match)) {
            $key = trim($match[1]);
            $acceptKey = base64_encode(sha1($key . WS_MAGIC_GUID, true));

            $upgrade = "HTTP/1.1 101 Switching Protocols\r\n" .
                       "Upgrade: websocket\r\n" .
                       "Connection: Upgrade\r\n" .
                       "Sec-WebSocket-Accept: $acceptKey\r\n" .
                       "Sec-WebSocket-Protocol: gtlm-protocol\r\n\r\n";

            @fwrite($socket, $upgrade);
            $this->handshakes[$socketId] = true;

            // Gửi tin nhắn chào mừng (welcome)
            $welcome = [
                'event' => 'connected',
                'socket_id' => $socketId,
                'server_time' => time(),
                'server_name' => 'GTLM Realtime Engine v1.0',
                'message' => 'Kết nối WebSocket thành công! Độ trễ 0ms.'
            ];
            $this->sendFrame($socket, json_encode($welcome, JSON_UNESCAPED_UNICODE));
        } else {
            // Không phải WebSocket handshake hợp lệ, trả về 400 Bad Request
            $response = "HTTP/1.1 400 Bad Request\r\nContent-Type: text/plain\r\n\r\nWebSocket handshake required.";
            @fwrite($socket, $response);
            $this->disconnectClient($socketId);
        }
    }

    private function handleHttpPublish($socketId, $rawRequest) {
        $socket = $this->clients[$socketId] ?? null;
        if (!$socket) return;

        // Tách phần body JSON từ POST request
        $parts = explode("\r\n\r\n", $rawRequest, 2);
        $body = $parts[1] ?? '';
        $payload = json_decode($body, true);

        $status = false;
        if ($payload && isset($payload['channel'])) {
            $channel = $payload['channel'];
            $event = $payload['event'] ?? 'message';
            $data = $payload['data'] ?? [];

            $broadcastPayload = json_encode([
                'channel' => $channel,
                'event' => $event,
                'data' => $data,
                'timestamp' => time()
            ], JSON_UNESCAPED_UNICODE);

            $this->broadcastToChannel($channel, $broadcastPayload);
            $status = true;
        }

        $resJson = json_encode(['success' => $status]);
        $response = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: " . strlen($resJson) . "\r\n\r\n" . $resJson;
        @fwrite($socket, $response);
        $this->disconnectClient($socketId);
    }

    private function handleFrame($socketId, $data) {
        $socket = $this->clients[$socketId] ?? null;
        if (!$socket) return;

        $unmasked = $this->decodeFrame($data);
        if ($unmasked === false) return;

        $opcode = $unmasked['opcode'];
        $payload = $unmasked['payload'];

        // Opcode 8: Close connection
        if ($opcode === 0x8) {
            $this->disconnectClient($socketId);
            return;
        }

        // Opcode 9: Ping -> trả về Pong
        if ($opcode === 0x9) {
            $pongFrame = chr(0x8A) . chr(0x00);
            @fwrite($socket, $pongFrame);
            return;
        }

        // Opcode 0xA: Pong nhận được
        if ($opcode === 0xA) {
            if (isset($this->clientInfo[$socketId])) {
                $this->clientInfo[$socketId]['last_ping'] = time();
            }
            return;
        }

        // Opcode 1: Text Frame (JSON)
        if ($opcode === 0x1) {
            $msg = @json_decode($payload, true);
            if (!is_array($msg)) return;

            $action = $msg['action'] ?? '';
            $channel = $msg['channel'] ?? 'global';
            $data = $msg['data'] ?? [];

            switch ($action) {
                // Đăng ký tham gia kênh (chat, live_{id}, pvp_{id})
                case 'subscribe':
                    $this->subscribeChannel($socketId, $channel);
                    if (isset($msg['user_id'])) {
                        $this->clientInfo[$socketId]['user_id'] = (int)$msg['user_id'];
                    }
                    if (isset($msg['username'])) {
                        $this->clientInfo[$socketId]['username'] = htmlspecialchars($msg['username'], ENT_QUOTES, 'UTF-8');
                    }
                    // Báo số lượng người đang xem/online trong kênh
                    $viewerCount = count($this->channelSubscribers[$channel] ?? []);
                    $this->broadcastToChannel($channel, json_encode([
                        'channel' => $channel,
                        'event' => 'viewer_count',
                        'count' => $viewerCount
                    ], JSON_UNESCAPED_UNICODE));
                    break;

                // Rời khỏi kênh
                case 'unsubscribe':
                    $this->unsubscribeChannel($socketId, $channel);
                    $viewerCount = count($this->channelSubscribers[$channel] ?? []);
                    $this->broadcastToChannel($channel, json_encode([
                        'channel' => $channel,
                        'event' => 'viewer_count',
                        'count' => $viewerCount
                    ], JSON_UNESCAPED_UNICODE));
                    break;

                // Tin nhắn Chat thế giới hoặc Chat phòng
                case 'chat_message':
                    $this->totalMessagesRouted++;
                    $outMsg = json_encode([
                        'channel' => $channel,
                        'event' => 'chat_message',
                        'data' => $data,
                        'timestamp' => time()
                    ], JSON_UNESCAPED_UNICODE);
                    $this->broadcastToChannel($channel, $outMsg);
                    break;

                // Báo hiệu đang gõ tin nhắn (Typing Indicator)
                case 'typing':
                    $outTyping = json_encode([
                        'channel' => $channel,
                        'event' => 'typing',
                        'username' => $this->clientInfo[$socketId]['username'] ?? 'Một đạo hữu',
                        'timestamp' => time()
                    ], JSON_UNESCAPED_UNICODE);
                    // Gửi tới mọi người trong kênh trừ người đang gõ
                    $this->broadcastToChannel($channel, $outTyping, $socketId);
                    break;

                // Nước đi Cờ Ca-rô PvP Realtime 0ms
                case 'caro_move':
                    $this->totalMessagesRouted++;
                    $outMove = json_encode([
                        'channel' => $channel,
                        'event' => 'caro_move',
                        'data' => $data,
                        'timestamp' => time()
                    ], JSON_UNESCAPED_UNICODE);
                    $this->broadcastToChannel($channel, $outMove);
                    break;

                // Cập nhật trạng thái trận đấu PvP
                case 'pvp_status':
                    $outStatus = json_encode([
                        'channel' => $channel,
                        'event' => 'pvp_status',
                        'data' => $data,
                        'timestamp' => time()
                    ], JSON_UNESCAPED_UNICODE);
                    $this->broadcastToChannel($channel, $outStatus);
                    break;

                // Heartbeat Ping
                case 'ping':
                    $this->sendFrame($socket, json_encode(['event' => 'pong', 'time' => time()]));
                    break;
            }
        }
    }

    private function subscribeChannel($socketId, $channel) {
        if (!isset($this->channelSubscribers[$channel])) {
            $this->channelSubscribers[$channel] = [];
        }
        $this->channelSubscribers[$channel][$socketId] = true;
        $this->clientInfo[$socketId]['channels'][$channel] = true;

        $ack = [
            'event' => 'subscribed',
            'channel' => $channel,
            'viewer_count' => count($this->channelSubscribers[$channel])
        ];
        $socket = $this->clients[$socketId] ?? null;
        if ($socket) {
            $this->sendFrame($socket, json_encode($ack, JSON_UNESCAPED_UNICODE));
        }
    }

    private function unsubscribeChannel($socketId, $channel) {
        if (isset($this->channelSubscribers[$channel][$socketId])) {
            unset($this->channelSubscribers[$channel][$socketId]);
            if (empty($this->channelSubscribers[$channel])) {
                unset($this->channelSubscribers[$channel]);
            }
        }
        if (isset($this->clientInfo[$socketId]['channels'][$channel])) {
            unset($this->clientInfo[$socketId]['channels'][$channel]);
        }
    }

    private function broadcastToChannel($channel, $encodedPayload, $excludeSocketId = null) {
        if (empty($this->channelSubscribers[$channel])) {
            return;
        }

        $frame = $this->encodeFrame($encodedPayload);
        foreach ($this->channelSubscribers[$channel] as $socketId => $bool) {
            if ($excludeSocketId !== null && $socketId === $excludeSocketId) {
                continue;
            }
            $socket = $this->clients[$socketId] ?? null;
            if ($socket) {
                @fwrite($socket, $frame);
            }
        }
    }

    private function disconnectClient($socketId) {
        $socket = $this->clients[$socketId] ?? null;
        if ($socket) {
            // Rút khỏi tất cả các kênh
            if (isset($this->clientInfo[$socketId]['channels'])) {
                foreach ($this->clientInfo[$socketId]['channels'] as $ch => $bool) {
                    $this->unsubscribeChannel($socketId, $ch);
                    // Báo cập nhật lại số lượng người xem
                    $viewerCount = count($this->channelSubscribers[$ch] ?? []);
                    $this->broadcastToChannel($ch, json_encode([
                        'channel' => $ch,
                        'event' => 'viewer_count',
                        'count' => $viewerCount
                    ], JSON_UNESCAPED_UNICODE));
                }
            }
            @fclose($socket);
        }

        unset($this->clients[$socketId]);
        unset($this->handshakes[$socketId]);
        unset($this->clientInfo[$socketId]);
    }

    private function decodeFrame($data) {
        $length = ord($data[1]) & 127;
        $opcode = ord($data[0]) & 0x0F;

        if ($length === 126) {
            $masks = substr($data, 4, 4);
            $payload = substr($data, 8);
        } elseif ($length === 127) {
            $masks = substr($data, 10, 4);
            $payload = substr($data, 14);
        } else {
            $masks = substr($data, 2, 4);
            $payload = substr($data, 6);
        }

        $text = '';
        for ($i = 0; $i < strlen($payload); ++$i) {
            $text .= $payload[$i] ^ $masks[$i % 4];
        }

        return ['opcode' => $opcode, 'payload' => $text];
    }

    private function encodeFrame($payload, $opcode = 0x1) {
        $length = strlen($payload);
        $header = chr(0x80 | $opcode);

        if ($length <= 125) {
            $header .= chr($length);
        } elseif ($length <= 65535) {
            $header .= chr(126) . pack('n', $length);
        } else {
            $header .= chr(127) . pack('J', $length);
        }

        return $header . $payload;
    }

    private function sendFrame($socket, $payload) {
        $frame = $this->encodeFrame($payload);
        @fwrite($socket, $frame);
    }

    private function cleanStaleConnections() {
        $now = time();
        foreach ($this->clientInfo as $socketId => $info) {
            // Nếu không ping quá 120s
            if ($now - $info['last_ping'] > 120) {
                $this->disconnectClient($socketId);
            }
        }
    }
}

// Khởi chạy server nếu được gọi trực tiếp
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $server = new GTLMWebSocketServer(WS_HOST, WS_PORT);
    $server->run();
}

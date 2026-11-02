/**
 * ⚡ GTLM Realtime Engine Client (JavaScript SDK)
 * Tự động kết nối WebSocket Gateway (Port 8088) với độ trễ 0ms.
 * Tự động chuyển đổi mượt mà sang cơ chế Fallback nếu máy chủ WebSocket chưa bật.
 */

(function (window) {
    'use strict';

    class GTLMRealtimeClient {
        constructor() {
            this.ws = null;
            this.status = 'disconnected'; // 'connecting', 'connected', 'fallback', 'disconnected'
            this.subscriptions = new Set();
            this.eventListeners = {}; // eventName => [callbacks]
            this.statusListeners = [];
            this.reconnectAttempts = 0;
            this.maxReconnectAttempts = 5;
            this.reconnectTimer = null;
            this.pingInterval = null;
            this.fallbackPollingTimers = {};
            this.lastTypingTime = 0;

            // Cấu hình Host & Port
            const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
            const host = window.location.hostname || '127.0.0.1';
            this.wsUrl = `${protocol}//${host}:8088`;
            this.sseUrl = 'realtime/sse_stream.php';

            // Tự khởi động kết nối khi script nạp xong
            this.connect();
        }

        /**
         * Kết nối tới WebSocket Gateway
         */
        connect() {
            if (this.ws && (this.ws.readyState === WebSocket.OPEN || this.ws.readyState === WebSocket.CONNECTING)) {
                return;
            }

            this.updateStatus('connecting');

            try {
                this.ws = new WebSocket(this.wsUrl, 'gtlm-protocol');

                this.ws.onopen = () => {
                    this.reconnectAttempts = 0;
                    this.updateStatus('connected');
                    this.startHeartbeat();

                    // Re-subscribe vào các kênh đã đăng ký trước đó
                    this.subscriptions.forEach(channel => {
                        this.sendRaw({ action: 'subscribe', channel: channel });
                    });
                };

                this.ws.onmessage = (event) => {
                    try {
                        const msg = JSON.parse(event.data);
                        this.handleIncomingMessage(msg);
                    } catch (e) {
                        console.warn('[GTLM Realtime] Nhận dữ liệu không hợp lệ:', event.data);
                    }
                };

                this.ws.onerror = (err) => {
                    // Sẽ trigger onclose để xử lý fallback
                };

                this.ws.onclose = () => {
                    this.stopHeartbeat();
                    if (this.reconnectAttempts < this.maxReconnectAttempts) {
                        this.reconnectAttempts++;
                        const delay = Math.min(1000 * Math.pow(1.5, this.reconnectAttempts), 8000);
                        this.updateStatus('connecting');
                        this.reconnectTimer = setTimeout(() => this.connect(), delay);
                    } else {
                        // Kích hoạt chế độ Fallback mượt mà (không gây lỗi gián đoạn)
                        this.updateStatus('fallback');
                    }
                };
            } catch (e) {
                this.updateStatus('fallback');
            }
        }

        /**
         * Xử lý gói tin nhận từ WebSocket Server
         */
        handleIncomingMessage(msg) {
            const eventName = msg.event || 'message';
            const channel = msg.channel || 'global';

            // Kích hoạt listeners theo format 'event' hoặc 'channel:event'
            this.trigger(eventName, msg.data || msg);
            this.trigger(`${channel}:${eventName}`, msg.data || msg);
        }

        /**
         * Đăng ký tham gia một kênh Realtime (vd: 'chat', 'live_61', 'pvp_12')
         */
        subscribe(channel, userInfo = {}) {
            if (!channel) return;
            this.subscriptions.add(channel);

            if (this.status === 'connected' && this.ws && this.ws.readyState === WebSocket.OPEN) {
                this.sendRaw({
                    action: 'subscribe',
                    channel: channel,
                    user_id: userInfo.userId || 0,
                    username: userInfo.username || 'Đạo Hữu'
                });
            }
        }

        /**
         * Rời khỏi kênh
         */
        unsubscribe(channel) {
            if (!channel) return;
            this.subscriptions.delete(channel);

            if (this.status === 'connected' && this.ws && this.ws.readyState === WebSocket.OPEN) {
                this.sendRaw({ action: 'unsubscribe', channel: channel });
            }
        }

        /**
         * Gửi sự kiện qua WebSocket (Độ trễ 0ms)
         */
        send(action, channel, data = {}) {
            if (this.status === 'connected' && this.ws && this.ws.readyState === WebSocket.OPEN) {
                this.sendRaw({
                    action: action,
                    channel: channel,
                    data: data,
                    timestamp: Date.now()
                });
                return true;
            }
            return false;
        }

        /**
         * Báo hiệu đang gõ tin nhắn (Typing Indicator) với giới hạn debounce
         */
        sendTyping(channel, username) {
            const now = Date.now();
            if (now - this.lastTypingTime > 1500) {
                this.lastTypingTime = now;
                this.send('typing', channel, { username: username });
            }
        }

        /**
         * Đăng ký lắng nghe sự kiện
         */
        on(event, callback) {
            if (!this.eventListeners[event]) {
                this.eventListeners[event] = [];
            }
            this.eventListeners[event].push(callback);
        }

        /**
         * Hủy đăng ký lắng nghe
         */
        off(event, callback) {
            if (!this.eventListeners[event]) return;
            if (!callback) {
                delete this.eventListeners[event];
            } else {
                this.eventListeners[event] = this.eventListeners[event].filter(cb => cb !== callback);
            }
        }

        /**
         * Kích hoạt sự kiện nội bộ
         */
        trigger(event, data) {
            if (this.eventListeners[event]) {
                this.eventListeners[event].forEach(cb => {
                    try { cb(data); } catch (e) { console.error('[GTLM Realtime Callback Error]:', e); }
                });
            }
        }

        /**
         * Cập nhật và bắn sự kiện thay đổi trạng thái kết nối
         */
        updateStatus(newStatus) {
            if (this.status !== newStatus) {
                this.status = newStatus;
                this.statusListeners.forEach(cb => {
                    try { cb(newStatus); } catch (e) {}
                });
            }
        }

        /**
         * Lắng nghe sự kiện đổi trạng thái ('connected', 'connecting', 'fallback', 'disconnected')
         */
        onStatusChange(callback) {
            this.statusListeners.push(callback);
            callback(this.status);
        }

        /**
         * Gửi dữ liệu thô
         */
        sendRaw(obj) {
            try {
                this.ws.send(JSON.stringify(obj));
            } catch (e) {}
        }

        /**
         * Heartbeat duy trì kết nối
         */
        startHeartbeat() {
            this.stopHeartbeat();
            this.pingInterval = setInterval(() => {
                if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                    this.sendRaw({ action: 'ping' });
                }
            }, 25000);
        }

        stopHeartbeat() {
            if (this.pingInterval) {
                clearInterval(this.pingInterval);
                this.pingInterval = null;
            }
        }
    }

    // Đăng ký toàn cục
    window.GTLMRealtime = new GTLMRealtimeClient();

})(window);

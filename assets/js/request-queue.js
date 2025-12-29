/**
 * Request Queue - Quản lý và tối ưu API requests
 */

class RequestQueue {
    constructor() {
        this.queue = [];
        this.processing = false;
        this.maxConcurrent = 3;
        this.currentConcurrent = 0;
        this.retryDelay = 1000;
        this.maxRetries = 3;
    }

    async add(requestFn, priority = 0) {
        return new Promise((resolve, reject) => {
            this.queue.push({
                requestFn,
                priority,
                resolve,
                reject,
                retries: 0
            });

            this.queue.sort((a, b) => b.priority - a.priority);
            this.process();
        });
    }

    async process() {
        if (this.processing || this.queue.length === 0) return;
        if (this.currentConcurrent >= this.maxConcurrent) return;

        this.processing = true;
        const item = this.queue.shift();

        if (!item) {
            this.processing = false;
            return;
        }

        this.currentConcurrent++;

        try {
            const result = await item.requestFn();
            item.resolve(result);
        } catch (error) {
            if (item.retries < this.maxRetries) {
                item.retries++;
                this.queue.unshift(item);
                setTimeout(() => {
                    this.currentConcurrent--;
                    this.process();
                }, this.retryDelay * item.retries);
            } else {
                item.reject(error);
            }
        } finally {
            this.currentConcurrent--;
            this.processing = false;
            this.process();
        }
    }

    clear() {
        this.queue = [];
        this.processing = false;
        this.currentConcurrent = 0;
    }

    getQueueSize() {
        return this.queue.length;
    }
}

// Global request queue
window.requestQueue = new RequestQueue();

// Wrapper for fetch with queue
window.queuedFetch = async (url, options = {}, priority = 0) => {
    return window.requestQueue.add(async () => {
        const response = await fetch(url, options);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    }, priority);
};


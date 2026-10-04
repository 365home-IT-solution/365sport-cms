<div
    x-data="{
        reader: null,
        paused: false,
        lastScannedCode: null,
        lastScannedAt: 0,
        error: null,

        async start() {
            if (! window.ZXingBrowser) {
                await this.loadZXing();
            }

            try {
                this.reader = new window.ZXingBrowser.BrowserMultiFormatReader();
                await this.reader.decodeFromConstraints(
                    { video: { facingMode: 'environment' } },
                    this.$refs.video,
                    (result, err) => {
                        if (result) {
                            this.onScan(result.getText());
                        }
                    }
                );
            } catch (e) {
                this.error = 'Không thể mở camera: ' + e.message;
            }
        },

        loadZXing() {
            return new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/@zxing/browser@0.0.2/umd/zxing-browser.min.js';
                script.onload = resolve;
                script.onerror = reject;
                document.head.appendChild(script);
            });
        },

        onScan(code) {
            const now = Date.now();
            if (this.paused) return;
            if (code === this.lastScannedCode && (now - this.lastScannedAt) < 3000) return;

            this.lastScannedCode = code;
            this.lastScannedAt = now;
            this.paused = true;
            setTimeout(() => { this.paused = false; }, 900);

            const input = document.getElementById('{{ \Modules\Warehouse\App\Filament\Support\WarehouseBarcodeScan::INPUT_ID }}');
            if (input) {
                const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
                setter.call(input, code);
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('blur', { bubbles: true }));
            }
        },

        stop() {
            try {
                this.reader?.reset();
            } catch (e) {}
        },
    }"
    x-init="start()"
    x-on:closed.stop="stop()"
>
    <video x-ref="video" style="width: 100%; max-height: 60vh; background: #000; border-radius: 0.5rem;" muted playsinline></video>
    <p x-show="error" x-text="error" style="color: #dc2626; margin-top: 0.5rem;"></p>
    <p style="margin-top: 0.5rem; font-size: 0.875rem; color: #6b7280;">
        Hướng camera vào mã QR/vạch dán trên vật tư — quét được sẽ tự thêm vào danh sách, có thể quét liên tục nhiều vật tư mà không cần đóng cửa sổ này.
    </p>
</div>

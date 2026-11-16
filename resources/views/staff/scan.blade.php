<x-layouts.staff title="Quét Mã QR - Nhân viên">
    <div class="mx-auto max-w-2xl py-6 sm:px-4 sm:py-16">
        <div class="glass rounded-2xl p-4 text-center animate-fade-in-up sm:p-8">
            <h1 class="text-2xl font-bold text-cinema-text mb-2">Quét Mã QR Soát Vé</h1>
            <p class="text-[var(--color-cinema-text-muted)] mb-8">Dành cho nhân viên rạp chiếu</p>

            <style>
                /* Làm sáng icon đen mặc định của html5-qrcode trên nền tối */
                html.dark #reader img, html.dark #reader svg {
                    filter: invert(1);
                    opacity: 0.7;
                }
            </style>

            <div id="scan-tools">
                <div class="mb-8">
                    <div id="reader" class="mx-auto overflow-hidden rounded-xl" style="max-width: 400px; border: 2px solid var(--color-cinema-border);"></div>
                    <p class="text-xs text-cinema-text-muted mt-3">
                        Khi quét bằng tệp, dùng ảnh PNG tải từ trang chi tiết vé. Nếu dùng ảnh chụp, hãy cắt quanh mã QR và giữ nguyên viền trắng.
                    </p>
                </div>

                <div class="text-[var(--color-cinema-text-muted)] mb-4">HOẶC</div>

                <div class="mx-auto flex max-w-md flex-col gap-2 sm:flex-row">
                    <input type="text" id="manual-qr" aria-label="Dữ liệu QR" placeholder="Nhập dữ liệu QR" class="min-w-0 flex-1 px-4 py-3 rounded-lg bg-cinema-card border border-cinema-border text-cinema-text text-sm focus-visible:outline-2 focus-visible:outline-cinema-success-text">
                    <button onclick="processQRData(document.getElementById('manual-qr').value)" class="btn-accent shrink-0 whitespace-nowrap px-6">Quét</button>
                </div>
            </div>

            <div id="scan-result" class="mt-8 text-left hidden">
                <div class="p-4 rounded-xl mb-4" id="result-alert"></div>
                <div class="glass rounded-xl p-4 space-y-2 text-sm" id="result-info">
                    <!-- Ticket info will be populated here -->
                </div>
                <button onclick="resetScanner()" class="btn-primary mt-6 w-full py-3 font-bold text-center">Tiếp tục soát vé khác</button>
            </div>
        </div>
    </div>

    <!-- HTML5 QR Code Scanner Library -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script>
        let html5QrcodeScanner = null;

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Html5QrcodeScanner !== 'undefined') {
                html5QrcodeScanner = new Html5QrcodeScanner("reader", { 
                    fps: 20,
                    // Bỏ qrbox để quét toàn bộ khung hình, giúp người dùng có thể để điện thoại ra xa cho camera dễ lấy nét
                    formatsToSupport: [ 0 ], // 0 = QR_CODE (chỉ quét QR code để tăng tốc độ)
                });
                html5QrcodeScanner.render(onScanSuccess, onScanFailure);
            } else {
                document.getElementById('reader').innerHTML = '<p class="p-8 text-cinema-warning-text">Không thể tải thư viện quét Camera, vui lòng dùng ô nhập tay để test.</p>';
            }
        });

        function onScanSuccess(decodedText, decodedResult) {
            console.log(`Scan result: ${decodedText}`);
            processQRData(decodedText);
        }

        function onScanFailure(error) {
            // handle scan failure, usually better to ignore and keep scanning.
        }

        function resetScanner() {
            document.getElementById('scan-result').classList.add('hidden');
            document.getElementById('scan-tools').style.display = 'block';
            document.getElementById('manual-qr').value = '';
            if (html5QrcodeScanner) {
                // html5QrcodeScanner.resume(); // optionally resume if paused
            }
        }

        function processQRData(qrString) {
            if (!qrString) {
                if (window.showNotificationModal) {
                    window.showNotificationModal({
                        title: 'Thiếu dữ liệu',
                        message: 'Vui lòng nhập hoặc quét mã QR hợp lệ',
                        type: 'warning'
                    });
                } else {
                    alert('Vui lòng nhập mã QR');
                }
                return;
            }

            const resultContainer = document.getElementById('scan-result');
            const alertBox = document.getElementById('result-alert');
            const infoBox = document.getElementById('result-info');

            fetch('/api/staff/tickets/scan', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.CSRF_TOKEN, 'Accept': 'application/json' },
                body: JSON.stringify({ qr_code: qrString })
            })
            .then(res => res.json().then(data => ({ status: res.status, ok: res.ok, data })))
            .then(({ status, ok, data }) => {
                resultContainer.classList.remove('hidden');
                
                if (ok) {
                    document.getElementById('scan-tools').style.display = 'none';
                    alertBox.className = 'p-4 rounded-xl mb-4 bg-green-500/20 border border-green-500/50 text-cinema-success-text';
                    alertBox.innerHTML = `<strong>Thành công:</strong> ${data.message}`;
                    
                    const o = data.data;
                    const startTime = new Date(o.show?.start_time);
                    const durationMins = o.show?.movie?.duration_minutes || 120;
                    const endTime = o.show?.end_time ? new Date(o.show.end_time) : new Date(startTime.getTime() + durationMins * 60000);
                    const startStr = startTime.toLocaleTimeString('vi-VN', {hour:'2-digit',minute:'2-digit'});
                    const endStr = endTime.toLocaleTimeString('vi-VN', {hour:'2-digit',minute:'2-digit'});
                    infoBox.innerHTML = `
                        <div class="flex justify-between"><span class="text-[var(--color-cinema-text-muted)]">Mã ĐH</span><span class="text-cinema-text font-bold">#${o.id}</span></div>
                        <div class="flex justify-between"><span class="text-[var(--color-cinema-text-muted)]">Phim</span><span class="text-cinema-text">${o.show?.movie?.title}</span></div>
                        <div class="flex justify-between"><span class="text-[var(--color-cinema-text-muted)]">Khách hàng</span><span class="text-cinema-text">${o.user?.name || ''}</span></div>
                        <div class="flex justify-between"><span class="text-[var(--color-cinema-text-muted)]">Giờ chiếu</span><span class="text-cinema-text font-medium">${startStr} ~ ${endStr}</span></div>
                        <div class="flex justify-between"><span class="text-[var(--color-cinema-text-muted)]">Phòng</span><span class="text-cinema-text font-bold">${o.show?.room?.name}</span></div>
                        <div class="flex justify-between"><span class="text-[var(--color-cinema-text-muted)]">Ghế</span><span class="text-cinema-accent-text font-bold text-lg">${o.seats?.map(s => s.seat?.row_label + s.seat?.seat_number).join(', ')}</span></div>
                        ${o.snacks?.length > 0 ? `<div class="flex justify-between"><span class="text-[var(--color-cinema-text-muted)]">Bắp nước</span><span class="text-cinema-accent-text font-semibold">${o.snacks.map(s => s.snack?.name + ' x' + s.quantity).join(', ')}</span></div>` : ''}
                    `;
                } else {
                    alertBox.className = 'p-4 rounded-xl mb-4 bg-red-500/20 border border-red-500/50 text-cinema-danger-text';
                    alertBox.innerHTML = `<strong>Cảnh báo:</strong> ${data.message}`;
                    infoBox.innerHTML = '';
                    
                    if (data.data) {
                        const o = data.data;
                        infoBox.innerHTML = `
                            <p class="text-cinema-text font-medium mb-2">Thông tin vé:</p>
                            <div class="flex justify-between"><span class="text-[var(--color-cinema-text-muted)]">Phim</span><span class="text-cinema-text">${o.show?.movie?.title || ''}</span></div>
                            <div class="flex justify-between"><span class="text-[var(--color-cinema-text-muted)]">Trạng thái vé</span><span class="${o.status === 'completed' ? 'text-cinema-text-muted' : 'text-cinema-warning-text'}">${o.status}</span></div>
                        `;
                    }
                }
                
                // Cuộn xuống để xem kết quả
                resultContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            })
            .catch(err => {
                if (window.showNotificationModal) {
                    window.showNotificationModal({
                        title: 'Lỗi kết nối',
                        message: 'Không thể kết nối đến máy chủ để xác thực vé!',
                        type: 'error'
                    });
                } else {
                    alert('Lỗi kết nối Server!');
                }
                console.error(err);
            });
        }
    </script>
</x-layouts.staff>

<x-layouts.main title="Chi tiết vé - CineStar">
    <div class="max-w-4xl mx-auto px-4 py-8">
        {{-- Booking Breadcrumb Stepper --}}
        <x-booking-breadcrumb :step="4" :orderId="$orderId" :showId="$showId ?? null" />

        <div class="max-w-2xl mx-auto glass rounded-2xl p-8 text-center animate-fade-in-up">
            <h1 class="text-2xl font-bold text-cinema-text mb-2" id="status-title">Đang tải...</h1>
            <p class="text-[var(--color-cinema-text-muted)] mb-8" id="status-message"></p>

            <div id="ticket-info" class="text-left space-y-4 mb-8">
                <div class="text-center py-8 text-[var(--color-cinema-text-muted)]">Đang tải thông tin vé...</div>
            </div>

            {{-- QR Code --}}
            <div id="qr-section" class="mb-8 hidden">
                <h3 class="text-sm font-semibold text-cinema-accent-text mb-3">Mã QR - Xuất trình khi vào rạp</h3>
                <div id="qr-display" class="inline-block max-w-full p-4 rounded-xl" style="background: white;">
                    <div class="text-center text-slate-600 text-sm py-4">Đang tạo QR...</div>
                </div>
                <p class="text-xs text-[var(--color-cinema-text-muted)] mt-3">Nhân viên sẽ quét mã QR này để soát vé</p>
                <div class="mt-4">
                    <a id="qr-download" class="hidden btn-accent !py-2 !px-4 text-sm" download="cinestar-ve-{{ $orderId }}.png">
                        Tải ảnh QR (PNG)
                    </a>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row justify-center gap-4">
                <a href="/booking/history" class="btn-primary !py-3 !px-8">Xem lịch sử vé</a>
                <a href="/" class="btn-accent !py-3 !px-8">Về trang chủ</a>
            </div>
        </div>
    </div>

    {{-- QR Code Library --}}
    <script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
    <script>
        const orderId = {{ $orderId }};

        document.addEventListener('DOMContentLoaded', function() {
            fetch(`/api/orders/${orderId}`, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': window.CSRF_TOKEN }
            })
            .then(res => res.json())
            .then(result => {
                const o = result.data;
                const startTime = new Date(o.show?.start_time || NaN);
                const durationMins = o.show?.movie?.duration_minutes || 120;
                const endTime = o.show?.end_time ? new Date(o.show.end_time) : new Date(startTime.getTime() + durationMins * 60000);
                const dateLabel = Number.isFinite(startTime.getTime()) ? startTime.toLocaleDateString('vi-VN') : '—';
                const formatTime = date => date.toLocaleTimeString('vi-VN', {hour:'2-digit',minute:'2-digit'});
                const timeLabel = Number.isFinite(startTime.getTime()) && Number.isFinite(endTime.getTime()) ? `${formatTime(startTime)} ~ ${formatTime(endTime)}` : '—';
                
                let statusTitle = '';
                let statusMessage = '';
                let statusLabel = '';
                let statusColor = '';
                let showQR = false;

                switch(o.status) {
                    case 'paid':
                    case 'completed':
                        statusTitle = 'Đặt vé thành công!';
                        statusMessage = 'Cảm ơn bạn đã sử dụng dịch vụ CineStar';
                        statusLabel = 'Đã thanh toán';
                        statusColor = 'text-cinema-success-text';
                        showQR = true;
                        break;
                    case 'cancelled':
                        statusTitle = 'Đơn hàng đã hủy';
                        statusMessage = 'Đơn hàng này đã bị hủy bỏ do hết thời gian thanh toán hoặc được người dùng hủy.';
                        statusLabel = 'Đã hủy';
                        statusColor = 'text-cinema-danger-text';
                        break;
                    case 'pending':
                        statusTitle = 'Chờ thanh toán';
                        statusMessage = 'Đơn hàng của bạn đang chờ thanh toán.';
                        statusLabel = 'Chờ thanh toán';
                        statusColor = 'text-cinema-warning-text';
                        break;
                    default:
                        statusTitle = 'Chi tiết đơn hàng';
                        statusMessage = 'Thông tin chi tiết về đơn đặt vé của bạn.';
                        statusLabel = o.status;
                        statusColor = 'text-cinema-text-muted';
                }
                
                document.getElementById('status-title').textContent = statusTitle;
                document.getElementById('status-message').textContent = o.show ? statusMessage : 'Thông tin suất chiếu của vé cũ chưa được khôi phục.';

                document.getElementById('ticket-info').innerHTML = `
                    <div class="glass rounded-xl p-4 space-y-2 text-sm">
                        <div class="flex justify-between"><span class="text-[var(--color-cinema-text-muted)]">Phim</span><span class="text-cinema-text font-semibold">${o.show?.movie?.title || '—'}</span></div>
                        <div class="flex justify-between"><span class="text-[var(--color-cinema-text-muted)]">Ngày chiếu</span><span class="text-cinema-text">${dateLabel}</span></div>
                        <div class="flex justify-between"><span class="text-[var(--color-cinema-text-muted)]">Giờ chiếu</span><span class="text-cinema-text font-medium">${timeLabel}</span></div>
                        <div class="flex justify-between"><span class="text-[var(--color-cinema-text-muted)]">Phòng</span><span class="text-cinema-text">${o.show?.room?.name || '—'}</span></div>
                        <div class="flex justify-between"><span class="text-[var(--color-cinema-text-muted)]">Ghế</span><span class="text-cinema-accent-text font-bold">${o.seats?.map(s => s.seat?.row_label + s.seat?.seat_number).join(', ') || '—'}</span></div>
                        ${o.snacks?.length > 0 ? `<div class="flex justify-between"><span class="text-[var(--color-cinema-text-muted)]">Bắp nước</span><span class="text-cinema-text">${o.snacks.map(s => s.snack?.name + ' x' + s.quantity).join(', ')}</span></div>` : ''}
                        <div class="border-t border-[var(--color-cinema-border)] my-2"></div>
                        <div class="flex justify-between text-lg font-bold"><span class="text-cinema-text">Tổng tiền</span><span class="text-cinema-accent-text">${Number(o.total_amount).toLocaleString('vi-VN')}đ</span></div>
                        <div class="flex justify-between"><span class="text-[var(--color-cinema-text-muted)]">Thanh toán</span><span class="text-cinema-text font-medium">${o.payment_method?.toUpperCase() || '—'}</span></div>
                        <div class="flex justify-between"><span class="text-[var(--color-cinema-text-muted)]">Trạng thái</span><span class="${statusColor} font-medium">${statusLabel}</span></div>
                    </div>
                `;

                // Generate QR Code
                if (showQR && o.show && o.qr_code && typeof QRCode !== 'undefined') {
                    document.getElementById('qr-section').classList.remove('hidden');
                    const qrDiv = document.getElementById('qr-display');
                    qrDiv.innerHTML = '';
                    const canvas = document.createElement('canvas');
                    canvas.className = 'block max-w-full h-auto';
                    qrDiv.appendChild(canvas);
                    QRCode.toCanvas(canvas, o.qr_code, { scale: 8, margin: 4 }, function(error) {
                        if (error) {
                            qrDiv.innerHTML = '<p class="text-red-800 text-sm p-4">Lỗi tạo QR Code</p>';
                            return;
                        }

                        const download = document.getElementById('qr-download');
                        download.href = canvas.toDataURL('image/png');
                        download.classList.remove('hidden');
                    });
                }
            });
        });
    </script>
</x-layouts.main>

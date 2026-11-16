<x-layouts.main title="Thanh toán - CineStar">
    @php($paymentMethod = $order->checkout_payment_method === 'momo' ? 'momo' : 'vnpay')
    <div class="max-w-6xl mx-auto px-4 py-8">
        {{-- Booking Breadcrumb Stepper --}}
        <x-booking-breadcrumb :step="3" :orderId="$orderId" :showId="$showId" :holdExpiresAt="$holdExpiresAt ?? null" />

        <h1 class="text-2xl font-bold text-[var(--color-cinema-text)] mt-2 mb-6">Thanh toán</h1>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            {{-- Left Column: Order Summary --}}
            <div class="glass rounded-2xl p-6 h-fit" id="order-summary-container">
                <h3 class="font-bold text-[var(--color-cinema-text)] mb-4 text-lg border-b border-[var(--color-cinema-border)] pb-3">Thông tin đặt vé</h3>
                <div id="order-summary-loading" class="animate-pulse space-y-5 py-4">
                    <div class="flex justify-between items-center"><div class="h-4 bg-[var(--color-cinema-border)] rounded w-16 opacity-30"></div><div class="h-5 bg-[var(--color-cinema-border)] rounded w-48 opacity-30"></div></div>
                    <div class="flex justify-between items-center"><div class="h-4 bg-[var(--color-cinema-border)] rounded w-24 opacity-30"></div><div class="h-4 bg-[var(--color-cinema-border)] rounded w-32 opacity-30"></div></div>
                    <div class="flex justify-between items-center"><div class="h-4 bg-[var(--color-cinema-border)] rounded w-24 opacity-30"></div><div class="h-4 bg-[var(--color-cinema-border)] rounded w-32 opacity-30"></div></div>
                    <div class="flex justify-between items-center"><div class="h-4 bg-[var(--color-cinema-border)] rounded w-16 opacity-30"></div><div class="h-4 bg-[var(--color-cinema-border)] rounded w-24 opacity-30"></div></div>
                    <div class="flex justify-between items-center"><div class="h-4 bg-[var(--color-cinema-border)] rounded w-28 opacity-30"></div><div class="h-4 bg-[var(--color-cinema-border)] rounded w-40 opacity-30"></div></div>
                </div>
                <div id="order-summary-content" class="hidden space-y-4 text-sm mt-4">
                    <div class="flex justify-between items-center"><span class="text-[var(--color-cinema-text-muted)]">Phim</span><span class="text-[var(--color-cinema-text)] font-bold text-base text-right" id="os-movie">—</span></div>
                    <div class="flex justify-between items-center"><span class="text-[var(--color-cinema-text-muted)]">Suất chiếu</span><span class="text-[var(--color-cinema-text)] text-right" id="os-time">—</span></div>
                    <div class="flex justify-between items-center"><span class="text-[var(--color-cinema-text-muted)]">Ngày chiếu</span><span class="text-[var(--color-cinema-text)] text-right" id="os-date">—</span></div>
                    <div class="flex justify-between items-center"><span class="text-[var(--color-cinema-text-muted)]">Phòng</span><span class="text-[var(--color-cinema-text)] text-right" id="os-room">—</span></div>
                    <div class="flex justify-between items-start">
                        <span class="text-[var(--color-cinema-text-muted)]">Ghế đã chọn</span>
                        <div class="text-right max-w-[60%]">
                            <span class="text-cinema-accent-text font-semibold block" id="os-seats">—</span>
                        </div>
                    </div>
                    
                    <div id="os-snacks-container" class="hidden">
                        <div class="border-t border-[var(--color-cinema-border)] my-3"></div>
                        <p class="text-[var(--color-cinema-text-muted)] mb-2">Bắp nước:</p>
                        <div id="os-snacks-list" class="space-y-2"></div>
                    </div>
                </div>
            </div>

            {{-- Right Column: Voucher & Payment Methods --}}
            <div class="space-y-6">
                {{-- Payment Details Summary --}}
                <div class="glass rounded-2xl p-6">
                    <h3 class="font-bold text-[var(--color-cinema-text)] mb-4 text-lg border-b border-[var(--color-cinema-border)] pb-3">Chi tiết thanh toán</h3>
                    <div id="payment-breakdown" class="space-y-3 text-sm mt-4">
                        <div class="animate-pulse flex justify-between"><div class="h-4 bg-[var(--color-cinema-border)] rounded w-24"></div><div class="h-4 bg-[var(--color-cinema-border)] rounded w-20"></div></div>
                    </div>
                </div>

                {{-- Voucher --}}
                <div class="glass rounded-2xl p-6">
                    <h3 class="font-bold text-[var(--color-cinema-text)] mb-4 text-lg border-b border-[var(--color-cinema-border)] pb-3">Mã giảm giá</h3>
                    <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                        <input type="text" id="voucher-input" aria-label="Mã giảm giá" placeholder="Nhập mã voucher (VD: WELCOME50)"
                               value="{{ $order->voucher?->code }}"
                               class="min-w-0 flex-1 px-4 py-3 rounded-lg text-[var(--color-cinema-text)] text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-cinema-accent)]"
                               style="background: var(--color-cinema-card); border: 1px solid var(--color-cinema-border);">
                        <button data-booking-action onclick="applyVoucher()" class="btn-accent shrink-0 !py-3 !px-6 text-sm">Áp dụng</button>
                    </div>
                    <p class="text-xs text-[var(--color-cinema-text-muted)] mt-2" id="voucher-status">
                        @if($order->voucher) Đã áp dụng mã {{ $order->voucher->code }} @endif
                    </p>
                </div>

                {{-- Payment Method --}}
                <div class="glass rounded-2xl p-6">
                    <h3 class="font-bold text-[var(--color-cinema-text)] mb-4 text-lg border-b border-[var(--color-cinema-border)] pb-3">Phương thức thanh toán</h3>
                    <div class="space-y-3 mt-4">
                        <label class="payment-method-label flex items-center gap-3 p-4 rounded-xl cursor-pointer transition-all hover:bg-[var(--color-cinema-card)] border-2 border-[var(--color-cinema-primary)]">
                            <input type="radio" name="payment_method" value="vnpay" @checked($paymentMethod === 'vnpay') class="w-4 h-4 hidden" onchange="updatePaymentUI()">
                            <div class="w-5 h-5 rounded-full border-2 border-[var(--color-cinema-primary)] flex items-center justify-center mr-2 radio-indicator">
                                <div class="w-2.5 h-2.5 rounded-full bg-[var(--color-cinema-primary)]"></div>
                            </div>
                            <div>
                                <span class="font-semibold text-[var(--color-cinema-text)] text-sm">VNPay</span>
                                <span class="text-xs text-[var(--color-cinema-text-muted)] block">Thanh toán qua ví VNPay</span>
                            </div>
                        </label>
                        <label class="payment-method-label flex items-center gap-3 p-4 rounded-xl cursor-pointer transition-all hover:bg-[var(--color-cinema-card)] border-2 border-[var(--color-cinema-border)]">
                            <input type="radio" name="payment_method" value="momo" @checked($paymentMethod === 'momo') class="w-4 h-4 hidden" onchange="updatePaymentUI()">
                            <div class="w-5 h-5 rounded-full border-2 border-[var(--color-cinema-border)] flex items-center justify-center mr-2 radio-indicator">
                                <div class="w-2.5 h-2.5 rounded-full bg-transparent"></div>
                            </div>
                            <div>
                                <span class="font-semibold text-[var(--color-cinema-text)] text-sm">MoMo</span>
                                <span class="text-xs text-[var(--color-cinema-text-muted)] block">Thanh toán qua ví MoMo</span>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        {{-- Bottom Bar: Total and Checkout --}}
        <div class="glass rounded-2xl p-4 sm:p-6 flex flex-col md:flex-row items-center justify-between gap-4 md:gap-6 md:sticky md:bottom-4 md:z-40 border border-[var(--color-cinema-primary)] shadow-[0_0_20px_rgba(229,9,20,0.2)]">
            <div class="flex min-w-0 items-center gap-6">
                <div class="text-center md:text-left">
                    <span class="text-xs text-[var(--color-cinema-text-muted)] block uppercase tracking-wider">Thời gian còn lại</span>
                    <span class="text-3xl font-bold text-cinema-danger-text font-mono" data-hold-countdown>--:--</span>
                </div>
                <div class="h-12 w-px bg-[var(--color-cinema-border)] hidden md:block"></div>
                <div id="payment-summary-bottom" class="min-w-0 break-words text-sm space-y-1 hidden md:block">
                    <!-- Loaded via JS -->
                </div>
            </div>
            
            <div class="flex w-full flex-col gap-3 sm:flex-row sm:items-center md:w-auto md:shrink-0 md:gap-6">
                <div class="text-center md:text-right flex-1 md:flex-none">
                    <span class="text-xs text-[var(--color-cinema-text-muted)] block uppercase tracking-wider">Tổng cộng</span>
                    <span class="text-2xl md:text-3xl font-bold text-cinema-accent-text" id="bottom-total-price">0đ</span>
                </div>
                <button onclick="processPayment()" id="btn-pay" data-booking-action class="btn-primary px-4 py-4 text-lg font-bold sm:min-w-[200px] sm:px-8 shadow-lg shadow-red-500/30">
                    Thanh toán ngay
                </button>
            </div>
        </div>
    </div>

    <script>
        const orderId = {{ $orderId }};
        const showId = {{ $showId }};
        let orderData = null;
        let paymentBusy = false;

        document.addEventListener('DOMContentLoaded', function() {
            loadOrderDetail();
            updatePaymentUI(); // Fix viền đỏ không hiện lúc đầu
        });

        function updatePaymentUI() {
            const labels = document.querySelectorAll('.payment-method-label');
            const radios = document.querySelectorAll('input[name="payment_method"]');
            
            labels.forEach((label, index) => {
                const radio = radios[index];
                const indicator = label.querySelector('.radio-indicator');
                const innerDot = indicator.querySelector('div');
                
                if (radio.checked) {
                    label.classList.remove('border-[var(--color-cinema-border)]');
                    label.classList.add('border-[var(--color-cinema-primary)]');
                    indicator.classList.remove('border-[var(--color-cinema-border)]');
                    indicator.classList.add('border-[var(--color-cinema-primary)]');
                    innerDot.classList.remove('bg-transparent');
                    innerDot.classList.add('bg-[var(--color-cinema-primary)]');
                } else {
                    label.classList.remove('border-[var(--color-cinema-primary)]');
                    label.classList.add('border-[var(--color-cinema-border)]');
                    indicator.classList.remove('border-[var(--color-cinema-primary)]');
                    indicator.classList.add('border-[var(--color-cinema-border)]');
                    innerDot.classList.remove('bg-[var(--color-cinema-primary)]');
                    innerDot.classList.add('bg-transparent');
                }
            });
        }

        function loadOrderDetail() {
            return fetch(`/api/orders/${orderId}`, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': window.CSRF_TOKEN }
            })
            .then(res => res.json())
            .then(result => {
                orderData = result.data;
                renderOrderSummary();
            })
            .catch(err => console.error(err));
        }

        function renderOrderSummary() {
            const o = orderData;
            document.getElementById('order-summary-loading').classList.add('hidden');
            document.getElementById('order-summary-content').classList.remove('hidden');

            const startTime = new Date(o.show?.start_time);
            // Assuming default duration of 120 mins if not provided
            const durationMins = o.show?.movie?.duration_minutes || 120;
            const endTime = new Date(startTime.getTime() + durationMins * 60000);

            const startTimeStr = startTime.toLocaleTimeString('vi-VN', {hour:'2-digit',minute:'2-digit'});
            const endTimeStr = endTime.toLocaleTimeString('vi-VN', {hour:'2-digit',minute:'2-digit'});
            const dateStr = startTime.toLocaleDateString('vi-VN', {weekday: 'long', year: 'numeric', month: '2-digit', day: '2-digit'});

            document.getElementById('os-movie').textContent = o.show?.movie?.title || 'Đang cập nhật';
            document.getElementById('os-time').textContent = `${startTimeStr} - ${endTimeStr}`;
            document.getElementById('os-date').textContent = dateStr;
            document.getElementById('os-room').textContent = o.show?.room?.name || '—';
            document.getElementById('os-seats').textContent = o.seats?.map(s => s.seat?.row_label + s.seat?.seat_number).join(', ') || '—';

            if (o.snacks?.length > 0) {
                document.getElementById('os-snacks-container').classList.remove('hidden');
                document.getElementById('os-snacks-list').innerHTML = o.snacks.map(s => `
                    <div class="flex justify-between"><span class="text-[var(--color-cinema-text-muted)]">${s.snack?.name} x${s.quantity}</span><span class="text-[var(--color-cinema-text)] font-medium">${Number(s.price).toLocaleString('vi-VN')}đ</span></div>
                `).join('');
            } else {
                document.getElementById('os-snacks-container').classList.add('hidden');
            }

            const seatsTotal = o.seats?.reduce((t,s) => t + parseFloat(s.price), 0) || 0;
            const snacksTotal = o.snacks?.reduce((t,s) => t + parseFloat(s.price), 0) || 0;
            const discount = o.voucher_id ? (seatsTotal + snacksTotal - parseFloat(o.total_amount)) : 0;

            document.getElementById('payment-summary-bottom').innerHTML = `
                <div class="flex justify-between gap-8"><span class="text-[var(--color-cinema-text-muted)]">Tiền vé</span><span class="text-[var(--color-cinema-text)] font-semibold">${seatsTotal.toLocaleString('vi-VN')}đ</span></div>
                <div class="flex justify-between gap-8"><span class="text-[var(--color-cinema-text-muted)]">Bắp nước</span><span class="text-[var(--color-cinema-text)] font-semibold">${snacksTotal.toLocaleString('vi-VN')}đ</span></div>
                ${o.voucher_id ? `<div class="flex justify-between gap-8 text-cinema-success-text font-semibold"><span>Giảm giá</span><span>-${discount.toLocaleString('vi-VN')}đ</span></div>` : ''}
            `;

            document.getElementById('payment-breakdown').innerHTML = `
                <div class="flex justify-between items-center"><span class="text-[var(--color-cinema-text-muted)]">Tổng tiền vé</span><span class="text-[var(--color-cinema-text)] font-semibold">${seatsTotal.toLocaleString('vi-VN')}đ</span></div>
                <div class="flex justify-between items-center"><span class="text-[var(--color-cinema-text-muted)]">Tổng bắp nước</span><span class="text-[var(--color-cinema-text)] font-semibold">${snacksTotal.toLocaleString('vi-VN')}đ</span></div>
                ${o.voucher_id ? `<div class="flex justify-between items-center"><span class="text-[var(--color-cinema-text-muted)]">Giảm giá</span><span class="text-cinema-success-text font-semibold">-${discount.toLocaleString('vi-VN')}đ</span></div>` : ''}
                <div class="border-t border-[var(--color-cinema-border)] my-2 pt-3 flex justify-between items-center">
                    <span class="font-bold text-[var(--color-cinema-text)] text-base">Thành tiền</span>
                    <span class="font-bold text-cinema-accent-text text-lg">${Number(o.total_amount).toLocaleString('vi-VN')}đ</span>
                </div>
            `;

            document.getElementById('bottom-total-price').textContent = `${Number(o.total_amount).toLocaleString('vi-VN')}đ`;
        }

        function setPaymentBusy(busy) {
            paymentBusy = busy;
            const disabled = busy || Boolean(window.bookingHold?.isUnavailable());
            document.querySelectorAll('[data-booking-action], input[name="payment_method"]').forEach(control => {
                control.disabled = disabled;
            });
        }

        async function applyVoucher() {
            if (paymentBusy || window.bookingHold?.isUnavailable()) return;

            const code = document.getElementById('voucher-input').value.trim();
            if (!code) return;

            const status = document.getElementById('voucher-status');
            setPaymentBusy(true);
            try {
                const response = await fetch(`/api/orders/${orderId}/voucher`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.CSRF_TOKEN, 'Accept': 'application/json' },
                    body: JSON.stringify({ voucher_code: code }),
                });
                const data = await response.json();
                if (window.bookingHold?.handleError(data)) return;
                if (!response.ok) throw new Error(data.message);

                status.textContent = `Đã áp dụng mã ${data.data.voucher_code} (Giảm ${Number(data.data.discount).toLocaleString('vi-VN')}đ)`;
                await loadOrderDetail();
            } catch (error) {
                status.textContent = error.message || 'Không áp dụng được voucher.';
            } finally {
                setPaymentBusy(false);
            }
        }

        async function leavePayment(nextStep) {
            if (paymentBusy || window.bookingHold?.isUnavailable()) return;

            const method = document.querySelector('input[name="payment_method"]:checked').value;
            setPaymentBusy(true);
            try {
                const response = await fetch(`/api/orders/${orderId}/payment-method`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.CSRF_TOKEN, 'Accept': 'application/json' },
                    body: JSON.stringify({ payment_method: method }),
                });
                const data = await response.json();
                if (window.bookingHold?.handleError(data)) return;
                if (!response.ok) throw new Error(data.message);

                window.bookingHold?.stop();
                window.location.href = nextStep === 'seats' ? `/booking/seats/${showId}` : `/booking/snacks/${orderId}`;
            } catch (error) {
                window.showNotificationModal({
                    title: 'Không thể lưu phương thức thanh toán',
                    message: error.message || 'Vui lòng thử lại.',
                    type: 'error',
                });
            } finally {
                setPaymentBusy(false);
            }
        }

        async function processPayment() {
            if (paymentBusy || window.bookingHold?.isUnavailable()) return;

            const method = document.querySelector('input[name="payment_method"]:checked').value;
            const btn = document.getElementById('btn-pay');
            setPaymentBusy(true);
            btn.textContent = 'Đang xử lý...';

            try {
                const response = await fetch(`/api/orders/${orderId}/pay`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.CSRF_TOKEN, 'Accept': 'application/json' },
                    body: JSON.stringify({ payment_method: method }),
                });
                const data = await response.json();
                if (window.bookingHold?.handleError(data)) return;
                if (!response.ok) throw new Error(data.message);

                window.bookingHold?.stop();
                window.location.href = `/booking/success/${orderId}`;
            } catch (err) {
                if (!window.bookingHold?.isUnavailable()) {
                    showNotificationModal({
                        title: 'Thanh toán không thành công',
                        message: err.message || 'Không thể xử lý giao dịch. Vui lòng kiểm tra lại!',
                        type: 'error'
                    });
                }
            } finally {
                setPaymentBusy(false);
                btn.textContent = 'Thanh toán ngay';
            }
        }
    </script>
</x-layouts.main>

<x-layouts.main title="Chọn bắp nước - CineStar">
    <div class="max-w-5xl mx-auto px-4 py-8">
        {{-- Booking Breadcrumb Stepper --}}
        <x-booking-breadcrumb :step="2" :orderId="$orderId" :showId="$showId" :holdExpiresAt="$holdExpiresAt ?? null" />

        <div class="flex items-center justify-between mb-4">
            <h1 class="text-2xl font-bold text-[var(--color-cinema-text)]">Chọn bắp nước & combo</h1>
            <span class="text-xs text-[var(--color-cinema-text-muted)]">
                Mã giữ chỗ: <span class="text-[var(--color-cinema-text)] font-mono font-medium">#{{ $orderId }}</span>
            </span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            <div class="lg:col-span-2">
                <div id="snacks-list" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="col-span-full text-center py-12 text-[var(--color-cinema-text-muted)]">Đang tải...</div>
                </div>
            </div>

            <div class="lg:col-span-1">
                <div class="glass rounded-2xl p-6 sticky top-24">
                    <h3 class="font-bold text-[var(--color-cinema-text)] mb-4">Giỏ hàng</h3>
                    <div id="cart-items" class="space-y-2 text-sm"></div>
                    <div class="border-t border-[var(--color-cinema-border)] my-4"></div>
                    <div class="flex justify-between text-lg font-bold">
                        <span class="text-[var(--color-cinema-text)]">Tổng bắp nước</span>
                        <span class="text-cinema-accent-text" id="snack-total">0đ</span>
                    </div>

                    <button id="btn-save-snacks" data-booking-action onclick="submitSnacks()" disabled class="btn-primary w-full text-center py-3 mt-4 text-sm font-bold shadow-lg shadow-red-500/20">
                        Tiếp tục thanh toán →
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const orderId = {{ $orderId }};
        const showId = {{ $showId }};
        const savedSnacks = @js($selectedSnacks->values());
        const cart = Object.fromEntries(savedSnacks.map(snack => [snack.id, Number(snack.quantity)]));
        let snacks = savedSnacks;
        let loadingSnacks = true;
        let isSubmitting = false;

        document.addEventListener('DOMContentLoaded', async function() {
            try {
                const response = await fetch('/api/snacks');
                if (!response.ok) throw new Error('Không tải được danh sách bắp nước.');

                const { data } = await response.json();
                const catalog = new Map(data.map(snack => [snack.id, snack]));
                savedSnacks.forEach(snack => catalog.set(snack.id, snack));
                snacks = [...catalog.values()];
            } catch {
                window.showToast('Không tải được danh sách bắp nước.', 'warning');
            } finally {
                loadingSnacks = false;
                renderSnacks();
                updateCart();
                updateSnackNavigation();
            }
        });

        function renderSnacks() {
            const defaultSnackImg = 'https://images.unsplash.com/photo-1585647347483-22b66260dfff?q=80&w=600&auto=format&fit=crop';
            const container = document.getElementById('snacks-list');
            container.innerHTML = snacks.map(snack => {
                const imgUrl = snack.image_url || defaultSnackImg;
                return `
                <div class="glass rounded-xl p-3 sm:p-4 flex items-center justify-between gap-3 transition-all hover:border-[var(--color-cinema-primary)]/40 min-w-0">
                    <img src="${imgUrl}" alt="${snack.name}" 
                         class="w-16 h-16 sm:w-20 sm:h-20 rounded-xl object-cover shrink-0 shadow-md border border-cinema-border"
                         onerror="this.onerror=null; this.src='${defaultSnackImg}';" />
                    <div class="flex-1 min-w-0 pr-1">
                        <h4 class="font-semibold text-xs sm:text-sm text-[var(--color-cinema-text)] line-clamp-2" title="${snack.name}">${snack.name}</h4>
                        <p class="text-cinema-accent-text font-bold text-xs sm:text-sm mt-1 whitespace-nowrap">${Number(snack.price).toLocaleString('vi-VN')}đ</p>
                    </div>
                    <div class="flex items-center gap-1 sm:gap-1.5 shrink-0">
                        <button onclick="changeQty(${snack.id}, -1)" class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg flex items-center justify-center text-base sm:text-lg font-bold transition-all hover:bg-cinema-primary hover:text-white cursor-pointer text-cinema-text"
                                style="background: var(--color-cinema-card);">−</button>
                        <span class="w-6 sm:w-7 text-center font-bold text-[var(--color-cinema-text)] text-xs sm:text-sm" id="qty-${snack.id}">${cart[snack.id] || 0}</span>
                        <button onclick="changeQty(${snack.id}, 1)" class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg flex items-center justify-center text-base sm:text-lg font-bold transition-all hover:bg-cinema-primary hover:text-white cursor-pointer text-cinema-text"
                                style="background: var(--color-cinema-card);">+</button>
                    </div>
                </div>
            `;
            }).join('');
        }

        function changeQty(snackId, delta) {
            if (isSubmitting || window.bookingHold?.isUnavailable()) return;
            cart[snackId] = Math.max(0, (cart[snackId] || 0) + delta);
            document.getElementById(`qty-${snackId}`).textContent = cart[snackId];
            updateCart();
        }

        function updateCart() {
            const cartDiv = document.getElementById('cart-items');
            let total = 0;
            let items = [];

            for (const [id, qty] of Object.entries(cart)) {
                if (qty > 0) {
                    const snack = snacks.find(s => s.id == id);
                    if (snack) {
                        const subtotal = snack.price * qty;
                        total += subtotal;
                        items.push(`<div class="flex justify-between text-[var(--color-cinema-text-muted)]"><span>${snack.name} x${qty}</span><span class="text-[var(--color-cinema-text)] font-semibold">${subtotal.toLocaleString('vi-VN')}đ</span></div>`);
                    }
                }
            }

            cartDiv.innerHTML = items.length > 0 ? items.join('') : '<p class="text-[var(--color-cinema-text-muted)] text-center">Chưa chọn gì</p>';
            document.getElementById('snack-total').textContent = total.toLocaleString('vi-VN') + 'đ';
        }

        function updateSnackNavigation() {
            const disabled = loadingSnacks || isSubmitting || Boolean(window.bookingHold?.isUnavailable());
            document.querySelectorAll('[data-booking-action]').forEach(button => { button.disabled = disabled; });
        }

        async function submitSnacks(nextStep = 'payment') {
            if (loadingSnacks || isSubmitting || window.bookingHold?.isUnavailable()) return;

            isSubmitting = true;
            updateSnackNavigation();
            const snackItems = [];
            for (const [id, qty] of Object.entries(cart)) {
                if (qty > 0) {
                    snackItems.push({ snack_id: parseInt(id), quantity: qty });
                }
            }

            try {
                const response = await fetch(`/api/orders/${orderId}/snacks`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': window.CSRF_TOKEN,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ snacks: snackItems, continue_to_payment: nextStep === 'payment' }),
                });
                const result = await response.json();
                if (window.bookingHold?.handleError(result)) return;
                if (!response.ok) throw new Error(result.message);

                window.bookingHold?.stop();
                window.location.href = nextStep === 'seats' ? `/booking/seats/${showId}` : `/booking/payment/${orderId}`;
            } catch (err) {
                if (!window.bookingHold?.isUnavailable()) {
                    window.showNotificationModal({
                        title: 'Lỗi chọn bắp nước',
                        message: err.message || 'Không thể lưu danh sách bắp nước. Vui lòng thử lại!',
                        type: 'error'
                    });
                }
            } finally {
                isSubmitting = false;
                updateSnackNavigation();
            }
        }
    </script>
</x-layouts.main>

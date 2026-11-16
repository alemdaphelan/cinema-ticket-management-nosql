<x-layouts.main title="Lịch sử vé - CineStar">
    <div class="max-w-4xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold text-cinema-text mb-6">Lịch sử đặt vé</h1>

        <div id="orders-list"></div>
        <p id="orders-empty" class="glass rounded-2xl p-12 text-center text-cinema-text-muted" hidden>
            Bạn chưa có vé nào. <a href="/" class="text-cinema-primary-text hover:underline">Đặt vé ngay!</a>
        </p>
        <div id="orders-end" class="text-center">
            <p id="orders-status" role="status" class="py-3 text-sm text-cinema-text-muted">Đang tải vé…</p>
            <button id="orders-retry" type="button" class="btn-accent !py-2 !px-4 text-sm" hidden>Thử lại</button>
        </div>
    </div>

    <template id="history-ticket">
        <a class="group relative block glass rounded-2xl p-5 sm:p-6 transition-all hover:-translate-y-1 hover:shadow-lg focus-visible:outline-2 focus-visible:outline-cinema-primary-text">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-5">
                <div class="flex-1 min-w-0">
                    <p class="text-xs text-cinema-text-muted mb-2">Đặt ngày <time data-field="bookingDate"></time></p>
                    <div class="flex items-center gap-3 mb-2 flex-wrap">
                        <h3 data-field="title" class="font-bold text-cinema-text text-lg md:text-xl group-hover:text-cinema-accent-text transition-colors break-words"></h3>
                        <span data-field="status" class="text-xs px-3 py-1 rounded-full border"></span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-1.5 gap-x-6 text-sm mt-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-medium uppercase tracking-wider text-cinema-text-muted">Suất chiếu:</span>
                            <span data-field="showTime" class="text-cinema-text font-medium"></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-medium uppercase tracking-wider text-cinema-text-muted">Phòng:</span>
                            <span data-field="room" class="text-cinema-text font-medium"></span>
                        </div>
                        <div class="sm:col-span-2 flex items-center gap-2">
                            <span class="text-xs font-medium uppercase tracking-wider text-cinema-text-muted">Ghế:</span>
                            <span data-field="seats" class="text-cinema-accent-text font-semibold"></span>
                        </div>
                    </div>
                </div>

                <div class="flex md:flex-col items-center md:items-end justify-between gap-3 pt-3 md:pt-0 border-t md:border-t-0 border-cinema-border shrink-0">
                    <div>
                        <span class="text-xs text-cinema-text-muted block md:text-right">Tổng tiền</span>
                        <span data-field="amount" class="font-bold text-xl md:text-2xl text-cinema-accent-text"></span>
                    </div>
                    <span data-payment class="btn-primary !py-2 !px-4 text-xs">Thanh toán ngay</span>
                    <span data-details class="w-8 h-8 rounded-full flex items-center justify-center bg-cinema-border/40 group-hover:bg-cinema-primary/20 transition-colors" aria-hidden="true">
                        <svg class="w-4 h-4 text-cinema-accent-text" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                    </span>
                </div>
            </div>
        </a>
    </template>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const list = document.getElementById('orders-list');
            const empty = document.getElementById('orders-empty');
            const status = document.getElementById('orders-status');
            const end = document.getElementById('orders-end');
            const retry = document.getElementById('orders-retry');
            const template = document.getElementById('history-ticket');
            const groups = new Map();
            const timeZone = 'Asia/Ho_Chi_Minh';
            const dateFormat = new Intl.DateTimeFormat('vi-VN', {
                timeZone, year: 'numeric', month: '2-digit', day: '2-digit',
            });
            const timeFormat = new Intl.DateTimeFormat('vi-VN', { timeZone, hour: '2-digit', minute: '2-digit' });
            const dayFormat = new Intl.DateTimeFormat('en-CA', {
                timeZone, year: 'numeric', month: '2-digit', day: '2-digit',
            });
            const today = dayNumber(new Date(@js(now()->toISOString())));
            const statuses = {
                pending: ['Chờ thanh toán', 'text-cinema-warning-text border-amber-500/40 bg-amber-500/20'],
                paid: ['Đã thanh toán', 'text-cinema-success-text border-green-500/50 bg-green-500/20'],
                completed: ['Đã sử dụng', 'text-cinema-info-text border-blue-500/40 bg-blue-500/20'],
                cancelled: ['Đã hủy', 'text-cinema-danger-text border-red-500/50 bg-red-500/20'],
                refunded: ['Đã hoàn tiền', 'text-cinema-text-muted border-cinema-border'],
            };
            let nextCursor = null;
            let loading = false;

            function dayKey(date) {
                const parts = Object.fromEntries(dayFormat.formatToParts(date).map(({ type, value }) => [type, value]));
                return parts.year + '-' + parts.month + '-' + parts.day;
            }

            function dayNumber(date) {
                return Date.parse(dayKey(date) + 'T00:00:00Z') / 86400000;
            }

            function groupFor(date) {
                if (!Number.isFinite(date.getTime())) return ['unknown', 'Chưa xác định ngày đặt'];

                const daysAgo = today - dayNumber(date);
                if (daysAgo === 0) return ['today', 'Hôm nay'];
                if (daysAgo === 1) return ['yesterday', 'Hôm qua'];
                if (daysAgo > 1 && daysAgo <= 7) return ['week', '7 ngày qua'];
                if (daysAgo > 7 && daysAgo <= 30) return ['month', '30 ngày qua'];

                const [year, month] = dayKey(date).split('-');
                return [year + '-' + month, 'Tháng ' + month + '/' + year];
            }

            function appendTicket(order) {
                const bookingDate = new Date(order.created_at || NaN);
                const [group, label] = groupFor(bookingDate);
                if (!groups.has(group)) {
                    const section = document.createElement('section');
                    section.dataset.historyGroup = group;
                    section.className = 'mb-8 space-y-3';
                    const heading = document.createElement('h2');
                    heading.className = 'text-lg font-semibold text-cinema-text';
                    heading.textContent = label;
                    section.append(heading);
                    list.append(section);
                    groups.set(group, section);
                }

                const card = template.content.firstElementChild.cloneNode(true);
                const isPending = order.status === 'pending';
                const start = new Date(order.show?.start_time || NaN);
                const end = new Date(order.show?.end_time || (start.getTime() + (order.show?.movie?.duration_minutes || 120) * 60000));
                const [statusLabel, statusClasses] = statuses[order.status] ?? [order.status, 'text-cinema-text-muted border-cinema-border'];
                const fields = {
                    bookingDate: Number.isFinite(bookingDate.getTime()) ? dateFormat.format(bookingDate) : '—',
                    title: order.show?.movie?.title || 'Vé cũ chưa rõ suất chiếu',
                    status: statusLabel,
                    showTime: Number.isFinite(start.getTime()) && Number.isFinite(end.getTime())
                        ? timeFormat.format(start) + ' ~ ' + timeFormat.format(end) + ' — ' + dateFormat.format(start)
                        : '—',
                    room: order.show?.room?.name || '—',
                    seats: order.seats?.map(item => item.seat ? item.seat.row_label + item.seat.seat_number : '').filter(Boolean).join(', ') || '—',
                    amount: Number(order.total_amount).toLocaleString('vi-VN') + 'đ',
                };

                for (const [field, value] of Object.entries(fields)) {
                    card.querySelector('[data-field="' + field + '"]').textContent = value;
                }

                card.href = (isPending ? '/booking/payment/' : '/booking/success/') + order.id;
                card.querySelector('[data-field="bookingDate"]').dateTime = order.created_at ?? '';
                card.querySelector('[data-field="status"]').classList.add(...statusClasses.split(' '));
                card.querySelector('[data-payment]').hidden = !isPending;
                card.querySelector('[data-details]').hidden = isPending;
                groups.get(group).append(card);
            }

            async function loadTickets() {
                if (loading) return;
                loading = true;
                retry.hidden = true;
                status.textContent = 'Đang tải vé…';

                const params = new URLSearchParams();
                if (nextCursor) params.set('cursor', nextCursor);

                try {
                    const response = await fetch('/api/orders/history?' + params, {
                        headers: { Accept: 'application/json' },
                    });
                    if (!response.ok) throw new Error('Không tải được lịch sử đặt vé.');
                    const page = await response.json();

                    page.data.forEach(appendTicket);
                    nextCursor = page.next_cursor;
                    empty.hidden = groups.size > 0;
                    status.textContent = '';
                } catch {
                    status.textContent = 'Không tải được vé. Vui lòng thử lại.';
                    retry.hidden = false;
                } finally {
                    loading = false;
                }
            }

            retry.addEventListener('click', loadTickets);
            window.addEventListener('scroll', () => {
                if (nextCursor && !loading && retry.hidden && end.getBoundingClientRect().top <= window.innerHeight + 160) {
                    loadTickets();
                }
            }, { passive: true });

            loadTickets();
        });
    </script>
</x-layouts.main>

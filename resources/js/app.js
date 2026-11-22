import { initializeI18n } from './i18n.js';
import { initializeAdminCatalog } from './admin-catalog.js';

initializeI18n();

const catalog = document.querySelector('[data-admin-catalog]');
if (catalog) {
    initializeAdminCatalog(catalog);
}

const bookingPage = document.querySelector('[data-booking-step]');

if (bookingPage) {
    initializeBookingHold(bookingPage);
}

function initializeBookingHold(page) {
    const step = Number(page.dataset.bookingStep);
    const statusBar = page.querySelector('[data-hold-status]');
    const timers = document.querySelectorAll('[data-hold-countdown]');
    let orderId = Number(page.dataset.orderId) || null;
    let deadline = localDeadline(page.dataset.holdExpiresAt, page.dataset.serverTime);
    let countdown;
    let checking = false;
    let retryAt = 0;
    let reportedCode;

    window.bookingHold = {
        orderId: () => orderId,
        isUnavailable: () => Boolean(
            reportedCode === 'booking_blocked' ||
            (reportedCode && step !== 1) ||
            (orderId && Date.now() >= deadline)
        ),
        handleError,
        stop,
    };

    if (orderId && Number.isFinite(deadline)) {
        countdown = setInterval(updateCountdown, 250);
        updateCountdown();
    }

    function localDeadline(expiresAt, serverTime) {
        return Date.now() + Date.parse(expiresAt) - Date.parse(serverTime);
    }

    function stop() {
        clearInterval(countdown);
        orderId = null;
    }

    function updateCountdown() {
        const remaining = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
        const minutes = String(Math.floor(remaining / 60)).padStart(2, '0');
        const seconds = String(remaining % 60).padStart(2, '0');
        timers.forEach(timer => { timer.textContent = `${minutes}:${seconds}`; });

        if (remaining === 0 && !checking && Date.now() >= retryAt) {
            checkExpiredHold();
        }
    }

    async function checkExpiredHold() {
        const checkedOrder = orderId;
        checking = true;

        try {
            const response = await fetch(page.dataset.holdStatusUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });
            if (orderId !== checkedOrder) return;

            if (response.status === 401 || response.status === 419) {
                stop();
                window.location.reload();
                return;
            }

            const result = await response.json();
            if (orderId !== checkedOrder || handleError(result)) return;

            if (response.status === 404) {
                handleError({
                    code: 'hold_inactive',
                    message: 'Phiên giữ ghế đã kết thúc. Vui lòng chọn lại ghế.',
                });
                return;
            }

            if (!response.ok) throw new Error(result.message);

            if (['paid', 'completed'].includes(result.data.status)) {
                stop();
                window.location.replace(page.dataset.successUrl);
                return;
            }

            deadline = localDeadline(result.data.hold_expires_at, result.data.server_time);
        } catch {
            if (retryAt === 0) {
                window.showToast('Đang kiểm tra trạng thái giữ ghế. Vui lòng chờ.', 'warning');
            }
            retryAt = Date.now() + 3000;
        } finally {
            checking = false;
        }
    }

    function handleError(result) {
        const code = result?.code;
        if (!['hold_expired', 'hold_inactive', 'booking_blocked'].includes(code)) return false;

        const blocked = code === 'booking_blocked';
        if (reportedCode && (!blocked || reportedCode === code)) return true;
        reportedCode = code;
        stop();
        statusBar?.classList.add('hidden');
        timers.forEach(timer => { timer.textContent = '00:00'; });
        document.querySelectorAll('[data-booking-action]').forEach(button => { button.disabled = true; });
        window.dispatchEvent(new CustomEvent('booking:hold-ended', { detail: { blocked } }));

        window.showNotificationModal({
            title: blocked ? 'Tạm khóa đặt vé' : (code === 'hold_expired' ? 'Hết thời gian giữ ghế' : 'Phiên giữ ghế đã kết thúc'),
            message: result.message,
            type: blocked ? 'error' : 'warning',
            confirmText: blocked ? 'Về trang chủ' : 'Chọn lại ghế',
            onConfirm: blocked || step !== 1
                ? () => window.location.replace(blocked ? page.dataset.homeUrl : page.dataset.seatsUrl)
                : null,
        });

        return true;
    }
}

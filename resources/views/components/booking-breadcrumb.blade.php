@props([
    'step' => 1,
    'orderId' => null,
    'showId' => null,
    'holdExpiresAt' => null,
    'checkoutReady' => false,
])

@php
    $steps = [
        1 => ['key' => 'seats', 'name' => 'Chọn ghế', 'desc' => 'Sơ đồ rạp'],
        2 => ['key' => 'snacks', 'name' => 'Bắp nước', 'desc' => 'Combo & Snacks'],
        3 => ['key' => 'payment', 'name' => 'Thanh toán', 'desc' => 'Voucher & Cổng TT'],
        4 => ['key' => 'success', 'name' => 'Hoàn tất', 'desc' => 'Vé xem phim & QR'],
    ];
@endphp

<div class="glass rounded-2xl p-4 sm:p-5 mb-8 border border-[var(--color-cinema-border)] shadow-xl relative overflow-hidden"
     @if($step < 4)
         data-booking-step="{{ $step }}"
         data-home-url="{{ route('home') }}"
         data-seats-url="{{ route('booking.seats', ['showId' => $showId]) }}"
         @if($orderId && $holdExpiresAt)
             data-order-id="{{ $orderId }}"
             data-hold-expires-at="{{ $holdExpiresAt->toIso8601String() }}"
             data-server-time="{{ now()->toIso8601String() }}"
             data-hold-status-url="{{ route('booking.hold.expire', ['id' => $orderId]) }}"
             data-success-url="{{ route('booking.success', ['orderId' => $orderId]) }}"
         @endif
     @endif>
    {{-- Ambient glow bar --}}
    <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-[var(--color-cinema-primary)] via-[var(--color-cinema-accent)] to-[var(--color-cinema-primary)] opacity-40"></div>

    <div class="flex items-center justify-between max-w-4xl mx-auto relative">
        @foreach($steps as $s => $info)
            @php
                $isCurrent = $step === $s;
                $isCompleted = $step > $s || ($checkoutReady && $s === 2 && $step === 1);
                $target = $info['key'];
                $clickAction = match (true) {
                    !$orderId || !$holdExpiresAt || $isCurrent || $step === 4 || $s === 4 => null,
                    $step === 1 && ($s === 2 || $checkoutReady) => "holdSeats('{$target}')",
                    $step === 2 => "submitSnacks('{$target}')",
                    $step === 3 => "leavePayment('{$target}')",
                    default => null,
                };
                $stepTag = $clickAction ? 'button' : 'div';
            @endphp

            {{-- Step Item --}}
            <{{ $stepTag }} class="flex-1 flex flex-col items-center relative z-10 group {{ $clickAction ? 'cursor-pointer rounded-lg focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-cinema-accent disabled:cursor-not-allowed' : '' }}"
                 @if($isCurrent) aria-current="step" @endif
                 @if($clickAction) type="button" data-booking-nav="{{ $target }}" data-booking-action onclick="{{ $clickAction }}" @disabled($step !== 3) @endif>
                
                {{-- Step Badge / Icon --}}
                <span class="w-10 h-10 sm:w-12 sm:h-12 rounded-2xl flex items-center justify-center font-bold text-sm sm:text-base transition-all duration-300 relative
                    @if($isCurrent)
                        bg-gradient-to-br from-red-700 to-red-900 text-white shadow-[0_0_20px_rgba(229,9,20,0.5)] ring-4 ring-red-500/20 scale-105
                    @elseif($isCompleted)
                        bg-emerald-500/20 text-cinema-success-text border border-emerald-500/40 shadow-inner group-hover:scale-105 group-hover:border-emerald-400
                    @elseif($clickAction)
                        bg-[var(--color-cinema-card)] text-[var(--color-cinema-text-muted)] border border-[var(--color-cinema-border)] group-enabled:border-cinema-accent group-enabled:text-cinema-accent-text
                    @else
                        bg-[var(--color-cinema-card)] text-[var(--color-cinema-text-muted)] border border-[var(--color-cinema-border)]
                    @endif">
                    
                    @if($isCompleted)
                        {{-- Completed checkmark --}}
                        <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                    @else
                        {{-- Step Number or Icon --}}
                        <span>{{ $s }}</span>
                    @endif

                    @if($clickAction && $isCompleted)
                        <span aria-hidden="true" class="absolute -top-1 -right-1 w-4 h-4 bg-amber-500 text-black rounded-full flex items-center justify-center text-[10px] font-bold shadow">
                            ↺
                        </span>
                    @endif
                </span>

                {{-- Step Label --}}
                <span class="mt-2 block text-center">
                    <span class="block text-xs sm:text-sm font-semibold transition-colors
                        @if($isCurrent)
                            text-[var(--color-cinema-text)] font-bold
                        @elseif($isCompleted)
                            text-cinema-success-text font-semibold
                        @else
                            text-[var(--color-cinema-text-muted)]
                        @endif">
                        {{ $info['name'] }}
                    </span>
                    <span class="hidden md:block text-[11px] mt-0.5
                        @if($isCurrent)
                            text-cinema-primary-text font-semibold
                        @elseif($isCompleted)
                            text-[var(--color-cinema-text-muted)] group-hover:text-[var(--color-cinema-text)]
                        @else
                            text-[var(--color-cinema-text-muted)]
                        @endif">
                        @if($isCurrent)
                            Đang chọn
                        @elseif($isCompleted && $s === 1 && in_array($step, [2, 3]))
                            (Đổi ghế)
                        @elseif($isCompleted && $s === 2 && $step === 3)
                            (Đổi bắp nước)
                        @elseif($step === 1 && $s === 3 && $clickAction)
                            (Quay lại thanh toán)
                        @else
                            {{ $info['desc'] }}
                        @endif
                    </span>
                </span>
            </{{ $stepTag }}>

            {{-- Connector Line --}}
            @if(!$loop->last)
                <div class="flex-1 h-0.5 mx-2 sm:mx-4 -mt-6 sm:-mt-7 transition-all duration-300
                    @if($step > $s)
                        bg-gradient-to-r from-emerald-500/80 to-emerald-400/80
                    @elseif($step === $s)
                        bg-gradient-to-r from-[var(--color-cinema-primary)] to-[var(--color-cinema-border)]
                    @else
                        bg-[var(--color-cinema-border)] opacity-40
                    @endif">
                </div>
            @endif
        @endforeach
    </div>

    {{-- Status info bar for Steps 1, 2, and 3 when holding seats --}}
    @if(($step === 1 && $orderId && $holdExpiresAt) || in_array($step, [2, 3]))
        <div data-hold-status class="mt-3 pt-3 border-t border-[var(--color-cinema-border)]/50 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-[var(--color-cinema-text-muted)]">
            <div class="flex items-center gap-2">
                <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>Ghế của bạn đang được giữ trong phiên đặt vé này. Bấm vào các bước phía trên để quay lại đổi ghế hoặc bắp nước.</span>
            </div>
            @if($holdExpiresAt)
                <div class="flex items-center gap-1.5 font-mono bg-red-500/10 border border-red-500/30 px-3 py-1.5 rounded-lg text-cinema-danger-text font-bold shrink-0">
                    <span class="text-[11px] font-sans font-medium text-[var(--color-cinema-text)]">Thời gian giữ:</span>
                    <span data-hold-countdown class="font-extrabold tracking-wider">--:--</span>
                </div>
            @endif
        </div>
    @endif
</div>

{{-- Global function for confirming seat changes --}}
@once
<script>
    function confirmChangeSeats(orderId, showId) {
        if (!orderId) {
            if (showId) window.location.href = `/booking/seats/${showId}`;
            return;
        }

        if (typeof window.showConfirmModal === 'function') {
            window.showConfirmModal({
                title: 'Đổi lại ghế đã chọn?',
                message: 'Bạn có chắc chắn muốn chọn lại ghế cho suất chiếu này không? Ghế đang giữ và bắp nước đã chọn trong đơn hiện tại sẽ được giải phóng để bạn chọn lại từ đầu.',
                type: 'warning',
                confirmText: 'Xác nhận đổi ghế',
                cancelText: 'Giữ ghế hiện tại',
                onConfirm: function() {
                    executeChangeSeats(orderId, showId);
                }
            });
        } else {
            if (confirm('Bạn có chắc muốn chọn lại ghế cho suất chiếu này? Ghế đang giữ hiện tại sẽ được giải phóng.')) {
                executeChangeSeats(orderId, showId);
            }
        }
    }

    function executeChangeSeats(orderId, showId) {
        if (window.showToast) {
            window.showToast('Đang giải phóng ghế để bạn chọn lại...', 'info');
        }

        fetch(`/api/orders/${orderId}/cancel`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                'Accept': 'application/json',
            },
            body: JSON.stringify({ reason: 'change_seats' })
        })
        .then(res => res.json())
        .then(result => {
            if (window.bookingHold?.handleError(result)) return;

            const targetShowId = showId || (result.data && result.data.show_id) || result.show_id;
            if (targetShowId) {
                window.location.href = `/booking/seats/${targetShowId}`;
            } else {
                window.location.href = '/schedule';
            }
        })
        .catch(err => {
            console.error('Error changing seats:', err);
            if (showId) {
                window.location.href = `/booking/seats/${showId}`;
            } else {
                window.location.href = '/schedule';
            }
        });
    }
</script>
@endonce

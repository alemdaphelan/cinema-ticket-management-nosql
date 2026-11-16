<x-layouts.admin title="Quản lý Suất chiếu" header="📅 Quản lý Suất chiếu">
    @php
        $period = $inputs['period'] ?? 'today';
        $deleteReturnUrl = $shows && $shows->count() === 1
            ? ($shows->previousPageUrl() ?? request()->fullUrl())
            : request()->fullUrl();
    @endphp

    <div class="mb-6 flex justify-end">
        <a href="{{ route('admin.shows.create') }}" class="btn-primary !py-2 !px-4 text-sm">+ Thêm suất chiếu</a>
    </div>

    <form action="{{ route('admin.shows') }}" method="GET" class="glass mb-6 space-y-4 rounded-xl p-4 sm:p-6">
        <div class="grid gap-4 md:grid-cols-3">
            <div class="min-w-0">
                <label for="show-filter-period" class="mb-1.5 block text-sm font-medium text-cinema-text">Thời gian chiếu</label>
                <select id="show-filter-period" name="period" class="w-full rounded-lg border border-cinema-border bg-cinema-card px-3 py-2 text-sm text-cinema-text">
                    <option value="today" @selected($period === 'today')>Hôm nay</option>
                    <option value="upcoming" @selected($period === 'upcoming')>Sắp chiếu</option>
                    <option value="all" @selected($period === 'all')>Mọi thời điểm</option>
                    <option value="custom" @selected($period === 'custom')>Khoảng ngày giờ</option>
                </select>
            </div>
            <div class="min-w-0">
                <label for="show-filter-room" class="mb-1.5 block text-sm font-medium text-cinema-text">Phòng chiếu</label>
                <select id="show-filter-room" name="room_id" class="w-full rounded-lg border border-cinema-border bg-cinema-card px-3 py-2 text-sm text-cinema-text">
                    <option value="">Tất cả phòng</option>
                    @foreach($rooms as $room)
                        <option value="{{ $room->id }}" @selected(($inputs['room_id'] ?? null) === (string) $room->id)>{{ $room->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-0">
                <label for="show-filter-movie" class="mb-1.5 block text-sm font-medium text-cinema-text">Phim</label>
                <select id="show-filter-movie" name="movie_id" class="w-full rounded-lg border border-cinema-border bg-cinema-card px-3 py-2 text-sm text-cinema-text">
                    <option value="">Tất cả phim</option>
                    @foreach($movies as $movie)
                        <option value="{{ $movie->id }}" @selected(($inputs['movie_id'] ?? null) === (string) $movie->id)>{{ $movie->title }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <fieldset id="show-filter-range" class="min-w-0" @if($period !== 'custom') hidden disabled @endif>
            <legend class="mb-2 text-xs text-cinema-text-muted">Lọc theo giờ bắt đầu của suất chiếu, giờ Việt Nam</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="min-w-0">
                    <label for="show-filter-from" class="mb-1.5 block text-sm font-medium text-cinema-text">Từ</label>
                    <input type="datetime-local" id="show-filter-from" name="from" value="{{ $inputs['from'] ?? '' }}" class="w-full min-w-0 max-w-full rounded-lg border border-cinema-border bg-cinema-card px-3 py-2 text-sm text-cinema-text">
                </div>
                <div class="min-w-0">
                    <label for="show-filter-to" class="mb-1.5 block text-sm font-medium text-cinema-text">Đến</label>
                    <input type="datetime-local" id="show-filter-to" name="to" value="{{ $inputs['to'] ?? '' }}" class="w-full min-w-0 max-w-full rounded-lg border border-cinema-border bg-cinema-card px-3 py-2 text-sm text-cinema-text">
                </div>
            </div>
        </fieldset>

        @if($filterErrors)
            <ul role="alert" class="space-y-1 text-sm text-cinema-danger-text">
                @foreach($filterErrors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="btn-primary !px-4 !py-2 text-sm">Áp dụng bộ lọc</button>
            <a href="{{ route('admin.shows') }}" class="rounded-lg px-3 py-2 text-sm text-cinema-text-muted hover:bg-cinema-card hover:text-cinema-text">Đặt lại</a>
        </div>
    </form>

    @if($shows)
        <div class="glass overflow-hidden rounded-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-cinema-card text-cinema-text-muted">
                        <tr class="border-b border-cinema-border">
                            <th scope="col" class="px-4 py-3 text-left">ID</th>
                            <th scope="col" class="px-4 py-3 text-left">Phim</th>
                            <th scope="col" class="px-4 py-3 text-left">Phòng</th>
                            <th scope="col" class="px-4 py-3 text-left">Thời gian chiếu</th>
                            <th scope="col" class="px-4 py-3 text-right">Giá vé cơ bản</th>
                            <th scope="col" class="px-4 py-3 text-center">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody id="shows-table">
                        @forelse($shows as $show)
                            @php
                                $start = $show->start_time->copy()->setTimezone(\App\Services\ShowSchedulingService::TIMEZONE);
                                $end = $show->end_time?->copy()->setTimezone(\App\Services\ShowSchedulingService::TIMEZONE);
                            @endphp
                            <tr class="border-b border-cinema-border transition-colors hover:bg-cinema-card" data-show-id="{{ $show->id }}">
                                <td class="px-4 py-3">#{{ $show->id }}</td>
                                <td class="px-4 py-3 font-medium text-cinema-text">{{ $show->movie?->title ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $show->room?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <time class="block text-cinema-text" datetime="{{ $start->toIso8601String() }}">{{ $start->format('d/m/Y') }}</time>
                                    <span class="text-xs text-cinema-text-muted">{{ $start->format('H:i') }} – {{ $end?->format('H:i') ?? '—' }}</span>
                                    @if($end && !$start->isSameDay($end))
                                        <span class="text-xs text-cinema-text-muted">({{ $end->format('d/m') }})</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-medium text-cinema-accent-text">{{ number_format($show->price, 0, ',', '.') }}đ</td>
                                <td class="px-4 py-3 text-center">
                                    <button type="button" onclick="deleteShow(this, {{ $show->id }})" class="text-xs font-medium text-cinema-danger-text hover:underline">Xóa</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-cinema-text-muted">Không tìm thấy suất chiếu phù hợp.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($shows->hasPages())
                <nav aria-label="Phân trang suất chiếu" class="flex flex-wrap items-center justify-between gap-3 border-t border-cinema-border px-4 py-3 text-sm">
                    <span class="text-cinema-text-muted">{{ $shows->firstItem() ?? 0 }}–{{ $shows->lastItem() ?? 0 }} / {{ $shows->total() }} suất</span>
                    <div class="flex flex-wrap items-center gap-2">
                        @if($shows->previousPageUrl())
                            <a href="{{ $shows->previousPageUrl() }}" rel="prev" class="rounded-lg border border-cinema-border px-3 py-1.5 text-cinema-text hover:bg-cinema-card">Trước</a>
                        @endif
                        <span class="text-cinema-text-muted">Trang {{ $shows->currentPage() }}/{{ $shows->lastPage() }}</span>
                        @if($shows->nextPageUrl())
                            <a href="{{ $shows->nextPageUrl() }}" rel="next" class="rounded-lg border border-cinema-border px-3 py-1.5 text-cinema-text hover:bg-cinema-card">Sau</a>
                        @endif
                    </div>
                </nav>
            @elseif($shows->total() > 0)
                <p class="border-t border-cinema-border px-4 py-3 text-sm text-cinema-text-muted">{{ $shows->total() }} suất chiếu</p>
            @endif
        </div>
    @endif

    <script>
        const showPeriod = document.getElementById('show-filter-period');
        const showRange = document.getElementById('show-filter-range');

        function updateShowRange() {
            const custom = showPeriod.value === 'custom';
            showRange.hidden = !custom;
            showRange.disabled = !custom;
        }

        showPeriod.addEventListener('change', updateShowRange);
        updateShowRange();

        async function deleteShow(button, id) {
            if (!confirm('Bạn có chắc muốn xóa suất chiếu này?')) return;

            button.disabled = true;
            try {
                const response = await fetch(`/api/admin/shows/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': window.CSRF_TOKEN, 'Accept': 'application/json' },
                });
                if (!response.ok) throw new Error('Không thể xóa suất chiếu. Vui lòng thử lại.');

                window.location.href = @js($deleteReturnUrl);
            } catch (error) {
                window.showNotificationModal({ title: 'Không thể xóa suất chiếu', message: error.message, type: 'error' });
                button.disabled = false;
            }
        }
    </script>
</x-layouts.admin>

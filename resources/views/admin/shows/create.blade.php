<x-layouts.admin title="Thêm Suất Chiếu Mới" header="📅 Thêm Suất Chiếu Mới">
    <div class="max-w-4xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <a href="/admin/shows" class="text-sm text-[var(--color-cinema-text-muted)] hover:text-cinema-text transition-colors flex items-center gap-1.5 font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span>Quay lại danh sách suất chiếu</span>
            </a>
            <span class="text-xs text-[var(--color-cinema-text-muted)]">
                Quy định: <span class="text-cinema-warning-text font-semibold">Giãn cách tối thiểu 30 phút dọn rạp</span>
            </span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Form Column (2/3) --}}
            <div class="lg:col-span-2 space-y-6">
                <div class="glass rounded-2xl p-6 sm:p-8 border border-[var(--color-cinema-border)] shadow-xl relative overflow-hidden">
                    {{-- Ambient top bar --}}
                    <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-[var(--color-cinema-primary)] via-amber-500 to-[var(--color-cinema-primary)] opacity-40"></div>

                    <form onsubmit="submitShow(event)" id="create-show-form" class="space-y-6">
                        {{-- Chọn Phim --}}
                        <div>
                            <label class="block text-sm font-semibold mb-2 text-cinema-text">
                                🎬 Chọn phim chiếu <span class="text-cinema-danger-text">*</span>
                            </label>
                            <select id="movie_id" required onchange="onMovieChange()"
                                    class="w-full px-4 py-3 rounded-xl text-cinema-text text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-cinema-primary)] transition-all cursor-pointer"
                                    style="background: var(--color-cinema-card); border: 1px solid var(--color-cinema-border);">
                                <option value="">-- Chọn phim --</option>
                                @foreach($movies as $movie)
                                    <option value="{{ $movie->id }}"
                                            data-duration="{{ $movie->duration_minutes ?? 120 }}"
                                            data-poster="{{ $movie->poster_url }}"
                                            data-genre="{{ $movie->genre ?? 'Chưa phân loại' }}"
                                            data-rating="{{ $movie->age_rating ?? 'P' }}">
                                        {{ $movie->title }} ({{ $movie->duration_minutes ?? 120 }} phút)
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Chọn Phòng Chiếu & Ngày Chiếu --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold mb-2 text-cinema-text">
                                    🚪 Phòng chiếu <span class="text-cinema-danger-text">*</span>
                                </label>
                                <select id="room_id" required onchange="onRoomOrDateChange()"
                                        class="w-full px-4 py-3 rounded-xl text-cinema-text text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-cinema-primary)] transition-all cursor-pointer"
                                        style="background: var(--color-cinema-card); border: 1px solid var(--color-cinema-border);">
                                    @foreach($rooms as $room)
                                        <option value="{{ $room->id }}">{{ $room->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold mb-2 text-cinema-text">
                                    📆 Ngày chiếu <span class="text-cinema-danger-text">*</span>
                                </label>
                                <input type="date" id="show_date" required
                                       value="{{ now(\App\Services\ShowSchedulingService::TIMEZONE)->toDateString() }}"
                                       min="{{ now(\App\Services\ShowSchedulingService::TIMEZONE)->toDateString() }}"
                                       onchange="onRoomOrDateChange()"
                                       class="w-full px-4 py-3 rounded-xl text-cinema-text text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-cinema-primary)] transition-all cursor-pointer"
                                       style="background: var(--color-cinema-card); border: 1px solid var(--color-cinema-border);">
                            </div>
                        </div>

                        {{-- Giờ Bắt Đầu --}}
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-sm font-semibold text-cinema-text">
                                    ⏰ Giờ bắt đầu chiếu <span class="text-cinema-danger-text">*</span>
                                </label>
                                <span class="text-xs text-[var(--color-cinema-text-muted)]" id="quick-time-hint">
                                    Giờ Việt Nam
                                </span>
                            </div>
                            <input type="time" id="show_time" required oninput="onTimeChange()"
                                   class="w-full px-4 py-3 rounded-xl text-cinema-text text-base font-mono focus:outline-none focus:ring-2 focus:ring-[var(--color-cinema-primary)] transition-all"
                                   style="background: var(--color-cinema-card); border: 1px solid var(--color-cinema-border);">
                            
                            {{-- Realtime Calculation & Conflict Status Banner --}}
                            <div id="time-preview-box" class="mt-3 hidden p-4 rounded-xl border text-sm transition-all duration-300">
                                <!-- Populated dynamically by JS -->
                            </div>
                        </div>

                        {{-- Giá Vé Cơ Bản --}}
                        <div>
                            <label class="block text-sm font-semibold mb-2 text-cinema-text">
                                💵 Giá vé cơ bản (VNĐ) <span class="text-cinema-danger-text">*</span>
                            </label>
                            <input type="number" id="price" required min="10000" step="1000" value="85000"
                                   class="w-full px-4 py-3 rounded-xl text-cinema-text text-sm font-mono focus:outline-none focus:ring-2 focus:ring-[var(--color-cinema-primary)] transition-all"
                                   style="background: var(--color-cinema-card); border: 1px solid var(--color-cinema-border);">
                            
                            {{-- Quick price presets --}}
                            <div class="flex flex-wrap gap-2 mt-2.5">
                                <span class="text-xs text-[var(--color-cinema-text-muted)] self-center mr-1">Giá gợi ý:</span>
                                @foreach([75000, 85000, 95000, 100000, 120000] as $presetPrice)
                                    <button type="button" onclick="setPrice({{ $presetPrice }})"
                                            class="text-xs px-2.5 py-1 rounded-lg border border-[var(--color-cinema-border)] hover:border-[var(--color-cinema-accent)] text-[var(--color-cinema-text-muted)] hover:text-cinema-text transition-all cursor-pointer">
                                        {{ number_format($presetPrice) }}đ
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Submit Button --}}
                        <div class="pt-2">
                            <button type="submit" id="submit-btn"
                                    class="btn-primary w-full py-3.5 rounded-xl font-bold text-base shadow-lg shadow-red-500/25 flex items-center justify-center gap-2 cursor-pointer transition-all hover:scale-[1.01]">
                                <span>Tạo suất chiếu</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Sidebar Column (1/3): Existing Schedule & Movie Info --}}
            <div class="space-y-6">
                {{-- Movie Info Card (if selected) --}}
                <div id="movie-preview-card" class="glass rounded-2xl p-5 border border-[var(--color-cinema-border)] hidden">
                    <h4 class="text-xs font-bold text-cinema-accent-text uppercase tracking-wider mb-3">Thông tin phim</h4>
                    <div class="flex gap-3.5">
                        <img id="mp-poster" src="" alt="Poster" class="w-16 h-24 object-cover rounded-lg shrink-0 border border-white/10 hidden">
                        <div class="space-y-1 text-xs">
                            <h5 id="mp-title" class="font-bold text-cinema-text text-sm leading-snug"></h5>
                            <p class="text-[var(--color-cinema-text-muted)]">Thời lượng: <span id="mp-duration" class="text-cinema-warning-text font-bold"></span></p>
                            <p class="text-[var(--color-cinema-text-muted)]">Thể loại: <span id="mp-genre" class="text-cinema-text"></span></p>
                            <p class="text-[var(--color-cinema-text-muted)]">Độ tuổi: <span id="mp-rating" class="px-1.5 py-0.5 rounded bg-red-500/20 text-cinema-danger-text font-bold"></span></p>
                        </div>
                    </div>
                </div>

                {{-- Existing Schedule in this Room on this Date --}}
                <div class="glass rounded-2xl p-5 border border-[var(--color-cinema-border)]">
                    <div class="flex items-center justify-between mb-3 border-b border-[var(--color-cinema-border)] pb-2.5">
                        <h4 class="text-xs font-bold text-cinema-text uppercase tracking-wider flex items-center gap-1.5">
                            <span>📋 Lịch chiếu trong ngày</span>
                        </h4>
                        <span id="room-date-badge" class="text-[11px] text-cinema-accent-text font-medium"></span>
                    </div>

                    <div id="existing-shows-container" class="space-y-2.5 max-h-[420px] overflow-y-auto pr-1">
                        <div class="text-center py-6 text-xs text-[var(--color-cinema-text-muted)]">
                            <div class="inline-block w-4 h-4 border-2 border-red-500 border-t-transparent rounded-full animate-spin mb-1"></div>
                            <p>Đang tải lịch phòng...</p>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-[var(--color-cinema-border)]/50 text-[11px] text-[var(--color-cinema-text-muted)] flex items-start gap-1.5">
                        <span class="text-cinema-warning-text text-sm leading-none">ℹ</span>
                        <span>Sau mỗi suất chiếu, phòng cần <strong>30 phút dọn rạp</strong>. Suất tiếp theo chỉ được đặt sau khi dọn rạp xong.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let existingShows = [];
        let selectedMovieDuration = 120;
        let scheduleRequest = 0;
        let scheduleLoading = false;
        let scheduleLoaded = false;
        let submitting = false;
        const cleanup = @js(\App\Services\ShowSchedulingService::CLEANING_MINUTES);
        const scheduleOffset = @js(now(\App\Services\ShowSchedulingService::TIMEZONE)->format('P'));
        const scheduleDateTime = new Intl.DateTimeFormat('vi-VN', { timeZone: @js(\App\Services\ShowSchedulingService::TIMEZONE), dateStyle: 'short', timeStyle: 'short' });

        document.addEventListener('DOMContentLoaded', function() {
            onMovieChange();
        });

        function setPrice(val) {
            document.getElementById('price').value = val;
        }

        function onMovieChange() {
            const select = document.getElementById('movie_id');
            const card = document.getElementById('movie-preview-card');
            const option = select.options[select.selectedIndex];

            if (!option || !option.value) {
                card.classList.add('hidden');
                selectedMovieDuration = 120;
                onRoomOrDateChange();
                return;
            }

            selectedMovieDuration = parseInt(option.getAttribute('data-duration')) || 120;
            const poster = option.getAttribute('data-poster');
            const genre = option.getAttribute('data-genre');
            const rating = option.getAttribute('data-rating');

            document.getElementById('mp-title').textContent = option.text.split(' (')[0];
            document.getElementById('mp-duration').textContent = `${selectedMovieDuration} phút`;
            document.getElementById('mp-genre').textContent = genre;
            document.getElementById('mp-rating').textContent = rating;

            const posterImg = document.getElementById('mp-poster');
            if (poster) {
                posterImg.src = poster;
                posterImg.classList.remove('hidden');
            } else {
                posterImg.classList.add('hidden');
            }

            card.classList.remove('hidden');
            onRoomOrDateChange();
        }

        async function onRoomOrDateChange() {
            const roomField = document.getElementById('room_id');
            const roomId = roomField.value;
            const movieId = document.getElementById('movie_id').value;
            const date = document.getElementById('show_date').value;
            const container = document.getElementById('existing-shows-container');
            const badge = document.getElementById('room-date-badge');
            const request = ++scheduleRequest;

            existingShows = [];
            scheduleLoaded = false;
            scheduleLoading = Boolean(roomId && date);
            validateTimeSlot();

            if (!scheduleLoading) {
                badge.textContent = '';
                container.textContent = 'Chọn phòng và ngày để xem lịch.';
                return;
            }

            badge.textContent = roomField.selectedOptions[0].text + ' • ' + date.split('-').reverse().join('/');
            container.textContent = 'Đang tải lịch phòng…';

            const params = new URLSearchParams({ room_id: roomId, date });
            if (movieId) params.set('movie_id', movieId);

            try {
                const response = await fetch('/api/admin/shows/room-schedule?' + params, {
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok) throw new Error('Không thể tải lịch phòng.');
                const result = await response.json();
                if (request !== scheduleRequest) return;

                existingShows = result.data;
                scheduleLoaded = true;
                renderExistingShows(container);
            } catch {
                if (request === scheduleRequest) container.textContent = 'Không thể tải lịch phòng.';
            } finally {
                if (request === scheduleRequest) {
                    scheduleLoading = false;
                    validateTimeSlot();
                }
            }
        }

        function renderExistingShows(container) {
            const shows = existingShows.filter(show => show.in_day);

            if (!shows.length) {
                container.textContent = 'Phòng không có suất chiếu hoặc thời gian dọn rạp trong ngày này.';
                return;
            }

            container.replaceChildren(...shows.map(show => {
                const card = document.createElement('div');
                card.className = 'space-y-1 rounded-xl border border-cinema-border bg-cinema-card p-3 text-xs';
                const title = document.createElement('p');
                title.className = 'font-semibold text-cinema-text break-words';
                title.textContent = show.movie_title;
                const time = document.createElement('p');
                time.className = 'font-mono text-cinema-accent-text';
                time.textContent = scheduleDateTime.format(new Date(show.raw_start)) + ' – ' + scheduleDateTime.format(new Date(show.raw_end));
                const cleanupLabel = document.createElement('p');
                cleanupLabel.className = 'text-cinema-warning-text';
                cleanupLabel.textContent = 'Dọn rạp đến ' + scheduleDateTime.format(new Date(show.raw_clean_end));
                card.append(title, time, cleanupLabel);
                return card;
            }));
        }

        function onTimeChange() {
            validateTimeSlot();
        }

        function validateTimeSlot() {
            const date = document.getElementById('show_date').value;
            const time = document.getElementById('show_time').value;
            const movieId = document.getElementById('movie_id').value;
            const roomId = document.getElementById('room_id').value;
            const previewBox = document.getElementById('time-preview-box');
            const submit = document.getElementById('submit-btn');

            submit.disabled = submitting || scheduleLoading || !date || !time || !movieId || !roomId;
            if (submit.disabled || !scheduleLoaded) {
                previewBox.classList.add('hidden');
                return false;
            }

            const start = Date.parse(date + 'T' + time + ':00' + scheduleOffset);
            const end = start + selectedMovieDuration * 60000;
            const cleanEnd = end + cleanup * 60000;
            const conflict = existingShows.find(show =>
                start < Date.parse(show.raw_clean_end) && cleanEnd > Date.parse(show.raw_start)
            );

            const title = document.createElement('p');
            title.className = 'font-bold';
            title.textContent = conflict ? 'Không thể đặt vào giờ này' : 'Khung giờ hợp lệ';

            const detail = document.createElement('p');
            detail.textContent = conflict
                ? 'Trùng với suất «' + conflict.movie_title + '» hoặc thời gian dọn rạp đến ' + scheduleDateTime.format(new Date(conflict.raw_clean_end)) + '.'
                : 'Chiếu đến ' + scheduleDateTime.format(new Date(end)) + '. Dọn rạp xong lúc ' + scheduleDateTime.format(new Date(cleanEnd)) + '.';

            previewBox.className = 'mt-3 space-y-1 rounded-xl border p-3.5 text-sm ' + (conflict
                ? 'border-red-500/40 bg-red-500/10 text-cinema-danger-text'
                : 'border-emerald-500/40 bg-emerald-500/10 text-cinema-success-text');
            previewBox.replaceChildren(title, detail);
            submit.disabled = Boolean(conflict);
            return Boolean(conflict);
        }

        function submitShow(e) {
            e.preventDefault();
            if (submitting || scheduleLoading || validateTimeSlot()) return;

            const movieId = document.getElementById('movie_id').value;
            const roomId = document.getElementById('room_id').value;
            const date = document.getElementById('show_date').value;
            const time = document.getElementById('show_time').value;
            const price = document.getElementById('price').value;
            const submitBtn = document.getElementById('submit-btn');

            if (!movieId || !roomId || !date || !time || !price) {
                alert('Vui lòng điền đầy đủ các trường bắt buộc!');
                return;
            }

            const startTime = date + 'T' + time + ':00' + scheduleOffset;

            submitting = true;
            submitBtn.disabled = true;
            submitBtn.innerHTML = `
                <div class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                <span>Đang kiểm tra & tạo suất...</span>
            `;

            fetch('/api/admin/shows', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': window.CSRF_TOKEN,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    movie_id: parseInt(movieId),
                    room_id: parseInt(roomId),
                    start_time: startTime,
                    price: parseFloat(price),
                }),
            })
            .then(res => {
                if (!res.ok) {
                    return res.json().then(data => { throw new Error(data.message || 'Lỗi tạo suất chiếu!'); });
                }
                return res.json();
            })
            .then(() => {
                if (window.showToast) {
                    window.showToast('Tạo suất chiếu thành công!', 'success');
                }
                const filters = new URLSearchParams({
                    period: 'custom',
                    from: date + 'T00:00',
                    to: date + 'T23:59',
                    room_id: roomId,
                    movie_id: movieId,
                });
                setTimeout(() => {
                    window.location.href = '/admin/shows?' + filters;
                }, 800);
            })
            .catch(err => {
                if (window.showNotificationModal) {
                    window.showNotificationModal({
                        title: 'Không thể tạo suất chiếu',
                        message: err.message,
                        type: 'error'
                    });
                } else {
                    alert(err.message);
                }
                submitting = false;
                submitBtn.innerHTML = '<span>Tạo suất chiếu</span>';
                onRoomOrDateChange();
            });
        }
    </script>
</x-layouts.admin>

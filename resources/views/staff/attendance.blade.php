<x-layouts.staff title="Chấm công - Nhân viên" header="Chấm công nhân viên">
    <style>
        @keyframes scanLaser {
            0%, 100% { top: 8%; }
            50% { top: 88%; }
        }
        .cv-scan-line {
            animation: scanLaser 2s ease-in-out infinite;
        }
    </style>

    <div class="space-y-6">
        {{-- Top Banner + Clock + Policy --}}
        <div class="glass rounded-2xl p-5 sm:p-6 border border-[var(--color-cinema-border)] flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-cinema-text">
                    Chấm công ca làm — <span id="att-staff-name" class="text-cinema-success-text">{{ $selectedStaff->name }}</span>
                    <span class="text-xs px-2.5 py-1 rounded-full bg-sky-500/15 text-cinema-info-text ml-2" id="att-staff-code">{{ $selectedStaff->employee_code ?? 'NV' }}</span>
                </h2>
                <p class="text-xs sm:text-sm text-[var(--color-cinema-text-muted)] mt-1.5">
                    Quy định bắt buộc: Phải chấm công bắt đầu ca <strong>trước 10 phút</strong> khi ca bắt đầu và <strong>không được chấm công kết thúc ca sớm hơn</strong> giờ kết thúc ca.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                @if($staffList->isNotEmpty())
                    <select id="att-preview-staff-id" onchange="loadMyTodayAttendance()"
                            class="bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3 py-2 text-xs font-semibold text-cinema-text">
                        @foreach($staffList as $s)
                            <option value="{{ $s->id }}" {{ $s->id === $selectedStaff->id ? 'selected' : '' }}>
                                {{ $s->employee_code }} - {{ $s->name }}
                            </option>
                        @endforeach
                    </select>
                @endif
                <div class="px-4 py-2.5 rounded-xl bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] text-right">
                    <div class="text-[11px] text-[var(--color-cinema-text-muted)]">Giờ hệ thống</div>
                    <div class="text-lg font-extrabold text-cinema-accent-text font-mono" id="live-vn-clock">--:--:--</div>
                </div>
            </div>
        </div>

        {{-- Main 2-column Workspace --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            {{-- Left: Today / Upcoming Shifts Selector --}}
            <div class="lg:col-span-5 space-y-4">
                <div class="glass rounded-2xl p-5 border border-[var(--color-cinema-border)]">
                    <h3 class="text-base font-bold text-cinema-text mb-3">Ca làm việc được phân công</h3>
                    <div id="today-shifts-list" class="space-y-3">
                        <div class="py-8 text-center text-sm text-[var(--color-cinema-text-muted)]">Đang tải danh sách ca...</div>
                    </div>
                </div>
            </div>

            {{-- Right: Check-in / Check-out Panel --}}
            <div class="lg:col-span-7">
                <div class="glass rounded-2xl p-5 sm:p-6 border border-[var(--color-cinema-border)] space-y-5">
                    {{-- Selected shift banner --}}
                    <div id="selected-shift-banner" class="rounded-xl p-4 bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)]">
                        <div class="text-sm text-[var(--color-cinema-text-muted)]">Chọn một ca làm bên trái để thực hiện chấm công.</div>
                    </div>

                    {{-- Mode Switcher --}}
                    <div class="flex flex-wrap gap-2 p-1 rounded-xl bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)]">
                        <button type="button" id="mode-btn-manual" onclick="switchAttendanceMode('manual')"
                                class="flex-1 py-2.5 px-4 rounded-lg text-sm font-bold transition-all cursor-pointer bg-emerald-600 text-white shadow">
                            Chấm công thủ công
                        </button>
                        <button type="button" id="mode-btn-camera" onclick="switchAttendanceMode('camera_ai')"
                                class="flex-1 py-2.5 px-4 rounded-lg text-sm font-bold transition-all cursor-pointer text-[var(--color-cinema-text-muted)] hover:text-cinema-text">
                            Camera AI (mô phỏng)
                        </button>
                    </div>

                    {{-- Manual Panel --}}
                    <div id="panel-manual" class="space-y-4">
                        <div class="rounded-xl p-4 bg-sky-500/10 border border-sky-500/30 text-xs sm:text-sm text-cinema-info-text">
                            Chấm công thủ công ghi nhận trực tiếp thời điểm hiện tại theo giờ máy chủ Việt Nam.
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <button type="button" id="manual-checkin-btn" onclick="submitCheckIn('manual')"
                                    class="py-4 px-5 rounded-2xl text-sm font-bold bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed text-white shadow-lg shadow-emerald-600/25 transition-all cursor-pointer">
                                Bắt đầu ca
                            </button>
                            <button type="button" id="manual-checkout-btn" onclick="submitCheckOut('manual', null, false)"
                                    class="py-4 px-5 rounded-2xl text-sm font-bold bg-sky-600 hover:bg-sky-700 disabled:opacity-40 disabled:cursor-not-allowed text-white shadow-lg shadow-sky-600/25 transition-all cursor-pointer">
                                Kết thúc ca
                            </button>
                        </div>
                    </div>

                    {{-- Camera AI Computer Vision Panel --}}
                    <div id="panel-camera" class="hidden space-y-4">
                        <div class="rounded-xl p-3.5 bg-purple-500/10 border border-purple-500/30 text-xs text-cinema-purple-text flex flex-wrap items-center justify-between gap-2">
                            <span><strong>Camera AI (mô phỏng):</strong> Chức năng minh họa chấm công bằng camera. Không nhận diện hoặc xác thực khuôn mặt; tỷ lệ tin cậy là số ngẫu nhiên để minh họa.</span>
                            <span class="px-2 py-0.5 rounded bg-purple-500/20 font-bold" id="cv-status-pill">Mô phỏng sẵn sàng</span>
                        </div>

                        <div class="relative mx-auto max-w-md aspect-[4/3] rounded-2xl overflow-hidden bg-slate-950 border-2 border-[var(--color-cinema-border)] flex items-center justify-center">
                            <video id="cv-video" autoplay playsinline muted class="w-full h-full object-cover hidden"></video>
                            <canvas id="cv-canvas" width="480" height="360" class="w-full h-full object-cover hidden"></canvas>

                            <div id="cv-placeholder" class="text-center p-6 space-y-2 flex flex-col items-center">
                                <svg class="w-10 h-10 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/></svg>
                                <p class="text-sm font-semibold text-white">Camera chưa bật</p>
                                <p class="text-xs text-slate-400">Nhấn «Bật Camera» hoặc bấm nút mô phỏng để minh họa chấm công.</p>
                            </div>

                            {{-- Face Scan HUD Overlay --}}
                            <div id="cv-hud" class="hidden absolute inset-0 pointer-events-none">
                                <div class="absolute inset-8 border-2 border-emerald-400/70 rounded-2xl">
                                    <div class="cv-scan-line absolute left-0 right-0 h-0.5 bg-emerald-400 shadow-[0_0_12px_#34d399]"></div>
                                </div>
                                <div class="absolute bottom-3 left-3 right-3 px-3 py-1.5 rounded-lg bg-black/75 text-emerald-300 text-xs font-mono flex items-center justify-between">
                                    <span id="cv-hud-text">Khung camera minh họa</span>
                                    <span id="cv-confidence-badge">Tin cậy mô phỏng</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 justify-center">
                            <button type="button" onclick="startWebcam()" id="btn-start-cam"
                                    class="px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] hover:border-[var(--color-cinema-success)] cursor-pointer">
                                Bật Camera
                            </button>
                            <button type="button" onclick="stopWebcam()" id="btn-stop-cam"
                                    class="hidden px-4 py-2.5 rounded-xl text-xs sm:text-sm font-semibold bg-red-600/20 border border-red-500/40 text-cinema-danger-text cursor-pointer">
                                Tắt Camera
                            </button>
                            <button type="button" id="cv-checkin-btn" onclick="runCameraAiAttendance('check_in')"
                                    class="px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 text-white cursor-pointer">
                                Mô phỏng &amp; Bắt đầu ca
                            </button>
                            <button type="button" id="cv-checkout-btn" onclick="runCameraAiAttendance('check_out')"
                                    class="px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold bg-sky-600 hover:bg-sky-700 disabled:opacity-40 text-white cursor-pointer">
                                Mô phỏng &amp; Kết thúc ca
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent History --}}
        <div class="glass rounded-2xl border border-[var(--color-cinema-border)] shadow-xl overflow-hidden">
            <div class="p-5 border-b border-[var(--color-cinema-border)] flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-base font-bold text-cinema-text">Lịch sử chấm công gần đây của bạn</h3>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" onclick="exportMyAttendanceExcel()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow transition cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span>Xuất file Excel</span>
                    </button>
                    <a href="/staff/payroll" class="text-xs font-bold text-cinema-accent-text hover:underline">Xem bảng tính lương chi tiết →</a>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[var(--color-cinema-text-muted)] border-b border-[var(--color-cinema-border)] text-xs uppercase bg-[var(--color-cinema-card)]/60">
                            <th class="text-left py-3 px-4">Ngày &amp; Ca</th>
                            <th class="text-left py-3 px-4">Vai trò</th>
                            <th class="text-left py-3 px-4">Vào ca</th>
                            <th class="text-left py-3 px-4">Ra ca</th>
                            <th class="text-center py-3 px-4">Giờ công</th>
                            <th class="text-left py-3 px-4">Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody id="recent-att-body">
                        <tr><td colspan="6" class="py-8 text-center text-[var(--color-cinema-text-muted)]">Đang tải...</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-[var(--color-cinema-border)] flex flex-col sm:flex-row items-center justify-between gap-3 bg-[var(--color-cinema-card)]/30">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-xs text-[var(--color-cinema-text-muted)]" id="recent-pagination-info">Hiển thị 0 bản ghi</span>
                    <div class="flex items-center gap-1.5 text-xs text-[var(--color-cinema-text-muted)]">
                        <span>Hiển thị:</span>
                        <select id="recent-per-page" onchange="loadMyTodayAttendance(1)"
                                class="bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-lg px-2 py-1 text-xs font-semibold text-cinema-text">
                            <option value="5">5 dòng</option>
                            <option value="10" selected>10 dòng</option>
                            <option value="15">15 dòng</option>
                            <option value="25">25 dòng</option>
                        </select>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-1.5" id="recent-pagination-buttons"></div>
            </div>
        </div>
    </div>

    <script>
        let todayAssignments = [];
        let selectedAssignmentId = null;
        let mediaStream = null;
        let isScanning = false;
        let recentCurrentPage = 1;

        document.addEventListener('DOMContentLoaded', () => {
            startLiveClock();
            loadMyTodayAttendance(1);
        });

        window.addEventListener('beforeunload', stopWebcam);

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }

        function startLiveClock() {
            const update = () => {
                const now = new Date();
                document.getElementById('live-vn-clock').textContent = now.toLocaleTimeString('vi-VN', {
                    timeZone: 'Asia/Ho_Chi_Minh',
                    hour12: false,
                }) + ' • ' + now.toLocaleDateString('vi-VN', { timeZone: 'Asia/Ho_Chi_Minh' });
            };
            update();
            setInterval(update, 1000);
        }

        function getStaffParam() {
            const el = document.getElementById('att-preview-staff-id');
            return el && el.value ? `?staff_id=${el.value}` : '';
        }

        function loadMyTodayAttendance(recentPage = 1) {
            recentCurrentPage = recentPage;
            const params = new URLSearchParams();
            const staffEl = document.getElementById('att-preview-staff-id');
            if (staffEl && staffEl.value) params.set('staff_id', staffEl.value);
            params.set('recent_page', recentPage);
            params.set('recent_per_page', document.getElementById('recent-per-page')?.value || 10);

            fetch(`/api/staff/my-attendance/today?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(res => {
                const d = res.data;
                if (d?.staff) {
                    document.getElementById('att-staff-name').textContent = d.staff.name;
                    document.getElementById('att-staff-code').textContent = d.staff.employee_code || 'NV';
                }
                todayAssignments = d.today_assignments || [];
                if (!selectedAssignmentId || !todayAssignments.some(a => a.id === selectedAssignmentId)) {
                    const activeFirst = todayAssignments.find(a => !a.check_out_at && a.attendance_status !== 'absent');
                    selectedAssignmentId = activeFirst ? activeFirst.id : (todayAssignments[0]?.id || null);
                }
                renderTodayShifts();
                renderSelectedShiftBanner();
                renderRecentTable(d.recent_assignments || []);
                renderRecentPagination(d.recent_meta || {});
            });
        }

        function buildPageSequence(current, last) {
            if (last <= 7) {
                return Array.from({ length: last }, (_, i) => i + 1);
            }
            const pages = [1];
            if (current > 3) pages.push('...');
            const start = Math.max(2, current - 1);
            const end = Math.min(last - 1, current + 1);
            for (let p = start; p <= end; p++) pages.push(p);
            if (current < last - 2) pages.push('...');
            pages.push(last);
            return pages;
        }

        function renderRecentPagination(meta) {
            const total = meta.total || 0;
            const current = meta.current_page || 1;
            const last = meta.last_page || 1;
            const from = meta.from || 0;
            const to = meta.to || 0;

            document.getElementById('recent-pagination-info').textContent = total > 0
                ? `Hiển thị ${from}–${to} / ${total} bản ghi • Trang ${current}/${last}`
                : 'Hiển thị 0 bản ghi';

            const baseBtn = 'px-3 py-1.5 rounded-lg text-xs font-semibold border transition';
            const normalBtn = `${baseBtn} bg-[var(--color-cinema-card)] border-[var(--color-cinema-border)] text-cinema-text hover:border-emerald-500/50 cursor-pointer`;
            const activeBtn = `${baseBtn} btn-primary border-transparent cursor-pointer`;
            const disabledBtn = `${baseBtn} bg-[var(--color-cinema-card)] border-[var(--color-cinema-border)] text-[var(--color-cinema-text-muted)] opacity-40 cursor-not-allowed`;

            const btns = [];
            btns.push(`<button type="button" ${current <= 1 ? 'disabled' : `onclick="loadMyTodayAttendance(${current - 1})"`} class="${current <= 1 ? disabledBtn : normalBtn}">‹ Trước</button>`);

            for (const p of buildPageSequence(current, last)) {
                if (p === '...') {
                    btns.push('<span class="px-2 text-xs text-[var(--color-cinema-text-muted)]">…</span>');
                } else {
                    btns.push(`<button type="button" onclick="loadMyTodayAttendance(${p})" class="${p === current ? activeBtn : normalBtn}">${p}</button>`);
                }
            }

            btns.push(`<button type="button" ${current >= last ? 'disabled' : `onclick="loadMyTodayAttendance(${current + 1})"`} class="${current >= last ? disabledBtn : normalBtn}">Sau ›</button>`);
            document.getElementById('recent-pagination-buttons').innerHTML = btns.join('');
        }

        function exportMyAttendanceExcel() {
            const params = new URLSearchParams({ period_type: 'month' });
            const staffEl = document.getElementById('att-preview-staff-id');
            if (staffEl && staffEl.value) params.set('staff_id', staffEl.value);
            window.location.href = `/staff/payroll/export?${params.toString()}`;
        }

        function selectAssignment(id) {
            selectedAssignmentId = id;
            renderTodayShifts();
            renderSelectedShiftBanner();
        }

        function renderTodayShifts() {
            const listEl = document.getElementById('today-shifts-list');
            if (todayAssignments.length === 0) {
                listEl.innerHTML = '<div class="py-8 text-center text-sm text-[var(--color-cinema-text-muted)]">Không có ca trực nào trong hôm nay hoặc 2 ngày tới.</div>';
                return;
            }

            listEl.innerHTML = todayAssignments.map(a => {
                const isSelected = a.id === selectedAssignmentId;
                return `
                    <div onclick="selectAssignment(${a.id})"
                         class="rounded-xl p-3.5 border cursor-pointer transition-all ${isSelected ? 'border-emerald-500 bg-emerald-500/10 shadow-md' : 'border-[var(--color-cinema-border)] bg-[var(--color-cinema-card)] hover:border-emerald-500/40'}">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-bold text-cinema-text">${escapeHtml(a.shift_name)} • ${escapeHtml(a.shift_display_date)}</span>
                            <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-sky-500/15 text-cinema-info-text">${escapeHtml(a.position_label)}</span>
                        </div>
                        <div class="text-xs text-[var(--color-cinema-text-muted)] mt-1">
                            ${escapeHtml(a.shift_time)} • Hạn vào đúng giờ: <strong class="text-cinema-warning-text">${escapeHtml(a.check_in_deadline_display)}</strong>
                        </div>
                        <div class="text-xs mt-1.5 flex items-center justify-between">
                            <span class="font-semibold text-cinema-text">${escapeHtml(a.attendance_status_label)}</span>
                            <span class="text-[11px] text-[var(--color-cinema-text-muted)]">
                                ${a.check_in_short ? `Vào ${escapeHtml(a.check_in_short)}` : 'Chưa vào'}${a.check_out_short ? ` → Ra ${escapeHtml(a.check_out_short)}` : ''}
                            </span>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function renderSelectedShiftBanner() {
            const a = todayAssignments.find(x => x.id === selectedAssignmentId);
            const banner = document.getElementById('selected-shift-banner');

            const canCheckIn = a && !a.check_in_at && a.attendance_status !== 'absent';
            const canCheckOut = a && a.check_in_at && !a.check_out_at;

            document.getElementById('manual-checkin-btn').disabled = !canCheckIn;
            document.getElementById('manual-checkout-btn').disabled = !canCheckOut;
            document.getElementById('cv-checkin-btn').disabled = !canCheckIn;
            document.getElementById('cv-checkout-btn').disabled = !canCheckOut;

            if (!a) {
                banner.innerHTML = '<div class="text-sm text-[var(--color-cinema-text-muted)]">Chưa chọn ca làm việc nào.</div>';
                return;
            }

            banner.innerHTML = `
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <div class="text-sm font-bold text-cinema-text">${escapeHtml(a.shift_name)} • ${escapeHtml(a.shift_time)} • Ngày ${escapeHtml(a.shift_display_date)}</div>
                        <div class="text-xs text-[var(--color-cinema-text-muted)] mt-1">
                            Vào đúng giờ trước: <strong class="text-cinema-warning-text">${escapeHtml(a.check_in_deadline_display)}</strong>
                            • Kết thúc ca từ: <strong class="text-cinema-info-text">${escapeHtml(a.check_out_allowed_display)}</strong>
                        </div>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-[var(--color-cinema-surface)] border border-[var(--color-cinema-border)] text-cinema-text">
                        ${escapeHtml(a.attendance_status_label)}
                    </span>
                </div>
            `;
        }

        function switchAttendanceMode(mode) {
            const isCam = mode === 'camera_ai';
            document.getElementById('panel-manual').classList.toggle('hidden', isCam);
            document.getElementById('panel-camera').classList.toggle('hidden', !isCam);

            const mBtn = document.getElementById('mode-btn-manual');
            const cBtn = document.getElementById('mode-btn-camera');
            if (isCam) {
                cBtn.className = 'flex-1 py-2.5 px-4 rounded-lg text-sm font-bold transition-all cursor-pointer bg-purple-600 text-white shadow';
                mBtn.className = 'flex-1 py-2.5 px-4 rounded-lg text-sm font-bold transition-all cursor-pointer text-[var(--color-cinema-text-muted)] hover:text-cinema-text';
            } else {
                stopWebcam();
                mBtn.className = 'flex-1 py-2.5 px-4 rounded-lg text-sm font-bold transition-all cursor-pointer bg-emerald-600 text-white shadow';
                cBtn.className = 'flex-1 py-2.5 px-4 rounded-lg text-sm font-bold transition-all cursor-pointer text-[var(--color-cinema-text-muted)] hover:text-cinema-text';
            }
        }

        async function startWebcam() {
            const video = document.getElementById('cv-video');
            const canvas = document.getElementById('cv-canvas');
            const placeholder = document.getElementById('cv-placeholder');
            const hud = document.getElementById('cv-hud');
            const statusPill = document.getElementById('cv-status-pill');

            canvas.classList.add('hidden');

            try {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    throw new Error('Trình duyệt không hỗ trợ webcam API');
                }
                mediaStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
                video.srcObject = mediaStream;
                await video.play();
                video.classList.remove('hidden');
                placeholder.classList.add('hidden');
                hud.classList.remove('hidden');
                document.getElementById('btn-start-cam').classList.add('hidden');
                document.getElementById('btn-stop-cam').classList.remove('hidden');
                statusPill.textContent = 'Camera minh họa đang hoạt động';
                document.getElementById('cv-hud-text').textContent = 'Hình ảnh xem trước, không xác thực khuôn mặt';
                return true;
            } catch (err) {
                // Fallback giả lập khung hình Camera AI khi không có webcam vật lý
                drawSimulatedCameraFrame(canvas);
                video.classList.add('hidden');
                canvas.classList.remove('hidden');
                placeholder.classList.add('hidden');
                hud.classList.remove('hidden');
                statusPill.textContent = 'Chế độ mô phỏng Camera AI';
                document.getElementById('cv-hud-text').textContent = 'Khung camera minh họa';
                return false;
            }
        }

        function drawSimulatedCameraFrame(canvas) {
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#090d16';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            // Grid
            ctx.strokeStyle = 'rgba(56, 189, 248, 0.14)';
            for (let x = 0; x < canvas.width; x += 24) {
                ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, canvas.height); ctx.stroke();
            }
            for (let y = 0; y < canvas.height; y += 24) {
                ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(canvas.width, y); ctx.stroke();
            }

            // Silhouette face guide
            ctx.strokeStyle = '#34d399';
            ctx.lineWidth = 2.5;
            ctx.beginPath();
            ctx.ellipse(240, 145, 56, 72, 0, 0, Math.PI * 2);
            ctx.stroke();

            ctx.beginPath();
            ctx.arc(240, 295, 92, Math.PI * 1.15, Math.PI * 1.85);
            ctx.stroke();

            ctx.fillStyle = '#a7f3d0';
            ctx.font = 'bold 14px monospace';
            ctx.textAlign = 'center';
            ctx.fillText('CAMERA AI SIMULATION', 240, 32);
        }

        function stopWebcam() {
            if (mediaStream) {
                mediaStream.getTracks().forEach(t => t.stop());
                mediaStream = null;
            }
            const video = document.getElementById('cv-video');
            const hud = document.getElementById('cv-hud');
            if (video) {
                video.srcObject = null;
                video.classList.add('hidden');
            }
            if (hud) hud.classList.add('hidden');
            const placeholder = document.getElementById('cv-placeholder');
            if (placeholder && document.getElementById('cv-canvas')?.classList.contains('hidden')) {
                placeholder.classList.remove('hidden');
            }
            document.getElementById('btn-start-cam')?.classList.remove('hidden');
            document.getElementById('btn-stop-cam')?.classList.add('hidden');
        }

        async function runCameraAiAttendance(actionType) {
            if (isScanning || !selectedAssignmentId) return;
            isScanning = true;

            const video = document.getElementById('cv-video');
            const canvas = document.getElementById('cv-canvas');
            const hudText = document.getElementById('cv-hud-text');
            const confBadge = document.getElementById('cv-confidence-badge');
            const statusPill = document.getElementById('cv-status-pill');

            if (!mediaStream && canvas.classList.contains('hidden')) {
                await startWebcam();
            }

            const confidence = Number((97.4 + Math.random() * 2.3).toFixed(2));
            statusPill.textContent = 'Đang chạy mô phỏng...';
            hudText.textContent = 'Đang minh họa thao tác chấm công';
            confBadge.textContent = `${confidence}% (mô phỏng)`;

            await new Promise(resolve => setTimeout(resolve, 1400));

            // Capture snapshot from video onto canvas if live webcam is active
            if (mediaStream && video.readyState >= 2) {
                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                video.classList.add('hidden');
                canvas.classList.remove('hidden');
            }

            hudText.textContent = 'Hoàn tất mô phỏng, không xác thực khuôn mặt';
            statusPill.textContent = 'Đã hoàn tất mô phỏng';

            stopWebcam();
            canvas.classList.remove('hidden');
            document.getElementById('cv-placeholder').classList.add('hidden');

            isScanning = false;

            if (actionType === 'check_in') {
                submitCheckIn('camera_ai', confidence);
            } else {
                submitCheckOut('camera_ai', confidence, false);
            }
        }

        function submitCheckIn(method, confidence = null) {
            if (!selectedAssignmentId) return;
            const staffEl = document.getElementById('att-preview-staff-id');
            const body = {
                assignment_id: selectedAssignmentId,
                method,
                confidence,
            };
            if (staffEl && staffEl.value) body.staff_id = Number(staffEl.value);

            fetch('/api/staff/my-attendance/check-in', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': window.CSRF_TOKEN,
                },
                body: JSON.stringify(body),
            })
            .then(r => r.json().then(data => ({ ok: r.ok, data })))
            .then(({ ok, data }) => {
                if (!ok) {
                    const errMsg = data.errors ? Object.values(data.errors).flat()[0] : data.message;
                    window.showNotificationModal({
                        title: 'Không thể chấm công',
                        message: errMsg || 'Thao tác thất bại.',
                        type: 'error',
                    });
                    return;
                }
                window.showNotificationModal({
                    title: data.data?.is_violation ? 'Chấm công · Đi trễ' : 'Chấm công thành công',
                    message: data.message,
                    type: data.data?.is_violation ? 'warning' : 'success',
                });
                loadMyTodayAttendance();
            });
        }

        function submitCheckOut(method, confidence = null, confirmEarly = false) {
            if (!selectedAssignmentId) return;
            const staffEl = document.getElementById('att-preview-staff-id');
            const body = {
                assignment_id: selectedAssignmentId,
                method,
                confidence,
                confirm_early: confirmEarly,
            };
            if (staffEl && staffEl.value) body.staff_id = Number(staffEl.value);

            fetch('/api/staff/my-attendance/check-out', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': window.CSRF_TOKEN,
                },
                body: JSON.stringify(body),
            })
            .then(r => r.json().then(data => ({ ok: r.ok, data })))
            .then(({ ok, data }) => {
                if (!ok) {
                    if (data.errors?.early_checkout && !confirmEarly) {
                        window.showConfirmModal({
                            title: 'Chưa tới giờ kết thúc ca!',
                            message: data.errors.early_checkout[0],
                            type: 'warning',
                            confirmText: 'Vẫn kết thúc ca',
                            cancelText: 'Ở lại làm tiếp',
                            onConfirm: () => submitCheckOut(method, confidence, true),
                        });
                        return;
                    }
                    const errMsg = data.errors ? Object.values(data.errors).flat()[0] : data.message;
                    window.showNotificationModal({
                        title: 'Không thể kết thúc ca',
                        message: errMsg || 'Thao tác thất bại.',
                        type: 'error',
                    });
                    return;
                }

                window.showNotificationModal({
                    title: data.data?.early_leave_minutes > 0 ? 'Kết thúc ca sớm · Vi phạm' : 'Hoàn thành ca làm!',
                    message: data.message,
                    type: data.data?.early_leave_minutes > 0 ? 'warning' : 'success',
                });
                loadMyTodayAttendance();
            });
        }

        function renderRecentTable(rows) {
            const tbody = document.getElementById('recent-att-body');
            if (rows.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="py-8 text-center text-[var(--color-cinema-text-muted)]">Chưa có lịch sử chấm công.</td></tr>';
                return;
            }
            tbody.innerHTML = rows.map(r => `
                <tr class="border-b border-[var(--color-cinema-border)]/60">
                    <td class="py-3 px-4">
                        <div class="font-bold text-cinema-text">${escapeHtml(r.shift_display_date)} • ${escapeHtml(r.shift_name)}</div>
                        <div class="text-xs text-[var(--color-cinema-text-muted)]">${escapeHtml(r.shift_time)}</div>
                    </td>
                    <td class="py-3 px-4">${escapeHtml(r.position_label)}</td>
                    <td class="py-3 px-4">${escapeHtml(r.check_in_display || '—')}</td>
                    <td class="py-3 px-4">${escapeHtml(r.check_out_display || '—')}</td>
                    <td class="py-3 px-4 text-center font-bold">${r.worked_hours > 0 ? r.worked_hours + 'h' : '—'}</td>
                    <td class="py-3 px-4">
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold ${r.is_violation ? 'bg-red-500/20 text-cinema-danger-text' : (r.check_in_at ? 'bg-emerald-500/20 text-cinema-success-text' : 'bg-slate-500/20 text-[var(--color-cinema-text-muted)]')}">
                            ${escapeHtml(r.attendance_status_label)}
                        </span>
                    </td>
                </tr>
            `).join('');
        }
    </script>
</x-layouts.staff>

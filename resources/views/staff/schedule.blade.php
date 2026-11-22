<x-layouts.staff title="Lịch làm việc - Nhân viên" header="Lịch làm cá nhân">
    <style>
        .timetable-cell-bg {
            background-image:
                linear-gradient(to right, rgba(148, 163, 184, 0.10) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(148, 163, 184, 0.10) 1px, transparent 1px);
            background-size: 16px 16px;
        }
        .shift-ribbon {
            position: absolute;
            top: 10px;
            left: -26px;
            width: 96px;
            transform: rotate(-45deg);
            text-align: center;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            padding: 2px 0;
            color: #fff;
            background: linear-gradient(135deg, #dc2626, #991b1b);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.35);
            z-index: 10;
            pointer-events: none;
        }
        @media print {
            aside.admin-sidebar, header, #admin-mobile-menu, .no-print {
                display: none !important;
            }
            .lg\:ml-64 {
                margin-left: 0 !important;
            }
        }
    </style>

    <div id="staff-schedule-workspace" class="space-y-5">
        {{-- Staff info & optional Admin preview switcher --}}
        <div class="glass rounded-2xl p-4 sm:p-5 border border-[var(--color-cinema-border)] flex flex-col md:flex-row md:items-center justify-between gap-4 no-print">
            <div>
                <h2 class="text-lg sm:text-xl font-bold text-cinema-text">
                    Lịch làm việc theo tuần — <span id="staff-banner-name" class="text-cinema-success-text">{{ $selectedStaff->name }}</span>
                </h2>
                <p class="text-xs text-[var(--color-cinema-text-muted)] mt-1">
                    Mã NV: <strong class="text-cinema-text" id="staff-banner-code">{{ $selectedStaff->employee_code ?? 'NV' }}</strong>
                    • Vị trí chính: <strong class="text-cinema-info-text" id="staff-banner-pos">{{ $selectedStaff->positionLabel() }}</strong>
                    • Chỉ hiển thị những ca làm bạn phụ trách
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                @if($staffList->isNotEmpty())
                    <div class="flex items-center gap-2">
                        <label class="text-xs text-[var(--color-cinema-text-muted)]">Xem theo NV:</label>
                        <select id="preview-staff-id" onchange="loadMySchedule()"
                                class="bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3 py-2 text-xs font-semibold text-cinema-text">
                            @foreach($staffList as $s)
                                <option value="{{ $s->id }}" {{ $s->id === $selectedStaff->id ? 'selected' : '' }}>
                                    {{ $s->employee_code }} - {{ $s->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <a href="/staff/attendance" class="px-4 py-2 rounded-xl text-xs sm:text-sm font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow transition-all">
                    Đi chấm công
                </a>
            </div>
        </div>

        {{-- Timetable Card --}}
        <div class="glass rounded-2xl border border-[var(--color-cinema-border)] shadow-xl overflow-hidden">
            <div class="p-4 sm:p-5 border-b border-[var(--color-cinema-border)] bg-[var(--color-cinema-surface)]/80 flex flex-col xl:flex-row xl:items-center justify-between gap-4 no-print">
                <div class="flex flex-wrap items-center gap-4">
                    <h3 class="text-base sm:text-lg font-bold text-cinema-text">Thời khóa biểu ca trực</h3>
                    <div class="flex flex-wrap items-center gap-3 text-xs sm:text-sm">
                        <label class="inline-flex items-center gap-1.5 cursor-pointer font-medium">
                            <input type="radio" name="my_shift_filter" value="" checked onchange="loadMySchedule()" class="accent-[var(--color-cinema-success)]">
                            <span>Tất cả</span>
                        </label>
                        <label class="inline-flex items-center gap-1.5 cursor-pointer font-medium">
                            <input type="radio" name="my_shift_filter" value="1" onchange="loadMySchedule()" class="accent-[var(--color-cinema-success)]">
                            <span>Ca 1 · 22h-6h</span>
                        </label>
                        <label class="inline-flex items-center gap-1.5 cursor-pointer font-medium">
                            <input type="radio" name="my_shift_filter" value="2" onchange="loadMySchedule()" class="accent-[var(--color-cinema-success)]">
                            <span>Ca 2 · 6h-14h</span>
                        </label>
                        <label class="inline-flex items-center gap-1.5 cursor-pointer font-medium">
                            <input type="radio" name="my_shift_filter" value="3" onchange="loadMySchedule()" class="accent-[var(--color-cinema-success)]">
                            <span>Ca 3 · 14h-22h</span>
                        </label>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <input type="date" id="my-schedule-date" value="{{ now('Asia/Ho_Chi_Minh')->format('Y-m-d') }}"
                           onchange="loadMySchedule()"
                           class="bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-lg px-3 py-1.5 text-sm text-cinema-text">
                    <button type="button" onclick="goMyToday()" class="px-3 py-1.5 rounded-lg text-xs sm:text-sm font-semibold bg-sky-600 hover:bg-sky-700 text-white cursor-pointer">Hiện tại</button>
                    <button type="button" onclick="window.print()" class="px-3 py-1.5 rounded-lg text-xs sm:text-sm font-semibold bg-sky-600 hover:bg-sky-700 text-white cursor-pointer inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z"/></svg>
                        <span>In lịch</span>
                    </button>
                    <button type="button" onclick="navigateMyWeek(-1)" class="px-3 py-1.5 rounded-lg text-xs sm:text-sm font-semibold bg-sky-600 hover:bg-sky-700 text-white cursor-pointer">&lsaquo; Trở về</button>
                    <button type="button" onclick="navigateMyWeek(1)" class="px-3 py-1.5 rounded-lg text-xs sm:text-sm font-semibold bg-sky-600 hover:bg-sky-700 text-white cursor-pointer">Tiếp &rsaquo;</button>
                    <button type="button" onclick="toggleMyFullscreen()" class="p-2 rounded-lg text-xs sm:text-sm font-semibold bg-sky-600 hover:bg-sky-700 text-white cursor-pointer" title="Toàn màn hình" aria-label="Toàn màn hình">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4"/></svg>
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1050px] border-collapse text-xs">
                    <thead id="my-timetable-head">
                        <tr class="bg-sky-500/10 border-b border-[var(--color-cinema-border)]">
                            <th class="w-28 py-3 px-2 text-center font-bold text-cinema-info-text border-r border-[var(--color-cinema-border)]">Ca làm</th>
                            <th colspan="7" class="py-3 text-center text-[var(--color-cinema-text-muted)]">Đang tải lịch làm...</th>
                        </tr>
                    </thead>
                    <tbody id="my-timetable-body">
                        <tr>
                            <td colspan="8" class="py-14 text-center text-[var(--color-cinema-text-muted)]">Đang tải dữ liệu...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="p-3.5 sm:px-5 border-t border-[var(--color-cinema-border)] bg-[var(--color-cinema-card)]/40 flex flex-wrap items-center justify-between gap-4 text-xs">
                <div class="flex flex-wrap items-center gap-5">
                    <span class="inline-flex items-center gap-2"><span class="w-6 h-3.5 rounded border border-sky-400/50 bg-sky-500/20 inline-block"></span><span class="text-[var(--color-cinema-text-muted)]">Ca sắp tới</span></span>
                    <span class="inline-flex items-center gap-2"><span class="w-6 h-3.5 rounded border border-emerald-500/60 bg-emerald-500/30 inline-block"></span><span class="text-[var(--color-cinema-text-muted)]">Đã chấm công / Hoàn thành</span></span>
                    <span class="inline-flex items-center gap-2"><span class="w-6 h-3.5 rounded border border-red-500/60 bg-red-600/80 inline-block"></span><span class="text-[var(--color-cinema-text-muted)]">Vi phạm / Vắng mặt</span></span>
                </div>
                <span class="text-[11px] text-[var(--color-cinema-text-muted)]">Bắt buộc chấm công trước giờ bắt đầu 10 phút và không kết thúc ca sớm.</span>
            </div>
        </div>
    </div>

    <script>
        let myScheduleData = null;

        document.addEventListener('DOMContentLoaded', () => {
            loadMySchedule();
        });

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }

        function loadMySchedule() {
            const date = document.getElementById('my-schedule-date').value;
            const shiftFilter = document.querySelector('input[name="my_shift_filter"]:checked')?.value || '';
            const staffEl = document.getElementById('preview-staff-id');
            const params = new URLSearchParams();
            if (date) params.set('date', date);
            if (shiftFilter) params.set('shift_number', shiftFilter);
            if (staffEl && staffEl.value) params.set('staff_id', staffEl.value);

            fetch(`/api/staff/my-schedule?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(res => {
                myScheduleData = res.data;
                if (myScheduleData?.staff) {
                    document.getElementById('staff-banner-name').textContent = myScheduleData.staff.name;
                    document.getElementById('staff-banner-code').textContent = myScheduleData.staff.employee_code || 'NV';
                    document.getElementById('staff-banner-pos').textContent = myScheduleData.staff.position_label;
                }
                renderMySchedule();
            });
        }

        function goMyToday() {
            if (myScheduleData?.today_date) {
                document.getElementById('my-schedule-date').value = myScheduleData.today_date;
            }
            loadMySchedule();
        }

        function navigateMyWeek(dir) {
            if (!myScheduleData) return;
            document.getElementById('my-schedule-date').value = dir < 0
                ? myScheduleData.prev_week_date
                : myScheduleData.next_week_date;
            loadMySchedule();
        }

        function toggleMyFullscreen() {
            const el = document.getElementById('staff-schedule-workspace');
            if (!document.fullscreenElement) el.requestFullscreen && el.requestFullscreen();
            else document.exitFullscreen && document.exitFullscreen();
        }

        function renderMySchedule() {
            if (!myScheduleData) return;
            const { days, shift_rows: shiftRows, matrix } = myScheduleData;

            document.getElementById('my-timetable-head').innerHTML = `
                <tr class="bg-sky-500/10 border-b border-[var(--color-cinema-border)]">
                    <th class="w-28 py-3 px-2 text-center font-bold text-cinema-info-text border-r border-[var(--color-cinema-border)]">Ca làm</th>
                    ${days.map(d => `
                        <th class="py-2.5 px-2 text-center border-r last:border-r-0 border-[var(--color-cinema-border)] ${d.is_today ? 'bg-amber-500/15' : ''}">
                            <div class="font-bold text-cinema-info-text">${escapeHtml(d.day_label)}</div>
                            <div class="text-[11px] font-semibold text-[var(--color-cinema-text-muted)]">${escapeHtml(d.display_date)}</div>
                        </th>
                    `).join('')}
                </tr>
            `;

            document.getElementById('my-timetable-body').innerHTML = shiftRows.map(row => {
                const cells = days.map(day => {
                    const shift = matrix?.[row.shift_number]?.[day.date] || null;
                    if (!shift || !shift.assignments || shift.assignments.length === 0) {
                        return `<td class="p-2 align-top border-r last:border-r-0 border-[var(--color-cinema-border)] timetable-cell-bg h-40"></td>`;
                    }

                    const myAssign = shift.assignments[0];
                    let cardStyle = 'bg-sky-500/15 border-sky-400/50';
                    if (myAssign.attendance_status === 'absent') {
                        cardStyle = 'bg-red-500/15 border-red-500/50';
                    } else if (myAssign.is_violation) {
                        cardStyle = 'bg-amber-500/15 border-amber-500/50';
                    } else if (myAssign.check_in_at) {
                        cardStyle = 'bg-emerald-500/20 border-emerald-500/50';
                    }

                    const ribbon = myAssign.is_violation
                        ? `<div class="shift-ribbon">${myAssign.attendance_status === 'absent' ? 'Vắng mặt' : 'Vi phạm'}</div>`
                        : '';

                    const staffEl = document.getElementById('preview-staff-id');
                    const attUrl = staffEl && staffEl.value ? `/staff/attendance?staff_id=${staffEl.value}` : '/staff/attendance';

                    return `
                        <td class="p-2 align-top border-r last:border-r-0 border-[var(--color-cinema-border)] timetable-cell-bg ${day.is_today ? 'bg-amber-500/5' : ''}">
                            <div class="relative overflow-hidden rounded-xl border p-3 ${cardStyle} space-y-1.5">
                                ${ribbon}
                                <div class="font-extrabold text-cinema-text text-sm ${myAssign.is_violation ? 'pl-6' : ''}">${escapeHtml(shift.name)}</div>
                                <div class="text-[11px] font-semibold text-cinema-info-text">Vai trò: ${escapeHtml(myAssign.position_label)}</div>
                                <div class="text-[11px] text-[var(--color-cinema-text-muted)]">Ngày: ${escapeHtml(shift.display_date)}</div>
                                <div class="text-[11px] text-[var(--color-cinema-text-muted)]">Giờ: ${escapeHtml(shift.time_label)}</div>
                                <div class="pt-1.5 border-t border-[var(--color-cinema-border)]/50 text-[11px]">
                                    <div>Trạng thái: <strong class="text-cinema-text">${escapeHtml(myAssign.attendance_status_label)}</strong></div>
                                    ${myAssign.check_in_short ? `<div>Vào: <strong>${escapeHtml(myAssign.check_in_short)}</strong>${myAssign.check_out_short ? ` • Ra: <strong>${escapeHtml(myAssign.check_out_short)}</strong>` : ''}</div>` : ''}
                                </div>
                                ${!myAssign.check_out_at && myAssign.attendance_status !== 'absent' ? `
                                    <a href="${attUrl}" class="block mt-2 text-center py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px]">
                                        ${myAssign.check_in_at ? 'Chấm công ra ca' : 'Chấm công vào ca'}
                                    </a>
                                ` : ''}
                            </div>
                        </td>
                    `;
                }).join('');

                return `
                    <tr class="border-b last:border-b-0 border-[var(--color-cinema-border)]">
                        <td class="py-4 px-2 text-center align-middle font-bold border-r border-[var(--color-cinema-border)] bg-amber-400/10 text-cinema-text">
                            <div class="text-sm font-extrabold">${escapeHtml(row.short_label)}</div>
                            <div class="text-[11px] font-medium text-[var(--color-cinema-text-muted)] mt-1">${escapeHtml(row.time_label)}</div>
                            <div class="text-[10px] text-[var(--color-cinema-text-muted)] mt-0.5">8 tiếng</div>
                        </td>
                        ${cells}
                    </tr>
                `;
            }).join('');
        }
    </script>
</x-layouts.staff>

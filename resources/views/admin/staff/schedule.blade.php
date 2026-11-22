<x-layouts.admin title="Quản lý nhân viên - Lịch tổng" header="Quản lý nhân viên">
    @include('admin.staff._tabs')

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

    <div id="schedule-workspace" class="space-y-5">
        {{-- Top Summary Strip --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 no-print">
            <div class="glass rounded-2xl p-4 border border-[var(--color-cinema-border)]">
                <p class="text-xs text-[var(--color-cinema-text-muted)]">Cấu trúc ca rạp chiếu</p>
                <p class="text-base font-bold text-cinema-text mt-1">3 Ca/Ngày • 8 tiếng/ca • 5 NV/ca</p>
                <p class="text-[11px] text-[var(--color-cinema-text-muted)] mt-0.5">2 Soát vé • 2 Thu ngân • 1 Chuẩn bị snacks</p>
            </div>
            <div class="glass rounded-2xl p-4 border border-[var(--color-cinema-border)]">
                <p class="text-xs text-[var(--color-cinema-text-muted)]">Tổng doanh thu tuần đang xem</p>
                <p class="text-xl font-extrabold text-cinema-accent-text mt-1" id="week-total-revenue">0 đ</p>
                <p class="text-[11px] text-[var(--color-cinema-text-muted)] mt-0.5" id="week-range-label">Đang tải...</p>
            </div>
            <div class="glass rounded-2xl p-4 border border-[var(--color-cinema-border)] flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs text-[var(--color-cinema-text-muted)]">Vi phạm &amp; Vắng mặt cần xử lý</p>
                    <p class="text-xl font-extrabold text-cinema-danger-text mt-1" id="week-violation-summary">0 cảnh báo</p>
                    <button type="button" onclick="openViolationModal()" class="text-xs font-semibold text-cinema-primary-text hover:underline mt-0.5 cursor-pointer">
                        Xem chi tiết cảnh báo
                    </button>
                </div>
                <button type="button" onclick="openViolationModal()" id="alert-bell-btn"
                        class="px-3.5 py-2.5 rounded-xl text-xs font-bold bg-red-600 hover:bg-red-700 text-white shadow-lg shadow-red-600/30 transition-all cursor-pointer inline-flex items-center gap-1.5 shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                    <span id="alert-count-badge">0</span>
                </button>
            </div>
        </div>

        {{-- Timetable Card --}}
        <div class="glass rounded-2xl border border-[var(--color-cinema-border)] shadow-xl overflow-hidden">
            {{-- Toolbar matching reference timetable --}}
            <div class="p-4 sm:p-5 border-b border-[var(--color-cinema-border)] bg-[var(--color-cinema-surface)]/80 flex flex-col xl:flex-row xl:items-center justify-between gap-4 no-print">
                <div class="flex flex-wrap items-center gap-4">
                    <h2 class="text-lg font-bold text-cinema-text">Lịch trực ca nhân viên theo tuần</h2>
                    <div class="flex flex-wrap items-center gap-3 text-xs sm:text-sm" id="shift-radio-group">
                        <label class="inline-flex items-center gap-1.5 cursor-pointer font-medium">
                            <input type="radio" name="shift_filter" value="" checked onchange="loadSchedule()" class="accent-[var(--color-cinema-primary)]">
                            <span>Tất cả</span>
                        </label>
                        <label class="inline-flex items-center gap-1.5 cursor-pointer font-medium">
                            <input type="radio" name="shift_filter" value="1" onchange="loadSchedule()" class="accent-[var(--color-cinema-primary)]">
                            <span>Ca 1 · 22h-6h</span>
                        </label>
                        <label class="inline-flex items-center gap-1.5 cursor-pointer font-medium">
                            <input type="radio" name="shift_filter" value="2" onchange="loadSchedule()" class="accent-[var(--color-cinema-primary)]">
                            <span>Ca 2 · 6h-14h</span>
                        </label>
                        <label class="inline-flex items-center gap-1.5 cursor-pointer font-medium">
                            <input type="radio" name="shift_filter" value="3" onchange="loadSchedule()" class="accent-[var(--color-cinema-primary)]">
                            <span>Ca 3 · 14h-22h</span>
                        </label>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <input type="date" id="schedule-date" value="{{ now('Asia/Ho_Chi_Minh')->format('Y-m-d') }}"
                           onchange="loadSchedule()"
                           class="bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-lg px-3 py-1.5 text-sm text-cinema-text focus:outline-none focus:border-[var(--color-cinema-primary)]">

                    <button type="button" onclick="goToday()"
                            class="px-3 py-1.5 rounded-lg text-xs sm:text-sm font-semibold bg-sky-600 hover:bg-sky-700 text-white transition-all cursor-pointer">
                        Hiện tại
                    </button>
                    <button type="button" onclick="window.print()"
                            class="px-3 py-1.5 rounded-lg text-xs sm:text-sm font-semibold bg-sky-600 hover:bg-sky-700 text-white transition-all cursor-pointer inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z"/></svg>
                        <span>In lịch</span>
                    </button>
                    <button type="button" onclick="navigateWeek(-1)"
                            class="px-3 py-1.5 rounded-lg text-xs sm:text-sm font-semibold bg-sky-600 hover:bg-sky-700 text-white transition-all cursor-pointer">
                        &lsaquo; Trở về
                    </button>
                    <button type="button" onclick="navigateWeek(1)"
                            class="px-3 py-1.5 rounded-lg text-xs sm:text-sm font-semibold bg-sky-600 hover:bg-sky-700 text-white transition-all cursor-pointer">
                        Tiếp &rsaquo;
                    </button>
                    <button type="button" onclick="toggleFullscreen()" title="Toàn màn hình" aria-label="Toàn màn hình"
                            class="p-2 rounded-lg text-xs sm:text-sm font-semibold bg-sky-600 hover:bg-sky-700 text-white transition-all cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4"/></svg>
                    </button>
                </div>
            </div>

            {{-- Timetable Matrix --}}
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1180px] border-collapse text-xs">
                    <thead id="timetable-head">
                        <tr class="bg-sky-500/10 border-b border-[var(--color-cinema-border)]">
                            <th class="w-28 py-3 px-2 text-center font-bold text-cinema-info-text border-r border-[var(--color-cinema-border)]">Ca làm</th>
                            <th colspan="7" class="py-3 text-center text-[var(--color-cinema-text-muted)]">Đang tải lịch tổng...</th>
                        </tr>
                    </thead>
                    <tbody id="timetable-body">
                        <tr>
                            <td colspan="8" class="py-16 text-center text-[var(--color-cinema-text-muted)]">
                                Đang tải dữ liệu lịch trực 3 ca...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Legend Footer matching reference --}}
            <div class="p-3.5 sm:px-5 border-t border-[var(--color-cinema-border)] bg-[var(--color-cinema-card)]/40 flex flex-wrap items-center justify-between gap-4 text-xs">
                <div class="flex flex-wrap items-center gap-5">
                    <span class="inline-flex items-center gap-2">
                        <span class="w-6 h-3.5 rounded border border-sky-400/50 bg-sky-500/20 inline-block"></span>
                        <span class="text-[var(--color-cinema-text-muted)]">Ca sắp diễn ra</span>
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <span class="w-6 h-3.5 rounded border border-emerald-500/60 bg-emerald-500/30 inline-block"></span>
                        <span class="text-[var(--color-cinema-text-muted)]">Ca đang làm việc</span>
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <span class="w-6 h-3.5 rounded border border-slate-400/40 bg-slate-500/20 inline-block"></span>
                        <span class="text-[var(--color-cinema-text-muted)]">Ca đã hoàn thành</span>
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <span class="w-6 h-3.5 rounded border border-red-500/60 bg-red-600/80 inline-block"></span>
                        <span class="text-[var(--color-cinema-text-muted)]">Có nhân viên vi phạm / vắng mặt</span>
                    </span>
                </div>
                <div class="text-[11px] text-[var(--color-cinema-text-muted)]">
                    Quy định: Chấm công bắt đầu <strong>trước 10 phút</strong> khi vào ca &amp; <strong>không kết thúc ca sớm</strong> hơn giờ quy định.
                </div>
            </div>
        </div>
    </div>

    {{-- Shift Detail Modal --}}
    <div id="shift-detail-modal" class="fixed inset-0 z-[9990] hidden flex items-center justify-center bg-black/75 backdrop-blur-sm p-4" onclick="if(event.target===this) closeShiftModal()">
        <div class="bg-cinema-surface border border-[var(--color-cinema-border)] rounded-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto shadow-2xl p-6 space-y-5">
            <div class="flex items-start justify-between gap-4 border-b border-[var(--color-cinema-border)] pb-4">
                <div>
                    <h3 class="text-xl font-bold text-cinema-text" id="detail-shift-title">Chi tiết ca làm việc</h3>
                    <p class="text-xs sm:text-sm text-[var(--color-cinema-text-muted)] mt-1" id="detail-shift-subtitle"></p>
                </div>
                <button type="button" onclick="closeShiftModal()" class="px-3 py-1.5 rounded-lg bg-[var(--color-cinema-card)] hover:bg-[var(--color-cinema-border)] text-sm font-semibold cursor-pointer">Đóng</button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3" id="detail-revenue-cards"></div>

            <div>
                <h4 class="text-sm font-bold text-cinema-text mb-3">Danh sách 5 nhân viên trực ca &amp; Chấm công</h4>
                <div class="space-y-2.5" id="detail-assignments-list"></div>
            </div>
        </div>
    </div>

    {{-- Violation / Absence Alert Popup Modal --}}
    <div id="violation-alert-modal" class="fixed inset-0 z-[9995] hidden flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" onclick="if(event.target===this) closeViolationModal()">
        <div class="bg-cinema-surface border border-red-500/50 rounded-3xl max-w-2xl w-full max-h-[88vh] overflow-hidden shadow-2xl flex flex-col">
            <div class="p-5 sm:p-6 bg-red-600/15 border-b border-red-500/30 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl border border-red-500/50 bg-red-500/15 text-cinema-danger-text flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-cinema-text">Cảnh báo Vi phạm &amp; Vắng mặt nhân viên</h3>
                        <p class="text-xs text-[var(--color-cinema-text-muted)]">Hệ thống tự động phát hiện nhân viên đi trễ, về sớm hoặc không đi làm</p>
                    </div>
                </div>
                <button type="button" onclick="closeViolationModal()" class="px-3 py-1.5 rounded-lg bg-[var(--color-cinema-card)] text-xs font-semibold cursor-pointer">Đóng</button>
            </div>

            <div class="p-5 sm:p-6 overflow-y-auto space-y-3 flex-1" id="violation-alert-list"></div>

            <div class="p-4 sm:px-6 border-t border-[var(--color-cinema-border)] bg-[var(--color-cinema-card)]/40 flex flex-wrap items-center justify-end gap-3">
                <button type="button" onclick="closeViolationModal()"
                        class="px-4 py-2.5 rounded-xl text-sm font-semibold bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] text-[var(--color-cinema-text-muted)] hover:text-cinema-text cursor-pointer">
                    Đóng
                </button>
                <button type="button" onclick="acknowledgeAllAlerts()" id="ack-alerts-btn"
                        class="btn-primary px-5 py-2.5 rounded-xl text-sm font-bold cursor-pointer">
                    Xác nhận đã xem tất cả
                </button>
            </div>
        </div>
    </div>

    <script>
        let scheduleData = null;
        let currentAlerts = [];
        let autoPopupShown = false;

        document.addEventListener('DOMContentLoaded', () => {
            loadSchedule(true);
        });

        function formatVnd(amount) {
            return Number(amount || 0).toLocaleString('vi-VN') + ' đ';
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }

        function loadSchedule(allowAutoPopup = false) {
            const date = document.getElementById('schedule-date').value;
            const shiftFilter = document.querySelector('input[name="shift_filter"]:checked')?.value || '';
            const params = new URLSearchParams();
            if (date) params.set('date', date);
            if (shiftFilter) params.set('shift_number', shiftFilter);

            fetch(`/api/admin/staff/schedule?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(res => {
                scheduleData = res.data;
                currentAlerts = scheduleData.unacknowledged_alerts || [];
                renderSchedule();
                updateAlertBadge();

                if (allowAutoPopup && !autoPopupShown && currentAlerts.length > 0) {
                    autoPopupShown = true;
                    openViolationModal();
                }
            })
            .catch(() => {
                window.showToast && window.showToast('Không thể tải dữ liệu lịch tổng.', 'error');
            });
        }

        function goToday() {
            if (scheduleData?.today_date) {
                document.getElementById('schedule-date').value = scheduleData.today_date;
            }
            loadSchedule();
        }

        function navigateWeek(direction) {
            if (!scheduleData) return;
            document.getElementById('schedule-date').value = direction < 0
                ? scheduleData.prev_week_date
                : scheduleData.next_week_date;
            loadSchedule();
        }

        function toggleFullscreen() {
            const el = document.getElementById('schedule-workspace');
            if (!document.fullscreenElement) {
                el.requestFullscreen && el.requestFullscreen();
            } else {
                document.exitFullscreen && document.exitFullscreen();
            }
        }

        function updateAlertBadge() {
            const count = currentAlerts.length;
            document.getElementById('alert-count-badge').textContent = count;
            document.getElementById('week-violation-summary').textContent = count > 0
                ? `${count} cảnh báo chưa xác nhận`
                : 'Không có cảnh báo mới';
        }

        function renderSchedule() {
            if (!scheduleData) return;
            const { days, shift_rows: shiftRows, matrix, summary, week_start, week_end } = scheduleData;

            document.getElementById('week-total-revenue').textContent = formatVnd(summary.week_total_revenue);
            document.getElementById('week-range-label').textContent = `Tuần ${week_start.split('-').reverse().join('/')} – ${week_end.split('-').reverse().join('/')}`;

            const headRow = `
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
            document.getElementById('timetable-head').innerHTML = headRow;

            const bodyHtml = shiftRows.map(row => {
                const rowCells = days.map(day => {
                    const shift = matrix?.[row.shift_number]?.[day.date] || null;
                    if (!shift) {
                        return `<td class="p-1.5 align-top border-r last:border-r-0 border-[var(--color-cinema-border)] timetable-cell-bg min-h-[210px]"></td>`;
                    }
                    return `<td class="p-1.5 align-top border-r last:border-r-0 border-[var(--color-cinema-border)] timetable-cell-bg ${day.is_today ? 'bg-amber-500/5' : ''}">${renderShiftCard(shift)}</td>`;
                }).join('');

                return `
                    <tr class="border-b last:border-b-0 border-[var(--color-cinema-border)]">
                        <td class="py-4 px-2 text-center align-middle font-bold border-r border-[var(--color-cinema-border)] bg-amber-400/10 text-cinema-text">
                            <div class="text-sm font-extrabold">${escapeHtml(row.short_label)}</div>
                            <div class="text-[11px] font-medium text-[var(--color-cinema-text-muted)] mt-1">${escapeHtml(row.time_label)}</div>
                            <div class="text-[10px] text-[var(--color-cinema-text-muted)] mt-0.5">8 tiếng</div>
                        </td>
                        ${rowCells}
                    </tr>
                `;
            }).join('');

            document.getElementById('timetable-body').innerHTML = bodyHtml;
        }

        function renderShiftCard(shift) {
            let cardStyle = 'bg-sky-500/15 border-sky-400/40';
            let statusBadge = '<span class="px-1.5 py-0.5 rounded bg-sky-500/20 text-cinema-info-text text-[10px] font-bold">Sắp làm</span>';

            if (shift.status === 'in_progress') {
                cardStyle = 'bg-emerald-500/20 border-emerald-500/50';
                statusBadge = '<span class="px-1.5 py-0.5 rounded bg-emerald-500/25 text-cinema-success-text text-[10px] font-bold">Đang trực</span>';
            } else if (shift.status === 'completed') {
                cardStyle = 'bg-slate-500/15 border-slate-400/35';
                statusBadge = '<span class="px-1.5 py-0.5 rounded bg-slate-500/25 text-[var(--color-cinema-text-muted)] text-[10px] font-bold">Đã xong</span>';
            }

            const ribbon = shift.has_violation
                ? `<div class="shift-ribbon">${shift.absent_count > 0 ? 'Vắng mặt' : 'Vi phạm'}</div>`
                : '';

            const groups = {
                ticket_checker: { label: 'Soát vé · 2', items: [] },
                cashier: { label: 'Thu ngân · 2', items: [] },
                snack_prep: { label: 'Snacks · 1', items: [] },
            };

            (shift.assignments || []).forEach(a => {
                if (groups[a.position]) groups[a.position].items.push(a);
            });

            const staffListHtml = Object.values(groups).map(g => `
                <div class="mt-1">
                    <div class="text-[10px] font-bold uppercase tracking-wide text-[var(--color-cinema-text-muted)]">${g.label}</div>
                    ${g.items.map(a => {
                        let dotClass = 'bg-slate-400';
                        let nameClass = 'text-cinema-text';
                        if (a.attendance_status === 'absent') {
                            dotClass = 'bg-red-500';
                            nameClass = 'text-cinema-danger-text font-semibold line-through';
                        } else if (a.is_violation) {
                            dotClass = 'bg-amber-500';
                            nameClass = 'text-cinema-warning-text font-semibold';
                        } else if (a.check_in_at) {
                            dotClass = 'bg-emerald-500';
                        }
                        return `
                            <div class="flex items-center justify-between gap-1 text-[11px] leading-tight py-0.5">
                                <span class="truncate inline-flex items-center gap-1.5 ${nameClass}">
                                    <span class="w-1.5 h-1.5 rounded-full shrink-0 ${dotClass}"></span>
                                    <span class="truncate">${escapeHtml(a.employee_name)}</span>
                                </span>
                                <span class="text-[10px] text-[var(--color-cinema-text-muted)] shrink-0">${a.check_in_short ? escapeHtml(a.check_in_short) : escapeHtml(a.employee_code)}</span>
                            </div>
                        `;
                    }).join('')}
                </div>
            `).join('');

            return `
                <div onclick="openShiftDetail(${shift.shift_number}, '${shift.shift_date}')"
                     class="relative overflow-hidden rounded-xl border p-2.5 cursor-pointer transition-all hover:scale-[1.01] hover:shadow-lg ${cardStyle}">
                    ${ribbon}
                    <div class="flex items-center justify-between gap-1 mb-1 ${shift.has_violation ? 'pl-6' : ''}">
                        <span class="font-extrabold text-cinema-text text-xs">${escapeHtml(shift.name)}</span>
                        ${statusBadge}
                    </div>
                    <div class="text-[11px] font-medium text-[var(--color-cinema-text-muted)] flex items-center justify-between">
                        <span>${escapeHtml(shift.time_label)}</span>
                        <span>${escapeHtml(shift.display_date)}</span>
                    </div>
                    <div class="mt-1.5 px-2 py-1 rounded-lg bg-[var(--color-cinema-bg)]/40 border border-[var(--color-cinema-border)]/60 flex items-center justify-between">
                        <span class="text-[10px] text-[var(--color-cinema-text-muted)]">Doanh thu ca</span>
                        <span class="text-[11px] font-bold text-cinema-accent-text">${formatVnd(shift.total_revenue)}</span>
                    </div>
                    <div class="mt-1.5 pt-1 border-t border-[var(--color-cinema-border)]/50">
                        ${staffListHtml}
                    </div>
                </div>
            `;
        }

        function openShiftDetail(shiftNumber, shiftDate) {
            const shift = scheduleData?.matrix?.[shiftNumber]?.[shiftDate];
            if (!shift) return;

            document.getElementById('detail-shift-title').textContent = `${shift.name} • Ngày ${shift.display_date}`;
            document.getElementById('detail-shift-subtitle').textContent = `Khung giờ: ${shift.time_label} • 8 tiếng • Trạng thái: ${shift.status_label}`;

            document.getElementById('detail-revenue-cards').innerHTML = `
                <div class="rounded-xl p-3 bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)]">
                    <div class="text-xs text-[var(--color-cinema-text-muted)]">Doanh thu tại quầy</div>
                    <div class="text-base font-bold text-cinema-text mt-0.5">${formatVnd(shift.counter_revenue)}</div>
                </div>
                <div class="rounded-xl p-3 bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)]">
                    <div class="text-xs text-[var(--color-cinema-text-muted)]">Doanh thu vé Online · ${shift.online_orders_count} đơn</div>
                    <div class="text-base font-bold text-cinema-info-text mt-0.5">${formatVnd(shift.online_revenue)}</div>
                </div>
                <div class="rounded-xl p-3 bg-amber-500/10 border border-amber-500/30">
                    <div class="text-xs text-cinema-accent-text font-semibold">Tổng doanh thu ca</div>
                    <div class="text-lg font-extrabold text-cinema-accent-text mt-0.5">${formatVnd(shift.total_revenue)}</div>
                </div>
            `;

            document.getElementById('detail-assignments-list').innerHTML = (shift.assignments || []).map(a => {
                let statusPill = `<span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-500/20 text-[var(--color-cinema-text-muted)]">${escapeHtml(a.attendance_status_label)}</span>`;
                if (a.attendance_status === 'absent') {
                    statusPill = `<span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-red-500/20 text-cinema-danger-text">${escapeHtml(a.attendance_status_label)}</span>`;
                } else if (a.is_violation) {
                    statusPill = `<span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-500/20 text-cinema-warning-text">${escapeHtml(a.attendance_status_label)}</span>`;
                } else if (a.check_in_at) {
                    statusPill = `<span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500/20 text-cinema-success-text">${escapeHtml(a.attendance_status_label)}</span>`;
                }

                return `
                    <div class="rounded-xl p-3.5 bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs font-bold px-2 py-0.5 rounded bg-[var(--color-cinema-surface)] border border-[var(--color-cinema-border)]">${escapeHtml(a.employee_code)}</span>
                                <span class="font-bold text-cinema-text">${escapeHtml(a.employee_name)}</span>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-sky-500/15 text-cinema-info-text font-semibold">${escapeHtml(a.position_label)}</span>
                                ${statusPill}
                            </div>
                            <div class="text-xs text-[var(--color-cinema-text-muted)] mt-1.5 flex flex-wrap gap-x-4 gap-y-1">
                                <span>Vào ca: <strong class="text-cinema-text">${escapeHtml(a.check_in_display || 'Chưa chấm công')}</strong></span>
                                <span>Ra ca: <strong class="text-cinema-text">${escapeHtml(a.check_out_display || 'Chưa chấm công')}</strong></span>
                                ${a.worked_hours > 0 ? `<span>Giờ công: <strong class="text-cinema-success-text">${a.worked_hours}h</strong></span>` : ''}
                            </div>
                            ${a.violation_reason ? `<div class="text-xs text-cinema-danger-text mt-1 font-medium">Vi phạm: ${escapeHtml(a.violation_reason)}</div>` : ''}
                        </div>
                        <div class="flex flex-wrap gap-1.5 shrink-0">
                            ${!a.check_in_at ? `<button type="button" onclick="adminAttendanceAction(${a.id}, 'check_in', ${shift.shift_number}, '${shift.shift_date}')" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white cursor-pointer">Vào ca</button>` : ''}
                            ${a.check_in_at && !a.check_out_at ? `<button type="button" onclick="adminAttendanceAction(${a.id}, 'check_out', ${shift.shift_number}, '${shift.shift_date}')" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-sky-600 hover:bg-sky-700 text-white cursor-pointer">Ra ca</button>` : ''}
                            ${a.attendance_status !== 'absent' && !a.check_in_at ? `<button type="button" onclick="adminAttendanceAction(${a.id}, 'mark_absent', ${shift.shift_number}, '${shift.shift_date}')" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-red-600 hover:bg-red-700 text-white cursor-pointer">Đánh dấu vắng</button>` : ''}
                            ${a.attendance_status !== 'pending' ? `<button type="button" onclick="adminAttendanceAction(${a.id}, 'reset', ${shift.shift_number}, '${shift.shift_date}')" class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-[var(--color-cinema-surface)] border border-[var(--color-cinema-border)] text-[var(--color-cinema-text-muted)] hover:text-cinema-text cursor-pointer">Đặt lại</button>` : ''}
                        </div>
                    </div>
                `;
            }).join('');

            document.getElementById('shift-detail-modal').classList.remove('hidden');
        }

        function closeShiftModal() {
            document.getElementById('shift-detail-modal').classList.add('hidden');
        }

        function adminAttendanceAction(assignmentId, action, shiftNumber, shiftDate) {
            fetch(`/api/admin/staff/assignments/${assignmentId}/attendance`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': window.CSRF_TOKEN,
                },
                body: JSON.stringify({ action, confirm_early: true }),
            })
            .then(res => res.json().then(data => ({ ok: res.ok, data })))
            .then(({ ok, data }) => {
                if (!ok) {
                    window.showToast && window.showToast(data.message || 'Thao tác thất bại.', 'error');
                    return;
                }
                window.showToast && window.showToast(data.message, 'success');
                fetch(`/api/admin/staff/schedule?date=${shiftDate}`, { headers: { 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(res => {
                        scheduleData = res.data;
                        currentAlerts = scheduleData.unacknowledged_alerts || [];
                        renderSchedule();
                        updateAlertBadge();
                        openShiftDetail(shiftNumber, shiftDate);
                    });
            });
        }

        function openViolationModal() {
            const listEl = document.getElementById('violation-alert-list');
            const ackBtn = document.getElementById('ack-alerts-btn');

            if (currentAlerts.length === 0) {
                listEl.innerHTML = `
                    <div class="text-center py-10">
                        <p class="font-bold text-cinema-text">Hiện tại không có vi phạm hoặc vắng mặt mới</p>
                        <p class="text-xs text-[var(--color-cinema-text-muted)] mt-1">Tất cả cảnh báo trước đó đã được xác nhận.</p>
                    </div>
                `;
                ackBtn.classList.add('hidden');
            } else {
                ackBtn.classList.remove('hidden');
                listEl.innerHTML = currentAlerts.map(item => `
                    <div class="rounded-2xl p-4 bg-[var(--color-cinema-card)] border ${item.attendance_status === 'absent' ? 'border-red-500/50' : 'border-amber-500/40'}">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-1.5">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded bg-red-600/20 text-cinema-danger-text text-xs font-bold">${escapeHtml(item.employee_code)}</span>
                                <span class="font-bold text-cinema-text">${escapeHtml(item.employee_name)}</span>
                                <span class="text-xs text-[var(--color-cinema-text-muted)]">• ${escapeHtml(item.position_label)}</span>
                            </div>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold ${item.attendance_status === 'absent' ? 'bg-red-500/20 text-cinema-danger-text' : 'bg-amber-500/20 text-cinema-warning-text'}">
                                ${escapeHtml(item.status_label)}
                            </span>
                        </div>
                        <div class="text-xs text-[var(--color-cinema-text-muted)] mb-1">
                            Ngày ${escapeHtml(item.shift_date)} • ${escapeHtml(item.shift_name)} • ${escapeHtml(item.shift_time)}
                            ${item.phone ? ` • SĐT: ${escapeHtml(item.phone)}` : ''}
                        </div>
                        <div class="text-xs font-semibold text-cinema-danger-text">
                            Lý do: ${escapeHtml(item.violation_reason)}
                        </div>
                    </div>
                `).join('');
            }

            document.getElementById('violation-alert-modal').classList.remove('hidden');
        }

        function closeViolationModal() {
            document.getElementById('violation-alert-modal').classList.add('hidden');
        }

        function acknowledgeAllAlerts() {
            fetch('/api/admin/staff/alerts/acknowledge', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': window.CSRF_TOKEN,
                },
                body: JSON.stringify({}),
            })
            .then(res => res.json())
            .then(data => {
                window.showToast && window.showToast(data.message || 'Đã xác nhận cảnh báo.', 'success');
                currentAlerts = [];
                updateAlertBadge();
                closeViolationModal();
            });
        }
    </script>
</x-layouts.admin>

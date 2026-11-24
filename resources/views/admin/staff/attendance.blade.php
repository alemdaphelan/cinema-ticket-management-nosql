<x-layouts.admin title="Quản lý nhân viên - Bảng chấm công" header="Quản lý nhân viên">
    @include('admin.staff._tabs')

    <div class="space-y-6">
        {{-- Header & Excel Export --}}
        <div class="glass rounded-2xl p-5 sm:p-6 border border-[var(--color-cinema-border)] flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-cinema-text">Bảng chấm công nhân viên theo tháng / kỳ</h2>
                <p class="text-xs sm:text-sm text-[var(--color-cinema-text-muted)] mt-1">
                    Theo dõi thời gian bắt đầu ca, kết thúc ca, phương thức chấm công và xuất báo cáo sang file Excel
                </p>
            </div>
            <button type="button" onclick="exportAttendanceExcel()"
                    class="px-5 py-2.5 rounded-xl text-sm font-bold bg-emerald-700 hover:bg-emerald-800 text-white shadow-lg shadow-emerald-600/30 transition-all flex items-center gap-2 cursor-pointer shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Xuất file Excel</span>
            </button>
        </div>

        {{-- Summary KPIs --}}
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="glass rounded-2xl p-4 border border-[var(--color-cinema-border)]">
                <p class="text-xs text-[var(--color-cinema-text-muted)]">Tổng lượt phân ca</p>
                <p class="text-2xl font-extrabold text-cinema-text mt-1" id="kpi-total">0</p>
            </div>
            <div class="glass rounded-2xl p-4 border border-[var(--color-cinema-border)]">
                <p class="text-xs text-[var(--color-cinema-text-muted)]">Đúng giờ / Hoàn thành</p>
                <p class="text-2xl font-extrabold text-cinema-success-text mt-1" id="kpi-valid">0</p>
            </div>
            <div class="glass rounded-2xl p-4 border border-[var(--color-cinema-border)]">
                <p class="text-xs text-[var(--color-cinema-text-muted)]">Vi phạm</p>
                <p class="text-2xl font-extrabold text-cinema-warning-text mt-1" id="kpi-violation">0</p>
            </div>
            <div class="glass rounded-2xl p-4 border border-[var(--color-cinema-border)]">
                <p class="text-xs text-[var(--color-cinema-text-muted)]">Vắng mặt</p>
                <p class="text-2xl font-extrabold text-cinema-danger-text mt-1" id="kpi-absent">0</p>
            </div>
            <div class="glass rounded-2xl p-4 border border-[var(--color-cinema-border)] col-span-2 lg:col-span-1">
                <p class="text-xs text-[var(--color-cinema-text-muted)]">Tổng giờ làm</p>
                <p class="text-2xl font-extrabold text-cinema-info-text mt-1" id="kpi-hours">0h</p>
            </div>
        </div>

        {{-- Filters --}}
        <div class="glass rounded-2xl p-5 sm:p-6 border border-[var(--color-cinema-border)] space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1.5">Lọc theo kỳ</label>
                    <select id="filter-period-type" onchange="onPeriodChange()"
                            class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2.5 text-sm text-cinema-text">
                        <option value="month" selected>Theo tháng</option>
                        <option value="week">Theo tuần</option>
                        <option value="year">Theo năm</option>
                        <option value="day">Ngày cụ thể</option>
                        <option value="all">Tất cả thời gian</option>
                    </select>
                </div>

                <div id="wrap-month">
                    <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1.5">Chọn tháng</label>
                    <input type="month" id="filter-month" value="{{ now('Asia/Ho_Chi_Minh')->format('Y-m') }}"
                           class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2.5 text-sm text-cinema-text">
                </div>

                <div id="wrap-week" class="hidden">
                    <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1.5">Chọn tuần</label>
                    <input type="week" id="filter-week" value="{{ now('Asia/Ho_Chi_Minh')->format('o-\WW') }}"
                           class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2.5 text-sm text-cinema-text">
                </div>

                <div id="wrap-year" class="hidden">
                    <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1.5">Nhập năm</label>
                    <input type="number" id="filter-year" value="{{ now('Asia/Ho_Chi_Minh')->format('Y') }}" min="2020" max="2035"
                           class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2.5 text-sm text-cinema-text">
                </div>

                <div id="wrap-day" class="hidden">
                    <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1.5">Chọn ngày cụ thể</label>
                    <input type="date" id="filter-date" value="{{ now('Asia/Ho_Chi_Minh')->format('Y-m-d') }}"
                           class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2.5 text-sm text-cinema-text">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1.5">Chọn nhân viên</label>
                    <select id="filter-user-id" class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2.5 text-sm text-cinema-text">
                        <option value="">-- Tất cả nhân viên --</option>
                        @foreach($staffList as $s)
                            <option value="{{ $s->id }}">{{ $s->employee_code }} - {{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1.5">Tìm theo tên / mã NV</label>
                    <input type="text" id="filter-search" placeholder="VD: Nguyễn Văn An, NV001..."
                           class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2.5 text-sm text-cinema-text">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-4 gap-4 pt-3 border-t border-[var(--color-cinema-border)]/60">
                <div>
                    <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1.5">Ca làm</label>
                    <select id="filter-shift-number" class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2.5 text-sm text-cinema-text">
                        <option value="">Tất cả 3 ca</option>
                        <option value="1">Ca 1 · 22:00 - 06:00</option>
                        <option value="2">Ca 2 · 06:00 - 14:00</option>
                        <option value="3">Ca 3 · 14:00 - 22:00</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1.5">Trạng thái chấm công</label>
                    <select id="filter-status" class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2.5 text-sm text-cinema-text">
                        <option value="all">Tất cả trạng thái</option>
                        <option value="valid">Hợp lệ</option>
                        <option value="violation">Có vi phạm</option>
                        <option value="absent">Vắng mặt</option>
                        <option value="late">Đi trễ</option>
                        <option value="early_leave">Về sớm</option>
                        <option value="pending">Chờ chấm công</option>
                    </select>
                </div>
                <div class="sm:col-span-1 lg:col-span-2 flex items-end gap-3">
                    <button type="button" onclick="loadAttendance(1)" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold cursor-pointer">
                        Tìm kiếm
                    </button>
                    <button type="button" onclick="resetAttendanceFilters()" class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] text-[var(--color-cinema-text-muted)] hover:text-cinema-text cursor-pointer">
                        Đặt lại
                    </button>
                </div>
            </div>
        </div>

        {{-- Table --}}
        <div class="glass rounded-2xl border border-[var(--color-cinema-border)] shadow-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[var(--color-cinema-text-muted)] border-b border-[var(--color-cinema-border)] text-xs uppercase bg-[var(--color-cinema-card)]/60">
                            <th class="text-left py-3.5 px-4">Nhân viên</th>
                            <th class="text-left py-3.5 px-4">Vị trí</th>
                            <th class="text-left py-3.5 px-4">Ngày &amp; Ca làm</th>
                            <th class="text-left py-3.5 px-4">Bắt đầu ca</th>
                            <th class="text-left py-3.5 px-4">Kết thúc ca</th>
                            <th class="text-center py-3.5 px-4">Số giờ</th>
                            <th class="text-left py-3.5 px-4">Trạng thái &amp; Ghi chú</th>
                        </tr>
                    </thead>
                    <tbody id="attendance-table-body">
                        <tr><td colspan="7" class="py-10 text-center text-[var(--color-cinema-text-muted)]">Đang tải dữ liệu chấm công...</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-[var(--color-cinema-border)] flex flex-col sm:flex-row items-center justify-between gap-3 bg-[var(--color-cinema-card)]/30">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-xs text-[var(--color-cinema-text-muted)]" id="att-pagination-info">Hiển thị 0 bản ghi</span>
                    <div class="flex items-center gap-1.5 text-xs text-[var(--color-cinema-text-muted)]">
                        <span>Hiển thị:</span>
                        <select id="att-per-page" onchange="loadAttendance(1)"
                                class="bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-lg px-2 py-1 text-xs font-semibold text-cinema-text">
                            <option value="10">10 dòng</option>
                            <option value="15" selected>15 dòng</option>
                            <option value="25">25 dòng</option>
                            <option value="50">50 dòng</option>
                        </select>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-1.5" id="att-pagination-buttons"></div>
            </div>
        </div>
    </div>

    <script>
        let attCurrentPage = 1;

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('filter-search').addEventListener('keydown', e => {
                if (e.key === 'Enter') loadAttendance(1);
            });
            loadAttendance(1);
        });

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }

        function onPeriodChange() {
            const val = document.getElementById('filter-period-type').value;
            ['month', 'week', 'year', 'day'].forEach(k => {
                document.getElementById(`wrap-${k}`).classList.toggle('hidden', k !== val);
            });
        }

        function getFilterQuery(page = 1) {
            const params = new URLSearchParams();
            const periodType = document.getElementById('filter-period-type').value;
            params.set('period_type', periodType);

            if (periodType === 'month') params.set('month', document.getElementById('filter-month').value);
            else if (periodType === 'week') params.set('week', document.getElementById('filter-week').value);
            else if (periodType === 'year') params.set('year', document.getElementById('filter-year').value);
            else if (periodType === 'day') params.set('date', document.getElementById('filter-date').value);

            const userId = document.getElementById('filter-user-id').value;
            if (userId) params.set('user_id', userId);

            const search = document.getElementById('filter-search').value.trim();
            if (search) params.set('search', search);

            const shiftNumber = document.getElementById('filter-shift-number').value;
            if (shiftNumber) params.set('shift_number', shiftNumber);

            const status = document.getElementById('filter-status').value;
            if (status) params.set('status', status);

            params.set('page', page);
            params.set('per_page', document.getElementById('att-per-page')?.value || 15);
            return params;
        }

        function loadAttendance(page = 1) {
            attCurrentPage = page;
            const params = getFilterQuery(page);

            fetch(`/api/admin/staff/attendance?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(res => {
                const s = res.summary || {};
                document.getElementById('kpi-total').textContent = s.total ?? 0;
                document.getElementById('kpi-valid').textContent = s.valid ?? 0;
                document.getElementById('kpi-violation').textContent = s.violation ?? 0;
                document.getElementById('kpi-absent').textContent = s.absent ?? 0;
                document.getElementById('kpi-hours').textContent = (s.total_hours ?? 0) + 'h';

                renderAttendanceRows(res.data || []);
                renderPagination(res.meta || {});
            });
        }

        function methodBadge(method, conf) {
            if (!method) return '';
            if (method === 'camera_ai') {
                return `<span class="inline-block mt-1 px-1.5 py-0.5 rounded bg-purple-500/20 text-cinema-purple-text text-[10px] font-semibold">Camera AI (mô phỏng)${conf ? ` · ${conf}% (mô phỏng)` : ''}</span>`;
            }
            return `<span class="inline-block mt-1 px-1.5 py-0.5 rounded bg-sky-500/15 text-cinema-info-text text-[10px] font-semibold">Thủ công</span>`;
        }

        function renderAttendanceRows(rows) {
            const tbody = document.getElementById('attendance-table-body');
            if (rows.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="py-12 text-center text-[var(--color-cinema-text-muted)]">Không tìm thấy dữ liệu chấm công phù hợp bộ lọc.</td></tr>';
                return;
            }

            tbody.innerHTML = rows.map(r => {
                let badgeClass = 'bg-slate-500/20 text-[var(--color-cinema-text-muted)]';
                if (r.attendance_status === 'absent') badgeClass = 'bg-red-500/20 text-cinema-danger-text';
                else if (r.is_violation) badgeClass = 'bg-amber-500/20 text-cinema-warning-text';
                else if (r.check_in_at) badgeClass = 'bg-emerald-500/20 text-cinema-success-text';

                return `
                    <tr class="border-b border-[var(--color-cinema-border)]/60 hover:bg-[var(--color-cinema-card)]/40 transition-colors">
                        <td class="py-3.5 px-4">
                            <div class="font-bold text-cinema-text">${escapeHtml(r.employee_name)}</div>
                            <div class="text-xs text-[var(--color-cinema-text-muted)]">${escapeHtml(r.employee_code)}</div>
                        </td>
                        <td class="py-3.5 px-4">
                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-sky-500/15 text-cinema-info-text">${escapeHtml(r.position_label)}</span>
                        </td>
                        <td class="py-3.5 px-4">
                            <div class="font-semibold text-cinema-text">${escapeHtml(r.shift_display_date)} • ${escapeHtml(r.shift_name)}</div>
                            <div class="text-xs text-[var(--color-cinema-text-muted)]">${escapeHtml(r.shift_time)}</div>
                        </td>
                        <td class="py-3.5 px-4">
                            <div class="font-medium text-cinema-text">${escapeHtml(r.check_in_display || '—')}</div>
                            ${methodBadge(r.check_in_method, r.check_in_confidence)}
                        </td>
                        <td class="py-3.5 px-4">
                            <div class="font-medium text-cinema-text">${escapeHtml(r.check_out_display || '—')}</div>
                            ${methodBadge(r.check_out_method)}
                        </td>
                        <td class="py-3.5 px-4 text-center font-bold text-cinema-text">
                            ${r.worked_hours > 0 ? `${r.worked_hours}h` : '—'}
                        </td>
                        <td class="py-3.5 px-4">
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold ${badgeClass}">${escapeHtml(r.attendance_status_label)}</span>
                            ${r.violation_reason ? `<div class="text-xs text-cinema-danger-text mt-1">${escapeHtml(r.violation_reason)}</div>` : ''}
                        </td>
                    </tr>
                `;
            }).join('');
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

        function renderPagination(meta) {
            const total = meta.total || 0;
            const current = meta.current_page || 1;
            const last = meta.last_page || 1;
            const from = meta.from || 0;
            const to = meta.to || 0;

            document.getElementById('att-pagination-info').textContent = total > 0
                ? `Hiển thị ${from}–${to} / ${total} bản ghi • Trang ${current}/${last}`
                : 'Hiển thị 0 bản ghi';

            const baseBtn = 'px-3 py-1.5 rounded-lg text-xs font-semibold border transition';
            const normalBtn = `${baseBtn} bg-[var(--color-cinema-card)] border-[var(--color-cinema-border)] text-cinema-text hover:border-emerald-500/50 cursor-pointer`;
            const activeBtn = `${baseBtn} btn-primary border-transparent cursor-pointer`;
            const disabledBtn = `${baseBtn} bg-[var(--color-cinema-card)] border-[var(--color-cinema-border)] text-[var(--color-cinema-text-muted)] opacity-40 cursor-not-allowed`;

            const btns = [];
            btns.push(`<button type="button" ${current <= 1 ? 'disabled' : `onclick="loadAttendance(${current - 1})"`} class="${current <= 1 ? disabledBtn : normalBtn}">‹ Trước</button>`);

            for (const p of buildPageSequence(current, last)) {
                if (p === '...') {
                    btns.push('<span class="px-2 text-xs text-[var(--color-cinema-text-muted)]">…</span>');
                } else {
                    btns.push(`<button type="button" onclick="loadAttendance(${p})" class="${p === current ? activeBtn : normalBtn}">${p}</button>`);
                }
            }

            btns.push(`<button type="button" ${current >= last ? 'disabled' : `onclick="loadAttendance(${current + 1})"`} class="${current >= last ? disabledBtn : normalBtn}">Sau ›</button>`);
            document.getElementById('att-pagination-buttons').innerHTML = btns.join('');
        }

        function resetAttendanceFilters() {
            document.getElementById('filter-period-type').value = 'month';
            onPeriodChange();
            document.getElementById('filter-user-id').value = '';
            document.getElementById('filter-search').value = '';
            document.getElementById('filter-shift-number').value = '';
            document.getElementById('filter-status').value = 'all';
            document.getElementById('att-per-page').value = '15';
            loadAttendance(1);
        }

        function exportAttendanceExcel() {
            const params = getFilterQuery(1);
            params.delete('page');
            params.delete('per_page');
            window.location.href = `/admin/staff/attendance/export?${params.toString()}`;
        }
    </script>
</x-layouts.admin>

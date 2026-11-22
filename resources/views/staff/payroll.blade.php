<x-layouts.staff title="Tính lương - Nhân viên" header="Bảng chấm công & Tính lương">
    <div class="space-y-6">
        {{-- Header & Admin staff switcher --}}
        <div class="glass rounded-2xl p-5 sm:p-6 border border-[var(--color-cinema-border)] flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-cinema-text">
                    Tổng hợp chấm công &amp; Lương — <span id="pay-staff-name" class="text-cinema-success-text">{{ $selectedStaff->name }}</span>
                </h2>
                <p class="text-xs sm:text-sm text-[var(--color-cinema-text-muted)] mt-1">
                    Mã NV: <strong class="text-cinema-text" id="pay-staff-code">{{ $selectedStaff->employee_code ?? 'NV' }}</strong>
                    • Vị trí: <strong class="text-cinema-info-text" id="pay-staff-pos">{{ $selectedStaff->positionLabel() }}</strong>
                    • Dữ liệu chỉ hiển thị bảng chấm công và lương của riêng nhân viên này
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                @if($staffList->isNotEmpty())
                    <div class="flex items-center gap-2">
                        <label class="text-xs text-[var(--color-cinema-text-muted)]">Xem theo NV:</label>
                        <select id="pay-preview-staff-id" onchange="loadMyPayroll(1)"
                                class="bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3 py-2 text-xs font-semibold text-cinema-text">
                            @foreach($staffList as $s)
                                <option value="{{ $s->id }}" {{ $s->id === $selectedStaff->id ? 'selected' : '' }}>
                                    {{ $s->employee_code }} - {{ $s->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <button type="button" onclick="exportMyPayrollExcel()"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs sm:text-sm font-bold shadow-lg transition cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Xuất file Excel</span>
                </button>
            </div>
        </div>

        {{-- Period & Status Filters (mirrors Bảng chấm công) --}}
        <div class="glass rounded-2xl p-5 border border-[var(--color-cinema-border)] grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1.5">Lọc theo kỳ</label>
                <select id="pay-period-type" onchange="onPayPeriodChange()"
                        class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2.5 text-sm text-cinema-text">
                    <option value="month" selected>Theo tháng</option>
                    <option value="week">Theo tuần</option>
                    <option value="year">Theo năm</option>
                    <option value="day">Ngày cụ thể</option>
                    <option value="all">Tất cả thời gian</option>
                </select>
            </div>

            <div id="pay-wrap-month">
                <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1.5">Chọn tháng</label>
                <input type="month" id="pay-month" value="{{ now('Asia/Ho_Chi_Minh')->format('Y-m') }}"
                       class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2.5 text-sm text-cinema-text">
            </div>

            <div id="pay-wrap-week" class="hidden">
                <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1.5">Chọn tuần</label>
                <input type="week" id="pay-week" value="{{ now('Asia/Ho_Chi_Minh')->format('o-\WW') }}"
                       class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2.5 text-sm text-cinema-text">
            </div>

            <div id="pay-wrap-year" class="hidden">
                <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1.5">Nhập năm</label>
                <input type="number" id="pay-year" value="{{ now('Asia/Ho_Chi_Minh')->format('Y') }}" min="2020" max="2035"
                       class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2.5 text-sm text-cinema-text">
            </div>

            <div id="pay-wrap-day" class="hidden">
                <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1.5">Chọn ngày cụ thể</label>
                <input type="date" id="pay-date" value="{{ now('Asia/Ho_Chi_Minh')->format('Y-m-d') }}"
                       class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2.5 text-sm text-cinema-text">
            </div>

            <div>
                <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1.5">Ca làm</label>
                <select id="pay-shift-number" class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2.5 text-sm text-cinema-text">
                    <option value="">Tất cả 3 ca</option>
                    <option value="1">Ca 1 · 22:00 - 06:00</option>
                    <option value="2">Ca 2 · 06:00 - 14:00</option>
                    <option value="3">Ca 3 · 14:00 - 22:00</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1.5">Trạng thái</label>
                <select id="pay-status" class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2.5 text-sm text-cinema-text">
                    <option value="all">Tất cả trạng thái</option>
                    <option value="valid">Hợp lệ</option>
                    <option value="violation">Có vi phạm</option>
                    <option value="absent">Vắng mặt</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="button" onclick="loadMyPayroll(1)" class="btn-primary w-full py-2.5 px-4 rounded-xl text-sm font-bold cursor-pointer">
                    Tính lương
                </button>
            </div>
        </div>

        {{-- Payroll Summary Cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-6 gap-4">
            <div class="glass rounded-2xl p-4 border border-[var(--color-cinema-border)]">
                <p class="text-xs text-[var(--color-cinema-text-muted)]">Đơn giá / giờ</p>
                <p class="text-lg font-extrabold text-cinema-text mt-1" id="sum-rate">0 đ</p>
            </div>
            <div class="glass rounded-2xl p-4 border border-[var(--color-cinema-border)]">
                <p class="text-xs text-[var(--color-cinema-text-muted)]">Ca hoàn thành / Giờ công</p>
                <p class="text-lg font-extrabold text-cinema-info-text mt-1" id="sum-hours">0 ca • 0h</p>
            </div>
            <div class="glass rounded-2xl p-4 border border-[var(--color-cinema-border)]">
                <p class="text-xs text-[var(--color-cinema-text-muted)]">Lương cơ bản</p>
                <p class="text-lg font-extrabold text-cinema-text mt-1" id="sum-base">0 đ</p>
            </div>
            <div class="glass rounded-2xl p-4 border border-[var(--color-cinema-border)]">
                <p class="text-xs text-[var(--color-cinema-text-muted)]">Phụ cấp ca đêm · +30%</p>
                <p class="text-lg font-extrabold text-cinema-success-text mt-1" id="sum-bonus">0 đ</p>
            </div>
            <div class="glass rounded-2xl p-4 border border-[var(--color-cinema-border)]">
                <p class="text-xs text-[var(--color-cinema-text-muted)]">Khấu trừ vi phạm</p>
                <p class="text-lg font-extrabold text-cinema-danger-text mt-1" id="sum-penalty">0 đ</p>
            </div>
            <div class="glass rounded-2xl p-4 border border-amber-500/40 bg-amber-500/10 col-span-2 lg:col-span-1">
                <p class="text-xs font-bold text-cinema-accent-text">Tổng lương thực lãnh</p>
                <p class="text-xl font-extrabold text-cinema-accent-text mt-1" id="sum-net">0 đ</p>
            </div>
        </div>

        <div class="rounded-xl p-3.5 bg-sky-500/10 border border-sky-500/30 text-xs text-cinema-info-text">
            <strong>Công thức tính lương:</strong> Lương ca = Số giờ công chuẩn tối đa 8h × Đơn giá/giờ + Phụ cấp 30% cho Ca 1 khung 22:00–06:00 − Khấu trừ 50.000đ nếu vi phạm chấm công. Các ca vắng mặt không tính lương.
        </div>

        {{-- Per-shift Breakdown Table --}}
        <div class="glass rounded-2xl border border-[var(--color-cinema-border)] shadow-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[var(--color-cinema-text-muted)] border-b border-[var(--color-cinema-border)] text-xs uppercase bg-[var(--color-cinema-card)]/60">
                            <th class="text-left py-3.5 px-4">Ngày &amp; Ca</th>
                            <th class="text-left py-3.5 px-4">Vào – Ra</th>
                            <th class="text-center py-3.5 px-4">Giờ tính lương</th>
                            <th class="text-right py-3.5 px-4">Lương cơ bản</th>
                            <th class="text-right py-3.5 px-4">Phụ cấp đêm</th>
                            <th class="text-right py-3.5 px-4">Khấu trừ</th>
                            <th class="text-right py-3.5 px-4">Thực lãnh</th>
                            <th class="text-left py-3.5 px-4">Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody id="payroll-table-body">
                        <tr><td colspan="8" class="py-10 text-center text-[var(--color-cinema-text-muted)]">Đang tính bảng lương...</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-[var(--color-cinema-border)] flex flex-col sm:flex-row items-center justify-between gap-3 bg-[var(--color-cinema-card)]/30">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-xs text-[var(--color-cinema-text-muted)]" id="pay-pagination-info">Hiển thị 0 bản ghi</span>
                    <div class="flex items-center gap-1.5 text-xs text-[var(--color-cinema-text-muted)]">
                        <span>Hiển thị:</span>
                        <select id="pay-per-page" onchange="loadMyPayroll(1)"
                                class="bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-lg px-2 py-1 text-xs font-semibold text-cinema-text">
                            <option value="10" selected>10 dòng</option>
                            <option value="15">15 dòng</option>
                            <option value="25">25 dòng</option>
                            <option value="50">50 dòng</option>
                        </select>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-1.5" id="pay-pagination-buttons"></div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            loadMyPayroll();
        });

        function formatVnd(amount) {
            return Number(amount || 0).toLocaleString('vi-VN') + ' đ';
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }

        function onPayPeriodChange() {
            const val = document.getElementById('pay-period-type').value;
            ['month', 'week', 'year', 'day'].forEach(k => {
                document.getElementById(`pay-wrap-${k}`).classList.toggle('hidden', k !== val);
            });
        }

        function getPayrollParams() {
            const params = new URLSearchParams();
            const periodType = document.getElementById('pay-period-type').value;
            params.set('period_type', periodType);

            if (periodType === 'month') params.set('month', document.getElementById('pay-month').value);
            else if (periodType === 'week') params.set('week', document.getElementById('pay-week').value);
            else if (periodType === 'year') params.set('year', document.getElementById('pay-year').value);
            else if (periodType === 'day') params.set('date', document.getElementById('pay-date').value);

            const shiftNumber = document.getElementById('pay-shift-number').value;
            if (shiftNumber) params.set('shift_number', shiftNumber);

            const status = document.getElementById('pay-status').value;
            if (status) params.set('status', status);

            const staffEl = document.getElementById('pay-preview-staff-id');
            if (staffEl && staffEl.value) params.set('staff_id', staffEl.value);

            return params;
        }

        function exportMyPayrollExcel() {
            window.location.href = `/staff/payroll/export?${getPayrollParams().toString()}`;
        }

        let payCurrentPage = 1;

        function loadMyPayroll(page = 1) {
            payCurrentPage = page;
            const params = new URLSearchParams(getPayrollParams().toString());
            params.set('page', page);
            params.set('per_page', document.getElementById('pay-per-page')?.value || 10);

            fetch(`/api/staff/my-payroll?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(res => {
                const d = res.data;
                if (d?.staff) {
                    document.getElementById('pay-staff-name').textContent = d.staff.name;
                    document.getElementById('pay-staff-code').textContent = d.staff.employee_code || 'NV';
                    document.getElementById('pay-staff-pos').textContent = d.staff.position_label;
                }
                const s = d?.summary || {};
                document.getElementById('sum-rate').textContent = formatVnd(s.hourly_rate);
                document.getElementById('sum-hours').textContent = `${s.completed_shifts ?? 0} ca • ${s.total_hours ?? 0}h`;
                document.getElementById('sum-base').textContent = formatVnd(s.total_base_pay);
                document.getElementById('sum-bonus').textContent = formatVnd(s.total_night_bonus);
                document.getElementById('sum-penalty').textContent = s.total_penalty > 0 ? '-' + formatVnd(s.total_penalty) : '0 đ';
                document.getElementById('sum-net').textContent = formatVnd(s.total_net_salary);

                renderPayrollRows(d?.rows || []);
                renderPayrollPagination(d?.meta || {});
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

        function renderPayrollPagination(meta) {
            const total = meta.total || 0;
            const current = meta.current_page || 1;
            const last = meta.last_page || 1;
            const from = meta.from || 0;
            const to = meta.to || 0;

            document.getElementById('pay-pagination-info').textContent = total > 0
                ? `Hiển thị ${from}–${to} / ${total} bản ghi • Trang ${current}/${last}`
                : 'Hiển thị 0 bản ghi';

            const baseBtn = 'px-3 py-1.5 rounded-lg text-xs font-semibold border transition';
            const normalBtn = `${baseBtn} bg-[var(--color-cinema-card)] border-[var(--color-cinema-border)] text-cinema-text hover:border-emerald-500/50 cursor-pointer`;
            const activeBtn = `${baseBtn} btn-primary border-transparent cursor-pointer`;
            const disabledBtn = `${baseBtn} bg-[var(--color-cinema-card)] border-[var(--color-cinema-border)] text-[var(--color-cinema-text-muted)] opacity-40 cursor-not-allowed`;

            const btns = [];
            btns.push(`<button type="button" ${current <= 1 ? 'disabled' : `onclick="loadMyPayroll(${current - 1})"`} class="${current <= 1 ? disabledBtn : normalBtn}">‹ Trước</button>`);

            for (const p of buildPageSequence(current, last)) {
                if (p === '...') {
                    btns.push('<span class="px-2 text-xs text-[var(--color-cinema-text-muted)]">…</span>');
                } else {
                    btns.push(`<button type="button" onclick="loadMyPayroll(${p})" class="${p === current ? activeBtn : normalBtn}">${p}</button>`);
                }
            }

            btns.push(`<button type="button" ${current >= last ? 'disabled' : `onclick="loadMyPayroll(${current + 1})"`} class="${current >= last ? disabledBtn : normalBtn}">Sau ›</button>`);
            document.getElementById('pay-pagination-buttons').innerHTML = btns.join('');
        }

        function renderPayrollRows(rows) {
            const tbody = document.getElementById('payroll-table-body');
            if (rows.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="py-12 text-center text-[var(--color-cinema-text-muted)]">Không có dữ liệu chấm công trong kỳ đã chọn.</td></tr>';
                return;
            }

            tbody.innerHTML = rows.map(r => `
                <tr class="border-b border-[var(--color-cinema-border)]/60 hover:bg-[var(--color-cinema-card)]/40">
                    <td class="py-3.5 px-4">
                        <div class="font-bold text-cinema-text">${escapeHtml(r.shift_display_date)} • ${escapeHtml(r.shift_name)}</div>
                        <div class="text-xs text-[var(--color-cinema-text-muted)]">${escapeHtml(r.shift_time)} • ${escapeHtml(r.position_label)}</div>
                    </td>
                    <td class="py-3.5 px-4 text-xs">
                        <div>Vào: <strong>${escapeHtml(r.check_in_short || '—')}</strong></div>
                        <div>Ra: <strong>${escapeHtml(r.check_out_short || '—')}</strong></div>
                    </td>
                    <td class="py-3.5 px-4 text-center font-bold">${r.billable_hours > 0 ? r.billable_hours + 'h' : '—'}</td>
                    <td class="py-3.5 px-4 text-right">${formatVnd(r.base_pay)}</td>
                    <td class="py-3.5 px-4 text-right text-cinema-success-text">${r.night_bonus > 0 ? '+' + formatVnd(r.night_bonus) : '0 đ'}</td>
                    <td class="py-3.5 px-4 text-right text-cinema-danger-text">${r.penalty > 0 ? '-' + formatVnd(r.penalty) : '0 đ'}</td>
                    <td class="py-3.5 px-4 text-right font-extrabold text-cinema-accent-text">${formatVnd(r.net_pay)}</td>
                    <td class="py-3.5 px-4">
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold ${r.is_violation ? 'bg-red-500/20 text-cinema-danger-text' : (r.check_out_at ? 'bg-emerald-500/20 text-cinema-success-text' : 'bg-slate-500/20 text-[var(--color-cinema-text-muted)]')}">
                            ${escapeHtml(r.attendance_status_label)}
                        </span>
                        ${r.violation_reason ? `<div class="text-[11px] text-cinema-danger-text mt-1">${escapeHtml(r.violation_reason)}</div>` : ''}
                    </td>
                </tr>
            `).join('');
        }
    </script>
</x-layouts.staff>

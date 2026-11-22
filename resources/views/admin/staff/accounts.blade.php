<x-layouts.admin title="Quản lý nhân viên - Tài khoản nhân viên" header="Quản lý nhân viên">
    @include('admin.staff._tabs')

    <div class="space-y-6">
        {{-- Top Summary & Create Button --}}
        <div class="glass rounded-2xl p-5 sm:p-6 border border-[var(--color-cinema-border)] flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-cinema-text">Danh sách &amp; Tài khoản nhân viên rạp</h2>
                <p class="text-xs sm:text-sm text-[var(--color-cinema-text-muted)] mt-1">
                    Quản lý thông tin cá nhân, vị trí làm việc, tài khoản/mật khẩu đăng nhập, tình trạng tài khoản và reset mật khẩu về mã số nhân viên
                </p>
            </div>
            <button type="button" onclick="openAccountModal()" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-bold cursor-pointer shrink-0">
                + Thêm nhân viên
            </button>
        </div>

        {{-- Stats Row --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="glass rounded-2xl p-4 border border-[var(--color-cinema-border)]">
                <p class="text-xs text-[var(--color-cinema-text-muted)]">Tổng nhân viên</p>
                <p class="text-2xl font-extrabold text-cinema-text mt-1" id="acc-total">0</p>
            </div>
            <div class="glass rounded-2xl p-4 border border-[var(--color-cinema-border)]">
                <p class="text-xs text-[var(--color-cinema-text-muted)]">Đang hoạt động</p>
                <p class="text-2xl font-extrabold text-cinema-success-text mt-1" id="acc-active">0</p>
            </div>
            <div class="glass rounded-2xl p-4 border border-[var(--color-cinema-border)]">
                <p class="text-xs text-[var(--color-cinema-text-muted)]">Tài khoản bị khóa</p>
                <p class="text-2xl font-extrabold text-cinema-danger-text mt-1" id="acc-locked">0</p>
            </div>
            <div class="glass rounded-2xl p-4 border border-[var(--color-cinema-border)]">
                <p class="text-xs text-[var(--color-cinema-text-muted)]">Nghỉ việc</p>
                <p class="text-2xl font-extrabold text-[var(--color-cinema-text-muted)] mt-1" id="acc-inactive">0</p>
            </div>
        </div>

        {{-- Search & Filters --}}
        <div class="glass rounded-2xl p-5 border border-[var(--color-cinema-border)] grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1.5">Tìm nhân viên</label>
                <input type="text" id="acc-search" placeholder="Mã NV, họ tên, email, SĐT..."
                       class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2.5 text-sm text-cinema-text">
            </div>
            <div>
                <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1.5">Vị trí công việc</label>
                <select id="acc-position" onchange="loadAccounts()" class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2.5 text-sm text-cinema-text">
                    <option value="all">Tất cả vị trí</option>
                    <option value="ticket_checker">Soát vé</option>
                    <option value="cashier">Thu ngân</option>
                    <option value="snack_prep">Chuẩn bị snacks</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1.5">Tình trạng tài khoản</label>
                <select id="acc-status" onchange="loadAccounts()" class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2.5 text-sm text-cinema-text">
                    <option value="all">Tất cả trạng thái</option>
                    <option value="active">Đang hoạt động</option>
                    <option value="locked">Đang khóa</option>
                    <option value="inactive">Nghỉ việc</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="button" onclick="loadAccounts()" class="btn-primary px-5 py-2.5 rounded-xl text-sm font-bold cursor-pointer">Tìm kiếm</button>
            </div>
        </div>

        {{-- Accounts Table --}}
        <div class="glass rounded-2xl border border-[var(--color-cinema-border)] shadow-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[var(--color-cinema-text-muted)] border-b border-[var(--color-cinema-border)] text-xs uppercase bg-[var(--color-cinema-card)]/60">
                            <th class="text-left py-3.5 px-4">Mã NV</th>
                            <th class="text-left py-3.5 px-4">Thông tin cá nhân</th>
                            <th class="text-left py-3.5 px-4">Vị trí &amp; Lương/giờ</th>
                            <th class="text-left py-3.5 px-4">Tài khoản &amp; Mật khẩu</th>
                            <th class="text-center py-3.5 px-4">Tình trạng</th>
                            <th class="text-right py-3.5 px-4">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody id="accounts-table-body">
                        <tr><td colspan="6" class="py-10 text-center text-[var(--color-cinema-text-muted)]">Đang tải danh sách nhân viên...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Create / Edit Account Modal --}}
    <div id="account-modal" class="fixed inset-0 z-[9990] hidden flex items-center justify-center bg-black/75 backdrop-blur-sm p-4" onclick="if(event.target===this) closeAccountModal()">
        <div class="bg-cinema-surface border border-[var(--color-cinema-border)] rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl p-6 space-y-5">
            <div class="flex items-center justify-between border-b border-[var(--color-cinema-border)] pb-3">
                <h3 class="text-lg font-bold text-cinema-text" id="account-modal-title">Thêm nhân viên mới</h3>
                <button type="button" onclick="closeAccountModal()" class="px-2.5 py-1 rounded-lg bg-[var(--color-cinema-card)] text-xs font-semibold cursor-pointer">Đóng</button>
            </div>

            <form id="account-form" onsubmit="submitAccountForm(event)" class="space-y-4">
                <input type="hidden" id="form-staff-id">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1">Mã nhân viên</label>
                        <input type="text" id="form-employee-code" placeholder="Tự sinh, VD: NV017"
                               class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2 text-sm text-cinema-text">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1">Họ và tên *</label>
                        <input type="text" id="form-name" required
                               class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2 text-sm text-cinema-text">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1">Email đăng nhập *</label>
                        <input type="email" id="form-email" required
                               class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2 text-sm text-cinema-text">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1">Mật khẩu <span class="font-normal opacity-75">· để trống = mã NV</span></label>
                        <input type="text" id="form-password" placeholder="Mặc định = mã nhân viên"
                               class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2 text-sm text-cinema-text">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1">Số điện thoại</label>
                        <input type="text" id="form-phone"
                               class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2 text-sm text-cinema-text">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1">CCCD / CMND</label>
                        <input type="text" id="form-citizen-id"
                               class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2 text-sm text-cinema-text">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1">Ngày sinh</label>
                        <input type="date" id="form-dob"
                               class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2 text-sm text-cinema-text">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1">Giới tính</label>
                        <select id="form-gender" class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2 text-sm text-cinema-text">
                            <option value="male">Nam</option>
                            <option value="female">Nữ</option>
                            <option value="other">Khác</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1">Vị trí làm việc *</label>
                        <select id="form-position" required class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2 text-sm text-cinema-text">
                            <option value="ticket_checker">Soát vé</option>
                            <option value="cashier">Thu ngân</option>
                            <option value="snack_prep">Chuẩn bị snacks</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1">Lương / giờ · VNĐ</label>
                        <input type="number" id="form-hourly-rate" value="36000" min="15000" step="1000"
                               class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2 text-sm text-cinema-text">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1">Tình trạng tài khoản</label>
                        <select id="form-account-status" class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2 text-sm text-cinema-text">
                            <option value="active">Đang hoạt động</option>
                            <option value="locked">Đang khóa</option>
                            <option value="inactive">Nghỉ việc</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1">Ngày vào làm</label>
                        <input type="date" id="form-hired-at" value="{{ now('Asia/Ho_Chi_Minh')->format('Y-m-d') }}"
                               class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2 text-sm text-cinema-text">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[var(--color-cinema-text-muted)] mb-1">Địa chỉ</label>
                    <input type="text" id="form-address"
                           class="w-full bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] rounded-xl px-3.5 py-2 text-sm text-cinema-text">
                </div>

                <div class="flex justify-end gap-3 pt-3 border-t border-[var(--color-cinema-border)]">
                    <button type="button" onclick="closeAccountModal()" class="px-4 py-2.5 rounded-xl text-sm font-semibold bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] cursor-pointer">Hủy</button>
                    <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-bold cursor-pointer">Lưu thông tin</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let accountsList = [];

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('acc-search').addEventListener('keydown', e => {
                if (e.key === 'Enter') loadAccounts();
            });
            loadAccounts();
        });

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }

        function loadAccounts() {
            const params = new URLSearchParams();
            const search = document.getElementById('acc-search').value.trim();
            const position = document.getElementById('acc-position').value;
            const status = document.getElementById('acc-status').value;

            if (search) params.set('search', search);
            if (position) params.set('position', position);
            if (status) params.set('status', status);

            fetch(`/api/admin/staff/accounts?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(res => {
                accountsList = res.data || [];
                const s = res.summary || {};
                document.getElementById('acc-total').textContent = s.total ?? 0;
                document.getElementById('acc-active').textContent = s.active ?? 0;
                document.getElementById('acc-locked').textContent = s.locked ?? 0;
                document.getElementById('acc-inactive').textContent = s.inactive ?? 0;
                renderAccountsTable();
            });
        }

        function renderAccountsTable() {
            const tbody = document.getElementById('accounts-table-body');
            if (accountsList.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="py-12 text-center text-[var(--color-cinema-text-muted)]">Không có nhân viên nào phù hợp.</td></tr>';
                return;
            }

            tbody.innerHTML = accountsList.map(u => {
                let statusBadge = 'bg-emerald-500/20 text-cinema-success-text';
                if (u.account_status === 'locked') statusBadge = 'bg-red-500/20 text-cinema-danger-text';
                else if (u.account_status === 'inactive') statusBadge = 'bg-slate-500/20 text-[var(--color-cinema-text-muted)]';

                const nextStatus = u.account_status === 'active' ? 'locked' : 'active';
                const toggleLabel = u.account_status === 'active' ? 'Khóa TK' : 'Mở khóa';

                return `
                    <tr class="border-b border-[var(--color-cinema-border)]/60 hover:bg-[var(--color-cinema-card)]/40 transition-colors">
                        <td class="py-4 px-4 align-top">
                            <span class="px-2.5 py-1 rounded-lg bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] font-extrabold text-xs text-cinema-accent-text">
                                ${escapeHtml(u.employee_code)}
                            </span>
                            <div class="text-[11px] text-[var(--color-cinema-text-muted)] mt-1.5">Vào làm: ${escapeHtml(u.hired_at_display || '—')}</div>
                        </td>
                        <td class="py-4 px-4 align-top">
                            <div class="font-bold text-cinema-text">${escapeHtml(u.name)}</div>
                            <div class="text-xs text-[var(--color-cinema-text-muted)] mt-1 space-y-0.5">
                                <div>SĐT: ${escapeHtml(u.phone || '—')} • ${escapeHtml(u.gender_label)} • NS: ${escapeHtml(u.date_of_birth_display || '—')}</div>
                                <div>CCCD: ${escapeHtml(u.citizen_id || '—')}</div>
                                <div>Địa chỉ: ${escapeHtml(u.address || '—')}</div>
                            </div>
                        </td>
                        <td class="py-4 px-4 align-top">
                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-sky-500/15 text-cinema-info-text">${escapeHtml(u.position_label)}</span>
                            <div class="text-xs font-bold text-cinema-accent-text mt-2">${Number(u.hourly_rate).toLocaleString('vi-VN')} đ / giờ</div>
                        </td>
                        <td class="py-4 px-4 align-top">
                            <div class="text-xs text-[var(--color-cinema-text-muted)]">Email: <strong class="text-cinema-text">${escapeHtml(u.email)}</strong></div>
                            <div class="flex items-center gap-2 mt-1.5">
                                <span class="text-xs text-[var(--color-cinema-text-muted)]">MK:</span>
                                <code id="pwd-${u.id}" data-plain="${escapeHtml(u.display_password)}" data-shown="0"
                                      class="px-2 py-0.5 rounded bg-[var(--color-cinema-card)] border border-[var(--color-cinema-border)] text-xs font-mono text-cinema-text">••••••••</code>
                                <button type="button" onclick="togglePassword(${u.id})" class="p-1 rounded bg-[var(--color-cinema-card)] hover:bg-[var(--color-cinema-border)] text-[var(--color-cinema-text-muted)] hover:text-cinema-text cursor-pointer" title="Hiện/Ẩn mật khẩu" aria-label="Hiện/Ẩn mật khẩu">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </button>
                            </div>
                        </td>
                        <td class="py-4 px-4 align-top text-center">
                            <span class="px-3 py-1 rounded-full text-xs font-bold ${statusBadge}">${escapeHtml(u.account_status_label)}</span>
                        </td>
                        <td class="py-4 px-4 align-top text-right">
                            <div class="flex flex-wrap justify-end gap-1.5">
                                <button type="button" onclick="confirmResetPassword(${u.id})"
                                        class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-amber-600/20 border border-amber-500/40 text-cinema-warning-text hover:bg-amber-600/30 cursor-pointer">
                                    Reset MK
                                </button>
                                <button type="button" onclick="openAccountModal(${u.id})"
                                        class="px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-sky-600/20 border border-sky-500/40 text-cinema-info-text hover:bg-sky-600/30 cursor-pointer">
                                    Sửa
                                </button>
                                <button type="button" onclick="changeAccountStatus(${u.id}, '${nextStatus}')"
                                        class="px-2.5 py-1.5 rounded-lg text-xs font-semibold ${u.account_status === 'active' ? 'bg-red-600/20 border border-red-500/40 text-cinema-danger-text' : 'bg-emerald-600/20 border border-emerald-500/40 text-cinema-success-text'} cursor-pointer">
                                    ${toggleLabel}
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function togglePassword(id) {
            const el = document.getElementById(`pwd-${id}`);
            if (!el) return;
            const shown = el.dataset.shown === '1';
            el.textContent = shown ? '••••••••' : el.dataset.plain;
            el.dataset.shown = shown ? '0' : '1';
        }

        function confirmResetPassword(id) {
            const staff = accountsList.find(x => x.id === id);
            if (!staff) return;

            window.showConfirmModal({
                title: 'Reset mật khẩu về mặc định?',
                message: `Mật khẩu tài khoản ${staff.name} · ${staff.email} sẽ được đặt lại thành mã nhân viên: ${staff.employee_code}`,
                type: 'warning',
                confirmText: 'Reset mật khẩu',
                onConfirm: () => {
                    fetch(`/api/admin/staff/accounts/${id}/reset-password`, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': window.CSRF_TOKEN,
                        },
                    })
                    .then(r => r.json().then(data => ({ ok: r.ok, data })))
                    .then(({ ok, data }) => {
                        if (!ok) {
                            window.showToast && window.showToast(data.message || 'Reset thất bại.', 'error');
                            return;
                        }
                        window.showNotificationModal({
                            title: 'Reset mật khẩu thành công',
                            message: data.message,
                            type: 'success',
                        });
                        loadAccounts();
                    });
                }
            });
        }

        function changeAccountStatus(id, status) {
            fetch(`/api/admin/staff/accounts/${id}/status`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': window.CSRF_TOKEN,
                },
                body: JSON.stringify({ account_status: status }),
            })
            .then(r => r.json())
            .then(data => {
                window.showToast && window.showToast(data.message || 'Đã cập nhật trạng thái.', 'success');
                loadAccounts();
            });
        }

        function openAccountModal(id = null) {
            const staff = id ? accountsList.find(x => x.id === id) : null;
            document.getElementById('account-modal-title').textContent = staff
                ? `Cập nhật nhân viên ${staff.employee_code}`
                : 'Thêm tài khoản nhân viên mới';

            document.getElementById('form-staff-id').value = staff ? staff.id : '';
            document.getElementById('form-employee-code').value = staff ? staff.employee_code : '';
            document.getElementById('form-name').value = staff ? staff.name : '';
            document.getElementById('form-email').value = staff ? staff.email : '';
            document.getElementById('form-password').value = '';
            document.getElementById('form-phone').value = staff?.phone || '';
            document.getElementById('form-citizen-id').value = staff?.citizen_id || '';
            document.getElementById('form-dob').value = staff?.date_of_birth || '';
            document.getElementById('form-gender').value = staff?.gender || 'male';
            document.getElementById('form-position').value = staff?.staff_position || 'ticket_checker';
            document.getElementById('form-hourly-rate').value = staff?.hourly_rate || 36000;
            document.getElementById('form-account-status').value = staff?.account_status || 'active';
            document.getElementById('form-hired-at').value = staff?.hired_at || '{{ now('Asia/Ho_Chi_Minh')->format('Y-m-d') }}';
            document.getElementById('form-address').value = staff?.address || '';

            document.getElementById('account-modal').classList.remove('hidden');
        }

        function closeAccountModal() {
            document.getElementById('account-modal').classList.add('hidden');
        }

        function submitAccountForm(e) {
            e.preventDefault();
            const id = document.getElementById('form-staff-id').value;
            const payload = {
                employee_code: document.getElementById('form-employee-code').value.trim() || null,
                name: document.getElementById('form-name').value.trim(),
                email: document.getElementById('form-email').value.trim(),
                password: document.getElementById('form-password').value || null,
                phone: document.getElementById('form-phone').value.trim() || null,
                citizen_id: document.getElementById('form-citizen-id').value.trim() || null,
                date_of_birth: document.getElementById('form-dob').value || null,
                gender: document.getElementById('form-gender').value,
                staff_position: document.getElementById('form-position').value,
                hourly_rate: Number(document.getElementById('form-hourly-rate').value) || 35000,
                account_status: document.getElementById('form-account-status').value,
                hired_at: document.getElementById('form-hired-at').value || null,
                address: document.getElementById('form-address').value.trim() || null,
            };

            const url = id ? `/api/admin/staff/accounts/${id}` : '/api/admin/staff/accounts';
            const method = id ? 'PUT' : 'POST';

            fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': window.CSRF_TOKEN,
                },
                body: JSON.stringify(payload),
            })
            .then(r => r.json().then(data => ({ ok: r.ok, data })))
            .then(({ ok, data }) => {
                if (!ok) {
                    const firstErr = data.errors ? Object.values(data.errors).flat()[0] : data.message;
                    window.showToast && window.showToast(firstErr || 'Không thể lưu thông tin.', 'error');
                    return;
                }
                window.showToast && window.showToast(data.message, 'success');
                closeAccountModal();
                loadAccounts();
            });
        }
    </script>
</x-layouts.admin>

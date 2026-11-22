<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div class="inline-flex flex-wrap gap-2 p-1.5 rounded-2xl glass border border-[var(--color-cinema-border)]">
        <a href="{{ route('admin.staff.schedule') }}"
           class="px-4 py-2.5 rounded-xl text-sm font-semibold transition-all {{ request()->is('admin/staff/schedule*') ? 'btn-primary shadow-md' : 'text-[var(--color-cinema-text-muted)] hover:text-cinema-text hover:bg-[var(--color-cinema-card)]' }}">
            Lịch tổng
        </a>
        <a href="{{ route('admin.staff.attendance') }}"
           class="px-4 py-2.5 rounded-xl text-sm font-semibold transition-all {{ request()->is('admin/staff/attendance*') ? 'btn-primary shadow-md' : 'text-[var(--color-cinema-text-muted)] hover:text-cinema-text hover:bg-[var(--color-cinema-card)]' }}">
            Bảng chấm công
        </a>
        <a href="{{ route('admin.staff.accounts') }}"
           class="px-4 py-2.5 rounded-xl text-sm font-semibold transition-all {{ request()->is('admin/staff/accounts*') ? 'btn-primary shadow-md' : 'text-[var(--color-cinema-text-muted)] hover:text-cinema-text hover:bg-[var(--color-cinema-card)]' }}">
            Tài khoản nhân viên
        </a>
    </div>
</div>

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Admin - CineStar' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen" style="background-color: var(--color-cinema-bg);">
    <div class="flex min-h-screen">
        {{-- Sidebar --}}
        <aside class="admin-sidebar w-64 fixed h-full overflow-y-auto hidden lg:block">
            <div class="p-6">
                <a href="/admin/dashboard" class="flex items-center gap-3 mb-8">
                    <span class="text-2xl">🎬</span>
                    <div>
                        <span class="text-lg font-bold gradient-text">CineStar</span>
                        <span class="block text-xs text-[var(--color-cinema-text-muted)]">Admin Panel</span>
                    </div>
                </a>

                <nav class="space-y-1">
                    <a href="/admin/dashboard" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium {{ request()->is('admin/dashboard') ? 'active' : 'text-[var(--color-cinema-text-muted)]' }}">
                        Thống kê
                    </a>
                    <a href="/admin/movies" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium {{ request()->is('admin/movies*') ? 'active' : 'text-[var(--color-cinema-text-muted)]' }}">
                        Quản lý Phim
                    </a>
                    <a href="/admin/shows" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium {{ request()->is('admin/shows*') ? 'active' : 'text-[var(--color-cinema-text-muted)]' }}">
                        Suất chiếu
                    </a>
                    <a href="/admin/snacks" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium {{ request()->is('admin/snacks*') ? 'active' : 'text-[var(--color-cinema-text-muted)]' }}">
                        Bắp nước
                    </a>
                    <a href="/admin/vouchers" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium {{ request()->is('admin/vouchers*') ? 'active' : 'text-[var(--color-cinema-text-muted)]' }}">
                        Voucher
                    </a>
                    <a href="/admin/orders" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium {{ request()->is('admin/orders*') ? 'active' : 'text-[var(--color-cinema-text-muted)]' }}">
                        Đơn hàng
                    </a>
                    <a href="/admin/staff/schedule" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium {{ request()->is('admin/staff*') ? 'active' : 'text-[var(--color-cinema-text-muted)]' }}">
                        Quản lý nhân viên
                    </a>

                    <div class="border-t border-[var(--color-cinema-border)] my-4"></div>

                    <a href="/" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-[var(--color-cinema-text-muted)] hover:text-cinema-text">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        <span>Xem trang chủ</span>
                    </a>
                    <a href="/staff/scan" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-cinema-success-text hover:bg-[var(--color-cinema-card)]">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                        <span>Chế độ Staff</span>
                    </a>
                </nav>
            </div>
        </aside>

        {{-- Main Content --}}
        <div class="min-w-0 flex-1 lg:ml-64">
            {{-- Top Bar --}}
            <header class="glass sticky top-0 z-40 flex items-center justify-between gap-2 px-3 py-3 sm:gap-3 sm:px-6 sm:py-4">
                {{-- Mobile Menu --}}
                <button type="button" aria-label="Menu quản trị" class="shrink-0 p-2 text-[var(--color-cinema-text)] lg:hidden" onclick="document.getElementById('admin-mobile-menu').classList.toggle('hidden')">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>

                <h1 class="min-w-0 flex-1 break-words text-sm font-bold sm:text-lg">{{ $header ?? 'Thống kê' }}</h1>

                <div class="flex shrink-0 items-center gap-1 sm:gap-3">
                    <x-language-toggle />
                    <x-theme-toggle />

                    <a href="/staff/scan" class="hidden sm:flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-lg border border-[var(--color-cinema-success)]/40 text-cinema-success-text hover:bg-[var(--color-cinema-success)]/10 transition-all">
                        <span>Chế độ Staff</span>
                    </a>

                    <span class="hidden max-w-40 truncate text-sm text-[var(--color-cinema-text-muted)] lg:block">
                        {{ Auth::user()->name ?? 'Admin' }}
                    </span>
                    <form action="/logout" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="whitespace-nowrap rounded-lg px-2 py-2 text-sm text-[var(--color-cinema-text-muted)] hover:bg-[var(--color-cinema-card)] sm:px-3">
                            Đăng xuất
                        </button>
                    </form>
                </div>
            </header>

            {{-- Mobile Sidebar --}}
            <div id="admin-mobile-menu" class="hidden lg:hidden fixed inset-0 z-50 bg-black/50" onclick="this.classList.add('hidden')">
                <div class="admin-sidebar w-64 h-full overflow-y-auto" onclick="event.stopPropagation()">
                    <div class="p-6">
                        <a href="/admin/dashboard" class="flex items-center gap-3 mb-8">
                            <span class="text-2xl">🎬</span>
                            <span class="text-lg font-bold gradient-text">CineStar Admin</span>
                        </a>
                        <nav class="space-y-1">
                            <a href="/admin/dashboard" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium {{ request()->is('admin/dashboard') ? 'active' : 'text-[var(--color-cinema-text-muted)]' }}">Thống kê</a>
                            <a href="/admin/movies" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-[var(--color-cinema-text-muted)]">Quản lý Phim</a>
                            <a href="/admin/shows" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-[var(--color-cinema-text-muted)]">Suất chiếu</a>
                            <a href="/admin/snacks" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-[var(--color-cinema-text-muted)]">Bắp nước</a>
                            <a href="/admin/vouchers" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-[var(--color-cinema-text-muted)]">Voucher</a>
                            <a href="/admin/orders" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-[var(--color-cinema-text-muted)]">Đơn hàng</a>
                            <a href="/admin/staff/schedule" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium {{ request()->is('admin/staff*') ? 'active' : 'text-[var(--color-cinema-text-muted)]' }}">Quản lý nhân viên</a>
                            
                            <div class="border-t border-[var(--color-cinema-border)] my-3"></div>
                            <a href="/staff/scan" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-cinema-success-text">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                                <span>Chế độ Staff</span>
                            </a>
                            <a href="/" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-[var(--color-cinema-text-muted)]">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                <span>Xem trang chủ</span>
                            </a>
                        </nav>
                    </div>
                </div>
            </div>

            {{-- Flash Messages --}}
            @if(session('success'))
                <div class="px-6 pt-4">
                    <div class="p-4 rounded-lg text-sm" style="background: rgba(0, 200, 83, 0.1); color: var(--cinema-success-text); border: 1px solid rgba(0, 200, 83, 0.3);">
                        {{ session('success') }}
                    </div>
                </div>
            @endif

            {{-- Page Content --}}
            <div class="px-4 py-6 sm:p-8 xl:p-10">
                {{ $slot }}
            </div>
        </div>
    </div>

    <script>
        window.CSRF_TOKEN = '{{ csrf_token() }}';
    </script>
    <x-notification-modal />
</body>
</html>

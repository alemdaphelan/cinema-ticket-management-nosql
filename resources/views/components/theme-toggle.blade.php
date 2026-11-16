@php
    $themeOptions = [
        'light' => [
            'label' => 'Giao diện Sáng',
            'path' => 'M12 2v2m0 16v2M2 12h2m16 0h2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42m0-14.14-1.42 1.42m-11.3 11.3-1.42 1.42M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z',
        ],
        'dark' => [
            'label' => 'Giao diện Tối',
            'path' => 'M20.35 15.35A9 9 0 0 1 8.65 3.65 9 9 0 1 0 20.35 15.35Z',
        ],
        'system' => [
            'label' => 'Theo hệ thống',
            'path' => 'M5 3h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm7 14v4m-4 0h8',
        ],
    ];
@endphp

<div class="relative inline-block text-left" id="theme-toggle-dropdown-container">
    <button type="button" onclick="toggleThemeMenu()" id="theme-toggle-btn"
            class="p-2 rounded-xl border border-[var(--color-cinema-border)] hover:bg-[var(--color-cinema-card)] text-[var(--color-cinema-text-muted)] hover:text-cinema-text transition-all flex items-center justify-center cursor-pointer"
            title="Đổi giao diện Sáng / Tối / Hệ thống">
        @foreach ($themeOptions as $theme => $option)
            <svg id="theme-icon-{{ $theme }}" aria-hidden="true" class="hidden h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $option['path'] }}" />
            </svg>
        @endforeach
    </button>

    {{-- Menu options --}}
    <div id="theme-menu" class="hidden absolute right-0 mt-2 w-36 glass rounded-xl border border-[var(--color-cinema-border)] shadow-2xl py-1 z-50 text-xs font-medium">
        @foreach ($themeOptions as $theme => $option)
            <button type="button" onclick="setAppTheme('{{ $theme }}')" class="w-full text-left px-3.5 py-2 hover:bg-[var(--color-cinema-card)] flex items-center gap-2.5 transition-colors cursor-pointer text-[var(--color-cinema-text)] {{ $theme === 'system' ? 'border-t border-[var(--color-cinema-border)]' : '' }}">
                <svg aria-hidden="true" class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $option['path'] }}" />
                </svg>
                <span>{{ $option['label'] }}</span>
            </button>
        @endforeach
    </div>
</div>

<script>
    (function() {
        // Init theme immediately to prevent flashing
        function applyInitialTheme() {
            const stored = localStorage.getItem('cinema_theme') || 'system';
            applyThemeClass(stored);
        }

        window.applyThemeClass = function(theme) {
            const root = document.documentElement;
            const darkIcon = document.getElementById('theme-icon-dark');
            const lightIcon = document.getElementById('theme-icon-light');
            const systemIcon = document.getElementById('theme-icon-system');

            let isDark = true;
            if (theme === 'light') {
                isDark = false;
            } else if (theme === 'dark') {
                isDark = true;
            } else {
                // system
                isDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            }

            const modeChanged = !root.classList.contains(isDark ? 'dark' : 'light');
            root.classList.toggle('dark', isDark);
            root.classList.toggle('light', !isDark);

            if (darkIcon && lightIcon && systemIcon) {
                darkIcon.classList.add('hidden');
                lightIcon.classList.add('hidden');
                systemIcon.classList.add('hidden');

                if (theme === 'light') lightIcon.classList.remove('hidden');
                else if (theme === 'dark') darkIcon.classList.remove('hidden');
                else systemIcon.classList.remove('hidden');
            }

            if (modeChanged) {
                window.dispatchEvent(new Event('cinema:theme-change'));
            }
        };

        window.toggleThemeMenu = function() {
            const menu = document.getElementById('theme-menu');
            if (menu) menu.classList.toggle('hidden');
        };

        window.setAppTheme = function(theme) {
            localStorage.setItem('cinema_theme', theme);
            applyThemeClass(theme);
            const menu = document.getElementById('theme-menu');
            if (menu) menu.classList.add('hidden');
        };

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const container = document.getElementById('theme-toggle-dropdown-container');
            const menu = document.getElementById('theme-menu');
            if (container && menu && !container.contains(e.target)) {
                menu.classList.add('hidden');
            }
        });

        // Listen for system changes if system mode selected
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
                const stored = localStorage.getItem('cinema_theme') || 'system';
                if (stored === 'system') {
                    applyThemeClass('system');
                }
            });
        }

        applyInitialTheme();
    })();
</script>


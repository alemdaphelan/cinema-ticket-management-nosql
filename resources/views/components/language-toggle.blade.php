@php
    $currentLocale = in_array(app()->getLocale(), ['vi', 'en'], true) ? app()->getLocale() : 'vi';
@endphp

<div data-no-translate
     data-language-toggle
     class="inline-flex items-center gap-1 rounded-xl border border-[var(--color-cinema-border)] bg-[var(--color-cinema-card)]/70 p-0.5 text-xs font-bold">
    <svg aria-hidden="true"
         class="ml-1.5 hidden h-3.5 w-3.5 shrink-0 text-[var(--color-cinema-text-muted)] sm:block"
         fill="none"
         stroke="currentColor"
         stroke-width="1.75"
         viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z" />
        <path stroke-linecap="round" stroke-linejoin="round" d="M3.6 9h16.8M3.6 15h16.8M12 3a13.5 13.5 0 0 1 0 18M12 3a13.5 13.5 0 0 0 0 18" />
    </svg>
    <button type="button"
            data-lang-btn="vi"
            onclick="window.setAppLocale('vi')"
            aria-label="Tiếng Việt"
            title="Tiếng Việt"
            class="cursor-pointer rounded-lg px-2 py-1 transition-all {{ $currentLocale === 'vi' ? 'bg-[var(--color-cinema-primary)] text-white shadow-sm' : 'text-[var(--color-cinema-text-muted)] hover:text-cinema-text' }}">
        VI
    </button>
    <button type="button"
            data-lang-btn="en"
            onclick="window.setAppLocale('en')"
            aria-label="English"
            title="English"
            class="cursor-pointer rounded-lg px-2 py-1 transition-all {{ $currentLocale === 'en' ? 'bg-[var(--color-cinema-primary)] text-white shadow-sm' : 'text-[var(--color-cinema-text-muted)] hover:text-cinema-text' }}">
        EN
    </button>
</div>

@once
<script>
    window.CINEMA_MOVIE_TITLES = @json(\App\Support\MovieTranslations::titleMap());
</script>
@endonce

<script>
    if (!window.setAppLocale) {
        window.setAppLocale = function (lang) {
            const target = lang === 'en' ? 'en' : 'vi';
            try {
                localStorage.setItem('cinema_locale', target);
            } catch {}
            document.cookie = 'cinema_locale=' + target + '; path=/; max-age=' + (60 * 60 * 24 * 365) + '; SameSite=Lax';
            window.location.href = '/locale/' + target;
        };
    }
</script>

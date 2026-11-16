<div class="w-full overflow-x-auto rounded-lg border border-cinema-border bg-cinema-surface shadow-sm">
    <table {{ $attributes->merge([
        'class' => 'w-full text-left text-sm text-cinema-text'
    ]) }}>
        {{ $slot }}
    </table>
</div>

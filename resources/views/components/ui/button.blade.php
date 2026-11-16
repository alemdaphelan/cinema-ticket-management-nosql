@props([
    'variant' => 'primary',
    'type' => 'button',
])

@php
    $variants = [
        'primary' => 'bg-blue-600 text-white hover:bg-blue-700',
        'secondary' => 'bg-cinema-card text-cinema-text border border-cinema-border hover:bg-cinema-border',
        'success' => 'bg-green-700 text-white hover:bg-green-800',
        'danger' => 'bg-red-600 text-white hover:bg-red-700',
    ];

    $variantClass = $variants[$variant] ?? $variants['primary'];
@endphp

<button
    type="{{ $type }}"
    {{ $attributes->merge([
        'class' => "inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-medium transition $variantClass"
    ]) }}
>
    {{ $slot }}
</button>

@props([
    'title' => 'Funcionalidade indisponível',
    'message',
    'tone' => 'warning',
])

@php
    $classes = $tone === 'danger'
        ? 'border-rn-danger/30 bg-red-50 text-rn-danger'
        : 'border-rn-border bg-rn-surface-soft text-rn-ink';
@endphp

<div {{ $attributes->merge(['class' => "rounded-xl border px-4 py-3 text-xs leading-relaxed {$classes}"]) }} role="status">
    <p class="font-semibold">{{ $title }}</p>
    <p class="mt-0.5 text-rn-muted">{{ $message }}</p>
</div>

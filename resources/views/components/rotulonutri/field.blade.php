@props([
    'id',
    'label',
    'errorKey' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col gap-1.5']) }}>
    <label for="{{ $id }}" class="text-xs font-medium text-rn-ink">
        {{ $label }}
    </label>
    {{ $slot }}
    <p data-error-for="{{ $errorKey ?? $id }}" class="text-xs text-rn-danger min-h-[1rem] hidden"></p>
</div>

@props([
    'current' => 1,
    'recipeUrl' => null,
    'tableUrl' => null,
    'labelUrl' => null,
])

@php
    $steps = [
        1 => ['label' => 'Receita', 'url' => $recipeUrl],
        2 => ['label' => 'Tabela nutricional', 'url' => $tableUrl],
        3 => ['label' => 'Rótulo em PDF', 'url' => $labelUrl],
    ];
@endphp

<nav aria-label="Etapas do fluxo" class="flex flex-wrap items-center gap-2">
    @foreach ($steps as $number => $step)
        @php
            $isActive = $current === $number;
            $isEnabled = $step['url'] !== null;
        @endphp

        @if ($isActive)
            <span aria-current="step"
                  class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-rn-primary text-white text-xs font-medium">
                <span class="w-5 h-5 rounded-full bg-white text-rn-primary inline-flex items-center justify-center font-semibold text-[11px]">{{ $number }}</span>
                <span>{{ $step['label'] }}</span>
            </span>
        @elseif ($isEnabled)
            <a href="{{ $step['url'] }}"
               class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-rn-surface border border-rn-border text-rn-ink text-xs font-medium hover:border-rn-border-strong">
                <span class="w-5 h-5 rounded-full bg-rn-surface-soft text-rn-primary inline-flex items-center justify-center font-semibold text-[11px]">{{ $number }}</span>
                <span>{{ $step['label'] }}</span>
            </a>
        @else
            <span aria-disabled="true"
                  title="Etapa indisponível"
                  class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-rn-surface border border-rn-border text-rn-muted text-xs font-medium opacity-75 cursor-not-allowed">
                <span class="w-5 h-5 rounded-full bg-rn-input text-rn-muted inline-flex items-center justify-center font-semibold text-[11px]">{{ $number }}</span>
                <span>{{ $step['label'] }}</span>
            </span>
        @endif
    @endforeach
</nav>

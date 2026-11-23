@props([
    'baseLabel' => '100 g',
    'portionText' => '—',
    'servingsPerPackageText' => '—',
    'compact' => false,
])

@php
    $rows = [
        ['key' => 'energy', 'label' => 'Valor energético'],
        ['key' => 'carbohydrates', 'label' => 'Carboidratos'],
        ['key' => 'total_sugars', 'label' => 'Açúcares totais'],
        ['key' => 'added_sugars', 'label' => 'Açúcares adicionados'],
        ['key' => 'proteins', 'label' => 'Proteínas'],
        ['key' => 'total_fat', 'label' => 'Gorduras totais'],
        ['key' => 'saturated_fat', 'label' => 'Gorduras saturadas'],
        ['key' => 'trans_fat', 'label' => 'Gorduras trans'],
        ['key' => 'dietary_fiber', 'label' => 'Fibra alimentar'],
        ['key' => 'sodium', 'label' => 'Sódio'],
    ];
@endphp

<div {{ $attributes->merge(['class' => 'border-2 border-rn-ink bg-white p-4 text-rn-ink']) }} data-nutrition-table>
    <h3 class="text-sm font-bold tracking-wide uppercase">INFORMAÇÃO NUTRICIONAL</h3>
    <div class="h-1 bg-rn-sidebar mt-1.5 mb-2"></div>

    @unless ($compact)
        <p class="text-xs text-rn-muted pb-2 border-b border-rn-border">
            Porções por embalagem: <span data-table-servings>{{ $servingsPerPackageText }}</span>
            · Porção: <span data-table-portion>{{ $portionText }}</span>
        </p>
    @endunless

    <table class="w-full text-xs border-collapse mt-1">
        <thead>
        <tr class="border-b border-rn-border text-right">
            <th scope="col" class="py-1.5 text-left font-normal text-rn-muted sr-only">Nutriente</th>
            <th scope="col" class="py-1.5 font-semibold w-20" data-table-base-col>{{ $baseLabel }}</th>
            <th scope="col" class="py-1.5 font-semibold w-20">Porção</th>
            <th scope="col" class="py-1.5 font-semibold w-16">%VD*</th>
        </tr>
        </thead>
        <tbody class="divide-y divide-rn-border/70">
        @foreach ($rows as $row)
            <tr data-nutrient-row="{{ $row['key'] }}">
                <th scope="row" class="py-2 text-left font-normal text-rn-ink">{{ $row['label'] }}</th>
                <td class="py-2 text-right tabular-nums text-rn-muted" data-cell="base" title="Sem valor declarado disponível">—</td>
                <td class="py-2 text-right tabular-nums text-rn-muted" data-cell="portion" title="Sem valor declarado disponível">—</td>
                <td class="py-2 text-right tabular-nums text-rn-muted" data-cell="vd" title="Sem %VD disponível">—</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    @unless ($compact)
        <p class="text-[11px] text-rn-muted mt-2 pt-2 border-t border-rn-border">
            *% Valores Diários de referência.
        </p>
    @endunless
</div>

@extends('layouts.rotulonutri')

@section('title', 'Cadastrar Ingrediente — RótuloNutri')
@section('page', 'ingredient')
@section('breadcrumb', 'Receita › Ingredientes')
@section('heading', 'Cadastrar Ingrediente')
@section('subheading', 'Ingrediente não encontrado na tabela TACO')

@section('content')
<form id="ingredient-form"
      data-recipe-url="{{ route('recipes.create') }}"
      novalidate
      class="flex flex-col gap-6">

    <div id="ingredient-feedback" class="hidden rounded-xl border px-4 py-3 text-sm" role="alert"></div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <div class="lg:col-span-2 flex flex-col gap-6">
            {{-- Dados do ingrediente --}}
            <section aria-labelledby="ingredient-data-heading"
                     class="bg-rn-surface border border-rn-border rounded-2xl p-6 shadow-xs flex flex-col gap-5">
                <h2 id="ingredient-data-heading" class="text-base font-semibold text-rn-ink">Dados do ingrediente</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-rotulonutri.field id="ing-name" label="Nome do ingrediente *" errorKey="name">
                        <input id="ing-name" name="name" type="text" autocomplete="off"
                               placeholder="Ex.: Leite em pó integral"
                               class="w-full rounded-xl bg-rn-input border border-rn-border px-3.5 py-2.5 text-sm text-rn-ink focus:outline-none focus:border-rn-border-strong">
                    </x-rotulonutri.field>

                    <x-rotulonutri.field id="ing-source" label="Origem do dado * (não usar TACO)" errorKey="source">
                        <input id="ing-source" name="source" type="text" autocomplete="off"
                               placeholder="Ex.: Rótulo do fabricante"
                               class="w-full rounded-xl bg-rn-input border border-rn-border px-3.5 py-2.5 text-sm text-rn-ink focus:outline-none focus:border-rn-border-strong">
                    </x-rotulonutri.field>

                    <x-rotulonutri.field id="ing-source-code" label="Código na fonte *" errorKey="source_code">
                        <input id="ing-source-code" name="source_code" type="text" autocomplete="off"
                               placeholder="Ex.: FAB-001"
                               class="w-full rounded-xl bg-rn-input border border-rn-border px-3.5 py-2.5 text-sm text-rn-ink focus:outline-none focus:border-rn-border-strong">
                    </x-rotulonutri.field>

                    <x-rotulonutri.field id="ing-evidence-ref" label="Referência da evidência *" errorKey="evidence_reference">
                        <input id="ing-evidence-ref" name="evidence_reference" type="text" autocomplete="off"
                               placeholder="Ex.: Rótulo embalagem 400 g"
                               class="w-full rounded-xl bg-rn-input border border-rn-border px-3.5 py-2.5 text-sm text-rn-ink focus:outline-none focus:border-rn-border-strong">
                    </x-rotulonutri.field>

                    <x-rotulonutri.field id="ing-evidence-source" label="Fonte da evidência *" errorKey="evidence_source">
                        <input id="ing-evidence-source" name="evidence_source" type="text" autocomplete="off"
                               placeholder="Ex.: Ficha técnica do fornecedor"
                               class="w-full rounded-xl bg-rn-input border border-rn-border px-3.5 py-2.5 text-sm text-rn-ink focus:outline-none focus:border-rn-border-strong">
                    </x-rotulonutri.field>

                    <x-rotulonutri.field id="ing-registered-by" label="Responsável pelo cadastro *" errorKey="registered_by">
                        <input id="ing-registered-by" name="registered_by" type="text" autocomplete="off"
                               placeholder="Nome do responsável"
                               class="w-full rounded-xl bg-rn-input border border-rn-border px-3.5 py-2.5 text-sm text-rn-ink focus:outline-none focus:border-rn-border-strong">
                    </x-rotulonutri.field>

                    <x-rotulonutri.field id="ing-registered-on" label="Data do registro *" errorKey="registered_on">
                        <input id="ing-registered-on" name="registered_on" type="date"
                               class="w-full rounded-xl bg-rn-input border border-rn-border px-3.5 py-2.5 text-sm text-rn-ink focus:outline-none focus:border-rn-border-strong">
                    </x-rotulonutri.field>
                </div>

                <fieldset class="flex flex-col gap-2">
                    <legend class="text-xs font-medium text-rn-ink">Valores informados por</legend>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="flex items-center gap-2.5 rounded-xl border border-rn-border-strong bg-rn-surface-soft px-3.5 py-2.5 text-sm font-medium text-rn-ink">
                            <input type="radio" name="reference_unit" value="g" checked class="accent-rn-primary">
                            <span>100 g</span>
                        </label>

                        <div aria-disabled="true"
                             class="flex items-center justify-between gap-2 rounded-xl border border-rn-border bg-rn-input px-3.5 py-2.5 text-sm text-rn-muted opacity-75 cursor-not-allowed">
                            <span class="flex items-center gap-2.5">
                                <input type="radio" name="reference_unit" value="ml" disabled>
                                <span>100 ml</span>
                            </span>
                            <span class="text-[11px]">Sem suporte no backend</span>
                        </div>
                    </div>
                </fieldset>
            </section>

            {{-- Informação nutricional --}}
            <section aria-labelledby="ingredient-nutrients-heading"
                     class="bg-rn-surface border border-rn-border rounded-2xl p-6 shadow-xs flex flex-col gap-4">
                <div>
                    <h2 id="ingredient-nutrients-heading" class="text-base font-semibold text-rn-ink">Informação nutricional</h2>
                    <p class="text-xs text-rn-muted mt-0.5">
                        Selecione o estado de cada nutriente informado. Campos não preenchidos não são enviados como zero.
                    </p>
                    <p data-error-for="nutrients" class="text-xs text-rn-danger mt-1 hidden"></p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach ($nutrientFields as $item)
                        @php
                            $reasonText = match ($item['reason']) {
                                'UNSUPPORTED' => 'Sem suporte no backend atual.',
                                'NOT_FOUND' => 'Nutriente não encontrado na base.',
                                'AMBIGUOUS' => 'Correspondência ambígua na base.',
                                default => null,
                            };
                        @endphp
                        <div class="rounded-xl border border-rn-border p-3 flex flex-col gap-2 {{ $item['available'] ? 'bg-rn-surface' : 'bg-rn-input opacity-80' }}"
                             data-nutrient-card="{{ $item['field'] }}"
                             data-nutrient-id="{{ $item['nutrientId'] ?? '' }}"
                             data-available="{{ $item['available'] ? '1' : '0' }}">
                            <span class="text-xs font-medium text-rn-ink">{{ $item['label'] }}</span>

                            @if ($item['available'])
                                <select aria-label="Estado de {{ $item['label'] }}"
                                        data-nutrient-status="{{ $item['field'] }}"
                                        class="w-full rounded-lg bg-rn-input border border-rn-border px-2.5 py-1.5 text-xs text-rn-ink">
                                    <option value="">Não informar</option>
                                    <option value="EXACT">Valor numérico</option>
                                    <option value="TRACE">Traço</option>
                                    <option value="NA">Não aplicável</option>
                                    <option value="ABSENT">Ausente</option>
                                    <option value="REEVALUATION">Em reavaliação</option>
                                </select>

                                <input type="text"
                                       inputmode="decimal"
                                       disabled
                                       aria-label="Valor de {{ $item['label'] }}"
                                       data-nutrient-amount="{{ $item['field'] }}"
                                       placeholder="—"
                                       class="w-full rounded-lg bg-rn-input border border-rn-border px-2.5 py-1.5 text-sm text-rn-ink disabled:opacity-50">
                                <p data-error-for="nutrient:{{ $item['field'] }}" class="text-xs text-rn-danger hidden"></p>
                            @else
                                <p class="text-[11px] text-rn-muted" aria-disabled="true">
                                    Indisponível: {{ $reasonText }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        {{-- Como funciona --}}
        <aside aria-labelledby="how-it-works-heading"
               class="bg-rn-surface border border-rn-border rounded-2xl p-6 shadow-xs flex flex-col gap-3">
            <h2 id="how-it-works-heading" class="text-base font-semibold text-rn-ink">Como funciona</h2>
            <p class="text-xs text-rn-muted leading-relaxed">
                O ingrediente é salvo no banco de dados e passa a aparecer na busca das próximas receitas.
            </p>
            <p class="text-xs text-rn-muted leading-relaxed">
                Preencha os valores por 100 g, informando exatamente os dados da fonte e o estado nutricional correspondente.
            </p>
        </aside>
    </div>

    <div class="flex flex-wrap items-center gap-3 pb-4">
        <button type="submit"
                id="save-ingredient-btn"
                class="rounded-xl bg-rn-primary hover:bg-rn-primary-hover disabled:opacity-60 text-white text-sm font-semibold px-6 py-3 transition-colors cursor-pointer">
            Salvar no banco e voltar à receita
        </button>

        <a href="{{ route('recipes.create') }}"
           class="rounded-xl border border-rn-border-strong text-rn-primary hover:bg-rn-surface-soft text-sm font-semibold px-6 py-3 transition-colors">
            Cancelar
        </a>
    </div>
</form>
@endsection

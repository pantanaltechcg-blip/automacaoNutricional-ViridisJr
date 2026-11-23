@extends('layouts.rotulonutri')

@section('title', 'Cadastro de Receita — RótuloNutri')
@section('page', 'recipe')
@section('heading', 'Cadastro de Receita')
@section('subheading', 'Base de dados: Tabela TACO')

@section('stepper')
    <x-rotulonutri.stepper :current="1" />
@endsection

@section('content')
<div id="recipe-app"
     data-ingredient-create-url="{{ route('ingredients.create') }}"
     data-table-url-template="/receitas/__ID__/tabela"
     data-initial-recipe='@json($initialRecipe ?? null)'
     class="flex flex-col gap-6">

    <div id="recipe-feedback" class="hidden rounded-xl border px-4 py-3 text-sm" role="alert"></div>

    {{-- Cartão 1: Informações da receita --}}
    <section aria-labelledby="recipe-info-heading" class="bg-rn-surface border border-rn-border rounded-2xl p-6 shadow-xs">
        <h2 id="recipe-info-heading" class="text-base font-semibold text-rn-ink mb-4">Informações da receita</h2>

        <div class="flex flex-col gap-5">
            <x-rotulonutri.field id="recipe-name" label="Nome da receita" errorKey="name">
                <input id="recipe-name"
                       type="text"
                       autocomplete="off"
                       placeholder="Ex.: Bolo de milho"
                       class="w-full rounded-xl bg-rn-input border border-rn-border px-3.5 py-2.5 text-sm text-rn-ink focus:outline-none focus:border-rn-border-strong">
            </x-rotulonutri.field>

            <fieldset class="flex flex-col gap-2">
                <legend class="text-xs font-medium text-rn-ink">Tipo do produto</legend>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label data-type-card="SOLID"
                           class="flex items-start gap-3 rounded-xl border p-3.5 cursor-pointer transition-colors border-rn-border-strong bg-rn-surface-soft">
                        <input type="radio" name="product_type" value="SOLID" checked class="mt-1 accent-rn-primary">
                        <span>
                            <span class="block text-sm font-medium text-rn-ink">Sólido ou semissólido</span>
                            <span class="block text-xs text-rn-muted">Valores por 100 g</span>
                        </span>
                    </label>

                    <label data-type-card="LIQUID"
                           class="flex items-start gap-3 rounded-xl border p-3.5 cursor-pointer transition-colors border-rn-border bg-rn-surface">
                        <input type="radio" name="product_type" value="LIQUID" class="mt-1 accent-rn-primary">
                        <span>
                            <span class="block text-sm font-medium text-rn-ink">Líquido</span>
                            <span class="block text-xs text-rn-muted">Valores por 100 ml</span>
                        </span>
                    </label>
                </div>
                <p data-error-for="yield_unit" class="text-xs text-rn-danger hidden"></p>
            </fieldset>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="flex flex-col gap-1.5">
                    <label for="yield-value" data-yield-label class="text-xs font-medium text-rn-ink">Peso final da receita (g)</label>
                    <input id="yield-value"
                           type="text"
                           inputmode="decimal"
                           placeholder="0"
                           class="w-full rounded-xl bg-rn-input border border-rn-border px-3.5 py-2.5 text-sm text-rn-ink focus:outline-none focus:border-rn-border-strong">
                    <p data-error-for="yield_value" class="text-xs text-rn-danger min-h-[1rem] hidden"></p>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="portion-value" data-portion-label class="text-xs font-medium text-rn-ink">Porção (g)</label>
                    <input id="portion-value"
                           type="text"
                           inputmode="decimal"
                           placeholder="0"
                           class="w-full rounded-xl bg-rn-input border border-rn-border px-3.5 py-2.5 text-sm text-rn-ink focus:outline-none focus:border-rn-border-strong">
                    <p class="text-[11px] text-rn-muted">Mantida apenas no estado local até liberação da etapa regulatória.</p>
                </div>

                <x-rotulonutri.field id="household-measure" label="Medida caseira" errorKey="household_measure">
                    <input id="household-measure"
                           type="text"
                           placeholder="ex.: 1 fatia"
                           class="w-full rounded-xl bg-rn-input border border-rn-border px-3.5 py-2.5 text-sm text-rn-ink focus:outline-none focus:border-rn-border-strong">
                </x-rotulonutri.field>
            </div>
        </div>
    </section>

    {{-- Cartão 2: Ingredientes e quantidades --}}
    <section aria-labelledby="ingredients-heading" class="bg-rn-surface border border-rn-border rounded-2xl p-6 shadow-xs">
        <h2 id="ingredients-heading" class="text-base font-semibold text-rn-ink mb-4">Ingredientes e quantidades</h2>

        <div class="flex flex-col gap-4">
            <div class="relative">
                <label for="ingredient-search" class="block text-xs font-medium text-rn-ink mb-1.5">Buscar ingrediente</label>
                <input id="ingredient-search"
                       type="search"
                       autocomplete="off"
                       placeholder="Digite para pesquisar na tabela TACO"
                       class="w-full rounded-xl bg-rn-input border border-rn-border px-3.5 py-2.5 text-sm text-rn-ink focus:outline-none focus:border-rn-border-strong">
                <p id="search-status" class="text-xs text-rn-muted mt-1 hidden"></p>

                <div id="search-results"
                     class="mt-2 rounded-xl border border-rn-border bg-rn-surface overflow-hidden hidden"
                     role="listbox"
                     aria-label="Resultados da busca de ingredientes">
                </div>
            </div>

            <p data-error-for="ingredients" class="text-xs text-rn-danger hidden"></p>

            <div id="ingredients-list" class="flex flex-col gap-2.5" aria-live="polite"></div>

            <div class="rounded-xl bg-rn-sidebar text-white px-5 py-3.5 flex items-center justify-between gap-4">
                <div>
                    <span class="text-sm font-medium block">Total informativo dos ingredientes</span>
                    <span class="text-[11px] text-white/70">Soma apenas informativa; não substitui o rendimento final da receita.</span>
                </div>
                <span class="text-lg font-semibold tabular-nums"><span id="ingredients-total">0</span> g</span>
            </div>

            <div class="rounded-xl border-2 border-dashed border-rn-border-strong/40 bg-rn-surface-soft/50 px-5 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-rn-ink">Ingrediente não está no banco de dados?</p>
                    <p class="text-xs text-rn-muted">Cadastre manualmente em uma tela separada.</p>
                </div>
                <a href="{{ route('ingredients.create') }}"
                   id="manual-ingredient-link"
                   class="inline-flex items-center justify-center rounded-xl bg-rn-primary hover:bg-rn-primary-hover text-white text-xs font-medium px-4 py-2.5 shrink-0 transition-colors">
                    + Cadastrar novo ingrediente
                </a>
            </div>
        </div>
    </section>

    <div class="flex justify-center pt-1 pb-4">
        <button type="button"
                id="generate-table-btn"
                class="rounded-xl bg-rn-primary hover:bg-rn-primary-hover disabled:opacity-60 text-white text-sm font-semibold px-10 py-3.5 transition-colors cursor-pointer">
            Gerar tabela nutricional
        </button>
    </div>
</div>
@endsection

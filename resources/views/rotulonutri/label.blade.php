@extends('layouts.rotulonutri')

@section('title', 'Montagem do Rótulo — RótuloNutri')
@section('page', 'label')
@section('heading', 'Montagem do Rótulo')
@section('subheading', 'Base de dados: Tabela TACO')

@section('stepper')
    <x-rotulonutri.stepper
        :current="3"
        :recipeUrl="route('recipes.create')"
        :tableUrl="route('recipes.table', ['recipe' => $declaredCalculation->source_recipe_id])"
        :labelUrl="route('labels.assembly', ['declaredNutritionCalculation' => $declaredCalculation->id])" />
@endsection

@section('content')
<div id="label-app"
     data-declared-id="{{ $declaredCalculation->id }}"
     class="flex flex-col gap-6">

    <div id="label-readiness-banner"
         class="hidden rounded-xl border border-rn-border bg-rn-surface-soft px-4 py-3 text-xs text-rn-ink"
         role="status">
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        {{-- Pré-visualização do rótulo --}}
        <section aria-labelledby="label-preview-heading"
                 class="lg:col-span-2 bg-rn-surface-soft border border-rn-border rounded-2xl p-6 flex justify-center">
            <h2 id="label-preview-heading" class="sr-only">Pré-visualização do rótulo</h2>

            <div class="w-full max-w-md bg-white border border-rn-border rounded-xl p-6 shadow-xs flex flex-col gap-4">
                <p class="text-xl font-bold text-center text-rn-ink">{{ $productName }}</p>
                <p class="text-xs text-rn-muted">
                    <strong class="text-rn-ink">Ingredientes:</strong> [lista de ingredientes — indisponível no backend atual]
                </p>

                <x-rotulonutri.nutrition-table
                    :baseLabel="$declaredCalculation->yield_unit === 'ml' ? '100 ml' : '100 g'"
                    :compact="true" />

                <p class="text-[11px] text-rn-muted text-center">
                    [Lote · Validade · Peso líquido · Contato — indisponível no backend atual]
                </p>
            </div>
        </section>

        {{-- Painel "Arquivo para impressão" --}}
        <aside aria-labelledby="print-file-heading"
               class="bg-rn-surface border border-rn-border rounded-2xl p-6 shadow-xs flex flex-col gap-4">
            <h2 id="print-file-heading" class="text-base font-semibold text-rn-ink">Arquivo para impressão</h2>

            <div class="flex flex-col gap-1.5">
                <span class="text-xs font-medium text-rn-ink">Tamanho do rótulo</span>
                <div class="rounded-xl bg-rn-input border border-rn-border px-3.5 py-2.5 text-sm text-rn-muted">
                    [ex.: 10 × 15 cm]
                </div>
            </div>

            <div class="flex flex-col gap-1.5">
                <span class="text-xs font-medium text-rn-ink">Formato</span>
                <div class="rounded-xl bg-rn-input border border-rn-border px-3.5 py-2.5 text-sm text-rn-ink">
                    PDF
                </div>
            </div>

            <div class="flex flex-col gap-1.5">
                <span class="text-xs font-medium text-rn-ink">Nome do arquivo</span>
                <div class="rounded-xl bg-rn-input border border-rn-border px-3.5 py-2.5 text-sm text-rn-muted">
                    [nome-do-produto].pdf
                </div>
            </div>

            <button type="button"
                    disabled
                    aria-disabled="true"
                    class="w-full rounded-xl bg-rn-primary text-white text-sm font-semibold py-2.5 opacity-50 cursor-not-allowed">
                Baixar PDF
            </button>

            <p class="text-[11px] text-rn-muted">
                Exportação em PDF indisponível no sistema atual.
            </p>

            <a href="{{ route('recipes.create') }}"
               class="w-full text-center rounded-xl border border-rn-border-strong text-rn-primary hover:bg-rn-surface-soft text-sm font-semibold py-2.5 transition-colors">
                Editar informações
            </a>
        </aside>
    </div>
</div>
@endsection

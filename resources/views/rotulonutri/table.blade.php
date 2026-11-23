@extends('layouts.rotulonutri')

@section('title', 'Tabela Nutricional — RótuloNutri')
@section('page', 'table')
@section('heading', 'Tabela Nutricional')
@section('subheading', 'Base de dados: Tabela TACO')

@section('stepper')
    <x-rotulonutri.stepper :current="2" :recipeUrl="route('recipes.create')" />
@endsection

@section('content')
<div id="table-app" data-recipe-id="{{ $recipe->id }}" class="flex flex-col gap-6">

    <div id="table-readiness-banner"
         class="hidden rounded-xl border border-rn-border bg-rn-surface-soft px-4 py-3 text-xs text-rn-ink"
         role="status">
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        {{-- Pré-visualização da tabela --}}
        <section aria-labelledby="table-preview-heading"
                 class="lg:col-span-2 bg-rn-surface border border-rn-border rounded-2xl p-6 shadow-xs">
            <h2 id="table-preview-heading" class="sr-only">Pré-visualização da tabela nutricional</h2>

            <x-rotulonutri.nutrition-table
                :baseLabel="$recipe->yield_unit === 'ml' ? '100 ml' : '100 g'"
                portionText="—"
                servingsPerPackageText="—" />
        </section>

        {{-- Painel "Modelo do cliente" (apenas visual — CQ-007) --}}
        <aside aria-labelledby="client-model-heading"
               class="bg-rn-surface border border-rn-border rounded-2xl p-6 shadow-xs flex flex-col gap-3">
            <h2 id="client-model-heading" class="text-base font-semibold text-rn-ink">Modelo do cliente</h2>

            <div class="rounded-xl border border-rn-border-strong bg-rn-surface-soft px-3.5 py-2.5 text-sm font-medium text-rn-ink">
                Vertical (padrão)
            </div>
            <div class="rounded-xl border border-rn-border bg-rn-surface px-3.5 py-2.5 text-sm text-rn-muted">
                padrão
            </div>
            <div class="rounded-xl border border-rn-border bg-rn-surface px-3.5 py-2.5 text-sm text-rn-muted">
                10 pt
            </div>

            <p class="text-[11px] text-rn-muted leading-relaxed">
                Elementos visuais do protótipo. Personalização e persistência de modelo não possuem suporte no backend atual.
            </p>

            <button type="button"
                    id="apply-to-label-btn"
                    class="w-full rounded-xl bg-rn-primary hover:bg-rn-primary-hover text-white text-sm font-semibold py-2.5 transition-colors cursor-pointer">
                Aplicar ao rótulo
            </button>

            <p id="apply-label-notice" class="hidden text-xs text-rn-muted bg-rn-surface-soft border border-rn-border rounded-xl px-3 py-2">
                A montagem do rótulo requer uma execução válida da Feature 005 e suporte de modelo no backend.
            </p>

            <a href="{{ route('recipes.create') }}"
               class="w-full text-center rounded-xl border border-rn-border-strong text-rn-primary hover:bg-rn-surface-soft text-sm font-semibold py-2.5 transition-colors">
                Voltar à receita
            </a>
        </aside>
    </div>

    {{-- Andamento das etapas --}}
    <section aria-labelledby="stages-heading" class="bg-rn-surface border border-rn-border rounded-2xl p-6 shadow-xs">
        <h2 id="stages-heading" class="text-sm font-semibold text-rn-ink mb-3">Andamento das etapas</h2>
        <ul id="stages-status-list" class="flex flex-col gap-2">
            <li class="rounded-xl border border-rn-border px-4 py-2.5 text-xs text-rn-muted">
                Receita #{{ $recipe->id }} ({{ $recipe->name }}). Nenhum relatório de execução recente na sessão.
            </li>
        </ul>
    </section>
</div>
@endsection

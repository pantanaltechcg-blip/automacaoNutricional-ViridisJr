@extends('layouts.rotulonutri')

@section('title', 'Detalhes do Cálculo Base — RótuloNutri')
@section('page', 'calculation')
@section('breadcrumb', 'Receita › Cálculo Nutricional (Feature 003)')
@section('heading', 'Cálculo Nutricional Base')
@section('subheading', 'Valores exatos retornados pelo servidor (sem arredondamento ou conversão)')

@section('content')
<div id="calculation-app"
     data-calculation-id="{{ $calculation->id }}"
     class="flex flex-col gap-6">

    <div id="calculation-feedback" class="hidden rounded-xl border px-4 py-3 text-sm" role="alert"></div>

    <section class="bg-rn-surface border border-rn-border rounded-2xl p-6 shadow-xs flex flex-col gap-2">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-rn-ink">Execução #{{ $calculation->id }}</h2>
            <a href="{{ route('recipes.table', ['recipe' => $calculation->recipe_id]) }}"
               class="rounded-xl border border-rn-border-strong text-rn-primary hover:bg-rn-surface-soft text-xs font-semibold px-4 py-2">
                Voltar à tabela nutricional
            </a>
        </div>
        <p id="calculation-meta" class="text-xs text-rn-muted">Carregando dados da execução...</p>
    </section>

    <section aria-labelledby="calc-nutrients-heading" class="bg-rn-surface border border-rn-border rounded-2xl p-6 shadow-xs">
        <h2 id="calc-nutrients-heading" class="text-base font-semibold text-rn-ink mb-4">Nutrientes calculados (razão exata)</h2>

        <div class="overflow-x-auto">
            <table class="w-full text-xs border-collapse">
                <thead>
                <tr class="border-b border-rn-border text-left text-rn-muted">
                    <th scope="col" class="py-2 pr-3 font-semibold">Nutriente</th>
                    <th scope="col" class="py-2 pr-3 font-semibold">Unidade</th>
                    <th scope="col" class="py-2 pr-3 font-semibold">Total (razão exata)</th>
                    <th scope="col" class="py-2 pr-3 font-semibold">Completude</th>
                    <th scope="col" class="py-2 font-semibold">Estados qualitativos</th>
                </tr>
                </thead>
                <tbody id="calc-nutrients-body" class="divide-y divide-rn-border/70">
                <tr><td colspan="5" class="py-4 text-rn-muted">Carregando...</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

@extends('layouts.rotulonutri')

@section('title', 'Rótulos — RótuloNutri')
@section('page', 'labels')
@section('breadcrumb', 'Rótulos')
@section('heading', 'Execuções de rótulo')
@section('subheading', 'Consulta de resultados preservados; esta tela não realiza cálculos.')

@section('content')
    @if ($calculations->isEmpty())
        <section aria-labelledby="labels-empty-heading" class="rounded-2xl border border-rn-border bg-rn-surface p-8 text-center shadow-xs">
            <h2 id="labels-empty-heading" class="text-base font-semibold text-rn-ink">Nenhuma execução de rótulo disponível</h2>
            <p class="mt-2 text-sm text-rn-muted">Quando houver execuções declaradas preservadas, elas aparecerão nesta lista.</p>
        </section>
    @else
        <section aria-labelledby="labels-list-heading" class="flex flex-col gap-4">
            <h2 id="labels-list-heading" class="sr-only">Execuções preservadas</h2>

            @foreach ($calculations as $calculation)
                <article class="rounded-2xl border border-rn-border bg-rn-surface p-5 shadow-xs">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="font-semibold text-rn-ink">Execução #{{ $calculation->id }}</h3>
                            <p class="mt-1 text-xs text-rn-muted">
                                Receita #{{ $calculation->source_recipe_id }}
                                <span aria-hidden="true">·</span>
                                Cálculo nutricional #{{ $calculation->source_nutrition_calculation_id }}
                            </p>
                            <p class="mt-1 text-xs text-rn-muted">Criada em {{ $calculation->created_at?->format('d/m/Y H:i') ?? 'data indisponível' }}</p>
                        </div>

                        @if ($calculation->is_label_ready)
                            <span class="inline-flex w-fit rounded-full border border-rn-border px-3 py-1 text-xs font-medium text-rn-ink">
                                Apta segundo a execução registrada
                            </span>
                        @else
                            <span class="inline-flex w-fit rounded-full border border-rn-border bg-rn-surface-soft px-3 py-1 text-xs font-medium text-rn-ink">
                                Não apta para rotulagem
                            </span>
                        @endif
                    </div>

                    <div class="mt-4 border-t border-rn-border pt-4">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-rn-muted">Causas registradas</h4>
                        @if ($calculation->causes->isEmpty())
                            <p class="mt-2 text-sm text-rn-muted">Sem causas registradas nesta execução.</p>
                        @else
                            <ul class="mt-2 flex flex-col gap-2">
                                @foreach ($calculation->causes as $cause)
                                    <li class="rounded-lg bg-rn-surface-soft px-3 py-2 text-sm text-rn-ink">
                                        <p class="font-medium">{{ $cause->code }} <span class="font-normal text-rn-muted">({{ $cause->category }} · {{ $cause->scope }})</span></p>
                                        @if ($cause->detail !== null)
                                            <p class="mt-1 text-xs text-rn-muted">{{ $cause->detail }}</p>
                                        @endif
                                        @if ($cause->source_reference !== null)
                                            <p class="mt-1 text-xs text-rn-muted">Referência: {{ $cause->source_reference }}</p>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <div class="mt-4 flex justify-end">
                        <a href="{{ route('labels.assembly', ['declaredNutritionCalculation' => $calculation->id]) }}"
                           class="rounded-xl border border-rn-border-strong px-4 py-2 text-sm font-semibold text-rn-primary transition-colors hover:bg-rn-surface-soft">
                            Abrir montagem
                        </a>
                    </div>
                </article>
            @endforeach

            <nav aria-label="Paginação das execuções de rótulo" class="pt-2">
                {{ $calculations->links() }}
            </nav>
        </section>
    @endif
@endsection

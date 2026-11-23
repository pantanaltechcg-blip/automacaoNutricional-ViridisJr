import { requestJson } from '../api.js';
import {
    addDecimalStrings,
    decrementByOnePositive,
    incrementByOne,
    normalizeDecimalInput,
} from '../decimal.js';
import { loadRecipeDraft, saveRecipeDraft, saveStageReport } from '../draft.js';
import { buildRecipePayload } from '../payloads.js';
import { runNutritionPipeline } from '../pipeline.js';

export function init() {
    const root = document.getElementById('recipe-app');
    if (!root) {
        return;
    }

    const ingredientCreateUrl = root.dataset.ingredientCreateUrl || '/ingredientes/novo';
    const tableUrlTemplate = root.dataset.tableUrlTemplate || '/receitas/__ID__/tabela';

    const nameEl = document.getElementById('recipe-name');
    const yieldEl = document.getElementById('yield-value');
    const portionEl = document.getElementById('portion-value');
    const householdEl = document.getElementById('household-measure');
    const yieldLabelEl = document.querySelector('[data-yield-label]');
    const portionLabelEl = document.querySelector('[data-portion-label]');
    const typeRadios = document.querySelectorAll('input[name="product_type"]');
    const searchEl = document.getElementById('ingredient-search');
    const searchStatusEl = document.getElementById('search-status');
    const searchResultsEl = document.getElementById('search-results');
    const listEl = document.getElementById('ingredients-list');
    const totalEl = document.getElementById('ingredients-total');
    const feedbackEl = document.getElementById('recipe-feedback');
    const generateBtn = document.getElementById('generate-table-btn');

    let draft = loadRecipeDraft();
    const initialRecipeRaw = root.dataset.initialRecipe;
    if (initialRecipeRaw && initialRecipeRaw !== 'null') {
        try {
            const initial = JSON.parse(initialRecipeRaw);
            if (initial && initial.recipeId) {
                draft = {
                    ...draft,
                    ...initial,
                };
                persistDraft();
            }
        } catch {
            // mantém rascunho existente
        }
    }
    let searchTimer = null;
    let searchAbort = null;

    function persistDraft() {
        saveRecipeDraft(draft);
    }

    function clearErrors() {
        document.querySelectorAll('[data-error-for]').forEach((el) => {
            el.textContent = '';
            el.classList.add('hidden');
        });
        feedbackEl.classList.add('hidden');
        feedbackEl.replaceChildren();
    }

    function showFieldError(field, message) {
        const el = document.querySelector(`[data-error-for="${CSS.escape(field)}"]`);
        if (el) {
            el.textContent = message;
            el.classList.remove('hidden');
            return true;
        }
        return false;
    }

    function showBanner(message, tone = 'error', retryFn = null) {
        feedbackEl.className = 'rounded-xl border px-4 py-3 text-sm flex flex-wrap items-center justify-between gap-3 ' + (
            tone === 'success'
                ? 'border-rn-border-strong/30 bg-rn-surface-soft text-rn-ink'
                : 'border-rn-danger/30 bg-red-50 text-rn-danger'
        );
        const span = document.createElement('span');
        span.textContent = message;
        feedbackEl.replaceChildren(span);

        if (retryFn) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = 'Tentar novamente';
            btn.className = 'rounded-lg border border-current px-3 py-1 text-xs font-medium cursor-pointer';
            btn.addEventListener('click', retryFn);
            feedbackEl.appendChild(btn);
        }
        feedbackEl.classList.remove('hidden');
    }

    function updateTypeLabels() {
        const isLiquid = draft.productType === 'LIQUID';
        yieldLabelEl.textContent = isLiquid ? 'Volume final da receita (ml)' : 'Peso final da receita (g)';
        portionLabelEl.textContent = isLiquid ? 'Porção (ml)' : 'Porção (g)';

        document.querySelectorAll('[data-type-card]').forEach((card) => {
            const selected = card.dataset.typeCard === draft.productType;
            card.classList.toggle('border-rn-border-strong', selected);
            card.classList.toggle('bg-rn-surface-soft', selected);
            card.classList.toggle('border-rn-border', !selected);
            card.classList.toggle('bg-rn-surface', !selected);
        });
    }

    function updateTotal() {
        totalEl.textContent = addDecimalStrings(draft.ingredients.map((i) => i.quantity));
    }

    function renderIngredients() {
        listEl.replaceChildren();

        draft.ingredients.forEach((item, index) => {
            const row = document.createElement('div');
            row.className = 'rounded-xl border border-rn-border bg-rn-surface px-4 py-3 flex flex-col gap-1.5';

            const top = document.createElement('div');
            top.className = 'flex flex-wrap items-center justify-between gap-3';

            const nameWrap = document.createElement('div');
            nameWrap.className = 'min-w-0 flex-1';
            const nameSpan = document.createElement('span');
            nameSpan.className = 'text-sm font-medium text-rn-ink';
            nameSpan.textContent = item.name;
            const sourceSpan = document.createElement('span');
            sourceSpan.className = 'ml-2 text-xs text-rn-muted';
            sourceSpan.textContent = item.source ? `(${item.source})` : '';
            nameWrap.append(nameSpan, sourceSpan);

            const controls = document.createElement('div');
            controls.className = 'flex items-center gap-2';

            const minusBtn = document.createElement('button');
            minusBtn.type = 'button';
            minusBtn.textContent = '−';
            minusBtn.setAttribute('aria-label', `Diminuir 1 g de ${item.name}`);
            minusBtn.className = 'w-8 h-8 rounded-lg border border-rn-border bg-rn-surface text-rn-ink hover:border-rn-border-strong disabled:opacity-40 cursor-pointer';
            minusBtn.disabled = decrementByOnePositive(item.quantity) === null;
            minusBtn.addEventListener('click', () => {
                const next = decrementByOnePositive(item.quantity);
                if (next !== null) {
                    draft.ingredients[index].quantity = next;
                    persistDraft();
                    renderIngredients();
                }
            });

            const qtyInput = document.createElement('input');
            qtyInput.type = 'text';
            qtyInput.inputMode = 'decimal';
            qtyInput.value = item.quantity;
            qtyInput.setAttribute('aria-label', `Quantidade em gramas de ${item.name}`);
            qtyInput.className = 'w-20 text-center rounded-lg bg-rn-input border border-rn-border px-2 py-1.5 text-sm tabular-nums';
            qtyInput.addEventListener('input', (e) => {
                draft.ingredients[index].quantity = e.target.value;
                minusBtn.disabled = decrementByOnePositive(e.target.value) === null;
                persistDraft();
                updateTotal();
            });

            const plusBtn = document.createElement('button');
            plusBtn.type = 'button';
            plusBtn.textContent = '+';
            plusBtn.setAttribute('aria-label', `Aumentar 1 g de ${item.name}`);
            plusBtn.className = 'w-8 h-8 rounded-lg border border-rn-border bg-rn-surface text-rn-ink hover:border-rn-border-strong cursor-pointer';
            plusBtn.addEventListener('click', () => {
                draft.ingredients[index].quantity = incrementByOne(item.quantity);
                persistDraft();
                renderIngredients();
            });

            const unitSpan = document.createElement('span');
            unitSpan.className = 'text-xs text-rn-muted';
            unitSpan.textContent = 'g';

            const kcalSpan = document.createElement('span');
            kcalSpan.className = 'text-xs text-rn-muted ml-1';
            kcalSpan.title = 'Cálculo nutricional ocorre apenas no servidor após salvar';
            kcalSpan.textContent = 'kcal indisponível';

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.textContent = '×';
            removeBtn.setAttribute('aria-label', `Remover ${item.name}`);
            removeBtn.className = 'w-8 h-8 rounded-lg border border-rn-border text-rn-danger hover:bg-red-50 cursor-pointer font-semibold';
            removeBtn.addEventListener('click', () => {
                draft.ingredients.splice(index, 1);
                persistDraft();
                renderIngredients();
            });

            controls.append(minusBtn, qtyInput, plusBtn, unitSpan, kcalSpan, removeBtn);
            top.append(nameWrap, controls);

            const errP = document.createElement('p');
            errP.dataset.errorFor = `ingredients.${index}.quantity`;
            errP.className = 'text-xs text-rn-danger hidden';

            row.append(top, errP);
            listEl.appendChild(row);
        });

        updateTotal();
    }

    function renderSearchResults(foods) {
        searchResultsEl.replaceChildren();

        if (foods.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'px-4 py-3 text-xs text-rn-muted';
            empty.textContent = 'Nenhum ingrediente encontrado para o termo informado.';
            searchResultsEl.appendChild(empty);
        } else {
            foods.forEach((food) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'w-full px-4 py-2.5 text-left text-sm hover:bg-rn-surface-soft flex items-center justify-between gap-3 border-b border-rn-border/60 cursor-pointer';

                const left = document.createElement('span');
                left.className = 'text-rn-ink';
                left.textContent = food.name;

                const right = document.createElement('div');
                right.className = 'flex items-center gap-2 shrink-0';

                const sourceBadge = document.createElement('span');
                sourceBadge.className = 'text-xs font-medium text-rn-muted';
                sourceBadge.textContent = food.source;
                right.appendChild(sourceBadge);

                if (String(food.source).toUpperCase() !== 'TACO') {
                    const editLink = document.createElement('a');
                    editLink.href = `/ingredientes/${encodeURIComponent(food.id)}`;
                    editLink.className = 'text-xs font-medium text-rn-primary hover:underline';
                    editLink.textContent = 'Editar';
                    editLink.addEventListener('click', (ev) => ev.stopPropagation());
                    right.appendChild(editLink);
                }

                btn.append(left, right);
                btn.addEventListener('click', () => {
                    draft.ingredients.push({
                        foodId: food.id,
                        name: food.name,
                        source: food.source,
                        quantity: '100',
                    });
                    persistDraft();
                    renderIngredients();
                    searchResultsEl.classList.add('hidden');
                    searchEl.value = '';
                    searchStatusEl.classList.add('hidden');
                });
                searchResultsEl.appendChild(btn);
            });
        }

        const manualLink = document.createElement('a');
        manualLink.href = ingredientCreateUrl;
        manualLink.className = 'block px-4 py-2.5 text-xs font-medium text-rn-primary bg-rn-surface-soft hover:underline';
        manualLink.textContent = 'Não encontrou? Cadastrar ingrediente manualmente';
        searchResultsEl.appendChild(manualLink);
        searchResultsEl.classList.remove('hidden');
    }

    async function runSearch(term) {
        if (searchAbort) {
            searchAbort.abort();
        }
        const trimmed = term.trim();
        if (trimmed === '') {
            searchResultsEl.classList.add('hidden');
            searchStatusEl.classList.add('hidden');
            return;
        }

        searchAbort = new AbortController();
        searchStatusEl.textContent = 'Buscando ingredientes...';
        searchStatusEl.classList.remove('hidden');

        const res = await requestJson(`/api/foods?q=${encodeURIComponent(trimmed)}`, {
            signal: searchAbort.signal,
        });

        if (!res.ok) {
            if (res.error.kind === 'aborted') {
                return;
            }
            searchStatusEl.textContent = res.error.message;
            return;
        }

        searchStatusEl.classList.add('hidden');
        renderSearchResults(Array.isArray(res.data?.data) ? res.data.data : []);
    }

    async function handleGenerate() {
        clearErrors();
        const built = buildRecipePayload(draft);
        if (!built.ok) {
            let unmapped = [];
            built.errors.forEach((err) => {
                if (!showFieldError(err.field, err.message)) {
                    unmapped.push(err.message);
                }
            });
            showBanner(unmapped[0] || 'Verifique os campos obrigatórios da receita antes de continuar.');
            return;
        }

        generateBtn.disabled = true;
        generateBtn.textContent = 'Processando...';

        try {
            const outcome = await runNutritionPipeline({
                draft,
                payload: built.payload,
                requestJson,
            });

            if (outcome.recipeId) {
                draft.recipeId = outcome.recipeId;
                persistDraft();
            }

            if (outcome.report) {
                saveStageReport(outcome.report);
            }

            if (!outcome.recipeSaved) {
                const err = outcome.error;
                if (err.kind === 'validation') {
                    const unmapped = [];
                    err.fields.forEach((f) => {
                        if (!showFieldError(f.field, f.message)) {
                            unmapped.push(f.message);
                        }
                    });
                    showBanner(unmapped[0] || err.message);
                } else {
                    showBanner(err.message, 'error', err.retryable ? handleGenerate : null);
                }
                return;
            }

            const targetUrl = tableUrlTemplate.replace('__ID__', String(outcome.recipeId));
            window.location.assign(targetUrl);
        } finally {
            generateBtn.disabled = false;
            generateBtn.textContent = 'Gerar tabela nutricional';
        }
    }

    // Hidratar estado inicial
    nameEl.value = draft.name;
    yieldEl.value = draft.yieldValue;
    portionEl.value = draft.portionValue;
    householdEl.value = draft.householdMeasure;
    typeRadios.forEach((r) => {
        r.checked = r.value === draft.productType;
    });
    updateTypeLabels();
    renderIngredients();

    if (draft.unsavedNotice) {
        showBanner(draft.unsavedNotice, 'success');
        draft.unsavedNotice = null;
        persistDraft();
    }

    nameEl.addEventListener('input', (e) => {
        draft.name = e.target.value;
        persistDraft();
    });
    yieldEl.addEventListener('input', (e) => {
        draft.yieldValue = e.target.value;
        persistDraft();
    });
    portionEl.addEventListener('input', (e) => {
        draft.portionValue = e.target.value;
        persistDraft();
    });
    householdEl.addEventListener('input', (e) => {
        draft.householdMeasure = e.target.value;
        persistDraft();
    });
    typeRadios.forEach((radio) => {
        radio.addEventListener('change', (e) => {
            if (e.target.checked) {
                draft.productType = e.target.value === 'LIQUID' ? 'LIQUID' : 'SOLID';
                persistDraft();
                updateTypeLabels();
            }
        });
    });

    searchEl.addEventListener('input', (e) => {
        clearTimeout(searchTimer);
        const value = e.target.value;
        searchTimer = setTimeout(() => runSearch(value), 300);
    });

    generateBtn.addEventListener('click', handleGenerate);
}

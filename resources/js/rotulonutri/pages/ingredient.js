import { requestJson } from '../api.js';
import { loadRecipeDraft, saveRecipeDraft } from '../draft.js';
import { buildManualFoodPayload } from '../payloads.js';

export function init() {
    const form = document.getElementById('ingredient-form');
    if (!form) {
        return;
    }

    const recipeUrl = form.dataset.recipeUrl || '/receitas/nova';
    const feedbackEl = document.getElementById('ingredient-feedback');
    const saveBtn = document.getElementById('save-ingredient-btn');

    function clearErrors() {
        document.querySelectorAll('[data-error-for]').forEach((el) => {
            el.textContent = '';
            el.classList.add('hidden');
        });
        feedbackEl.classList.add('hidden');
        feedbackEl.replaceChildren();
    }

    function showFieldError(key, message) {
        const el = document.querySelector(`[data-error-for="${CSS.escape(key)}"]`);
        if (el) {
            el.textContent = message;
            el.classList.remove('hidden');
            return true;
        }
        return false;
    }

    function showBanner(message, retryFn = null) {
        feedbackEl.className = 'rounded-xl border border-rn-danger/30 bg-red-50 text-rn-danger px-4 py-3 text-sm flex flex-wrap items-center justify-between gap-3';
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

    // Habilita/desabilita campo numérico conforme o estado selecionado
    document.querySelectorAll('[data-nutrient-status]').forEach((select) => {
        const field = select.dataset.nutrientStatus;
        const amountInput = document.querySelector(`[data-nutrient-amount="${CSS.escape(field)}"]`);
        select.addEventListener('change', () => {
            if (!amountInput) {
                return;
            }
            const isExact = select.value === 'EXACT';
            amountInput.disabled = !isExact;
            if (!isExact) {
                amountInput.value = '';
                amountInput.placeholder = '—';
            } else {
                amountInput.placeholder = '0';
            }
        });
    });

    async function submitManualFood() {
        clearErrors();

        const nutrients = [];
        document.querySelectorAll('[data-nutrient-card][data-available="1"]').forEach((card) => {
            const field = card.dataset.nutrientCard;
            const nutrientId = Number(card.dataset.nutrientId);
            const statusEl = card.querySelector(`[data-nutrient-status="${CSS.escape(field)}"]`);
            const amountEl = card.querySelector(`[data-nutrient-amount="${CSS.escape(field)}"]`);
            nutrients.push({
                field,
                nutrientId,
                status: statusEl ? statusEl.value : '',
                amountValue: amountEl ? amountEl.value : '',
            });
        });

        const built = buildManualFoodPayload({
            name: document.getElementById('ing-name')?.value,
            source: document.getElementById('ing-source')?.value,
            sourceCode: document.getElementById('ing-source-code')?.value,
            evidenceReference: document.getElementById('ing-evidence-ref')?.value,
            evidenceSource: document.getElementById('ing-evidence-source')?.value,
            registeredBy: document.getElementById('ing-registered-by')?.value,
            registeredOn: document.getElementById('ing-registered-on')?.value,
            nutrients,
        });

        saveBtn.disabled = true;
        saveBtn.textContent = 'Salvando...';

        try {
            const res = await requestJson('/api/manual-foods', {
                method: 'POST',
                body: built.payload,
            });

            if (res.ok) {
                const draft = loadRecipeDraft();
                draft.unsavedNotice = `Ingrediente "${built.payload.name}" cadastrado com sucesso. Pesquise-o abaixo para adicionar à receita.`;
                saveRecipeDraft(draft);
                window.location.assign(recipeUrl);
                return;
            }

            const err = res.error;
            if (err.kind === 'validation') {
                const unmapped = [];
                err.fields.forEach((f) => {
                    const match = /^nutrients\.(\d+)\.(amount_value|status|nutrient_id)$/.exec(f.field);
                    if (match) {
                        const targetField = built.nutrientIndexToField[Number(match[1])];
                        if (targetField && showFieldError(`nutrient:${targetField}`, f.message)) {
                            return;
                        }
                    }
                    if (!showFieldError(f.field, f.message)) {
                        unmapped.push(f.message);
                    }
                });
                showBanner(unmapped[0] || err.message);
            } else {
                showBanner(err.message, err.retryable ? submitManualFood : null);
            }
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Salvar no banco e voltar à receita';
        }
    }

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        submitManualFood();
    });
}

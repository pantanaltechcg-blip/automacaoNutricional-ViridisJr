import { requestJson } from '../api.js';
import { loadRecipeDraft, saveRecipeDraft } from '../draft.js';
import { buildManualFoodPayload } from '../payloads.js';

export async function init() {
    const form = document.getElementById('ingredient-edit-form');
    if (!form) {
        return;
    }

    const foodId = form.dataset.foodId;
    const recipeUrl = form.dataset.recipeUrl || '/receitas/nova';
    const feedbackEl = document.getElementById('ingredient-edit-feedback');
    const updateBtn = document.getElementById('update-ingredient-btn');
    const toggleBtn = document.getElementById('toggle-status-btn');
    const badgeEl = document.getElementById('ing-status-badge');
    const historyEl = document.getElementById('version-history-list');

    let currentIsActive = true;

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

    function renderStatus(isActive) {
        currentIsActive = Boolean(isActive);
        badgeEl.textContent = currentIsActive ? 'Ativo' : 'Inativo';
        badgeEl.className = 'px-2.5 py-1 rounded-full text-xs font-semibold ' + (
            currentIsActive ? 'bg-rn-surface-soft text-rn-primary' : 'bg-red-50 text-rn-danger'
        );
        toggleBtn.textContent = currentIsActive ? 'Inativar ingrediente' : 'Reativar ingrediente';
        toggleBtn.disabled = false;
    }

    function renderVersions(versions) {
        historyEl.replaceChildren();
        if (!Array.isArray(versions) || versions.length === 0) {
            const li = document.createElement('li');
            li.textContent = 'Nenhuma versão registrada.';
            historyEl.appendChild(li);
            return;
        }

        [...versions].reverse().forEach((v) => {
            const li = document.createElement('li');
            li.className = 'rounded-xl border border-rn-border p-2.5 flex flex-col gap-0.5';
            const head = document.createElement('strong');
            head.className = 'text-rn-ink';
            head.textContent = `v${v.version_number} — ${v.registered_on}`;
            const meta = document.createElement('span');
            meta.textContent = `${v.registered_by}${v.change_reason ? ` · ${v.change_reason}` : ''}`;
            li.append(head, meta);
            historyEl.appendChild(li);
        });
    }

    function populateForm(data) {
        const ver = data.current_version || {};
        document.getElementById('ing-name').value = ver.name ?? data.name ?? '';
        document.getElementById('ing-source').value = ver.source ?? data.source ?? '';
        document.getElementById('ing-source-code').value = ver.source_code ?? data.source_code ?? '';
        document.getElementById('ing-evidence-ref').value = ver.evidence_reference ?? '';
        document.getElementById('ing-evidence-source').value = ver.evidence_source ?? '';
        document.getElementById('ing-registered-by').value = ver.registered_by ?? '';
        document.getElementById('ing-registered-on').value = ver.registered_on ?? '';

        const byId = new Map();
        (ver.nutrients || []).forEach((n) => byId.set(Number(n.id), n));

        document.querySelectorAll('[data-nutrient-card][data-available="1"]').forEach((card) => {
            const field = card.dataset.nutrientCard;
            const nutrientId = Number(card.dataset.nutrientId);
            const saved = byId.get(nutrientId);
            const select = card.querySelector(`[data-nutrient-status="${CSS.escape(field)}"]`);
            const input = card.querySelector(`[data-nutrient-amount="${CSS.escape(field)}"]`);
            if (!select || !input) {
                return;
            }
            if (saved) {
                select.value = saved.status || '';
                const isExact = saved.status === 'EXACT';
                input.disabled = !isExact;
                input.value = isExact && saved.amount_value !== null ? String(saved.amount_value) : '';
                input.placeholder = isExact ? '0' : '—';
            }
        });

        renderStatus(data.is_active);
        renderVersions(data.versions);
    }

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

    async function loadManualFood() {
        const res = await requestJson(`/api/manual-foods/${encodeURIComponent(foodId)}`);
        if (!res.ok) {
            showBanner(res.error.message, 'error', res.error.retryable ? loadManualFood : null);
            return;
        }
        populateForm(res.data?.data || {});
    }

    toggleBtn.addEventListener('click', async () => {
        toggleBtn.disabled = true;
        const res = await requestJson(`/api/manual-foods/${encodeURIComponent(foodId)}/status`, {
            method: 'PATCH',
            body: { is_active: !currentIsActive },
        });
        if (!res.ok) {
            toggleBtn.disabled = false;
            showBanner(res.error.message);
            return;
        }
        renderStatus(res.data?.data?.is_active);
        showBanner('Status atualizado com sucesso.', 'success');
    });

    async function submitUpdate() {
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

        built.payload.change_reason = String(document.getElementById('ing-change-reason')?.value ?? '').trim();

        updateBtn.disabled = true;
        updateBtn.textContent = 'Salvando...';

        try {
            const res = await requestJson(`/api/manual-foods/${encodeURIComponent(foodId)}`, {
                method: 'PUT',
                body: built.payload,
            });

            if (res.ok) {
                const draft = loadRecipeDraft();
                draft.unsavedNotice = `Ingrediente "${built.payload.name}" atualizado (nova versão registrada).`;
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
                showBanner(err.message, 'error', err.retryable ? submitUpdate : null);
            }
        } finally {
            updateBtn.disabled = false;
            updateBtn.textContent = 'Salvar nova versão e voltar';
        }
    }

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        submitUpdate();
    });

    await loadManualFood();
}

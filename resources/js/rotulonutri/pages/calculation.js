import { requestJson } from '../api.js';

const QUALITATIVE_STATE_LABELS = {
    EXACT: 'Valor numérico',
    TRACE: 'Traço',
    NA: 'Não aplicável',
    ABSENT: 'Ausente',
    REEVALUATION: 'Em reavaliação',
};

export async function init() {
    const root = document.getElementById('calculation-app');
    if (!root) {
        return;
    }

    const calcId = root.dataset.calculationId;
    const metaEl = document.getElementById('calculation-meta');
    const bodyEl = document.getElementById('calc-nutrients-body');
    const feedbackEl = document.getElementById('calculation-feedback');

    async function load() {
        const res = await requestJson(`/api/nutrition-calculations/${encodeURIComponent(calcId)}`);
        if (!res.ok) {
            feedbackEl.className = 'rounded-xl border border-rn-danger/30 bg-red-50 text-rn-danger px-4 py-3 text-sm';
            feedbackEl.textContent = res.error.message;
            feedbackEl.classList.remove('hidden');
            return;
        }

        const data = res.data?.data;
        if (!data) {
            return;
        }

        metaEl.textContent = `Receita #${data.recipe_id} · Regras: ${data.rules_version} · Rendimento: ${data.yield?.value} ${data.yield?.unit}`;

        bodyEl.replaceChildren();
        (data.nutrients || []).forEach((item) => {
            const tr = document.createElement('tr');

            const nameTd = document.createElement('td');
            nameTd.className = 'py-2 pr-3 font-medium text-rn-ink';
            nameTd.textContent = item.nutrient?.name ?? '—';

            const unitTd = document.createElement('td');
            unitTd.className = 'py-2 pr-3 text-rn-muted';
            unitTd.textContent = item.nutrient?.unit ?? '—';

            const valTd = document.createElement('td');
            valTd.className = 'py-2 pr-3 tabular-nums text-rn-ink';
            const ratio = item.total?.value;
            valTd.textContent = ratio ? `${ratio.numerator}/${ratio.denominator}` : '—';

            const compTd = document.createElement('td');
            compTd.className = 'py-2 pr-3';
            compTd.textContent = item.total?.complete ? 'Completo' : 'Incompleto';

            const statesTd = document.createElement('td');
            statesTd.className = 'py-2 text-rn-muted';
            const states = (item.total?.qualitative_states || []).map(
                (state) => QUALITATIVE_STATE_LABELS[state] ?? state,
            );
            const missing = item.total?.has_missing_relation ? 'Relação ausente' : null;
            const list = missing ? [...states, missing] : states;
            statesTd.textContent = list.length > 0 ? list.join(', ') : '—';

            tr.append(nameTd, unitTd, valTd, compTd, statesTd);
            bodyEl.appendChild(tr);
        });
    }

    await load();
}

import { loadStageReport } from '../draft.js';
import { extractLabelReadiness, renderDeclaredTable } from './table-format.js';

const STAGE_LABELS = {
    RECIPE_SAVED: 'Receita salva',
    CALC_003: 'Cálculo nutricional base (Feature 003)',
    SERVING_004: 'Porção e %VD (Feature 004)',
    DECLARED_005: 'Valores declarados e arredondamento (Feature 005)',
};

const STATE_BADGES = {
    DONE: 'Concluída',
    FAILED: 'Falha',
    BLOCKED: 'Bloqueada',
    SKIPPED: 'Não executada',
};

export function init() {
    const root = document.getElementById('table-app');
    if (!root) {
        return;
    }

    const stagesList = document.getElementById('stages-status-list');
    const readinessBanner = document.getElementById('table-readiness-banner');
    const tableEl = document.querySelector('[data-nutrition-table]');
    const applyNotice = document.getElementById('apply-label-notice');
    const applyBtn = document.getElementById('apply-to-label-btn');

    const report = loadStageReport();

    if (report && Array.isArray(report.stages) && stagesList) {
        stagesList.replaceChildren();
        report.stages.forEach((s) => {
            const li = document.createElement('li');
            li.className = 'rounded-xl border border-rn-border bg-rn-surface px-4 py-2.5 text-xs flex flex-col gap-1';

            const top = document.createElement('div');
            top.className = 'flex items-center justify-between gap-2';

            const name = document.createElement('span');
            name.className = 'font-medium text-rn-ink';
            name.textContent = STAGE_LABELS[s.stage] || s.stage;

            const badge = document.createElement('span');
            badge.className = 'font-semibold ' + (s.state === 'DONE' ? 'text-rn-primary' : 'text-rn-danger');
            badge.textContent = STATE_BADGES[s.state] || s.state;

            top.append(name, badge);
            li.appendChild(top);

            if (s.cause) {
                const cause = document.createElement('p');
                cause.className = 'text-rn-muted';
                cause.textContent = s.cause;
                li.appendChild(cause);
            }

            stagesList.appendChild(li);
        });
    }

    if (report?.declaredCalculation) {
        renderDeclaredTable(tableEl, report.declaredCalculation);
        const readiness = extractLabelReadiness(report.declaredCalculation);
        if (!readiness.ready && readinessBanner) {
            readinessBanner.classList.remove('hidden');
            const title = document.createElement('p');
            title.className = 'font-semibold';
            title.textContent = 'Resultado não apto à rotulagem.';
            readinessBanner.replaceChildren(title);
            readiness.causes.forEach((c) => {
                const p = document.createElement('p');
                p.className = 'mt-1 text-rn-muted';
                p.textContent = c;
                readinessBanner.appendChild(p);
            });
        }
    } else if (readinessBanner) {
        readinessBanner.classList.remove('hidden');
        const p = document.createElement('p');
        p.className = 'font-semibold';
        p.textContent = 'Tabela nutricional não concluída — nenhuma execução válida da Feature 005 disponível.';
        const sub = document.createElement('p');
        sub.className = 'mt-1 text-rn-muted';
        sub.textContent = 'As células permanecem como "—" sem valores fictícios ou cálculos no cliente.';
        readinessBanner.replaceChildren(p, sub);
    }

    if (applyBtn && applyNotice) {
        applyBtn.addEventListener('click', () => {
            applyNotice.classList.remove('hidden');
        });
    }
}

/**
 * Formata célula declarada da Feature 005 sem arredondamento nem conversão.
 * null ou ausência permanece "—".
 *
 * @param {{ declared_value?: string|null, unit?: string|null, qualitative_states?: string[] }|null|undefined} entry
 * @returns {{ text: string, isUnavailable: boolean }}
 */
export function formatDeclaredCell(entry) {
    if (!entry || entry.declared_value === null || entry.declared_value === undefined) {
        return { text: '—', isUnavailable: true };
    }
    const unit = entry.unit ? ` ${entry.unit}` : '';
    return { text: `${entry.declared_value}${unit}`, isUnavailable: false };
}

/**
 * Extrai is_label_ready e label_readiness_causes exatamente como retornados pela Feature 005.
 * @param {any} declared
 * @returns {{ ready: boolean, causes: string[] }}
 */
export function extractLabelReadiness(declared) {
    if (!declared || typeof declared !== 'object') {
        return { ready: false, causes: ['Execução da Feature 005 não disponível.'] };
    }
    const ready = Boolean(declared.is_label_ready);
    const causes = Array.isArray(declared.label_readiness_causes)
        ? declared.label_readiness_causes.map((c) => String(c?.detail || c?.code || 'Bloqueio regulatório'))
        : [];
    return { ready, causes };
}

const CODE_TO_ROW = {
    'MAP-001': 'energy',
    'MAP-002': 'carbohydrates',
    'MAP-003': 'total_sugars',
    'MAP-004': 'added_sugars',
    'MAP-005': 'proteins',
    'MAP-006': 'total_fat',
    'MAP-007': 'saturated_fat',
    'MAP-008': 'trans_fat',
    'MAP-009': 'dietary_fiber',
    'MAP-010': 'sodium',
};

/**
 * Preenche o componente Blade [data-nutrition-table] a partir de uma execução 005 (ou mantém "—").
 * @param {HTMLElement} tableEl
 * @param {any} declared
 */
export function renderDeclaredTable(tableEl, declared) {
    if (!tableEl || !declared) {
        return;
    }

    const portionEl = tableEl.querySelector('[data-table-portion]');
    if (portionEl && declared.portion?.value && declared.portion?.unit) {
        portionEl.textContent = `${declared.portion.value} ${declared.portion.unit}`;
    }

    const baseCol = tableEl.querySelector('[data-table-base-col]');
    if (baseCol && declared.yield?.unit) {
        baseCol.textContent = declared.yield.unit === 'ml' ? '100 ml' : '100 g';
    }

    const components = Array.isArray(declared.components) ? declared.components : [];
    for (const comp of components) {
        const rowKey = CODE_TO_ROW[comp?.mapping_code] || comp?.row_key;
        if (!rowKey) {
            continue;
        }
        const row = tableEl.querySelector(`[data-nutrient-row="${CSS.escape(rowKey)}"]`);
        if (!row) {
            continue;
        }

        const baseCell = row.querySelector('[data-cell="base"]');
        const portionCell = row.querySelector('[data-cell="portion"]');
        const vdCell = row.querySelector('[data-cell="vd"]');

        if (baseCell) {
            const formatted = formatDeclaredCell(comp.reference_100 ?? null);
            baseCell.textContent = formatted.text;
        }
        if (portionCell) {
            const formatted = formatDeclaredCell(comp.portion ?? null);
            portionCell.textContent = formatted.text;
        }
        if (vdCell) {
            const vdVal = comp.daily_value_percentage?.declared_value ?? null;
            vdCell.textContent = vdVal === null || vdVal === undefined ? '—' : `${vdVal}%`;
        }
    }
}

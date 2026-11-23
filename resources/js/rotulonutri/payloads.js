import { isPositiveDecimal, normalizeDecimalInput } from './decimal.js';

/**
 * Monta payload para POST/PUT /api/recipes a partir do rascunho da Tela 1.
 * Porção, medida caseira e total informativo NUNCA são enviados aqui.
 *
 * @param {{
 *   name?: string,
 *   productType?: 'SOLID'|'LIQUID',
 *   yieldValue?: string,
 *   ingredients?: Array<{ foodId: number, quantity: string }>,
 * }} draft
 * @returns {{ ok: true, payload: object } | { ok: false, errors: Array<{ field: string, message: string }> }}
 */
export function buildRecipePayload(draft) {
    const errors = [];
    const name = String(draft?.name ?? '').trim();
    const yieldUnit = draft?.productType === 'LIQUID' ? 'ml' : 'g';
    const yieldValue = normalizeDecimalInput(draft?.yieldValue);

    if (name === '') {
        errors.push({ field: 'name', message: 'Informe o nome da receita.' });
    }

    if (!yieldValue || !isPositiveDecimal(yieldValue)) {
        errors.push({ field: 'yield_value', message: 'Informe um rendimento positivo maior que zero.' });
    }

    const list = Array.isArray(draft?.ingredients) ? draft.ingredients : [];
    if (list.length === 0) {
        errors.push({ field: 'ingredients', message: 'Adicione pelo menos um ingrediente à receita.' });
    }

    const ingredients = [];
    list.forEach((item, index) => {
        const qty = normalizeDecimalInput(item?.quantity);
        if (!qty || !isPositiveDecimal(qty)) {
            errors.push({
                field: `ingredients.${index}.quantity`,
                message: 'Informe uma quantidade positiva em gramas (maior que zero).',
            });
            return;
        }
        ingredients.push({
            food_id: Number(item.foodId),
            quantity: qty,
            unit: 'g',
            position: index + 1,
        });
    });

    if (errors.length > 0) {
        return { ok: false, errors };
    }

    return {
        ok: true,
        payload: {
            name,
            yield_value: yieldValue,
            yield_unit: yieldUnit,
            ingredients,
        },
    };
}

/**
 * Monta payload para POST /api/manual-foods (Feature 006).
 * Não envia category, não preenche obrigatórios silenciosamente e omite nutrientes sem estado.
 *
 * @param {{
 *   name?: string,
 *   source?: string,
 *   sourceCode?: string,
 *   evidenceReference?: string,
 *   evidenceSource?: string,
 *   registeredBy?: string,
 *   registeredOn?: string,
 *   nutrients?: Array<{ field: string, nutrientId: number, status: string, amountValue?: string|null }>,
 * }} form
 */
export function buildManualFoodPayload(form) {
    const nutrientsPayload = [];
    const nutrientIndexToField = [];

    const list = Array.isArray(form?.nutrients) ? form.nutrients : [];
    for (const item of list) {
        if (!item?.status || !item?.nutrientId) {
            continue;
        }
        let amountValue = null;
        if (item.status === 'EXACT') {
            const normalized = normalizeDecimalInput(item.amountValue);
            amountValue = normalized === null ? String(item.amountValue ?? '') : normalized;
        }
        nutrientsPayload.push({
            nutrient_id: Number(item.nutrientId),
            status: item.status,
            amount_value: amountValue,
        });
        nutrientIndexToField.push(item.field);
    }

    return {
        ok: true,
        nutrientIndexToField,
        payload: {
            name: String(form?.name ?? '').trim(),
            source: String(form?.source ?? '').trim(),
            source_code: String(form?.sourceCode ?? '').trim(),
            reference_unit: 'g',
            evidence_reference: String(form?.evidenceReference ?? '').trim(),
            evidence_source: String(form?.evidenceSource ?? '').trim(),
            registered_by: String(form?.registeredBy ?? '').trim(),
            registered_on: String(form?.registeredOn ?? '').trim(),
            nutrients: nutrientsPayload,
        },
    };
}

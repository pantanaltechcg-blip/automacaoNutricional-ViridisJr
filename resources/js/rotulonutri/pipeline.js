import { normalizeDecimalInput, isPositiveDecimal } from './decimal.js';
import { resolveRegulatoryReferences } from './regulatory-references.js';

/**
 * Executa a cadeia: Receita -> Feature 003 -> (Feature 004 -> Feature 005 se disponíveis).
 * Nunca inventa identificadores regulatórios nem converte resultados da 003 para a tabela.
 *
 * @param {{
 *   draft: { recipeId?: number|null, productType?: 'SOLID'|'LIQUID', portionValue?: string, householdMeasure?: string },
 *   payload: object,
 *   requestJson: Function,
 *   resolveRefs?: Function,
 * }} params
 */
export async function runNutritionPipeline({ draft, payload, requestJson, resolveRefs = resolveRegulatoryReferences }) {
    const isUpdate = Boolean(draft?.recipeId);
    const recipeUrl = isUpdate ? `/api/recipes/${draft.recipeId}` : '/api/recipes';
    const recipeMethod = isUpdate ? 'PUT' : 'POST';

    const recipeRes = await requestJson(recipeUrl, { method: recipeMethod, body: payload });
    if (!recipeRes.ok) {
        return {
            recipeSaved: false,
            recipeId: draft?.recipeId ?? null,
            error: recipeRes.error,
            report: null,
        };
    }

    const recipeId = Number(recipeRes.data?.data?.id ?? draft?.recipeId);
    const stages = [
        { stage: 'RECIPE_SAVED', state: 'DONE', cause: null, resourceId: recipeId },
    ];

    // Etapa 003
    const calc003Res = await requestJson(`/api/recipes/${recipeId}/nutrition-calculations`, {
        method: 'POST',
    });

    if (!calc003Res.ok) {
        stages.push({ stage: 'CALC_003', state: 'FAILED', cause: calc003Res.error.message });
        stages.push({ stage: 'SERVING_004', state: 'SKIPPED', cause: 'Depende da Feature 003.' });
        stages.push({ stage: 'DECLARED_005', state: 'SKIPPED', cause: 'Depende da Feature 004.' });
        return {
            recipeSaved: true,
            recipeId,
            report: { recipeId, stages, declaredCalculation: null },
        };
    }

    const calc003Id = Number(calc003Res.data?.data?.id);
    stages.push({ stage: 'CALC_003', state: 'DONE', cause: null, resourceId: calc003Id });

    // Etapa 004 — somente prossegue com referências regulatórias efetivamente disponibilizadas
    const refs = await resolveRefs();
    if (!refs || !refs.available) {
        const cause = refs?.cause || 'Etapa bloqueada: Feature 004 — referências regulatórias não disponibilizadas pelo sistema.';
        stages.push({ stage: 'SERVING_004', state: 'BLOCKED', cause });
        stages.push({ stage: 'DECLARED_005', state: 'BLOCKED', cause: 'Etapa bloqueada: Feature 005 — depende da execução da Feature 004.' });
        return {
            recipeSaved: true,
            recipeId,
            report: { recipeId, stages, declaredCalculation: null },
        };
    }

    const portionValue = normalizeDecimalInput(draft?.portionValue);
    if (!portionValue || !isPositiveDecimal(portionValue)) {
        stages.push({ stage: 'SERVING_004', state: 'BLOCKED', cause: 'Informe uma porção positiva para executar a Feature 004.' });
        stages.push({ stage: 'DECLARED_005', state: 'BLOCKED', cause: 'Etapa bloqueada: Feature 005 — depende da execução da Feature 004.' });
        return {
            recipeSaved: true,
            recipeId,
            report: { recipeId, stages, declaredCalculation: null },
        };
    }

    const portionUnit = draft?.productType === 'LIQUID' ? 'ml' : 'g';
    const household = String(draft?.householdMeasure ?? '').trim();

    const calc004Res = await requestJson(`/api/nutrition-calculations/${calc003Id}/serving-daily-value-calculations`, {
        method: 'POST',
        body: {
            portion: {
                value: portionValue,
                unit: portionUnit,
                household_measure: household === '' ? null : household,
            },
            portion_authorization_id: refs.portionAuthorizationId,
            regulatory_profile_id: refs.regulatoryProfileId,
        },
    });

    if (!calc004Res.ok) {
        stages.push({ stage: 'SERVING_004', state: 'FAILED', cause: calc004Res.error.message });
        stages.push({ stage: 'DECLARED_005', state: 'SKIPPED', cause: 'Depende da Feature 004.' });
        return {
            recipeSaved: true,
            recipeId,
            report: { recipeId, stages, declaredCalculation: null },
        };
    }

    const calc004Id = Number(calc004Res.data?.data?.id);
    stages.push({ stage: 'SERVING_004', state: 'DONE', cause: null, resourceId: calc004Id });

    // Etapa 005
    const calc005Res = await requestJson(`/api/serving-daily-value-calculations/${calc004Id}/declared-nutrition-calculations`, {
        method: 'POST',
        body: {
            regulatory_declaration_profile_id: refs.regulatoryDeclarationProfileId,
        },
    });

    if (!calc005Res.ok) {
        stages.push({ stage: 'DECLARED_005', state: 'FAILED', cause: calc005Res.error.message });
        return {
            recipeSaved: true,
            recipeId,
            report: { recipeId, stages, declaredCalculation: null },
        };
    }

    const declaredCalculation = calc005Res.data?.data ?? null;
    stages.push({
        stage: 'DECLARED_005',
        state: 'DONE',
        cause: null,
        resourceId: declaredCalculation?.id ?? null,
    });

    return {
        recipeSaved: true,
        recipeId,
        report: { recipeId, stages, declaredCalculation },
    };
}

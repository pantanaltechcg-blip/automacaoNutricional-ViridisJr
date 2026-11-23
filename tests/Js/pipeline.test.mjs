import test from 'node:test';
import assert from 'node:assert/strict';
import { runNutritionPipeline } from '../../resources/js/rotulonutri/pipeline.js';

const validPayload = {
    name: 'Bolo',
    yield_value: '500',
    yield_unit: 'g',
    ingredients: [{ food_id: 1, quantity: '200', unit: 'g', position: 1 }],
};

test('executa receita -> 003 e interrompe na 004 quando referências regulatórias estão indisponíveis', async () => {
    const calls = [];
    const fakeRequest = async (url, opts) => {
        calls.push({ url, method: opts.method, body: opts.body });
        if (url === '/api/recipes') {
            return { ok: true, status: 200, data: { data: { id: 42 } } };
        }
        if (url === '/api/recipes/42/nutrition-calculations') {
            return { ok: true, status: 201, data: { data: { id: 99 } } };
        }
        throw new Error(`Chamada inesperada: ${url}`);
    };

    const outcome = await runNutritionPipeline({
        draft: { recipeId: null, productType: 'SOLID', portionValue: '50', householdMeasure: '1 fatia' },
        payload: validPayload,
        requestJson: fakeRequest,
        resolveRefs: async () => ({ available: false, cause: 'Etapa bloqueada: Feature 004' }),
    });

    assert.equal(outcome.recipeSaved, true);
    assert.equal(outcome.recipeId, 42);
    assert.equal(calls.length, 2);
    assert.deepEqual(
        outcome.report.stages.map((s) => [s.stage, s.state]),
        [
            ['RECIPE_SAVED', 'DONE'],
            ['CALC_003', 'DONE'],
            ['SERVING_004', 'BLOCKED'],
            ['DECLARED_005', 'BLOCKED'],
        ],
    );
});

test('falha na receita marca 003 como SKIPPED e não chama endpoints seguintes', async () => {
    const fakeRequest = async () => ({
        ok: false,
        status: 422,
        error: { kind: 'validation', message: 'Inválido', retryable: false, fields: [] },
    });

    const outcome = await runNutritionPipeline({
        draft: { recipeId: null, productType: 'SOLID', portionValue: '', householdMeasure: '' },
        payload: validPayload,
        requestJson: fakeRequest,
    });

    assert.equal(outcome.recipeSaved, false);
});

test('com stub de referências disponíveis (apenas teste) envia porção à 004 e segue até 005', async () => {
    const calls = [];
    const fakeRequest = async (url, opts) => {
        calls.push({ url, body: opts.body });
        if (url === '/api/recipes') return { ok: true, status: 200, data: { data: { id: 10 } } };
        if (url === '/api/recipes/10/nutrition-calculations') return { ok: true, status: 201, data: { data: { id: 20 } } };
        if (url === '/api/nutrition-calculations/20/serving-daily-value-calculations') return { ok: true, status: 201, data: { data: { id: 30 } } };
        if (url === '/api/serving-daily-value-calculations/30/declared-nutrition-calculations') {
            return { ok: true, status: 201, data: { data: { id: 40, is_label_ready: false, label_readiness_causes: [] } } };
        }
        throw new Error(`Chamada inesperada: ${url}`);
    };

    const outcome = await runNutritionPipeline({
        draft: { recipeId: null, productType: 'LIQUID', portionValue: '200', householdMeasure: '1 copo' },
        payload: { ...validPayload, yield_unit: 'ml' },
        requestJson: fakeRequest,
        resolveRefs: async () => ({
            available: true,
            portionAuthorizationId: 7,
            regulatoryProfileId: 8,
            regulatoryDeclarationProfileId: 9,
        }),
    });

    assert.equal(outcome.recipeSaved, true);
    assert.equal(calls.length, 4);
    assert.deepEqual(calls[2].body, {
        portion: { value: '200', unit: 'ml', household_measure: '1 copo' },
        portion_authorization_id: 7,
        regulatory_profile_id: 8,
    });
    assert.equal(outcome.report.declaredCalculation.id, 40);
});

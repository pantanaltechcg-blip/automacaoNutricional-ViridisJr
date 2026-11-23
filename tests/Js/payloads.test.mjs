import test from 'node:test';
import assert from 'node:assert/strict';
import { buildRecipePayload, buildManualFoodPayload } from '../../resources/js/rotulonutri/payloads.js';

test('buildRecipePayload gera yield_unit correto e nunca envia porção, medida caseira ou total informativo', () => {
    const result = buildRecipePayload({
        name: '  Suco verde  ',
        productType: 'LIQUID',
        yieldValue: '500,5',
        portionValue: '200',
        householdMeasure: '1 copo',
        ingredients: [
            { foodId: 10, name: 'Couve', source: 'TACO', quantity: '50,5' },
            { foodId: 20, name: 'Maçã', source: 'TACO', quantity: '100' },
        ],
    });

    assert.equal(result.ok, true);
    assert.deepEqual(result.payload, {
        name: 'Suco verde',
        yield_value: '500.5',
        yield_unit: 'ml',
        ingredients: [
            { food_id: 10, quantity: '50.5', unit: 'g', position: 1 },
            { food_id: 20, quantity: '100', unit: 'g', position: 2 },
        ],
    });
    assert.equal('portion' in result.payload, false);
    assert.equal('household_measure' in result.payload, false);
    assert.equal('total_ingredients' in result.payload, false);
});

test('buildRecipePayload rejeita quantidade vazia ou <= 0 sem converter para zero', () => {
    const result = buildRecipePayload({
        name: 'Bolo',
        productType: 'SOLID',
        yieldValue: '400',
        ingredients: [
            { foodId: 1, name: 'Farinha', source: 'TACO', quantity: '' },
        ],
    });

    assert.equal(result.ok, false);
    assert.ok(result.errors.some((e) => e.field === 'ingredients.0.quantity'));
});

test('buildManualFoodPayload omite nutrientes sem estado, mantém null nos qualitativos e não envia category', () => {
    const result = buildManualFoodPayload({
        name: ' Leite em pó ',
        source: 'Fabricante X',
        sourceCode: 'FX-01',
        evidenceReference: 'Rótulo lote 10',
        evidenceSource: 'Embalagem',
        registeredBy: 'Maria',
        registeredOn: '2026-11-22',
        nutrients: [
            { field: 'energy_kcal', nutrientId: 2, status: 'EXACT', amountValue: '496,5' },
            { field: 'sodium', nutrientId: 15, status: 'TRACE', amountValue: '999' },
            { field: 'proteins', nutrientId: 4, status: '', amountValue: '' },
        ],
    });

    assert.equal(result.ok, true);
    assert.deepEqual(result.payload, {
        name: 'Leite em pó',
        source: 'Fabricante X',
        source_code: 'FX-01',
        reference_unit: 'g',
        evidence_reference: 'Rótulo lote 10',
        evidence_source: 'Embalagem',
        registered_by: 'Maria',
        registered_on: '2026-11-22',
        nutrients: [
            { nutrient_id: 2, status: 'EXACT', amount_value: '496.5' },
            { nutrient_id: 15, status: 'TRACE', amount_value: null },
        ],
    });
    assert.deepEqual(result.nutrientIndexToField, ['energy_kcal', 'sodium']);
    assert.equal('category' in result.payload, false);
});

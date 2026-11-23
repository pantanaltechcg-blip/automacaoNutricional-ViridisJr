import test from 'node:test';
import assert from 'node:assert/strict';
import {
    createEmptyDraft,
    loadRecipeDraft,
    saveRecipeDraft,
    loadStageReport,
    saveStageReport,
    DRAFT_STORAGE_KEY,
    STAGE_REPORT_KEY,
} from '../../resources/js/rotulonutri/draft.js';

function createMemoryStorage() {
    const store = new Map();
    return {
        getItem: (k) => (store.has(k) ? store.get(k) : null),
        setItem: (k, v) => store.set(k, String(v)),
        removeItem: (k) => store.delete(k),
    };
}

test('grava e lê recipeDraft preservando porção e medida caseira apenas localmente', () => {
    const storage = createMemoryStorage();
    const draft = createEmptyDraft();
    draft.name = 'Bolo de milho';
    draft.portionValue = '60';
    draft.householdMeasure = '1 fatia';

    saveRecipeDraft(draft, storage);
    assert.ok(storage.getItem(DRAFT_STORAGE_KEY));

    const loaded = loadRecipeDraft(storage);
    assert.equal(loaded.name, 'Bolo de milho');
    assert.equal(loaded.portionValue, '60');
    assert.equal(loaded.householdMeasure, '1 fatia');
});

test('grava e lê stageReport sem valores nutricionais', () => {
    const storage = createMemoryStorage();
    const report = {
        recipeId: 10,
        stages: [
            { stage: 'RECIPE_SAVED', state: 'DONE', cause: null, resourceId: 10 },
            { stage: 'CALC_003', state: 'DONE', cause: null, resourceId: 22 },
            { stage: 'SERVING_004', state: 'BLOCKED', cause: 'Referências indisponíveis' },
            { stage: 'DECLARED_005', state: 'BLOCKED', cause: 'Depende da Feature 004' },
        ],
    };

    saveStageReport(report, storage);
    assert.ok(storage.getItem(STAGE_REPORT_KEY));
    assert.deepEqual(loadStageReport(storage), report);
});

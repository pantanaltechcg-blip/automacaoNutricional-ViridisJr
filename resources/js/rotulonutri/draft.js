export const DRAFT_STORAGE_KEY = 'rotulonutri.recipeDraft';
export const STAGE_REPORT_KEY = 'rotulonutri.stageReport';

/**
 * @returns {{
 *   name: string,
 *   productType: 'SOLID'|'LIQUID',
 *   yieldValue: string,
 *   portionValue: string,
 *   householdMeasure: string,
 *   ingredients: Array<{ foodId: number, name: string, source: string, quantity: string }>,
 *   recipeId: number|null,
 *   unsavedNotice: string|null,
 * }}
 */
export function createEmptyDraft() {
    return {
        name: '',
        productType: 'SOLID',
        yieldValue: '',
        portionValue: '',
        householdMeasure: '',
        ingredients: [],
        recipeId: null,
        unsavedNotice: null,
    };
}

function defaultStorage() {
    return typeof window !== 'undefined' && window.sessionStorage ? window.sessionStorage : null;
}

/**
 * @param {Storage|null} [storage]
 */
export function loadRecipeDraft(storage = defaultStorage()) {
    if (!storage) {
        return createEmptyDraft();
    }
    try {
        const raw = storage.getItem(DRAFT_STORAGE_KEY);
        if (!raw) {
            return createEmptyDraft();
        }
        const parsed = JSON.parse(raw);
        return {
            ...createEmptyDraft(),
            ...parsed,
            ingredients: Array.isArray(parsed.ingredients) ? parsed.ingredients : [],
        };
    } catch {
        return createEmptyDraft();
    }
}

/**
 * @param {ReturnType<typeof createEmptyDraft>} draft
 * @param {Storage|null} [storage]
 */
export function saveRecipeDraft(draft, storage = defaultStorage()) {
    if (!storage) {
        return;
    }
    storage.setItem(DRAFT_STORAGE_KEY, JSON.stringify(draft));
}

/**
 * @param {Storage|null} [storage]
 */
export function loadStageReport(storage = defaultStorage()) {
    if (!storage) {
        return null;
    }
    try {
        const raw = storage.getItem(STAGE_REPORT_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

/**
 * @param {object} report
 * @param {Storage|null} [storage]
 */
export function saveStageReport(report, storage = defaultStorage()) {
    if (!storage) {
        return;
    }
    storage.setItem(STAGE_REPORT_KEY, JSON.stringify(report));
}

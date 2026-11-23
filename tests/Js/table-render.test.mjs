import test from 'node:test';
import assert from 'node:assert/strict';
import { formatDeclaredCell, extractLabelReadiness } from '../../resources/js/rotulonutri/pages/table-format.js';

test('formatDeclaredCell mantém valor exato sem arredondar e null vira travessão', () => {
    assert.deepEqual(formatDeclaredCell({ declared_value: '12.50', unit: 'g' }), {
        text: '12.50 g',
        isUnavailable: false,
    });
    assert.deepEqual(formatDeclaredCell({ declared_value: null, qualitative_states: ['TRACE'] }), {
        text: '—',
        isUnavailable: true,
    });
    assert.deepEqual(formatDeclaredCell(null), {
        text: '—',
        isUnavailable: true,
    });
});

test('extractLabelReadiness preserva is_label_ready e causas exatamente como retornadas', () => {
    const info = extractLabelReadiness({
        is_label_ready: false,
        label_readiness_causes: [
            { code: 'MANDATORY_COMPONENT_UNAVAILABLE', detail: 'MAP-004 indisponível.' },
        ],
    });

    assert.equal(info.ready, false);
    assert.deepEqual(info.causes, ['MAP-004 indisponível.']);
});

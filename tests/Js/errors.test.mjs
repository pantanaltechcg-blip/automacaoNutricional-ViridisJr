import test from 'node:test';
import assert from 'node:assert/strict';
import { normalizeApiError } from '../../resources/js/rotulonutri/errors.js';

test('normaliza erros 422 com campos simples e aninhados', () => {
    const normalized = normalizeApiError({
        status: 422,
        body: {
            message: 'Dados inválidos.',
            errors: {
                name: ['O campo nome é obrigatório.'],
                'ingredients.2.quantity': ['A quantidade deve ser positiva.'],
                'nutrients.1.amount_value': ['Formato inválido.'],
            },
        },
    });

    assert.equal(normalized.kind, 'validation');
    assert.equal(normalized.retryable, false);
    assert.deepEqual(normalized.fields, [
        { field: 'name', message: 'O campo nome é obrigatório.' },
        { field: 'ingredients.2.quantity', message: 'A quantidade deve ser positiva.' },
        { field: 'nutrients.1.amount_value', message: 'Formato inválido.' },
    ]);
});

test('normaliza erro 5xx e falha de rede como retryable', () => {
    const serverErr = normalizeApiError({ status: 500, body: null });
    assert.equal(serverErr.kind, 'server');
    assert.equal(serverErr.retryable, true);

    const netErr = normalizeApiError({ status: 0, networkError: true });
    assert.equal(netErr.kind, 'network');
    assert.equal(netErr.retryable, true);
});

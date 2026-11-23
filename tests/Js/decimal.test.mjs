import test from 'node:test';
import assert from 'node:assert/strict';
import {
    addDecimalStrings,
    incrementByOne,
    decrementByOnePositive,
    isPositiveDecimal,
    normalizeDecimalInput,
} from '../../resources/js/rotulonutri/decimal.js';

test('normaliza vírgula decimal para ponto e remove espaços', () => {
    assert.equal(normalizeDecimalInput(' 1,5 '), '1.5');
    assert.equal(normalizeDecimalInput('100.25'), '100.25');
    assert.equal(normalizeDecimalInput(''), '');
});

test('rejeita notação científica e valores não numéricos', () => {
    assert.equal(normalizeDecimalInput('1e3'), null);
    assert.equal(normalizeDecimalInput('1E-2'), null);
    assert.equal(normalizeDecimalInput('abc'), null);
    assert.equal(normalizeDecimalInput('-5'), null);
});

test('incrementa 1 g com exatidão sem ponto flutuante', () => {
    assert.equal(incrementByOne('0.1'), '1.1');
    assert.equal(incrementByOne(''), '1');
    assert.equal(incrementByOne('1,25'), '2.25');
});

test('decrementa 1 g apenas quando resultado permanece > 0', () => {
    assert.equal(decrementByOnePositive('2'), '1');
    assert.equal(decrementByOnePositive('1.5'), '0.5');
    assert.equal(decrementByOnePositive('1'), null);
    assert.equal(decrementByOnePositive('0.5'), null);
    assert.equal(decrementByOnePositive(''), null);
});

test('soma decimal exata sem erro de ponto flutuante', () => {
    assert.equal(addDecimalStrings(['0.1', '0.2']), '0.3');
    assert.equal(addDecimalStrings(['100', '25,5', '0.5']), '126');
    assert.equal(addDecimalStrings([]), '0');
});

test('valida decimal positivo estritamente > 0', () => {
    assert.equal(isPositiveDecimal('0'), false);
    assert.equal(isPositiveDecimal('0.0'), false);
    assert.equal(isPositiveDecimal('0.01'), true);
    assert.equal(isPositiveDecimal('1e2'), false);
});

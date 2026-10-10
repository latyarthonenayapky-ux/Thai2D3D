const assert = require('node:assert/strict');
const test = require('node:test');

const { normalizeKeyInput, normalizeTypedText } = require('../../public/js/sale-entry-keyboard.js');

test('typed sale codes are uppercased and asterisk becomes reverse', () => {
    assert.equal(normalizeTypedText('x12*1000'), 'X12R1000');
});

test('normal digits and ordinary uppercase entry remain unchanged', () => {
    assert.equal(normalizeTypedText('121000'), '121000');
    assert.equal(normalizeTypedText('A1000'), 'A1000');
});

test('numpad decimal key inserts three zeroes', () => {
    assert.equal(normalizeKeyInput('.', 'NumpadDecimal'), '000');
});

test('period from another key is not treated as the numpad decimal key', () => {
    assert.equal(normalizeKeyInput('.', 'Period'), '.');
});

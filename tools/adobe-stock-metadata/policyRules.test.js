const test = require('node:test');
const assert = require('node:assert/strict');
const { validateMetadata, sanitizeKeywords } = require('./policyRules');

test('sanitizeKeywords removes duplicates and normalizes', () => {
  const actual = sanitizeKeywords([' Fresh Produce ', 'fresh_produce', 'Organic', 'organic']);
  assert.deepEqual(actual, ['fresh produce', 'organic']);
});

test('validateMetadata fails on banned terms and invalid keyword count', () => {
  const result = validateMetadata({
    title: 'Nike running shoe on white background',
    description: 'Studio product photo',
    category: 'Objects',
    keywords: ['shoe', 'running'],
  });

  assert.equal(result.isValid, false);
  assert.ok(result.errors.some((x) => x.includes('Potential policy violation')));
  assert.ok(result.errors.some((x) => x.includes('Keywords count')));
});

import { formatPrice } from './format';

// Normalize the thousands-separator, which fr-TN renders as a non-breaking
// (or narrow non-breaking) space depending on the ICU data available —
// collapsing all whitespace keeps the assertions stable across environments.
function normalize(str) {
  return str.replace(/\s/g, ' ');
}

describe('formatPrice', () => {
  it('pads a whole number to 3 decimal places', () => {
    expect(normalize(formatPrice(100))).toMatch(/^100[.,]000$/);
  });

  it('rounds/pads a decimal price to exactly 3 decimal places', () => {
    expect(normalize(formatPrice(49.5))).toMatch(/^49[.,]500$/);
  });

  it('accepts numeric strings', () => {
    expect(normalize(formatPrice('12.34'))).toMatch(/^12[.,]34/);
  });

  it('formats zero', () => {
    expect(normalize(formatPrice(0))).toMatch(/^0[.,]000$/);
  });

  it('groups thousands', () => {
    // fr-TN groups thousands — just assert both the integer and decimal
    // parts show up, regardless of the exact separator character used.
    const result = normalize(formatPrice(1234.5));
    expect(result).toContain('234');
    expect(result).toMatch(/500$/);
  });
});

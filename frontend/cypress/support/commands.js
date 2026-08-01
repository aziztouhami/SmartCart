Cypress.Commands.add('getByTestId', (testId, options) => {
  return cy.get(`[data-testid="${testId}"]`, options);
});

// i18next-browser-languagedetector checks localStorage before the browser's
// own language — pinning it to English here keeps every spec's text
// assertions deterministic regardless of the machine/CI runner's locale.
Cypress.Commands.add('visitEn', (url, options = {}) => {
  return cy.visit(url, {
    ...options,
    onBeforeLoad(win) {
      win.localStorage.setItem('language', 'en');
      options.onBeforeLoad?.(win);
    },
  });
});

describe('Login', () => {
  beforeEach(() => {
    cy.visitEn('/login');
  });

  it('shows a client-side error when submitting an empty form', () => {
    cy.getByTestId('login-submit').click();
    cy.getByTestId('login-error').should('contain.text', 'Please fill in all fields.');
  });

  it('shows a backend error for invalid credentials', () => {
    cy.getByTestId('login-email').type('nobody-e2e@example.com');
    cy.getByTestId('login-password').type('wrong-password');
    cy.getByTestId('login-submit').click();

    // Whatever the exact copy, the backend rejects bogus credentials and the
    // form must surface an error instead of silently succeeding/navigating.
    cy.getByTestId('login-error').should('be.visible');
    cy.url().should('include', '/login');
  });

  it('toggles password visibility', () => {
    cy.getByTestId('login-password').type('secret123').should('have.attr', 'type', 'password');
    cy.get('.field-group__toggle').first().click();
    cy.getByTestId('login-password').should('have.attr', 'type', 'text');
  });
});

describe('Register', () => {
  beforeEach(() => {
    cy.visitEn('/register');
  });

  it('shows a client-side error when required fields are missing', () => {
    cy.getByTestId('register-submit').click();
    cy.contains(/first and last name are required/i).should('be.visible');
  });

  it('shows a client-side error on password mismatch', () => {
    const email = `e2e-${Date.now()}@example.com`;
    cy.getByTestId('register-firstName').type('Test');
    cy.getByTestId('register-lastName').type('User');
    cy.getByTestId('register-email').type(email);
    cy.getByTestId('register-password').type('Password123!');
    cy.getByTestId('register-confirmPassword').type('Different123!');
    cy.getByTestId('register-submit').click();

    cy.getByTestId('register-error').should('contain.text', 'Passwords do not match.');
  });

  it('creates a new account and redirects to login with a confirmation prompt', () => {
    const email = `e2e-${Date.now()}@example.com`;

    cy.getByTestId('register-firstName').type('Test');
    cy.getByTestId('register-lastName').type('User');
    cy.getByTestId('register-email').type(email);
    cy.getByTestId('register-password').type('Password123!');
    cy.getByTestId('register-confirmPassword').type('Password123!');
    cy.getByTestId('register-submit').click();

    cy.url().should('include', '/login');
    cy.contains(new RegExp(`Check your inbox at ${email}`, 'i')).should('be.visible');
  });
});

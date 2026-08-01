describe('Navigation', () => {
  beforeEach(() => {
    cy.visitEn('/');
  });

  it('loads the home page with the navbar', () => {
    cy.getByTestId('nav-logo').should('be.visible').and('contain.text', 'SmartCart');
    cy.getByTestId('nav-search-input').should('be.visible');
  });

  it('shows Sign in / Create account when logged out', () => {
    cy.getByTestId('nav-signin-button').should('be.visible');
    cy.getByTestId('nav-register-button').should('be.visible');
  });

  it('navigates to the login page', () => {
    cy.getByTestId('nav-signin-button').click();
    cy.url().should('include', '/login');
    cy.getByTestId('login-email').should('be.visible');
    cy.getByTestId('login-password').should('be.visible');
  });

  it('navigates to the register page', () => {
    cy.getByTestId('nav-register-button').click();
    cy.url().should('include', '/register');
    cy.getByTestId('register-email').should('be.visible');
  });

  it('navigates to the cart page and back to home', () => {
    cy.getByTestId('nav-cart-button').click();
    cy.url().should('include', '/cart');

    cy.getByTestId('nav-logo').click();
    cy.url().should('eq', Cypress.config('baseUrl') + '/');
  });

  it('navigates from the login page to the register page and back', () => {
    cy.visitEn('/login');
    cy.contains('a', /create account|créer un compte/i).click();
    cy.url().should('include', '/register');

    cy.contains('a', /sign in|se connecter/i).click();
    cy.url().should('include', '/login');
  });

  it('redirects an unknown route back to home', () => {
    cy.visitEn('/this-route-does-not-exist');
    cy.url().should('eq', Cypress.config('baseUrl') + '/');
  });
});

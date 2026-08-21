/* eslint-disable no-undef */
describe('administrator core workflow', () => {
  it('offers an accessible login form without embedded credentials', () => {
    cy.visit('/admin/login');

    cy.get('main').should('exist');
    cy.get('h1').should('be.visible');
    cy.get('label[for="username"]').should('be.visible');
    cy.get('input[name="username"]').should('have.attr', 'autocomplete', 'username');
    cy.get('label[for="password"]').should('be.visible');
    cy.get('input[name="password"]').should('have.attr', 'autocomplete', 'current-password');
  });

  it('opens the administrator dashboard and supports its mobile controls', () => {
    cy.loginWithUsername();
    cy.viewport(390, 844);
    cy.visit('/admin');

    cy.get('#admin-content').should('exist');
    cy.get('main').should('exist');
    cy.get('h1').contains('Overview').should('be.visible');
    cy.get('#menuToggle').should('be.visible').and('have.attr', 'aria-expanded');
    cy.get('details.igf-mobile-search').should('be.visible').find('summary').click();
    cy.get('#admin-mobile-search').should('be.visible').and('have.attr', 'type', 'search');
    cy.document().then((document) => {
      expect(document.documentElement.scrollWidth).to.be.at.most(document.documentElement.clientWidth + 1);
    });
  });
});

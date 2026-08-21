// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************
//
//
// -- This is a parent command --
// Cypress.Commands.add('login', (email, password) => { ... })
//
//
// -- This is a child command --
// Cypress.Commands.add('drag', { prevSubject: 'element'}, (subject, options) => { ... })
//
//
// -- This is a dual command --
// Cypress.Commands.add('dismiss', { prevSubject: 'optional'}, (subject, options) => { ... })
//
//
// -- This will overwrite an existing command --
// Cypress.Commands.overwrite('visit', (originalFn, url, options) => { ... })

Cypress.Commands.add('loginWithUsername', () => {
    const username = Cypress.env('ADMIN_USERNAME');
    const password = Cypress.env('ADMIN_PASSWORD');

    expect(username, 'CYPRESS_ADMIN_USERNAME').to.be.a('string').and.not.be.empty;
    expect(password, 'CYPRESS_ADMIN_PASSWORD').to.be.a('string').and.have.length.at.least(12);

    cy.session(['admin', username], () => {
        cy.visit('/admin/login');
        cy.get('input[name="username"]').type(username);
        cy.get('input[name="password"]').type(password, { log: false });
        cy.get('button[type="submit"]:visible').first().click();
        cy.location('pathname').should('eq', '/admin');
    });
});

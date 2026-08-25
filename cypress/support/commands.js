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
    cy.env(['ADMIN_USERNAME', 'ADMIN_PASSWORD'], { log: false }).then(({ ADMIN_USERNAME: username, ADMIN_PASSWORD: password }) => {
        if (typeof username !== 'string' || username.trim() === '') {
            throw new Error('The Cypress administrator username is missing.');
        }

        if (typeof password !== 'string' || password.length < 12) {
            throw new Error('The Cypress administrator password is missing or too short.');
        }

        cy.session(['admin', username], () => {
            cy.visit('/admin/login');
            cy.get('input[name="username"]').type(username);
            cy.get('input[name="password"]').type(password, { log: false });
            cy.get('button[type="submit"]:visible').first().click();
            cy.location('pathname').should('eq', '/admin');
        }, {
            cacheAcrossSpecs: true,
            validate () {
                cy.request({
                    url: '/admin',
                    followRedirect: false,
                    failOnStatusCode: false,
                    log: false
                }).its('status').should('eq', 200);
            }
        });
    });
});

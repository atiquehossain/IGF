/* eslint-disable no-undef */
describe('Editor draft', () => {
  const name = `Cypress editor draft ${Date.now().toString(36)}`;
  const updatedName = `${name} updated`;

  beforeEach(() => {
    cy.loginWithUsername();
    cy.visit('/admin/edito-draft');
  });

  it('creates a new editor draft', () => {
    cy.get('[data-e2e="edito-draft-name"]').type(name);
    cy.get('[data-e2e="edito-draft-description"]').type('Created by the isolated Cypress test suite.');
    cy.get('button[name="save"]').click();
    cy.location('pathname').should('eq', '/admin/edito-draft');
    cy.contains('#editorDraft_table tbody tr', name).should('be.visible');
  });

  it('updates the editor draft created by the suite', () => {
    cy.contains('#editorDraft_table tbody tr', name).find('button.edit').click();
    cy.get('[data-e2e="edito-draft-name-edit"]').should('have.value', name).clear().type(updatedName);
    cy.get('[data-e2e="edito-draft-description-edit"]').clear().type('Updated by the isolated Cypress test suite.');
    cy.get('button[name="update"]').click();
    cy.location('pathname').should('eq', '/admin/edito-draft');
    cy.contains('#editorDraft_table tbody tr', updatedName).should('be.visible');
  });
});

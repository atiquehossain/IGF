/* eslint-disable no-undef */
describe('Edito-draft', () => {
  // cy.refreshDatabase();
  before(() => {
    // cy.seed();
  });

  beforeEach(() => {
    cy.loginWithUsername();
    cy.visit('/admin/edito-draft');
  });

  it('create new edito-draft by save', () => {
    cy.visit('/admin/edito-draft');

    cy.get('[data-e2e=edito-draft-name]').type('edito-draft');
    cy.get('[data-e2e=edito-draft-description]').type('edito-draft description');

    cy.wait(1000);
    cy.get('button[name="save"]').click();
    cy.wait(1000);
  });

  it('update edito-draft', () => {
    cy.get('#editorDraft_table tbody tr a.edit').first().click();

    cy.get('[data-e2e=edito-draft-name-edit]').type('edito-draft update');
    cy.get('[data-e2e=edito-draft-description-edit]').type('edito-draft description update');

    cy.wait(1000);
    cy.get('button[name="update"]').click();
    cy.wait(1000);
  });
});

/* eslint-disable no-undef */

const uniqueToken = () => Date.now().toString(36) + '-' + Cypress._.random(1000, 9999);

const teamGroupCard = (name) => cy.contains('.border.rounded.p-3.h-100', name);
const donationCauseRow = (name) => cy.contains('#donation_type_table tbody tr', name);

describe('dynamic administrator content workflows', () => {
  beforeEach(() => {
    cy.loginWithUsername();
  });

  it('creates, edits, changes visibility, and deletes an empty team group', () => {
    const token = uniqueToken();
    const originalName = 'QA Team Group ' + token;
    const updatedName = 'QA Operations Group ' + token;

    cy.visit('/admin/member');
    cy.get('#new-team-group-name').type(originalName);
    cy.get('#new-team-group-slug').type('qa-team-' + token);
    cy.get('#new-team-group-order').clear().type('975');
    cy.get('#new-team-group-description').type('Created by the isolated administrator workflow test.');
    cy.contains('button[type="submit"]', 'Create group').click();

    cy.location('pathname').should('eq', '/admin/member');
    teamGroupCard(originalName).should('exist').within(() => {
      cy.get('[id^="team-group-name-"]').clear().type(updatedName);
      cy.get('[id^="team-group-slug-"]').clear().type('qa-operations-' + token);
      cy.get('[id^="team-group-order-"]').clear().type('976');
      cy.get('[id^="team-group-description-"]').clear().type('Updated through the administrator team-group editor.');
      cy.contains('button[type="submit"]', 'Save group').click();
    });

    cy.location('pathname').should('eq', '/admin/member');
    teamGroupCard(updatedName).should('contain.text', 'Updated through the administrator team-group editor.');

    teamGroupCard(updatedName).find('button').contains(/Hide group|Show group/).invoke('text').then((initialLabel) => {
      teamGroupCard(updatedName).find('button').contains(/Hide group|Show group/).click();
      cy.location('pathname').should('eq', '/admin/member');
      teamGroupCard(updatedName).find('button').contains(/Hide group|Show group/).invoke('text').should((nextLabel) => {
        expect(nextLabel.trim()).not.to.eq(initialLabel.trim());
      });
    });

    cy.on('window:confirm', () => true);
    teamGroupCard(updatedName).contains('button', 'Delete group').click();
    cy.location('pathname').should('eq', '/admin/member');
    cy.contains('.border.rounded.p-3.h-100', updatedName).should('not.exist');
  });

  it('creates, edits, publishes, and deletes a donation cause', () => {
    const token = uniqueToken();
    const originalName = 'QA Community Fund ' + token;
    const updatedName = 'QA Resilience Fund ' + token;

    cy.visit('/admin/donation-type');
    cy.get('#name').type(originalName);
    cy.get('#description').type('Visitor-ready support for urgent community priorities and accountable local response.');
    cy.get('#new_destination_type').select('restricted_fund');
    cy.get('#new_destination_name').type('QA Community Restricted Fund');
    cy.get('#new_display_order').clear().type('5');
    cy.get('#new_icon_key').select('hand-heart');
    cy.contains('#new_donation_type button[type="submit"]', 'Create donation cause').click();

    cy.location('pathname').should('eq', '/admin/donation-type');
    donationCauseRow(originalName).should('exist');

    cy.intercept('GET', '/admin/donation-type/*/edit').as('loadDonationCause');
    donationCauseRow(originalName).find('.edit').click();
    cy.wait('@loadDonationCause').its('response.statusCode').should('eq', 200);
    cy.get('#donationTypeModal').should('be.visible');
    cy.get('#e_name').clear().type(updatedName);
    cy.get('#e_description').clear().type('Updated visitor-ready support for resilient families and transparent community response.');
    cy.get('#e_destination_type').select('restricted_fund');
    cy.get('#e_destination_name').clear().type('QA Resilience Restricted Fund');
    cy.get('#e_display_order').clear().type('6');
    cy.contains('#donationTypeModal button[type="submit"]', 'Save donation cause').click();

    cy.location('pathname').should('eq', '/admin/donation-type');
    donationCauseRow(updatedName).should('contain.text', 'QA Resilience Restricted Fund');

    cy.intercept('PUT', '/admin/donation-type/*').as('publishDonationCause');
    donationCauseRow(updatedName).find('.status').should('have.attr', 'aria-pressed', 'false').click();
    cy.wait('@publishDonationCause').its('response.statusCode').should('eq', 200);
    cy.location('pathname').should('eq', '/admin/donation-type');
    donationCauseRow(updatedName).find('.status').should('have.attr', 'aria-pressed', 'true');

    cy.intercept('DELETE', '/admin/donation-type/*').as('deleteDonationCause');
    cy.on('window:confirm', () => true);
    donationCauseRow(updatedName).find('.trash').click();
    cy.wait('@deleteDonationCause').its('response.statusCode').should('eq', 200);
    cy.contains('#donation_type_table tbody tr', updatedName).should('not.exist');
  });

  it('creates an award draft, edits and publishes it, then moves it to trash', () => {
    const token = uniqueToken();
    const originalName = 'QA Service Award ' + token;
    const updatedName = 'QA Community Service Award ' + token;

    cy.visit('/admin/page/create');
    cy.get('[data-e2e="page-language"]').select('en');
    cy.get('[data-e2e="page-name"]').type(originalName);
    cy.get('[data-e2e="page-subtitle"]').type('Recognition created through the isolated awards workflow test.');
    cy.get('#draft-category').select('Awards & Recognition');
    cy.get('[data-e2e="create-page-draft"]').click();

    cy.location('pathname').should('match', /^\/admin\/page-builder\/[0-9a-f-]+$/i);
    cy.get('#simple-editor').should('exist');
    cy.contains('summary', 'Page title and publishing').click();
    cy.get('#simple-page-name').clear().type(updatedName);
    cy.get('#simple-page-status').select('published');

    cy.intercept('PUT', '/admin/page-builder/*/simple-save').as('saveAward');
    cy.get('[data-save-changes]').should('not.be.disabled').click();
    cy.wait('@saveAward').its('response.statusCode').should('eq', 200);
    cy.get('#simple-page-heading').should('have.text', updatedName);
    cy.get('#simple-page-status').should('have.value', 'published');
    cy.get('#simple-save-state').should('contain.text', 'All changes saved');

    cy.visit('/admin/page?search=' + encodeURIComponent(updatedName));
    cy.contains('article.hub-row', updatedName).as('awardRow');
    cy.get('@awardRow').should('contain.text', 'Awards & Recognition');
    cy.get('@awardRow').find('.hub-badge').should('contain.text', 'published');

    cy.intercept('DELETE', '/admin/page/*').as('trashAward');
    cy.on('window:confirm', () => true);
    cy.get('@awardRow').find('button.trash').click();
    cy.wait('@trashAward').its('response.statusCode').should('eq', 200);
    cy.contains('article.hub-row', updatedName).should('not.exist');
  });
});

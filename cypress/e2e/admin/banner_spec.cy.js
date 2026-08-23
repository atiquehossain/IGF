/* eslint-disable no-undef */
describe('Banner', () => {
  const fixture = 'cypress/fixtures/banner.jpg';
  const runId = Date.now().toString(36);

  function fillVisibleLanguages(name, type = 'banner-page') {
    cy.get('#pills-tab li').each((element) => {
      const lang = element.attr('data-id');
      cy.get(`#${lang}-tab`).click();
      cy.get(`[data-e2e=banner-headline-${lang}]`).clear().type(`${name} ${lang}`);
      cy.get(`[data-e2e=banner-type-${lang}]`).select(type);
      cy.get(`[data-e2e=banner-description-${lang}]`).clear().type(`${name} description ${lang}`);
      cy.get(`[data-e2e=banner-image-${lang}]`).selectFile(fixture, { force: true });
    });
  }

  beforeEach(() => {
    cy.loginWithUsername();
    cy.visit('/admin/banner');
  });

  it('creates a new banner and returns to the list', () => {
    const name = `Cypress banner ${runId}`;
    cy.visit('/admin/banner/create');
    fillVisibleLanguages(name);
    cy.get('button[name="save"]').click();
    cy.location('pathname').should('eq', '/admin/banner');
    cy.contains('#banner_table tbody tr', name).should('be.visible');
  });

  it('creates a banner, continues editing, and saves the update', () => {
    const name = `Cypress banner continue ${runId}`;
    const updatedName = `${name} updated`;
    cy.visit('/admin/banner/create');
    fillVisibleLanguages(name, 'banner-home');
    cy.get('button[name="save_and_update"]').click();
    cy.location('pathname').should('match', /^\/admin\/banner\/.+\/edit$/);

    fillVisibleLanguages(updatedName, 'banner-home');
    cy.get('button[name="save"]').click();
    cy.location('pathname').should('eq', '/admin/banner');
    cy.contains('#banner_table tbody tr', updatedName).should('be.visible');
  });

  it('opens an existing banner for editing', () => {
    cy.get('#banner_table tbody tr button.edit').first().click();
    cy.location('pathname').should('match', /^\/admin\/banner\/.+\/edit$/);
    cy.get('[data-e2e="banner-headline-en"]').should('be.visible');
    cy.get('#go-back').click();
    cy.location('pathname').should('eq', '/admin/banner');
  });
});

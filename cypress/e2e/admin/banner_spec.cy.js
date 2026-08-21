/* eslint-disable no-undef */
describe('Banner', () => {
  // cy.refreshDatabase();
  before(() => {
    // cy.seed();
  });

  beforeEach(() => {
    cy.loginWithUsername();
    cy.visit('/admin/banner');
  });

  it('create new banner by save', () => {
    cy.visit('/admin/banner/create');
    const path = 'cypress/fixtures/banner.jpg';
    if (Cypress.env('APP_LOCALIZATION')) {
      cy.get('#pills-tab li').each((element) => {
        const lang = element.attr('data-id');
        cy.get(`#${lang}-tab`).click();
        cy.get(`[data-e2e=banner-name-${lang}]`).type(`banner ${lang}`);
        cy.get(`[data-e2e=banner-type-${lang}]`).select('banner-page');
        cy.get(`[data-e2e=banner-description-${lang}]`).type(`banner description ${lang}`);
        cy.wait(500);
        cy.get(`[data-e2e=banner-image-${lang}]`).selectFile(path, { force: true });
      });
    } else {
      const lang = 'en';
      cy.get(`[data-e2e=banner-name-${lang}]`).type(`banner ${lang}`);
      cy.get(`[data-e2e=banner-type-${lang}]`).select('banner-page');
      cy.get(`[data-e2e=banner-description-${lang}]`).type(`banner description ${lang}`);
      cy.wait(500);
      cy.get(`[data-e2e=banner-image-${lang}]`).selectFile(path, { force: true });
    }

    cy.wait(1000);
    cy.get('button[name="save"]').click();
    cy.wait(1000);
  });

  it('create new banner by save and update', () => {
    cy.visit('/admin/banner/create');
    const path = 'cypress/fixtures/banner.jpg';
    if (Cypress.env('APP_LOCALIZATION')) {
      cy.get('#pills-tab li').each((element) => {
        const lang = element.attr('data-id');
        cy.get(`#${lang}-tab`).click();
        cy.get(`[data-e2e=banner-name-${lang}]`).type(`banner update ${lang}`);
        cy.get(`[data-e2e=banner-type-${lang}]`).type('banner-home');
        cy.get(`[data-e2e=banner-description-${lang}]`).type(`banner update description ${lang}`);
        cy.wait(500);
        cy.get(`[data-e2e=banner-image-${lang}]`).selectFile(path, { force: true });
      });

      cy.wait(1000);
      cy.get('button[name="save_and_update"]').click();
      cy.wait(1000);

      cy.get('#pills-tab li').each((element) => {
        const lang = element.attr('data-id');
        cy.get(`#${lang}-tab`).click();
        cy.get(`[data-e2e=banner-name-${lang}]`).type(`banner update 2 ${lang}`);
        cy.get(`[data-e2e=banner-type-${lang}]`).type('banner-home 2');
        cy.get(`[data-e2e=banner-description-${lang}]`).type(`banner update description 2 ${lang}`);
        cy.wait(500);
        cy.get(`[data-e2e=banner-image-${lang}]`).selectFile(path, { force: true });
      });
    } else {
      const lang = 'en';

      cy.get(`[data-e2e=banner-name-${lang}]`).type(`banner update ${lang}`);
      cy.get(`[data-e2e=banner-type-${lang}]`).type('banner-home');
      cy.get(`[data-e2e=banner-description-${lang}]`).type(`banner update description ${lang}`);
      cy.wait(500);
      cy.get(`[data-e2e=banner-image-${lang}]`).selectFile(path, { force: true });

      cy.wait(1000);
      cy.get('button[name="save_and_update"]').click();
      cy.wait(1000);

      cy.get(`[data-e2e=banner-name-${lang}]`).type(`banner update ${lang}`);
      cy.get(`[data-e2e=banner-type-${lang}]`).type('banner-home');
      cy.get(`[data-e2e=banner-description-${lang}]`).type(`banner update description ${lang}`);
      cy.wait(500);
      cy.get(`[data-e2e=banner-image-${lang}]`).selectFile(path, { force: true });
    }

    cy.wait(1000);
    cy.get('button[name="save"]').click();
    cy.wait(1000);
  });

  it('update banner by save', () => {
    cy.get('#banner_table tbody tr a.edit').first().click();
    if (Cypress.env('APP_LOCALIZATION')) {
      cy.get('#pills-tab li').each((element) => {
        const lang = element.attr('data-id');
        cy.wait(500);
        cy.get(`#${lang}-tab`).click();
      });
    }

    cy.wait(1000);
    cy.get('button[name="save_and_update"]').click();
    cy.wait(1000);
    cy.get('#go-back').click();
  });
});

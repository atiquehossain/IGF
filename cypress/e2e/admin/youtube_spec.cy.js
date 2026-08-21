/* eslint-disable no-undef */
describe('Youtube', () => {
  // cy.refreshDatabase();
  before(() => {
    // cy.seed();
  });

  beforeEach(() => {
    cy.loginWithUsername();
    cy.visit('/admin/youtube');
  });

  it('create new youtube by save', () => {
    cy.visit('/admin/youtube/create');
    if (Cypress.env('APP_LOCALIZATION')) {
      cy.get('#pills-tab li').each((element) => {
        const lang = element.attr('data-id');
        cy.get(`#${lang}-tab`).click();
        cy.get(`[data-e2e=youtube-name-${lang}]`).type(`youtube ${lang}`);
        cy.get(`[data-e2e=youtube-video-id-${lang}]`).type('akXfPbUeeyA');
        cy.get(`[data-e2e=youtube-activision-time-${lang}]`).type(1);
        cy.get(`[data-e2e=youtube-duration-time-${lang}]`).type(5);
        cy.get(`[data-e2e=youtube-order-by-${lang}]`).type(5);
      });
    } else {
      const lang = 'en';
      cy.get(`[data-e2e=youtube-name-${lang}]`).type(`youtube ${lang}`);
      cy.get(`[data-e2e=youtube-video-id-${lang}]`).type('akXfPbUeeyA');
      cy.get(`[data-e2e=youtube-activision-time-${lang}]`).type(1);
      cy.get(`[data-e2e=youtube-duration-time-${lang}]`).type(5);
      cy.get(`[data-e2e=youtube-order-by-${lang}]`).type(5);
    }

    cy.wait(1000);
    cy.get('button[name="save"]').click();
    cy.wait(1000);
  });

  it('create new youtube by save and update', () => {
    cy.visit('/admin/youtube/create');
    if (Cypress.env('APP_LOCALIZATION')) {
      cy.get('#pills-tab li').each((element) => {
        const lang = element.attr('data-id');
        cy.get(`#${lang}-tab`).click();
        cy.get(`[data-e2e=youtube-name-${lang}]`).type(`youtube ${lang}`);
        cy.get(`[data-e2e=youtube-video-id-${lang}]`).type('akXfPbUeeyA');
        cy.get(`[data-e2e=youtube-activision-time-${lang}]`).type(1);
        cy.get(`[data-e2e=youtube-duration-time-${lang}]`).type(5);
        cy.get(`[data-e2e=youtube-order-by-${lang}]`).type(5);
      });

      cy.wait(1000);
      cy.get('button[name="save_and_update"]').click();
      cy.wait(1000);

      cy.get('#pills-tab li').each((element) => {
        const lang = element.attr('data-id');
        cy.get(`#${lang}-tab`).click();
        cy.get(`[data-e2e=youtube-name-${lang}]`).type(`youtube ${lang}`);
        cy.get(`[data-e2e=youtube-video-id-${lang}]`).type('akXfPbUeeyA');
        cy.get(`[data-e2e=youtube-activision-time-${lang}]`).type(1);
        cy.get(`[data-e2e=youtube-duration-time-${lang}]`).type(5);
        cy.get(`[data-e2e=youtube-order-by-${lang}]`).type(5);
      });
    } else {
      const lang = 'en';

      cy.get(`[data-e2e=youtube-name-${lang}]`).type(`youtube ${lang}`);
      cy.get(`[data-e2e=youtube-video-id-${lang}]`).type('akXfPbUeeyA');
      cy.get(`[data-e2e=youtube-activision-time-${lang}]`).type(1);
      cy.get(`[data-e2e=youtube-duration-time-${lang}]`).type(5);
      cy.get(`[data-e2e=youtube-order-by-${lang}]`).type(5);

      cy.wait(1000);
      cy.get('button[name="save_and_update"]').click();
      cy.wait(1000);

      cy.get(`[data-e2e=youtube-name-${lang}]`).type(`youtube ${lang}`);
      cy.get(`[data-e2e=youtube-video-id-${lang}]`).type('akXfPbUeeyA');
      cy.get(`[data-e2e=youtube-activision-time-${lang}]`).type(1);
      cy.get(`[data-e2e=youtube-duration-time-${lang}]`).type(5);
      cy.get(`[data-e2e=youtube-order-by-${lang}]`).type(5);
    }

    cy.wait(1000);
    cy.get('button[name="save"]').click();
    cy.wait(1000);
  });

  it('update youtube by save', () => {
    cy.get('#youtube_table tbody tr a.edit').first().click();
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

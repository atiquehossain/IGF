/* eslint-disable no-undef */
describe('YouTube', () => {
  const runId = Date.now().toString(36).slice(-8);

  function fillVisibleLanguages(mode, name, videoId) {
    cy.get(`#youtube-${mode}-tabs li`).each((element) => {
      const lang = element.attr('data-id');
      cy.get(`#youtube-${mode}-tab-${lang}`).click();
      cy.get(`[data-e2e=youtube-name-${lang}]`).clear().type(`${name} ${lang}`);
      cy.get(`[data-e2e=youtube-video-id-${lang}]`).clear().type(videoId);
      cy.get(`[data-e2e=youtube-activision-time-${lang}]`).clear().type('1');
      cy.get(`[data-e2e=youtube-duration-time-${lang}]`).clear().type('5');
      cy.get(`[data-e2e=youtube-order-by-${lang}]`).clear().type('5');
    });
  }

  beforeEach(() => {
    cy.loginWithUsername();
    cy.visit('/admin/youtube');
  });

  it('creates a new YouTube item and returns to the list', () => {
    const name = `Cypress YouTube ${runId}`;
    cy.visit('/admin/youtube/create');
    fillVisibleLanguages('create', name, 'e-ORhEE9VVg');
    cy.get('button[name="save"]').click();
    cy.location('pathname').should('eq', '/admin/youtube');
    cy.contains('#youtube_table tbody tr', name).should('be.visible');
  });

  it('creates a YouTube item, continues editing, and saves the update', () => {
    const name = `Cypress YouTube continue ${runId}`;
    const updatedName = `${name} updated`;
    const videoId = 'kffacxfA7G4';
    cy.visit('/admin/youtube/create');
    fillVisibleLanguages('create', name, videoId);
    cy.get('button[name="save_and_update"]').click();
    cy.location('pathname').should('match', /^\/admin\/youtube\/.+\/edit$/);

    fillVisibleLanguages('edit', updatedName, videoId);
    cy.get('button[name="save"]').click();
    cy.location('pathname').should('eq', '/admin/youtube');
    cy.contains('#youtube_table tbody tr', updatedName).should('be.visible');
  });

  it('opens an existing YouTube item for editing', () => {
    cy.get('#youtube_table tbody tr button.edit').first().click();
    cy.location('pathname').should('match', /^\/admin\/youtube\/.+\/edit$/);
    cy.get('[data-e2e="youtube-name-en"]').should('be.visible');
    cy.get('#go-back').click();
    cy.location('pathname').should('eq', '/admin/youtube');
  });
});

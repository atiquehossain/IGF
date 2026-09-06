/* eslint-disable no-undef */

const uniqueToken = () => `${Date.now().toString(36)}-${Cypress._.random(1000, 9999)}`;

const pressEnter = (selector) => {
  cy.get(selector)
    .focus()
    .then(($element) => {
      const KeyboardEvent = $element[0].ownerDocument.defaultView.KeyboardEvent;
      $element[0].dispatchEvent(new KeyboardEvent('keydown', {
        key: 'Enter',
        code: 'Enter',
        keyCode: 13,
        which: 13,
        bubbles: true,
        cancelable: true
      }));
    });
};

const trashPageIfPresent = (title, assertRemoved = false) => {
  cy.visit(`/admin/page?search=${encodeURIComponent(title)}`);
  cy.get('body').then(($body) => {
    const matchingRows = $body.find('article.hub-row').filter((_, row) =>
      Cypress.$(row).text().includes(title)
    );

    if (!matchingRows.length) {
      if (assertRemoved) {
        expect(matchingRows.length, 'temporary page shown in Content Hub').to.eq(1);
      }
      return;
    }

    cy.intercept('DELETE', '/admin/page/*').as('trashTemporaryBuilderPage');
    cy.on('window:confirm', () => true);
    cy.wrap(matchingRows.first()).find('button.trash').click();
    cy.wait('@trashTemporaryBuilderPage').its('response.statusCode').should('eq', 200);

    if (assertRemoved) {
      cy.contains('article.hub-row', title).should('not.exist');
    }
  });
};

describe('Simple Page Builder for a nontechnical administrator', () => {
  let temporaryPageTitle = '';

  beforeEach(() => {
    cy.loginWithUsername();
  });

  afterEach(() => {
    if (!temporaryPageTitle) return;

    const title = temporaryPageTitle;
    temporaryPageTitle = '';
    trashPageIfPresent(title);
  });

  it('builds and persists a visual layout through the guided interface', () => {
    const token = uniqueToken();
    temporaryPageTitle = `Cypress guided builder ${token}`;
    const persistedHeading = `Community action ${token}`;

    cy.visit('/admin/page/create');
    cy.get('[data-e2e="page-language"]').select('en');
    cy.get('[data-e2e="page-name"]').type(temporaryPageTitle);
    cy.get('[data-e2e="page-subtitle"]').type('A temporary draft used to verify the guided visual editor.');
    cy.get('[data-e2e="create-page-draft"]').click();

    cy.location('pathname').should('match', /^\/admin\/page-builder\/[0-9a-f-]+$/i);
    cy.get('#simple-editor')
      .should('have.attr', 'data-editor-mode', 'content');
    cy.get('[data-editor-mode="content"]')
      .should('have.attr', 'aria-pressed', 'true')
      .and('contain.text', 'Edit content');
    cy.get('[data-editor-mode="layout"]')
      .should('have.attr', 'aria-pressed', 'false')
      .and('contain.text', 'Customize layout');
    cy.get('aside[aria-label="Page structure"]')
      .should('contain.text', 'Page Structure')
      .and('contain.text', 'Choose anything here or in the preview');

    cy.intercept('POST', '/admin/page-builder/*/blocks').as('addVisualLayout');
    cy.get('#open-add-section').click();
    cy.get('#add-section-modal')
      .should('be.visible')
      .and('contain.text', 'Choose what you want visitors to see');
    cy.get('#add-section-modal [data-add-section="layout"]')
      .should('contain.text', 'Visual layout')
      .click();
    cy.wait('@addVisualLayout').its('response.statusCode').should('be.oneOf', [200, 201]);

    cy.get('#add-section-modal').should('not.be.visible');
    cy.get('#simple-section-list [data-section]')
      .should('have.length', 1)
      .and('contain.text', 'Visual layout');
    cy.get('#simple-preview [data-preview-type="layout"]')
      .should('exist')
      .and('have.attr', 'data-label', 'Visual layout');

    cy.get('[data-editor-mode="layout"]').click();
    cy.get('#simple-editor').should('have.attr', 'data-editor-mode', 'layout');
    cy.get('[data-editor-mode="layout"]').should('have.attr', 'aria-pressed', 'true');
    cy.get('#simple-canvas-tip').should('contain.text', 'Click a row, column, or content item');

    cy.get('#simple-preview [data-preview-type="layout"] [data-preview-layout-action="add-row"]')
      .last()
      .should('contain.text', 'Add row here')
      .click();

    cy.get('#simple-preview [data-layout-node="row"][data-layout-row="1"]')
      .should('have.class', 'is-layout-selected');
    cy.get('#simple-section-list [data-navigator-kind="row"][data-layout-row="1"]')
      .should('have.class', 'is-selected')
      .and('have.attr', 'aria-current', 'true');
    cy.get('#simple-inspector-title').should('have.text', 'Row 2');
    cy.get('#simple-selection-breadcrumb')
      .should('contain.text', 'Visual layout')
      .and('contain.text', 'Row 2');

    cy.get('#simple-inspector-body [data-layout-preset="halves"]')
      .should('have.attr', 'aria-label', 'Use Two equal columns')
      .click();
    cy.get('#simple-inspector-body [data-layout-preset="halves"]')
      .should('have.attr', 'aria-pressed', 'true');
    cy.get('#simple-preview [data-layout-node="row"][data-layout-row="1"]')
      .should('have.class', 'simple-layout-preview-row--halves')
      .within(() => {
        cy.get('[data-layout-node="column"]').should('have.length', 2);
      });

    cy.get('#simple-preview [data-layout-node="column"][data-layout-row="1"][data-layout-column="0"]')
      .click();
    cy.get('#simple-preview [data-layout-node="column"][data-layout-row="1"][data-layout-column="0"]')
      .should('have.class', 'is-layout-selected');
    cy.get('#simple-section-list [data-navigator-kind="column"][data-layout-row="1"][data-layout-column="0"]')
      .should('have.class', 'is-selected')
      .and('have.attr', 'aria-current', 'true');
    cy.get('#simple-inspector-title').should('have.text', 'Column 1');

    cy.get('#simple-preview [data-layout-node="column"][data-layout-row="1"][data-layout-column="0"]')
      .find('[data-preview-layout-action="add-content"]')
      .should('contain.text', 'Add content')
      .click();
    cy.get('#element-picker-modal')
      .should('be.visible')
      .and('contain.text', 'Choose one content type');
    cy.get('#element-picker-modal [data-layout-pick-element="heading"]')
      .should('contain.text', 'Heading')
      .click();
    cy.get('#element-picker-modal').should('not.be.visible');

    const newHeadingSelector = '#simple-preview [data-layout-node="element"][data-layout-row="1"][data-layout-column="0"][data-layout-element="0"]';
    const newHeadingNavigator = '#simple-section-list [data-navigator-kind="element"][data-layout-row="1"][data-layout-column="0"][data-layout-element="0"]';

    cy.get(newHeadingSelector)
      .should('have.class', 'is-layout-selected')
      .and('have.attr', 'tabindex', '0');
    cy.get(newHeadingNavigator)
      .should('have.class', 'is-selected')
      .and('have.attr', 'aria-current', 'true');
    cy.get('#simple-inspector-type').should('have.text', 'Heading');
    cy.get('#simple-selection-breadcrumb')
      .should('contain.text', 'Row 2')
      .and('contain.text', 'Column 1')
      .and('contain.text', 'Heading');

    pressEnter('#simple-preview [data-layout-node="row"][data-layout-row="0"]');
    cy.get('#simple-preview [data-layout-node="row"][data-layout-row="0"]')
      .should('have.class', 'is-layout-selected');
    cy.get('#simple-section-list [data-navigator-kind="row"][data-layout-row="0"]')
      .should('have.class', 'is-selected')
      .and('have.attr', 'aria-current', 'true');

    cy.get('[data-editor-mode="content"]').click();
    cy.get('#simple-editor').should('have.attr', 'data-editor-mode', 'content');
    cy.get('[data-editor-mode="content"]').should('have.attr', 'aria-pressed', 'true');

    pressEnter(newHeadingSelector);
    cy.get(newHeadingSelector).should('have.class', 'is-layout-selected');
    cy.get(newHeadingNavigator)
      .should('have.class', 'is-selected')
      .and('have.attr', 'aria-current', 'true');
    cy.get('#simple-inspector-body [data-layout-element-field="text"]')
      .should('have.value', 'New heading')
      .clear()
      .type(persistedHeading);
    cy.get(newHeadingSelector).should('contain.text', persistedHeading);

    cy.get('button[aria-label="Mobile preview"]')
      .click()
      .should('have.attr', 'aria-pressed', 'true');
    cy.get('#simple-preview').should('have.attr', 'data-viewport', 'mobile');
    cy.get('button[aria-label="Tablet preview"]')
      .click()
      .should('have.attr', 'aria-pressed', 'true');
    cy.get('#simple-preview').should('have.attr', 'data-viewport', 'tablet');
    cy.get('button[aria-label="Desktop preview"]')
      .click()
      .should('have.attr', 'aria-pressed', 'true');
    cy.get('#simple-preview').should('have.attr', 'data-viewport', 'desktop');

    cy.intercept('PUT', '/admin/page-builder/*/simple-save').as('saveGuidedLayout');
    cy.get('[data-save-changes]').should('not.be.disabled').click();
    cy.wait('@saveGuidedLayout').its('response.statusCode').should('eq', 200);
    cy.get('#simple-save-state').should('contain.text', 'All changes saved');

    cy.reload();
    cy.get('#simple-editor').should('have.attr', 'data-editor-mode', 'content');
    cy.get('#simple-preview [data-preview-type="layout"]')
      .should('contain.text', persistedHeading);
    cy.get('#simple-section-list')
      .should('contain.text', 'Visual layout')
      .and('contain.text', persistedHeading);
    cy.get('#simple-save-state').should('contain.text', 'All changes saved');

    trashPageIfPresent(temporaryPageTitle, true);
    temporaryPageTitle = '';
  });
});

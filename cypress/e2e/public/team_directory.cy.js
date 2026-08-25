/* eslint-disable no-undef */
describe('public team directory', () => {
  const assertNoHorizontalOverflow = () => {
    cy.document().then((document) => {
      expect(document.documentElement.scrollWidth).to.be.at.most(document.documentElement.clientWidth + 1);
    });
  };

  const assertTeamDirectory = () => {
    cy.get('.igf-page-block--team').scrollIntoView().should('be.visible');
    cy.get('.igf-team-panel').should('be.visible');
    cy.get('.igf-team-card').its('length').should('be.greaterThan', 0);

    cy.get('body').then(($body) => {
      const tabs = $body.find('[role="tab"]');

      if (tabs.length > 1) {
        cy.wrap(tabs.first()).should('have.attr', 'aria-selected', 'true');
        cy.wrap(tabs.eq(1)).click().should('have.attr', 'aria-selected', 'true');
        cy.get('.igf-team-panel').should('be.visible');
      } else {
        cy.get('.igf-team-tabs').should('not.exist');
      }
    });
  };

  it('renders the team directory without desktop overflow', () => {
    cy.viewport(1280, 900);
    cy.visit('/about-us');

    assertTeamDirectory();
    assertNoHorizontalOverflow();
  });

  it('keeps team cards usable on a phone viewport', () => {
    cy.viewport(390, 844);
    cy.visit('/about-us');

    assertTeamDirectory();
    assertNoHorizontalOverflow();
  });
});

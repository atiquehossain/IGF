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
    cy.get('.igf-team-tabs[role="tablist"]').should('be.visible');
    cy.get('.igf-team-tabs [role="tab"]').its('length').should('be.greaterThan', 0);
    cy.get('.igf-team-tabs [role="tab"]').first()
      .should('have.attr', 'aria-selected', 'true')
      .and('have.attr', 'tabindex', '0');
    cy.get('.igf-team-tabs [role="tab"]').each(($tab) => {
      const panelId = $tab.attr('aria-controls');
      expect(panelId, 'tab panel id').to.be.a('string').and.not.be.empty;
      cy.get('#' + panelId)
        .should('have.attr', 'role', 'tabpanel')
        .and('have.attr', 'aria-labelledby', $tab.attr('id'));
    });
    cy.get('.igf-team-panel:not([hidden])').should('have.length', 1).and('be.visible');

    cy.get('.igf-team-tabs [role="tab"]').then(($tabs) => {
      if ($tabs.length > 1) {
        cy.wrap($tabs.eq(1)).click().should('have.attr', 'aria-selected', 'true');
        cy.wrap($tabs.eq(0)).should('have.attr', 'aria-selected', 'false');
        cy.get('.igf-team-panel:not([hidden])').should('have.length', 1).and('be.visible');
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

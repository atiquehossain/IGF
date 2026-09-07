/* eslint-disable no-undef */
describe('programs archive presentation', () => {
  const assertNoHorizontalOverflow = () => {
    cy.document().then((document) => {
      expect(document.documentElement.scrollWidth)
        .to.be.at.most(document.documentElement.clientWidth + 1);
    });
  };

  const assertImageLoaded = ($image) => {
    expect($image[0].complete).to.eq(true);
    expect($image[0].naturalWidth).to.be.greaterThan(0);
  };

  it('shows the split hero and a balanced four-program desktop grid', () => {
    cy.viewport(1440, 1000);
    cy.visit('/category/our-causes?lang=en');

    cy.get('.igf-program-hero--split').should('be.visible');
    cy.get('.igf-program-hero__visual img').should(assertImageLoaded);
    cy.get('.igf-program-hero__jump').should('contain.text', 'Browse programs');
    cy.get('.igf-card-grid > .igf-content-card').should('have.length', 4);
    cy.get('.igf-card-grid').should(($grid) => {
      const columns = getComputedStyle($grid[0]).gridTemplateColumns.trim().split(/\s+/);
      expect(columns).to.have.length(2);
    });
    cy.contains('.igf-content-card', 'Livelihoods')
      .find('img')
      .should(assertImageLoaded);
    cy.get('.igf-listing__cta')
      .should('be.visible')
      .and('contain.text', 'Help create lasting change')
      .find('a')
      .should('have.attr', 'href', '/donate');
    assertNoHorizontalOverflow();
  });

  it('keeps the hero image, cards, and closing action usable on a phone', () => {
    cy.viewport(390, 844);
    cy.visit('/category/our-causes?lang=en');

    cy.get('.igf-program-hero__visual').scrollIntoView().should('be.visible');
    cy.get('.igf-program-hero__visual img').should(assertImageLoaded);
    cy.get('.igf-card-grid').should(($grid) => {
      const columns = getComputedStyle($grid[0]).gridTemplateColumns.trim().split(/\s+/);
      expect(columns).to.have.length(1);
    });
    cy.get('.igf-listing__cta a').scrollIntoView().should('be.visible');
    assertNoHorizontalOverflow();
  });
});

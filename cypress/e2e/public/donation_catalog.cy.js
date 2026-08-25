/* eslint-disable no-undef */
describe('public donation catalog', () => {
  const causeCardSelector = '[data-test="donation-cause-card"]';
  const causeLinkSelector = '[data-test="donation-cause-link"]';
  const causeSlugs = [
    'where-it-is-needed-most',
    'education',
    'zakat',
    'sadaqah',
    'food-support',
    'emergency-relief',
    'orphan-support',
    'school-stationery',
    'school-uniforms',
    'school-meals',
    'adopt-a-school',
    'ramadan-iftar',
    'qurbani',
    'pure-water-and-sanitation',
    'women-empowerment',
    'youth-development',
    'street-children-education',
  ];
  const lastCause = {
    name: 'Street Children Education',
    slug: 'street-children-education',
  };

  const assertNoHorizontalOverflow = () => {
    cy.document().then((document) => {
      expect(document.documentElement.scrollWidth).to.be.at.most(document.documentElement.clientWidth + 1);
    });
  };

  const assertPageMode = (expectedMode) => {
    cy.get('#app').should(($app) => {
      const page = JSON.parse($app.attr('data-page'));
      expect(page.props.data.pageMode).to.eq(expectedMode);
    });
  };


  const assertCatalogAndOpenLastCause = () => {
    assertPageMode('catalog');
    cy.get('.igf-donate-causes').scrollIntoView().should('be.visible');
    cy.get(causeCardSelector).should('have.length', 17);
    cy.get(causeLinkSelector)
      .should('have.length', 17)
      .then((links) => {
        expect([...links].map(link => link.getAttribute('href')))
          .to.deep.equal(causeSlugs.map(slug => `/donate/${slug}`));
      });
    cy.get('#donation-form-title').should('not.exist');
    cy.get('#donation-cause').should('not.exist');
    cy.get('[data-test="locked-donation-cause"]').should('not.exist');
    assertNoHorizontalOverflow();

    cy.get(causeLinkSelector)
      .last()
      .should('have.attr', 'aria-label')
      .and('contain', lastCause.name);
    cy.get(causeLinkSelector)
      .last()
      .scrollIntoView()
      .should('be.visible')
      .and('have.attr', 'href', `/donate/${lastCause.slug}`)
      .click();

    cy.location('pathname').should('eq', `/donate/${lastCause.slug}`);
    assertPageMode('detail');
    cy.get('.igf-donate-causes').should('not.exist');
    cy.get(causeCardSelector).should('not.exist');
    cy.get('[data-test="locked-donation-cause"]')
      .should('be.visible')
      .and('contain.text', lastCause.name)
      .find('.fa-lock')
      .should('exist');
    cy.get('#donation-cause').should('not.exist');
    cy.get('#donation-form-title').should('be.visible');
    cy.get('#footer-office-contact-heading').should('not.exist');
    cy.get('.footer-contact-block').should('not.contain.text', 'Office contact details');
    cy.get('.igf-cause-back-link')
      .should('have.attr', 'href')
      .and('match', /\/donate$/);
    assertNoHorizontalOverflow();
  };

  it('links all catalog cards to locked cause checkouts without desktop overflow', () => {
    cy.viewport(1280, 900);
    cy.visit('/donate');

    assertCatalogAndOpenLastCause();
  });

  it('keeps the catalog and locked cause checkout usable on a phone viewport', () => {
    cy.viewport(390, 844);
    cy.visit('/donate');

    assertCatalogAndOpenLastCause();
  });

  it('redirects a legacy cause query to its canonical cause page', () => {
    cy.visit(`/donate?cause=${lastCause.slug}&amount=1000`);

    cy.location('pathname').should('eq', `/donate/${lastCause.slug}`);
    cy.location('search').should('eq', '?amount=1000');
    assertPageMode('detail');
    cy.get('[data-test="locked-donation-cause"]').should('contain.text', lastCause.name);
  });
});

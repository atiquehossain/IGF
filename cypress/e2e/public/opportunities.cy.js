/* eslint-disable no-undef */
describe('anonymous public jobs and workshops', () => {
  const jobPath = '/careers/program-officer';
  const workshopPath = '/workshops/free-leadership';

  const assertNoHorizontalOverflow = () => {
    cy.document().then((document) => {
      const viewportWidth = document.documentElement.clientWidth;
      const overflow = [...document.body.querySelectorAll('*')]
        .map(element => ({
          selector: `${element.tagName.toLocaleLowerCase()}${element.id ? `#${element.id}` : ''}${[...element.classList].map(name => `.${name}`).join('')}`,
          left: Math.round(element.getBoundingClientRect().left),
          right: Math.round(element.getBoundingClientRect().right),
          scrollWidth: element.scrollWidth,
        }))
        .filter(item => item.right > viewportWidth + 1 || item.left < -1)
        .sort((left, right) => right.right - left.right)
        .slice(0, 5);
      expect(document.documentElement.scrollWidth, JSON.stringify(overflow)).to.be.at.most(viewportWidth + 1);
    });
  };

  const assertSinglePageHeading = (text) => {
    cy.get('main h1').should('have.length', 1).and('contain.text', text);
  };

  const assertControlHasLabel = (selector) => {
    cy.get(selector).should(($control) => {
      const id = $control.attr('id');
      expect(id, `${selector} has an id`).to.be.a('string').and.not.be.empty;
      expect(Cypress.$(`label[for="${id}"]`).length, `${selector} has an associated label`).to.eq(1);
    });
  };

  const assertLinkPath = (selector, path) => {
    cy.get(selector).should(($link) => {
      expect(new URL($link.prop('href')).pathname).to.eq(path);
    });
  };

  const assertReciprocalDisclosure = ($toggle) => {
    const toggle = $toggle[0];
    const toggleId = toggle.getAttribute('id');
    const panelId = toggle.getAttribute('aria-controls');
    const panel = toggle.ownerDocument.getElementById(panelId);

    expect(typeof toggleId, 'disclosure id type').to.eq('string');
    expect(toggleId, 'disclosure has a stable id').not.to.eq('');
    expect(typeof panelId, 'panel id type').to.eq('string');
    expect(panelId, 'disclosure identifies its panel').not.to.eq('');
    expect(panel, 'controlled panel exists').not.to.eq(null);
    const labelId = panel.getAttribute('aria-labelledby');
    const label = toggle.ownerDocument.getElementById(labelId);
    expect(typeof labelId, 'panel label id type').to.eq('string');
    expect(labelId, 'panel has a stable visible label reference').not.to.eq('');
    expect(label, 'panel label exists').not.to.eq(null);
    expect(label.textContent.trim(), 'panel label has visible text').not.to.eq('');
  };

  const validPdf = (fileName = 'candidate-cv.pdf') => {
    const objects = [
      '<< /Type /Catalog /Pages 2 0 R >>',
      '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
      '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>',
      '<< /Length 0 >>\nstream\n\nendstream',
    ];
    let contents = '%PDF-1.7\n%IGF\n';
    const offsets = [];
    objects.forEach((object, index) => {
      offsets.push(Cypress.Buffer.byteLength(contents));
      contents += `${index + 1} 0 obj\n${object}\nendobj\n`;
    });
    const xrefOffset = Cypress.Buffer.byteLength(contents);
    contents += 'xref\n0 5\n0000000000 65535 f \n';
    offsets.forEach(offset => {
      contents += `${String(offset).padStart(10, '0')} 00000 n \n`;
    });
    contents += `trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n${xrefOffset}\n%%EOF\n`;

    return {
      contents: Cypress.Buffer.from(contents),
      fileName,
      mimeType: 'application/pdf',
    };
  };

  before(() => {
    cy.refreshDatabase();
    cy.seed('LocalDevelopmentSeeder');
    cy.seed('CypressOpportunitySeeder');
  });

  beforeEach(() => {
    cy.clearCookies();
    cy.intercept('GET', '**/storage/media/**', { statusCode: 204, body: '' });
  });

  it('keeps the complete desktop header inside a 1440px browser viewport', () => {
    cy.viewport(1440, 900);
    cy.visit('/?lang=en');

    cy.get('.site-nav__actions .sponsor-button').should('not.be.visible');
    cy.get('.site-nav__actions .donate-button').should('be.visible');
    assertNoHorizontalOverflow();
  });

  it('renders Workshop only under Our Work and Youth Development with keyboard disclosures', () => {
    cy.viewport(1280, 900);
    cy.visit('/workshops?lang=en');

    cy.get('.desktop-nav')
      .should('not.contain.text', 'Opportunities')
      .and('not.contain.text', 'Free Workshop');
    cy.get('.desktop-nav a[href="/workshops"]')
      .should('have.length', 1)
      .and('contain.text', 'Workshop')
      .and('have.attr', 'aria-current', 'page')
      .parents('.desktop-nav__item')
      .first()
      .should('have.class', 'is-active')
      .then(($ourWork) => {
        const $topToggle = $ourWork.children('.desktop-nav__trigger');
        const $youth = $ourWork.find('a[href="/workshops"]')
          .first()
          .closest('.desktop-nav__entry[data-nav-depth="2"]');
        const $youthToggle = $youth.children('.desktop-nav__parent-row').find('button[aria-expanded]');

        expect($topToggle).to.have.length(1);
        expect($youth).to.have.length(1).and.have.class('is-active');
        expect($youthToggle).to.have.length(1);
        assertReciprocalDisclosure($topToggle);
        assertReciprocalDisclosure($youthToggle);

        cy.wrap($topToggle)
          .focus()
          .should('be.focused')
          .and('have.attr', 'aria-expanded', 'false')
          .type('{enter}')
          .should('have.attr', 'aria-expanded', 'true');
        cy.wrap($youthToggle)
          .focus()
          .type('{enter}')
          .should('have.attr', 'aria-expanded', 'true');
        assertNoHorizontalOverflow();
        cy.wrap($youth).find('a[href="/workshops"]')
          .should('be.visible')
          .focus()
          .type('{esc}');
        cy.wrap($youthToggle).should('be.focused').and('have.attr', 'aria-expanded', 'false');
        cy.wrap($topToggle).should('have.attr', 'aria-expanded', 'true');
      });
  });

  it('uses the desktop navigation to reach the active job list and full detail', () => {
    cy.viewport(1280, 900);
    cy.visit('/?lang=en');

    cy.get('.desktop-nav a[href="/careers"]')
      .parents('.desktop-nav__item')
      .first()
      .within(() => {
        cy.get('button[aria-expanded]')
          .first()
          .focus()
          .should('be.focused')
          .and('have.attr', 'aria-expanded', 'false')
          .type('{enter}')
          .should('have.attr', 'aria-expanded', 'true');
        cy.get('a[href="/careers"]').should('be.visible').and('contain.text', 'Careers').click();
      });

    cy.location('pathname').should('eq', '/careers');
    assertSinglePageHeading('Careers');
    cy.get('.igf-opportunity-card').should('have.length', 1).and('contain.text', 'Program Officer');
    cy.get('.igf-opportunity-card').should('not.contain.text', 'Closed Role');
    assertLinkPath('.igf-opportunity-card h2 a', jobPath);
    cy.get('.igf-opportunity-card h2 a').click();

    cy.location('pathname').should('eq', jobPath);
    assertSinglePageHeading('Program Officer');
    cy.get('.igf-opportunity-detail__article')
      .should('contain.text', 'Build practical programs with local communities.')
      .and('contain.text', 'Responsibilities')
      .and('contain.text', 'Coordinate partners and program delivery.')
      .and('contain.text', 'Requirements')
      .and('contain.text', 'Strong communication and organization skills.');
    cy.get('.igf-opportunity-detail__form-panel')
      .should('have.attr', 'aria-labelledby', 'job-form-title');
    cy.get('#job-form-title').should('contain.text', 'Apply for this position');
    assertNoHorizontalOverflow();
  });

  it('keeps mobile navigation, workshop list, and detail keyboard-usable without overflow', () => {
    cy.viewport(390, 844);
    cy.visit('/?lang=en');

    cy.get('.menu-button').should(($button) => {
      const panelId = $button.attr('aria-controls');
      const panel = $button[0].ownerDocument.getElementById(panelId);
      expect(panel, 'collapsed menu button keeps its controlled region mounted').not.to.eq(null);
      expect(panel.hidden, 'controlled region is hidden while collapsed').to.eq(true);
    });
    cy.get('.menu-button')
      .focus()
      .should('be.focused')
      .and('have.attr', 'aria-expanded', 'false')
      .type('{enter}')
      .should('have.attr', 'aria-expanded', 'true');
    cy.get('.mobile-nav')
      .should('not.contain.text', 'Opportunities')
      .and('not.contain.text', 'Free Workshop');
    cy.get('.mobile-nav a[href="/workshops"]')
      .should('have.length', 1)
      .and('contain.text', 'Workshop')
      .parents('.mobile-nav__group')
      .first()
      .then(($ourWork) => {
        const $topToggle = $ourWork.children('.mobile-nav__parent');
        const $youth = $ourWork.find('a[href="/workshops"]')
          .first()
          .closest('.mobile-nav__entry[data-nav-depth="2"]');
        const $youthToggle = $youth.children('.mobile-nav__parent-row').find('button[aria-expanded]');

        expect($topToggle).to.have.length(1);
        expect($youthToggle).to.have.length(1);
        assertReciprocalDisclosure($topToggle);
        assertReciprocalDisclosure($youthToggle);

        cy.wrap($topToggle).click().should('have.attr', 'aria-expanded', 'true');
        cy.wrap($youthToggle).click().should('have.attr', 'aria-expanded', 'true');
        assertNoHorizontalOverflow();
        cy.wrap($youth).find('a[href="/workshops"]').should('be.visible').focus().type('{esc}');
        cy.wrap($youthToggle).should('be.focused').and('have.attr', 'aria-expanded', 'false');
        cy.wrap($topToggle).should('have.attr', 'aria-expanded', 'true');

        cy.wrap($youthToggle).type('{esc}');
        cy.wrap($topToggle).should('be.focused').and('have.attr', 'aria-expanded', 'false');
        cy.wrap($topToggle).type('{esc}');
      });
    cy.get('.mobile-nav').should('exist').and('not.be.visible').and('have.attr', 'hidden');
    cy.get('.menu-button').should('be.focused').and('have.attr', 'aria-expanded', 'false');

    cy.get('.menu-button').type('{enter}');
    cy.get('.mobile-nav a[href="/workshops"]')
      .parents('.mobile-nav__group')
      .first()
      .then(($ourWork) => {
        const $youth = $ourWork.find('a[href="/workshops"]')
          .first()
          .closest('.mobile-nav__entry[data-nav-depth="2"]');

        cy.wrap($ourWork).children('.mobile-nav__parent').click();
        cy.wrap($youth).children('.mobile-nav__parent-row').find('button[aria-expanded]').click();
        cy.wrap($youth).find('a[href="/workshops"]').should('be.visible').click();
      });

    cy.location('pathname').should('eq', '/workshops');
    cy.get('.menu-button').click();
    cy.get('.mobile-nav a[href="/workshops"]')
      .should('have.attr', 'aria-current', 'page')
      .parents('.mobile-nav__entry[data-nav-depth="3"]')
      .should('have.class', 'is-active')
      .parents('.mobile-nav__entry[data-nav-depth="2"]')
      .should('have.class', 'is-active')
      .parents('.mobile-nav__group')
      .should('have.class', 'is-active')
      .find('button[aria-expanded="true"]')
      .should('have.length', 2);
    cy.get('.mobile-nav__group.is-active > .mobile-nav__parent')
      .should(($active) => {
        const activeStyle = getComputedStyle($active[0]);
        const inactiveParent = document.querySelector('.mobile-nav__group:not(.is-active) > .mobile-nav__parent');

        expect(activeStyle.backgroundColor).not.to.equal('rgba(0, 0, 0, 0)');
        expect(activeStyle.backgroundColor).not.to.equal('transparent');
        expect(activeStyle.boxShadow).not.to.equal('none');
        if (inactiveParent) {
          expect(activeStyle.backgroundColor).not.to.equal(getComputedStyle(inactiveParent).backgroundColor);
        }
      });
    cy.get('.menu-button').click();
    assertSinglePageHeading('Workshops');
    cy.get('.igf-opportunity-card').should('have.length', 1).and('contain.text', 'Community Leadership Workshop');
    cy.get('.igf-opportunity-card').should('not.contain.text', 'Closed Workshop');
    cy.get('.igf-opportunity-card').should('not.contain.text', 'A free, practical leadership workshop.');
    cy.get('.igf-opportunity-card__media img')
      .should('have.attr', 'src', '/storage/media/cypress/workshop-poster.jpg')
      .and('have.attr', 'alt', 'Community Leadership Workshop poster')
      .and('have.attr', 'loading', 'lazy')
      .and('have.attr', 'decoding', 'async')
      .and('have.css', 'object-fit', 'contain');
    assertNoHorizontalOverflow();
    assertLinkPath('.igf-opportunity-card h2 a', workshopPath);
    cy.get('.igf-opportunity-card h2 a').click();
    cy.location('pathname').should('eq', workshopPath);
    cy.get('.desktop-nav a[href="/workshops"]').should('not.have.attr', 'aria-current');
    assertSinglePageHeading('Community Leadership Workshop');
    cy.get('.igf-opportunity-detail__lead').should('contain.text', 'A free, practical leadership workshop.');
    cy.get('#workshop-form-title').should('be.visible');
    cy.get('.igf-opportunity-detail__main').should('be.visible');
    assertNoHorizontalOverflow();
  });

  it('renders Bangla list, detail, dynamic fields, and validation copy', () => {
    cy.viewport(1280, 900);
    cy.visit('/careers?lang=bn');

    cy.document().its('documentElement.lang').should('eq', 'bn');
    assertSinglePageHeading('কর্মজীবন');
    cy.get('.igf-opportunity-card h2 a').should('contain.text', 'কর্মসূচি কর্মকর্তা');
    assertLinkPath('.igf-opportunity-card h2 a', '/careers/program-officer-bn');
    cy.get('.igf-opportunity-card h2 a').click();
    cy.location('pathname').should('eq', '/careers/program-officer-bn');
    assertSinglePageHeading('কর্মসূচি কর্মকর্তা');
    cy.get('#job-responsibilities-title').should('contain.text', 'দায়িত্বসমূহ');
    cy.get('label[for="field-cypress-full-name"]').should('contain.text', 'পূর্ণ নাম');
    cy.get('label[for="field-experience-level"]').should('contain.text', 'অভিজ্ঞতার স্তর');
    cy.get('.igf-schema-form__submit').click();
    cy.get('[data-test="form-error-summary"]')
      .should('be.visible')
      .and('be.focused')
      .and('contain.text', 'ফর্মটি যাচাই করুন')
      .and('contain.text', 'পূর্ণ নাম আবশ্যক।');

    cy.visit('/workshops?lang=bn');
    assertSinglePageHeading('কর্মশালা');
    cy.get('.igf-opportunity-card h2 a').should('contain.text', 'কমিউনিটি নেতৃত্ব কর্মশালা');
    assertLinkPath('.igf-opportunity-card h2 a', '/workshops/free-leadership-bn');
    assertNoHorizontalOverflow();
  });

  it('clears hidden conditional answers and exposes accessible client errors and PDF rules', () => {
    cy.viewport(1280, 900);
    cy.visit(`${jobPath}?lang=en`);

    assertControlHasLabel('#field-cypress-full-name');
    assertControlHasLabel('#field-cypress-email');
    assertControlHasLabel('#field-cypress-phone');
    assertControlHasLabel('#field-cypress-cv');
    assertControlHasLabel('#field-experience-level');
    cy.get('#field-cypress-cv').should('have.attr', 'required');
    cy.get('#field-cypress-cv').should('have.attr', 'accept', '.pdf,application/pdf');
    cy.get('#field-cypress-cv-help').should('contain.text', 'Maximum size: 5 MB');
    cy.get('#field-leadership-example').should('not.exist');

    cy.get('.igf-schema-form__submit').click();
    cy.get('[data-test="form-error-summary"]')
      .should('be.focused')
      .and('contain.text', 'Please check the form');
    cy.get('#field-cypress-full-name').should('have.attr', 'aria-invalid', 'true');
    cy.get('#field-cypress-email').should('have.attr', 'aria-invalid', 'true');
    cy.get('#field-cypress-cv').should('have.attr', 'aria-invalid', 'true');
    cy.get('#field-experience-level').should('have.attr', 'aria-invalid', 'true');

    cy.get('#field-cypress-full-name').type('Cypress Candidate');
    cy.get('#field-cypress-email').type('candidate@example.test');
    cy.get('#field-experience-level').select('experienced');
    cy.get('#field-leadership-example').should('be.visible').type('I led a community project team.');
    cy.get('#field-experience-level').select('entry');
    cy.get('#field-leadership-example').should('not.exist');
    cy.get('#field-experience-level').select('experienced');
    cy.get('#field-leadership-example').should('have.value', '');
    cy.get('#field-leadership-example').type('I led a community project team.');

    cy.get('#field-cypress-cv').selectFile({
      contents: Cypress.Buffer.from('not a pdf'),
      fileName: 'candidate.txt',
      mimeType: 'text/plain',
    });
    cy.get('.igf-schema-form__submit').click();
    cy.get('[data-test="form-error-summary"]')
      .should('be.focused')
      .and('contain.text', 'Upload a supported PDF file.');

    cy.get('#field-cypress-cv').selectFile({
      contents: Cypress.Buffer.alloc((5 * 1024 * 1024) + 1),
      fileName: 'too-large.pdf',
      mimeType: 'application/pdf',
    });
    cy.get('.igf-schema-form__submit').click();
    cy.get('[data-test="form-error-summary"]')
      .should('be.focused')
      .and('contain.text', 'The file must be 5 MB or smaller.');
  });

  it('submits a valid anonymous job application and shows an on-screen reference', () => {
    cy.viewport(1280, 900);
    cy.intercept('POST', '**/careers/program-officer/apply').as('submitJob');
    cy.visit(`${jobPath}?lang=en`);

    cy.get('#field-cypress-full-name').type('Cypress Job Applicant');
    cy.get('#field-cypress-email').type(`job-${Date.now()}@example.test`);
    cy.get('#field-cypress-phone').type('+8801712345678');
    cy.get('#field-experience-level').select('experienced');
    cy.get('#field-leadership-example').type('I coordinated volunteers for a community education project.');
    cy.get('#field-cypress-cv').selectFile(validPdf());
    cy.wait(1100, { log: false });
    cy.get('.igf-schema-form__submit').click();
    cy.wait('@submitJob', { requestTimeout: 30000, responseTimeout: 30000 })
      .its('response.statusCode')
      .should('be.oneOf', [302, 303]);

    cy.location('pathname').should('eq', jobPath);
    cy.get('[data-test="submission-reference"]', { timeout: 30000 })
      .should('be.visible')
      .and('be.focused')
      .and('have.attr', 'role', 'status')
      .and('contain.text', 'Application received')
      .and('contain.text', 'Received');
    cy.get('[data-test="submission-reference"] code').invoke('text').should('match', /^IGF-JOB-/);
    cy.get('.igf-schema-form').should('not.exist');
  });

  it('preserves closed detail pages while removing them from active lists', () => {
    cy.viewport(1280, 900);

    cy.visit('/careers?lang=en');
    cy.get('.igf-opportunity-card').should('not.contain.text', 'Closed Role');
    cy.visit('/careers/closed-role?lang=en');
    assertSinglePageHeading('Closed Role');
    cy.get('.igf-opportunity-detail__closed')
      .should('have.attr', 'role', 'status')
      .and('contain.text', 'Applications closed');
    cy.get('.igf-schema-form').should('not.exist');

    cy.visit('/workshops?lang=en');
    cy.get('.igf-opportunity-card').should('not.contain.text', 'Closed Workshop');
    cy.visit('/workshops/closed-workshop?lang=en');
    assertSinglePageHeading('Closed Workshop');
    cy.get('.igf-opportunity-detail__closed')
      .should('have.attr', 'role', 'status')
      .and('contain.text', 'Registration closed');
    cy.get('.igf-schema-form').should('not.exist');
  });

  it('keeps workshops free, private, CV-free, and anonymously registrable', () => {
    cy.viewport(1280, 900);
    cy.intercept('POST', '**/workshops/free-leadership/register').as('registerWorkshop');
    cy.visit(`${workshopPath}?lang=en`);

    cy.get('.igf-opportunity-detail')
      .should('contain.text', 'Registration is completely free.');
    cy.get('.igf-opportunity-detail input[type="file"]').should('not.exist');
    cy.get('.igf-opportunity-detail').should('not.contain.text', 'cypress-secret-meeting');
    cy.get('[name*="payment"], [name*="certificate"], [name*="qr"]', { timeout: 0 }).should('not.exist');
    assertControlHasLabel('#field-cypress-workshop-full-name');
    assertControlHasLabel('#field-cypress-workshop-email');
    assertControlHasLabel('#field-cypress-workshop-phone');
    assertControlHasLabel('#field-workshop-interest');

    cy.get('#field-cypress-workshop-full-name').type('Cypress Workshop Participant');
    cy.get('#field-cypress-workshop-email').type(`workshop-${Date.now()}@example.test`);
    cy.get('#field-cypress-workshop-phone').type('+8801812345678');
    cy.get('#field-workshop-interest').type('I want practical tools for leading local initiatives.');
    cy.wait(1100, { log: false });
    cy.get('.igf-schema-form__submit').click();
    cy.wait('@registerWorkshop', { requestTimeout: 30000, responseTimeout: 30000 })
      .its('response.statusCode')
      .should('be.oneOf', [302, 303]);

    cy.location('pathname').should('eq', workshopPath);
    cy.get('[data-test="submission-reference"]', { timeout: 30000 })
      .should('be.visible')
      .and('be.focused')
      .and('contain.text', 'Registration received')
      .and('contain.text', 'Registration confirmed');
    cy.get('[data-test="submission-reference"] code').invoke('text').should('match', /^IGF-WS-/);
    cy.get('.igf-schema-form').should('not.exist');
    assertNoHorizontalOverflow();
  });
});

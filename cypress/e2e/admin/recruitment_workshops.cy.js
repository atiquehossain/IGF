/* eslint-disable no-undef */

describe('administrator recruitment and workshop critical paths', () => {
  const jobUuid = '90000000-0000-4000-8000-000000000001';
  const workshopUuid = '90000000-0000-4000-8000-000000000002';
  const applicationsPath = `/admin/recruitment/applications?listing=${jobUuid}`;
  const registrationsPath = `/admin/workshop/registrations?listing=${workshopUuid}`;

  const resetFixture = () => {
    cy.then(() => Cypress.session.clearAllSavedSessions());
    cy.clearCookies();
    cy.clearAllLocalStorage();
    cy.refreshDatabase();
    cy.seed('CypressAdminOpportunitySeeder');
  };

  const configuredPassword = () => cy
    .env(['ADMIN_USERNAME', 'ADMIN_PASSWORD'], { log: false })
    .then(({ ADMIN_USERNAME: username, ADMIN_PASSWORD: password }) => ({ username, password }));

  const loginOwner = () => configuredPassword().then(({ username, password }) => {
    cy.loginAsAdmin(username, password);
  });

  const loginFixtureAdmin = (username, expectedPath = '/admin') => configuredPassword().then(({ password }) => {
    cy.loginAsAdmin(username, password, expectedPath);
  });

  const logout = () => {
    cy.get('#left-panel form[action$="/admin/logout"] button[type="submit"]').click({ force: true });
    cy.location('pathname').should('eq', '/admin/login');
  };

  const localDateTime = (hoursFromNow) => {
    const date = new Date(Date.now() + (hoursFromNow * 60 * 60 * 1000));
    date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
    return date.toISOString().slice(0, 16);
  };

  const setRichText = (id, html) => {
    cy.window({ timeout: 20000 }).should((window) => {
      expect(window.tinymce, 'TinyMCE is available').to.exist;
      expect(window.tinymce.get(id), `TinyMCE editor ${id}`).to.exist;
    }).then((window) => {
      const editor = window.tinymce.get(id);
      editor.setContent(html);
      editor.save();
    });
  };

  const assertNoHorizontalOverflow = () => {
    cy.document().then((document) => {
      const viewportWidth = document.documentElement.clientWidth;
      const overflow = [...document.body.querySelectorAll('*')]
        .map((element) => ({
          selector: `${element.tagName.toLocaleLowerCase()}${element.id ? `#${element.id}` : ''}`,
          left: Math.round(element.getBoundingClientRect().left),
          right: Math.round(element.getBoundingClientRect().right),
        }))
        .filter((item) => item.right > viewportWidth + 1 || item.left < -1)
        .slice(0, 8);
      expect(document.documentElement.scrollWidth, JSON.stringify(overflow)).to.be.at.most(viewportWidth + 1);
    });
  };

  const rowContaining = (text) => cy.contains('tbody tr', text);

  const fillJobContent = () => {
    cy.get('#application_opens_at').type(localDateTime(-1));
    cy.get('#application_closes_at').type(localDateTime(72));
    cy.get('#employment_type').select('full_time');
    cy.get('#work_arrangement').select('hybrid');
    cy.get('#vacancy_count').clear().type('3');
    cy.get('#en-title').type('Browser Hiring Coordinator');
    cy.get('#en-slug').type('browser-hiring-coordinator');
    cy.get('#en-department').type('Programmes');
    cy.get('#en-location').type('Dhaka');
    cy.get('#en-summary').type('A browser-tested recruitment listing.');
    cy.get('#bn-title').type('ব্রাউজার নিয়োগ সমন্বয়কারী');
    cy.get('#bn-slug').type('browser-hiring-coordinator-bn');
    cy.get('#bn-department').type('কর্মসূচি');
    cy.get('#bn-location').type('ঢাকা');
    cy.get('#bn-summary').type('ব্রাউজার পরীক্ষিত নিয়োগ বিজ্ঞপ্তি।');
    setRichText('en-description', '<p>Coordinate community recruitment.</p>');
    setRichText('en-responsibilities', '<ul><li>Lead fair hiring.</li></ul>');
    setRichText('en-requirements', '<ul><li>Clear communication.</li></ul>');
    setRichText('bn-description', '<p>কমিউনিটি নিয়োগ সমন্বয় করুন।</p>');
    setRichText('bn-responsibilities', '<ul><li>ন্যায্য নিয়োগে নেতৃত্ব দিন।</li></ul>');
    setRichText('bn-requirements', '<ul><li>স্পষ্ট যোগাযোগ দক্ষতা।</li></ul>');
  };

  const fillWorkshopContent = () => {
    cy.get('#registration_opens_at').type(localDateTime(-1));
    cy.get('#registration_closes_at').type(localDateTime(24));
    cy.get('#starts_at').type(localDateTime(48));
    cy.get('#ends_at').type(localDateTime(51));
    cy.get('#attendance_mode').select('offline');
    cy.get('#registration_mode').select('manual');
    cy.get('label[for="capacity-limited"]').click();
    cy.get('#capacity').type('30');
    cy.get('#en-title').type('Browser Community Workshop');
    cy.get('#en-summary').type('A free browser-tested workshop.');
    cy.get('#en-facilitator').type('IGF Learning Team');
    cy.get('#en-venue').type('Community Hall');
    cy.get('#en-address').type('Dhanmondi, Dhaka');
    cy.get('#bn-title').type('ব্রাউজার কমিউনিটি কর্মশালা');
    cy.get('#bn-summary').type('বিনামূল্যের ব্রাউজার পরীক্ষিত কর্মশালা।');
    cy.get('#bn-facilitator').type('আইজিএফ লার্নিং টিম');
    cy.get('#bn-venue').type('কমিউনিটি হল');
    cy.get('#bn-address').type('ধানমন্ডি, ঢাকা');
    setRichText('en-description', '<p>Practice inclusive community leadership.</p>');
    setRichText('en-instructions', '<p>Registration is always free.</p>');
    setRichText('bn-description', '<p>অন্তর্ভুক্তিমূলক কমিউনিটি নেতৃত্ব অনুশীলন করুন।</p>');
    setRichText('bn-instructions', '<p>নিবন্ধন সবসময় বিনামূল্যে।</p>');
  };

  beforeEach(() => {
    resetFixture();
  });

  it('enforces the forced-password flow and role-scoped navigation and routes', () => {
    loginFixtureAdmin('cypress-hr', '/admin/password');
    cy.get('h1').should('contain.text', 'Change password');
    cy.get('[role="alert"], .alert-warning').should('contain.text', 'temporary password');
    cy.request({
      url: '/admin/workshops',
      followRedirect: false,
      failOnStatusCode: false,
    }).then((response) => {
      expect(response.status).to.eq(302);
      expect(response.redirectedToUrl).to.match(/\/admin\/password$/);
    });

    const replacementPassword = 'Cypress-Hr-Rotation!2026';
    cy.get('#password').type(replacementPassword, { log: false });
    cy.get('#password_confirmation').type(replacementPassword, { log: false });
    cy.contains('button', 'Change password').click();
    cy.location('pathname').should('eq', '/admin');
    cy.get('#left-panel').should('contain.text', 'Recruitment').and('not.contain.text', 'Workshops');
    cy.request('/admin/recruitment/jobs').its('status').should('eq', 200);
    cy.request({ url: '/admin/workshops', failOnStatusCode: false }).its('status').should('eq', 403);
    logout();

    loginFixtureAdmin('cypress-workshop');
    cy.get('#left-panel').should('contain.text', 'Workshops').and('not.contain.text', 'Recruitment');
    cy.request('/admin/workshops').its('status').should('eq', 200);
    cy.request({ url: '/admin/recruitment/jobs', failOnStatusCode: false }).its('status').should('eq', 403);
    logout();

    loginFixtureAdmin('cypress-combined');
    cy.get('#left-panel').should('contain.text', 'Recruitment').and('contain.text', 'Workshops');
    cy.request('/admin/recruitment/jobs').its('status').should('eq', 200);
    cy.request('/admin/workshops').its('status').should('eq', 200);
    logout();

    loginOwner();
    cy.get('#left-panel').should('contain.text', 'Recruitment').and('contain.text', 'Workshops');
    cy.viewport(390, 844);
    cy.visit('/admin');
    cy.get('#menuToggle').focus().should('be.focused').and('have.attr', 'aria-expanded', 'false').type('{enter}');
    cy.get('#menuToggle').should('have.attr', 'aria-expanded', 'true');
    cy.get('#left-panel').should('be.visible').and('have.attr', 'aria-label', 'Administration navigation');
    cy.get('#left-panel summary[aria-label="Recruitment navigation"]').should('be.visible').click();
    assertNoHorizontalOverflow();
  });

  it('creates, edits, publishes, closes, and duplicates a bilingual job', () => {
    loginOwner();
    cy.visit('/admin/recruitment/jobs/create');
    cy.get('#job-form-title').should('contain.text', 'Create job');
    cy.get('#form-help').should('contain.text', 'CV remains mandatory, PDF-only, and at most 5 MB');
    fillJobContent();
    cy.get('#add-scorecard-criterion').click();
    cy.get('#criterion-0-label').type('Inclusive hiring evidence');
    cy.get('#criterion-0-description').type('Assess concrete evidence.');
    cy.get('#criterion-0-maximum').clear().type('10');
    cy.contains('button', 'Create draft').click();

    cy.location('pathname').should('match', /^\/admin\/recruitment\/jobs\/[0-9a-f-]+\/edit$/);
    cy.get('#job-form-title').should('contain.text', 'Edit job');
    cy.get('#en-title').clear().type('Browser Hiring Coordinator Updated');
    cy.get('#bn-title').clear().type('ব্রাউজার নিয়োগ সমন্বয়কারী আপডেট');
    cy.contains('button', 'Save job').click();
    cy.get('#en-title').should('have.value', 'Browser Hiring Coordinator Updated');

    cy.visit('/admin/recruitment/jobs');
    rowContaining('Browser Hiring Coordinator Updated').within(() => {
      cy.contains('span', 'Draft').should('be.visible');
      cy.contains('button', 'Publish').click();
    });
    rowContaining('Browser Hiring Coordinator Updated').within(() => {
      cy.contains('span', 'Published').should('be.visible');
      cy.contains('button', 'Close now').click();
    });

    cy.visit('/careers/browser-hiring-coordinator?lang=en');
    cy.get('h1').should('contain.text', 'Browser Hiring Coordinator Updated');
    cy.get('#job-closed-title').should('contain.text', 'Applications closed');
    cy.get('.igf-schema-form').should('not.exist');

    cy.visit('/admin/recruitment/jobs');
    rowContaining('Browser Hiring Coordinator Updated').within(() => {
      cy.contains('button', 'Duplicate').click();
    });
    cy.location('pathname').should('match', /^\/admin\/recruitment\/jobs\/[0-9a-f-]+\/edit$/);
    cy.get('#en-title').should('have.value', 'Browser Hiring Coordinator Updated');
    cy.get('input[name="editor_version"]').should('have.value', '1');
  });

  it('keeps workshops free through create, edit, publish, close, and duplicate', () => {
    loginOwner();
    cy.viewport(390, 844);
    cy.visit('/admin/workshops/create');
    cy.get('#workshop-form-title').should('contain.text', 'Create workshop');
    cy.contains('strong', 'Always free.').should('be.visible');
    cy.get('input[name*="price"], input[name*="fee"], input[name*="payment"], select[name*="payment"]').should('not.exist');
    fillWorkshopContent();
    assertNoHorizontalOverflow();
    cy.contains('button', 'Create free workshop draft').click();

    cy.location('pathname').should('match', /^\/admin\/workshops\/[0-9a-f-]+\/edit$/);
    cy.get('#en-title').clear().type('Browser Community Workshop Updated');
    cy.get('#bn-title').clear().type('ব্রাউজার কমিউনিটি কর্মশালা আপডেট');
    cy.contains('button', 'Save workshop').click();
    cy.get('#en-title').should('have.value', 'Browser Community Workshop Updated');

    cy.visit('/admin/workshops');
    rowContaining('Browser Community Workshop Updated').within(() => {
      cy.contains('small', 'Always free').should('be.visible');
      cy.contains('button', 'Review & publish').click();
    });
    rowContaining('Browser Community Workshop Updated').within(() => {
      cy.contains('span', 'Published').should('be.visible');
      cy.contains('button', 'Close registration').click();
    });

    cy.visit('/workshops/browser-community-workshop?lang=en');
    cy.get('h1').should('contain.text', 'Browser Community Workshop Updated');
    cy.get('#workshop-closed-title').should('contain.text', 'Registration closed');
    cy.get('.igf-schema-form').should('not.exist');
    cy.get('body').should('not.contain.text', 'Payment').and('not.contain.text', 'Certificate');

    cy.visit('/admin/workshops');
    rowContaining('Browser Community Workshop Updated').within(() => {
      cy.contains('button', 'Duplicate').click();
    });
    cy.location('pathname').should('match', /^\/admin\/workshops\/[0-9a-f-]+\/edit$/);
    cy.get('#en-title').should('have.value', 'Browser Community Workshop Updated');
    cy.contains('strong', 'Always free.').should('be.visible');
  });

  it('builds, reorders, conditions, previews, and publishes a bilingual form draft', () => {
    loginOwner();
    cy.visit('/admin/recruitment/forms');
    rowContaining('Cypress Browser Builder').within(() => {
      cy.contains('a', 'Edit').click();
    });
    cy.get('#application-form-builder').should('have.attr', 'data-purpose', 'job');
    cy.get('[data-field-index]').should('have.length', 4);

    cy.get('[data-add-field]').first().click();
    cy.get('[data-field-index]').last().as('eligibility');
    cy.get('@eligibility').find('[data-copy-locale="en"][data-copy-key="label"]').clear().type('Eligibility');
    cy.get('@eligibility').find('[data-copy-locale="bn"][data-copy-key="label"]').clear().type('যোগ্যতা');
    cy.get('@eligibility').find('[data-field-type]').select('dropdown');
    cy.get('[data-field-index]').last().within(() => {
      cy.get('[data-required]').check();
      cy.get('[data-option-index="0"] [data-option-locale="en"]').clear().type('Eligible');
      cy.get('[data-option-index="0"] [data-option-locale="bn"]').clear().type('যোগ্য');
      cy.get('[data-option-index="1"] [data-option-locale="en"]').clear().type('Not eligible');
      cy.get('[data-option-index="1"] [data-option-locale="bn"]').clear().type('যোগ্য নন');
    });

    cy.get('[data-add-field]').first().click();
    cy.get('[data-field-index]').last().within(() => {
      cy.get('[data-copy-locale="en"][data-copy-key="label"]').clear().type('Motivation detail');
      cy.get('[data-copy-locale="bn"][data-copy-key="label"]').clear().type('প্রেরণার বিবরণ');
      cy.get('[data-required]').check();
      cy.get('[data-move-field="up"]').click();
    });
    cy.get('[data-summary-label]').then(($labels) => {
      const labels = [...$labels].map((label) => label.textContent.trim());
      expect(labels.slice(-2)).to.deep.eq(['Motivation detail', 'Eligibility']);
    });
    cy.contains('[data-field-index]', 'Motivation detail').find('[data-move-field="down"]').click();
    cy.get('[data-summary-label]').then(($labels) => {
      const labels = [...$labels].map((label) => label.textContent.trim());
      expect(labels.slice(-2)).to.deep.eq(['Eligibility', 'Motivation detail']);
    });

    cy.contains('[data-field-index]', 'Motivation detail').within(() => {
      cy.get('[data-add-condition]').click();
    });
    cy.contains('[data-field-index]', 'Motivation detail').within(() => {
      cy.get('[data-condition-key="source_key"]').select('Eligibility');
      cy.get('[data-condition-key="operator"]').select('equals');
      cy.get('[data-condition-key="value"]').select('Eligible');
    });

    cy.intercept('POST', '**/admin/recruitment/forms/*').as('saveForm');
    cy.get('[data-save-draft]').click();
    cy.wait('@saveForm').its('response.statusCode').should('eq', 200);
    cy.get('#afb-builder-alert').should('be.visible').and('be.focused').and('contain.text', 'Draft saved.');

    cy.contains('a', 'Preview EN').invoke('removeAttr', 'target').click();
    cy.get('#afb-preview-title').should('contain.text', 'Cypress Browser Builder');
    cy.contains('[data-preview-field]', 'Motivation detail').should('not.be.visible');
    cy.contains('[data-preview-field]', 'Eligibility').find('select').select('Eligible');
    cy.contains('[data-preview-field]', 'Motivation detail').should('be.visible');
    cy.contains('a', 'Back to builder').click();

    cy.on('window:confirm', () => true);
    cy.intercept('POST', '**/admin/recruitment/forms/*/publish').as('publishForm');
    cy.get('[data-publish-form]').click();
    cy.wait('@publishForm').its('response.statusCode').should('eq', 200);
    cy.location('pathname').should('match', /^\/admin\/recruitment\/forms\/[0-9a-f-]+\/edit$/);
    cy.get('#application-form-builder').should('have.attr', 'data-has-draft', '0');
    cy.get('[data-publish-form]').should('be.disabled');
    cy.get('.afb-builder-heading').should('contain.text', 'published version');
  });

  it('reviews job applications with private search, filters, columns, bulk actions, exports, and detail tools', () => {
    loginOwner();
    cy.visit(applicationsPath);
    cy.get('#ad-page-title').should('contain.text', 'Applications');
    cy.get('tbody tr').should('have.length', 3);

    cy.get('#ad-private-search').type('Alice Candidate');
    cy.get('.ad-private-search').contains('button', 'Search').click();
    cy.url().should('not.include', 'Alice%20Candidate').and('not.include', 'search=');
    cy.get('.ad-active-search').should('contain.text', 'Alice Candidate');
    cy.get('tbody tr').should('have.length', 1).and('contain.text', 'Alice Candidate');
    cy.contains('button', 'Clear search').click();

    cy.get('#ad-status').select('rejected');
    cy.get('#ad-sort').select('name');
    cy.get('#ad-direction').select('asc');
    cy.contains('button', 'Apply filters').click();
    cy.get('tbody tr').should('have.length', 1).and('contain.text', 'Charlie Candidate');
    cy.contains('a', 'Reset filters').click();

    cy.get('.ad-column-menu summary').click();
    cy.get('.ad-column-menu input[value="answer:motivation"]').uncheck();
    cy.get('.ad-column-menu').contains('button', 'Save my table').click();
    cy.get('[role="status"]').should('contain.text', 'Table preferences saved.');
    cy.get('thead').should('not.contain.text', 'Motivation');
    cy.get('.ad-column-menu summary').click();
    cy.get('.ad-column-menu input[value="answer:motivation"]').check();
    cy.get('.ad-column-menu').contains('button', 'Save my table').click();
    cy.get('thead').should('contain.text', 'Motivation');

    cy.window().then((window) => {
      const writeText = cy.stub().resolves();
      Object.defineProperty(window.navigator, 'clipboard', { configurable: true, value: { writeText } });
      cy.wrap(writeText).as('writeClipboard');
    });
    rowContaining('Alice Candidate').find('[data-copy-value]').click();
    cy.get('@writeClipboard').should('have.been.calledWith', 'alice.candidate@example.test');
    cy.get('[data-copy-status]').should('contain.text', 'Copied to clipboard.');

    rowContaining('Alice Candidate').find('[data-row-select]').check();
    rowContaining('Bob Candidate').find('[data-row-select]').check();
    cy.get('[data-selected-count]').should('contain.text', '2 selected');
    cy.get('#ad-bulk-operation').select('status');
    cy.get('[data-bulk-status]').should('be.visible');
    cy.get('#ad-bulk-status-value').select('under_review');
    cy.contains('button', 'Apply to selected').click();
    cy.get('[role="status"]').should('contain.text', '2 application statuses updated.');

    cy.get('.ad-export-menu summary').click();
    cy.get('.ad-export-menu input[value="answer:motivation"]').check();
    cy.intercept('GET', '**/admin/recruitment/applications/export*').as('exportApplications');
    cy.contains('button', 'Download secure CSV').click();
    cy.wait('@exportApplications').then(({ response }) => {
      expect(response.statusCode).to.eq(200);
      expect(response.headers['content-type']).to.include('text/csv');
      expect(response.headers['content-disposition']).to.include('attachment');
      expect(response.body).to.include('Motivation');
      expect(response.body).to.include('Alice Candidate');
    });

    rowContaining('Alice Candidate').contains('a', 'Review').click();
    cy.get('#ad-detail-title').should('contain.text', 'Alice Candidate');
    cy.get('#ad-detail-assignee').select('Cypress Combined Manager');
    cy.contains('button', 'Save assignment').click();
    cy.get('[role="status"]').should('contain.text', 'Application assignment updated.');
    cy.get('#ad-detail-assignee').should('contain.text', 'Cypress Combined Manager');
    cy.get('#ad-detail-status').select('shortlisted');
    cy.contains('button', 'Update status').click();
    cy.get('[role="status"]').should('contain.text', 'Application status updated.');
    cy.get('.ad-detail-header').should('contain.text', 'Shortlisted');
    cy.get('#ad-note-body').type('Strong community facilitation evidence.');
    cy.contains('button', 'Add private note').click();
    cy.get('[role="status"]').should('contain.text', 'Private note added.');
    cy.get('[data-cy="private-notes"]').should('contain.text', 'Strong community facilitation evidence.');
    cy.get('[id^="score-"]').first().clear().type('8');
    cy.get('[id^="score-comment-"]').first().type('Clear evidence against the criterion.');
    cy.contains('button', 'Save score').click();
    cy.get('[role="status"]').should('contain.text', 'Score saved.');
    cy.get('#ad-scorecard-title').closest('section').should('contain.text', '8');

    cy.intercept('GET', '**/admin/recruitment/applications/*/documents/*').as('downloadCv');
    cy.contains('a', 'Download').click();
    cy.wait('@downloadCv').then(({ response }) => {
      expect(response.statusCode).to.eq(200);
      expect(response.headers['content-type']).to.include('application/pdf');
      expect(response.headers['cache-control']).to.include('no-store');
    });
  });

  it('reviews a workshop registration on mobile without introducing paid-workshop features', () => {
    loginOwner();
    cy.viewport(390, 844);
    cy.visit(registrationsPath);
    cy.get('#ad-page-title').should('contain.text', 'Registrations');
    cy.get('body').should('not.contain.text', 'Payment').and('not.contain.text', 'Certificate').and('not.contain.text', 'QR code');
    rowContaining('Pending Participant').contains('a', 'Review').click();
    cy.get('#ad-detail-title').should('contain.text', 'Pending Participant');
    cy.get('#ad-detail-assignee').select('Cypress Combined Manager');
    cy.contains('button', 'Save assignment').click();
    cy.get('[role="status"]').should('contain.text', 'Registration assignment updated.');
    cy.get('#ad-note-body').type('Confirmed after manual workshop review.');
    cy.contains('button', 'Add private note').click();
    cy.get('[role="status"]').should('contain.text', 'Private note added.');
    cy.get('#ad-detail-status').select('confirmed');
    cy.contains('button', 'Update status').click();
    cy.get('[role="status"]').should('contain.text', 'Registration status updated.');
    cy.get('.ad-detail-header').should('contain.text', 'Confirmed');
    cy.get('#ad-history-title').closest('section').should('contain.text', 'Pending').and('contain.text', 'Confirmed');
    assertNoHorizontalOverflow();
  });

  it('maps, previews, rejects invalid CSV rows, downloads safe errors, and confirms a corrected import', () => {
    loginOwner();
    cy.visit(`/admin/recruitment/imports/create?listing=${jobUuid}`);
    cy.get('#import-file').selectFile('cypress/fixtures/admin-applications-invalid.csv');
    cy.contains('button', 'Upload and map columns').click();
    cy.get('#import-preview-title').should('contain.text', 'Map CSV columns');
    cy.get('#mapping-0').should('have.value', 'applicant_name');
    cy.get('#mapping-1').should('have.value', 'email');
    cy.get('#mapping-2').should('have.value', 'phone');
    cy.get('#mapping-3').should('have.value', 'motivation');
    cy.get('input[name="duplicate_policy"][value="update"]').check();
    cy.contains('button', 'Generate reviewed preview').click();
    cy.get('#import-preview-title').should('contain.text', 'Review import preview');
    cy.contains('.card', 'Total rows').should('contain.text', '2');
    cy.contains('.card', 'Valid rows').should('contain.text', '1');
    cy.contains('.card', 'Invalid rows').should('contain.text', '1');
    cy.get('[role="alert"]').should('contain.text', 'cannot be confirmed');
    cy.intercept('GET', '**/admin/recruitment/imports/*/errors*').as('errorReport');
    cy.contains('a', 'Download the safe error report').click();
    cy.wait('@errorReport').then(({ response }) => {
      expect(response.statusCode).to.eq(200);
      expect(response.headers['content-type']).to.include('text/csv');
      expect(response.body).to.include('Validation errors');
      expect(response.body).not.to.include('not-an-email');
    });

    cy.contains('a', 'CSV imports').click();
    cy.contains('a', 'Upload CSV').click();
    cy.get('#import-file').selectFile('cypress/fixtures/admin-applications-valid.csv');
    cy.contains('button', 'Upload and map columns').click();
    cy.get('#mapping-0').should('have.value', 'applicant_name');
    cy.get('#mapping-1').should('have.value', 'email');
    cy.get('#mapping-2').should('have.value', 'phone');
    cy.get('#mapping-3').should('have.value', 'motivation');
    cy.get('input[name="duplicate_policy"][value="update"]').check();
    cy.contains('button', 'Generate reviewed preview').click();
    cy.contains('.card', 'Valid rows').should('contain.text', '2');
    cy.contains('.card', 'Invalid rows').should('contain.text', '0');
    cy.get('input[name="confirm_import"]').check();
    cy.contains('button', 'Confirm and import').click();
    cy.get('#import-result-title').should('contain.text', 'CSV import Completed');
    cy.get('[role="status"]').should('contain.text', 'Import completed.');
    cy.contains('dt', 'Imported creates or updates').next('dd').should('contain.text', '2');

    cy.visit(applicationsPath);
    cy.get('#ad-private-search').type('Imported One');
    cy.get('.ad-private-search').contains('button', 'Search').click();
    cy.get('tbody tr').should('have.length', 1).and('contain.text', 'Imported One').and('contain.text', 'Programme delivery');
  });
});

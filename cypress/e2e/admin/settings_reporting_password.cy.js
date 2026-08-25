/* eslint-disable no-undef */

const openSettingsGroup = (selector) => {
  cy.get(selector).then(($group) => {
    if (!$group.prop('open')) {
      cy.wrap($group).find('summary').first().click();
    }
  });
};

describe('administrator settings and reporting workflows', () => {
  beforeEach(() => {
    cy.loginWithUsername();
  });

  it('publishes an office-address change to the public footer and restores it', () => {
    const address = 'QA Dynamic Office ' + Date.now().toString(36) + ', Dhaka';

    cy.visit('/admin/site-settings?locale=en#settings-contact');
    openSettingsGroup('#settings-contact');

    cy.get('#setting-contact-address').invoke('val').then((originalAddress) => {
      cy.get('#setting-contact-address').clear().type(address);
      cy.get('#customizer-save').should('not.be.disabled').click();

      cy.location('pathname').should('eq', '/admin/site-settings');
      cy.get('#setting-contact-address').should('have.value', address);

      cy.visit('/');
      cy.get('.footer-contact__address', { timeout: 20000 }).should('contain.text', address);

      cy.visit('/admin/site-settings?locale=en#settings-contact');
      openSettingsGroup('#settings-contact');
      cy.get('#setting-contact-address')
        .invoke('val', String(originalAddress || ''))
        .trigger('input');
      cy.get('#customizer-save').should('not.be.disabled').click();
      cy.location('pathname').should('eq', '/admin/site-settings');
      cy.get('#setting-contact-address').should('have.value', String(originalAddress || ''));
    });
  });

  it('renders donation cause analytics as a chart with an accessible data table', () => {
    cy.visit('/admin/donations');

    cy.get('#cause-attribution-title').should('contain.text', 'Successful giving by donor-selected cause');
    cy.get('#cause-attribution-chart')
      .should('be.visible')
      .and('have.attr', 'role', 'img')
      .and('have.attr', 'aria-describedby')
      .and('contain', 'cause-attribution-table-caption');

    cy.get('#cause-attribution-table-caption')
      .closest('table')
      .find('tbody tr')
      .should('have.length.greaterThan', 0);

    cy.window().then((window) => {
      expect(window.Chart).to.be.a('function');
      expect(Object.keys(window.Chart.instances || {})).not.to.be.empty;
    });
  });
});

const resetAdminPassword = "\\App\\Models\\Admin::query()->where('username', env('LOCAL_ADMIN_USERNAME'))->firstOrFail()->forceFill(['password' => \\Illuminate\\Support\\Facades\\Hash::make(env('LOCAL_ADMIN_PASSWORD')), 'must_change_password' => false, 'password_changed_at' => null, 'auth_version' => 0, 'remember_token' => null])->save()";

describe('administrator password workflow', () => {
  beforeEach(() => {
    cy.then(() => Cypress.session.clearAllSavedSessions());
    cy.php(resetAdminPassword).should('eq', true);
    cy.loginWithUsername();
  });

  afterEach(() => {
    cy.php(resetAdminPassword).should('eq', true);
    cy.then(() => Cypress.session.clearAllSavedSessions());
  });

  it('redirects to the dashboard after a successful password change', () => {
    const replacementPassword = 'CypressQaReplacement!2026';

    cy.visit('/admin/password');
    cy.env(['ADMIN_PASSWORD'], { log: false }).then(({ ADMIN_PASSWORD: currentPassword }) => {
      cy.get('#current_password').type(currentPassword, { log: false });
    });
    cy.get('#password').type(replacementPassword, { log: false });
    cy.get('#password_confirmation').type(replacementPassword, { log: false });
    cy.contains('button[type="submit"]', 'Change password').click();

    cy.location('pathname').should('eq', '/admin');
    cy.get('#admin-content').should('exist');
  });
});

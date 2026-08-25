import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Zakat from '@/Pages/zakat.vue';

const layoutStub = { template: '<main><slot /></main>' };

function calculatorSettings(overrides = {}) {
  return {
    enabled: true,
    title: 'Personal Zakat estimator',
    introduction: 'A transparent estimate.',
    nisab_default_basis: 'silver',
    nisab_weight_standard: 'standard_87_48_612_36',
    gold_price_per_gram: 10000,
    silver_price_per_gram: 100,
    nisab_price_updated_at: new Date().toISOString(),
    nisab_source_label: 'Trusted market source',
    nisab_source_url: 'https://prices.example.test/metals',
    nisab_method_label: 'Choose Gold or Silver Nisab',
    gold_method_label: 'Gold method',
    silver_method_label: 'Silver method',
    calculated_nisab_label: 'Calculated threshold',
    lunar_year_question: 'Has one lunar year passed?',
    lunar_year_yes_label: 'Yes, the lunar year has passed.',
    lunar_year_no_label: 'No, the lunar year has not passed.',
    property_for_resale_label: 'Property held specifically for resale',
    net_rental_income_label: 'Net rent still held on the Zakat date',
    exclusions_note: 'Do not include your home or rental-property capital.',
    immediate_debt_label: 'Eligible debt currently due',
    immediate_debt_help: 'Do not enter the whole balance of a long-term loan.',
    assets_legend: 'Eligible assets',
    liabilities_legend: 'Current liabilities only',
    amount_placeholder: 'Enter BDT',
    estimate_eyebrow: 'Estimate',
    total_assets_label: 'Total eligible assets',
    less_liabilities_label: 'Current liabilities',
    net_amount_label: 'Net eligible wealth',
    result_label: 'Estimated Zakat due',
    donate_label: 'Give Zakat',
    impact_view_details_label: 'View full details',
    impact_close_label: 'Close details',
    impact_dialog_eyebrow: 'Zakat impact',
    disclaimer: 'Ask a qualified scholar about personal circumstances.',
    food_title: 'Food security',
    food_body: 'Nutritious food and emergency assistance.',
    food_details: 'Complete food-support information.',
    food_image: '/food.jpg',
    food_image_alt: 'Food support',
    livelihood_title: 'Sustainable livelihoods',
    livelihood_body: 'Training and productive assets.',
    livelihood_details: 'Complete livelihood-support information.',
    livelihood_image: '/livelihood.jpg',
    livelihood_image_alt: 'Livelihood support',
    education_title: 'Education access',
    education_body: 'Learning support for children.',
    education_details: 'Complete education-support information.',
    education_image: '/education.jpg',
    education_image_alt: 'Education support',
    ...overrides,
  };
}

function mountCalculator(overrides = {}, mountOptions = {}) {
  usePage().props = {
    data: { zakat: { visible_blocks: [{ type: 'hero' }] } },
    siteSettings: {
      zakat_calculator: calculatorSettings(overrides),
      regional: { number_locale: 'en-BD', date_locale: 'en-BD', timezone: 'Asia/Dhaka' },
    },
  };
  const routeMock = vi.fn((name, value) => `/${name}/${value || ''}`);
  vi.stubGlobal('route', routeMock);

  return mount(Zakat, {
    attachTo: mountOptions.attachTo,
    global: {
      mocks: { route: routeMock },
      stubs: {
        Layout: layoutStub,
        App: layoutStub,
        AppBannerPage: true,
        PageBlocks: true,
      },
    },
  });
}

describe('public Zakat estimator', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  test('offers accessible details and Zakat checkout controls on every impact card', async () => {
    const wrapper = mountCalculator({ donate_label: 'Support with Zakat' });
    const cards = wrapper.findAll('.igf-zakat-impact__grid article');
    const donateLinks = wrapper.findAll('.igf-zakat-impact__donate');
    const detailsButtons = wrapper.findAll('.igf-zakat-impact__details');

    expect(cards).toHaveLength(3);
    expect(detailsButtons).toHaveLength(3);
    expect(detailsButtons.map(button => button.element.tagName)).toEqual(['BUTTON', 'BUTTON', 'BUTTON']);
    expect(detailsButtons.map(button => button.attributes('type'))).toEqual(['button', 'button', 'button']);
    expect(detailsButtons.map(button => button.attributes('aria-haspopup'))).toEqual(['dialog', 'dialog', 'dialog']);
    expect(detailsButtons.map(button => button.attributes('aria-controls'))).toEqual(['zakat-impact-dialog', 'zakat-impact-dialog', 'zakat-impact-dialog']);
    expect(detailsButtons.map(button => button.attributes('aria-label'))).toEqual(['View full details: Food security', 'View full details: Sustainable livelihoods', 'View full details: Education access']);
    expect(donateLinks).toHaveLength(3);
    expect(donateLinks.map(link => link.element.tagName)).toEqual(['A', 'A', 'A']);
    expect(donateLinks.map(link => link.text())).toEqual(['Support with Zakat', 'Support with Zakat', 'Support with Zakat']);
    expect(donateLinks.map(link => link.attributes('href'))).toEqual([
      '/frontend.donate.cause/zakat',
      '/frontend.donate.cause/zakat',
      '/frontend.donate.cause/zakat',
    ]);
    expect(donateLinks.map(link => link.attributes('aria-label'))).toEqual([
      'Support with Zakat: Food security',
      'Support with Zakat: Sustainable livelihoods',
      'Support with Zakat: Education access',
    ]);
    donateLinks.forEach(link => expect(link.get('i').attributes('aria-hidden')).toBe('true'));
    await donateLinks[0].trigger('click');
    await nextTick();
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    expect(route).toHaveBeenCalledWith('frontend.donate.cause', 'zakat');
  });

  test('opens every impact card with its matching admin-managed full content', async () => {
    const wrapper = mountCalculator({
      food_details: 'Complete food-support information.',
      livelihood_details: 'Complete livelihood-support information.',
      education_details: 'Complete education-support information.',
    });
    const cases = [
      { title: 'Food security', details: 'Complete food-support information.', image: '/food.jpg', alt: 'Food support' },
      { title: 'Sustainable livelihoods', details: 'Complete livelihood-support information.', image: '/livelihood.jpg', alt: 'Livelihood support' },
      { title: 'Education access', details: 'Complete education-support information.', image: '/education.jpg', alt: 'Education support' },
    ];
    const cards = wrapper.findAll('.igf-zakat-impact__grid article');

    for (const [index, expected] of cases.entries()) {
      await cards[index].get('img').trigger('click');
      await nextTick();

      const dialogs = wrapper.findAll('[role="dialog"][aria-modal="true"]');
      expect(dialogs).toHaveLength(1);
      const dialog = dialogs[0];
      expect(dialog.attributes()).toMatchObject({
        id: 'zakat-impact-dialog',
        'aria-labelledby': 'zakat-impact-dialog-title',
        'aria-describedby': 'zakat-impact-dialog-description',
      });
      expect(dialog.get('#zakat-impact-dialog-title').text()).toBe(expected.title);
      expect(dialog.get('#zakat-impact-dialog-description').text()).toBe(expected.details);
      expect(dialog.get('img').attributes()).toMatchObject({ src: expected.image, alt: expected.alt });
      expect(dialog.get('.igf-donate').attributes()).toMatchObject({
        href: '/frontend.donate.cause/zakat',
        'aria-label': `Give Zakat: ${expected.title}`,
      });

      await wrapper.get('.igf-impact-dialog__close').trigger('click');
      await nextTick();
      expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    }
  });

  test('falls back to the card summary and renders admin detail text as escaped plain text', async () => {
    const unsafeText = '<img src=x onerror=alert(1)><script>alert(2)</script>';
    const wrapper = mountCalculator({
      food_body: 'Fallback food summary.',
      food_details: '   ',
      livelihood_details: unsafeText,
    });
    const triggers = wrapper.findAll('.igf-zakat-impact__details');

    await triggers[0].trigger('click');
    await nextTick();
    expect(wrapper.get('#zakat-impact-dialog-description').text()).toBe('Fallback food summary.');
    await wrapper.get('.igf-impact-dialog__close').trigger('click');

    await triggers[1].trigger('click');
    await nextTick();
    const dialog = wrapper.get('.igf-impact-dialog__panel');
    expect(dialog.get('#zakat-impact-dialog-description').text()).toBe(unsafeText);
    expect(dialog.find('script').exists()).toBe(false);
    expect(dialog.find('[onerror]').exists()).toBe(false);
  });

  test('traps dialog focus and restores the originating card trigger after every close path', async () => {
    const wrapper = mountCalculator({}, { attachTo: document.body });

    try {
      const trigger = wrapper.findAll('.igf-zakat-impact__details')[1];
      trigger.element.focus();
      await trigger.trigger('click');
      await nextTick();

      const close = wrapper.get('.igf-impact-dialog__close');
      const dialogDonate = wrapper.get('.igf-impact-dialog__content .igf-donate');
      expect(document.activeElement).toBe(close.element);

      dialogDonate.element.focus();
      await dialogDonate.trigger('keydown', { key: 'Tab' });
      expect(document.activeElement).toBe(close.element);

      close.element.focus();
      await close.trigger('keydown', { key: 'Tab', shiftKey: true });
      expect(document.activeElement).toBe(dialogDonate.element);

      await dialogDonate.trigger('keydown', { key: 'Escape' });
      await nextTick();
      expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
      expect(document.activeElement).toBe(trigger.element);

      await trigger.trigger('click');
      await nextTick();
      await wrapper.get('.igf-impact-dialog__description').trigger('click');
      expect(wrapper.find('[role="dialog"]').exists()).toBe(true);
      await wrapper.get('.igf-impact-dialog').trigger('click');
      await nextTick();
      expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
      expect(document.activeElement).toBe(trigger.element);

      await trigger.trigger('click');
      await nextTick();
      await wrapper.get('.igf-impact-dialog__close').trigger('click');
      await nextTick();
      expect(document.activeElement).toBe(trigger.element);
    } finally {
      wrapper.unmount();
    }
  });


  test('calculates the selected Gold or Silver threshold from protected weights', async () => {
    const wrapper = mountCalculator();

    expect(wrapper.get('#nisab-basis-silver').element.checked).toBe(true);
    expect(wrapper.vm.nisab).toBeCloseTo(612.36 * 100, 6);
    expect(wrapper.text()).toContain('612.36 grams');
    expect(wrapper.text()).toContain('87.48 grams');
    expect(wrapper.get('.igf-price-source a').attributes()).toMatchObject({
      href: 'https://prices.example.test/metals',
      rel: 'noopener noreferrer',
      target: '_blank',
    });

    await wrapper.get('#nisab-basis-gold').setValue();

    expect(wrapper.vm.selectedBasis).toBe('gold');
    expect(wrapper.vm.nisab).toBeCloseTo(87.48 * 10000, 6);
    expect(wrapper.text()).toContain('Gold method');
  });

  test('uses an admin-selected approved weight standard and rejects an unknown payload', async () => {
    const wrapper = mountCalculator({
      nisab_weight_standard: 'standard_85_595',
      methodology: 'Active method: {gold_weight}g gold / {silver_weight}g silver at {rate}%.',
    });

    expect(wrapper.vm.selectedWeightStandard).toBe('standard_85_595');
    expect(wrapper.vm.nisab).toBeCloseTo(595 * 100, 6);
    expect(wrapper.text()).toContain('595 grams');
    expect(wrapper.text()).toContain('85 grams');
    expect(wrapper.get('.igf-methodology').text()).toContain('85g gold / 595g silver at 2.5%');

    await wrapper.get('#nisab-basis-gold').setValue();

    expect(wrapper.vm.nisab).toBeCloseTo(85 * 10000, 6);
    expect(wrapper.vm.nisabWeights).toEqual({ gold: 85, silver: 595 });

    const fallback = mountCalculator({ nisab_weight_standard: 'unapproved_custom_formula' });
    expect(fallback.vm.selectedWeightStandard).toBe('standard_87_48_612_36');
    expect(fallback.vm.nisab).toBeCloseTo(612.36 * 100, 6);
  });

  test('requires both the exact Nisab threshold and lunar-year confirmation', async () => {
    const wrapper = mountCalculator();
    await wrapper.get('#asset-cash').setValue('61235');
    await wrapper.get('#zakat-haul-confirmation').setValue(true);

    expect(wrapper.vm.meetsNisab).toBe(false);
    expect(wrapper.vm.zakatDue).toBe(0);
    expect(wrapper.get('.igf-zakat-due').text()).toContain('below the selected Nisab threshold');

    await wrapper.get('#asset-cash').setValue('70000');
    await wrapper.get('#liability-debtsDue').setValue('8764');
    await wrapper.get('#zakat-haul-confirmation').setValue(false);

    expect(wrapper.vm.eligibleAmount).toBeCloseTo(61236, 6);
    expect(wrapper.vm.meetsNisab).toBe(true);
    expect(wrapper.vm.zakatDue).toBe(0);
    expect(wrapper.get('.igf-zakat-due').text()).toContain('lunar-year condition is not yet confirmed');

    await wrapper.get('#zakat-haul-confirmation').setValue(true);

    expect(wrapper.vm.zakatDue).toBeCloseTo(1530.9, 6);
    expect(wrapper.get('.igf-zakat-due').text()).toContain('lunar-year condition is confirmed');
    expect(wrapper.text()).toContain('2.5%');
  });

  test('clamps unsafe numbers and never allows liabilities to create negative wealth', async () => {
    const wrapper = mountCalculator();
    const cashInput = wrapper.get('#asset-cash');

    expect(cashInput.attributes('max')).toBe('999999999999.99');
    wrapper.vm.assets.cash = Number.POSITIVE_INFINITY;
    await nextTick();
    expect(wrapper.vm.totalAssets).toBe(0);

    wrapper.vm.assets.cash = Number.MAX_SAFE_INTEGER;
    wrapper.vm.liabilities.debtsDue = Number.MAX_SAFE_INTEGER;
    await nextTick();
    expect(wrapper.vm.totalAssets).toBe(999999999999.99);
    expect(wrapper.vm.totalLiabilities).toBe(999999999999.99);
    expect(wrapper.vm.eligibleAmount).toBe(0);
    expect(wrapper.vm.zakatDue).toBe(0);
  });

  test('clearly separates resale property from retained rent and current liabilities', () => {
    const wrapper = mountCalculator();

    expect(wrapper.get('label[for="asset-resaleProperty"]').text()).toContain('Property held specifically for resale');
    expect(wrapper.get('#asset-resaleProperty-help').text()).toContain('Do not include your home or rental-property capital');
    expect(wrapper.get('label[for="asset-retainedRentalIncome"]').text()).toContain('Net rent still held on the Zakat date');
    expect(wrapper.get('#asset-retainedRentalIncome-help').text()).toContain('not enter the same retained rental income again under cash and bank balances');
    expect(wrapper.get('label[for="liability-debtsDue"]').text()).toContain('Eligible debt currently due');
    expect(wrapper.get('#liability-debtsDue-help').text()).toContain('long-term loan');
  });

  test('uses server freshness status instead of the visitor device clock', () => {
    const serverStale = mountCalculator({
      nisab_price_updated_at: new Date().toISOString(),
      nisab_prices_current: false,
      stale_price_notice: 'Server says these prices require verification.',
    });
    expect(serverStale.get('.igf-price-warning').text()).toContain('Server says');
    expect(serverStale.vm.nisabUsable).toBe(false);

    const serverCurrent = mountCalculator({
      nisab_price_updated_at: '2000-01-01',
      nisab_prices_current: true,
    });
    expect(serverCurrent.find('.igf-price-warning').exists()).toBe(false);
    expect(serverCurrent.vm.nisabUsable).toBe(true);
  });

  test('keeps results suppressed when the server rejects a fresh but invalid metal price', async () => {
    const wrapper = mountCalculator({
      nisab_default_basis: 'gold',
      gold_price_per_gram: 999,
      nisab_price_updated_at: new Date().toISOString(),
      nisab_prices_current: false,
      stale_price_notice: 'The configured metal prices require verification.',
      stale_price_result_note: 'No result is shown until the prices are corrected.',
    });
    wrapper.vm.assets.cash = 1000000;
    wrapper.vm.haulSatisfied = true;
    await nextTick();

    expect(wrapper.vm.nisabAvailable).toBe(true);
    expect(wrapper.vm.priceNeedsVerification).toBe(true);
    expect(wrapper.vm.nisabUsable).toBe(false);
    expect(wrapper.vm.meetsNisab).toBe(false);
    expect(wrapper.vm.zakatDue).toBe(0);
    expect(wrapper.get('.igf-price-warning').attributes('role')).toBe('alert');
    expect(wrapper.get('.igf-price-warning').text()).toContain('require verification');
    expect(wrapper.get('.igf-zakat-due').text()).toContain('until the prices are corrected');
  });

  test('shows stale thresholds for reference but suppresses results for stale, future, or missing price dates', async () => {
    const staleDate = new Date(Date.now() - (8 * 24 * 60 * 60 * 1000)).toISOString();
    const stale = mountCalculator({
      nisab_price_updated_at: staleDate,
      stale_price_notice: 'Verify these prices because they are more than seven days old.',
      stale_price_result_note: 'No Zakat due is shown until these prices are verified.',
    });
    stale.vm.assets.cash = 1000000;
    stale.vm.haulSatisfied = true;
    await nextTick();

    expect(stale.get('.igf-price-warning').attributes('role')).toBe('alert');
    expect(stale.get('.igf-price-warning').text()).toContain('more than seven days old');
    expect(stale.vm.nisab).toBeGreaterThan(0);
    expect(stale.vm.nisabUsable).toBe(false);
    expect(stale.vm.meetsNisab).toBe(false);
    expect(stale.vm.zakatDue).toBe(0);
    expect(stale.get('.igf-nisab').text()).toContain('Calculated threshold');
    expect(stale.get('.igf-zakat-due').text()).toContain('until these prices are verified');

    const future = mountCalculator({ nisab_price_updated_at: new Date(Date.now() + 86400000).toISOString() });
    future.vm.assets.cash = 1000000;
    future.vm.haulSatisfied = true;
    await nextTick();
    expect(future.find('.igf-price-warning').exists()).toBe(true);
    expect(future.vm.nisabUsable).toBe(false);
    expect(future.vm.zakatDue).toBe(0);

    const missing = mountCalculator({ nisab_price_updated_at: '' });
    missing.vm.assets.cash = 1000000;
    missing.vm.haulSatisfied = true;
    await nextTick();
    expect(missing.find('.igf-price-warning').exists()).toBe(true);
    expect(missing.vm.nisabUsable).toBe(false);
    expect(missing.vm.zakatDue).toBe(0);
  });

  test('rejects unsafe source links and clear preserves method while resetting values and lunar year', async () => {
    const wrapper = mountCalculator({
      nisab_source_url: 'javascript:alert(1)',
      nisab_source_label: 'Untrusted source label',
    });
    expect(wrapper.find('.igf-price-source a').exists()).toBe(false);
    expect(wrapper.get('.igf-price-source').text()).toContain('Untrusted source label');

    await wrapper.get('#nisab-basis-gold').setValue();
    await wrapper.get('#asset-cash').setValue('900000');
    await wrapper.get('#zakat-haul-confirmation').setValue(true);
    await wrapper.get('.igf-clear').trigger('click');

    expect(wrapper.vm.selectedBasis).toBe('gold');
    expect(wrapper.vm.assets.cash).toBe('');
    expect(wrapper.vm.haulSatisfied).toBe(false);
    expect(wrapper.vm.zakatDue).toBe(0);
  });
});

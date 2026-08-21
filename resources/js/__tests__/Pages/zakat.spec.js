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
    disclaimer: 'Ask a qualified scholar about personal circumstances.',
    ...overrides,
  };
}

function mountCalculator(overrides = {}) {
  usePage().props = {
    data: { zakat: { visible_blocks: [{ type: 'hero' }] } },
    siteSettings: {
      zakat_calculator: calculatorSettings(overrides),
      regional: { number_locale: 'en-BD', date_locale: 'en-BD', timezone: 'Asia/Dhaka' },
    },
  };
  vi.stubGlobal('route', vi.fn((name, value) => `/${name}/${value || ''}`));

  return mount(Zakat, {
    global: {
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

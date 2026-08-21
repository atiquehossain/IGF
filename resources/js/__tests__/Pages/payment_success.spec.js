import { mount } from '@vue/test-utils';
import { usePage } from '@inertiajs/vue3';
import PaymentResult from '@/Pages/payment_success.vue';

const layoutStub = { template: '<main><slot /></main>' };

function baseSettings() {
  return {
    success_eyebrow: 'Payment confirmed',
    success_title: 'Thank you for standing with communities.',
    success_note: 'Your successful contribution has been received and confirmed.',
    transaction_donor_label: 'Donor',
    transaction_amount_label: 'Amount',
    transaction_method_label: 'Payment method',
    transaction_reference_label: 'Reference',
    transaction_date_label: 'Date',
    home_label: 'Return home',
    another_donation_label: 'Make another donation',
  };
}

function transaction() {
  return {
    donor_name: 'Community Donor',
    amount: 500,
    currency: 'BDT',
    payment_method: 'bKash',
    reference: 'IGF-RESULT-1',
  };
}

function mountResult(data) {
  usePage().props = {
    data,
    siteSettings: {
      system_pages: baseSettings(),
      regional: {},
    },
  };
  usePage().url = '/donation/payment/result';
  globalThis.route = vi.fn(name => name);

  return mount(PaymentResult, {
    global: {
      stubs: {
        App: layoutStub,
        Layout: layoutStub,
      },
    },
  });
}

describe('payment result presentation state', () => {
  test('renders review with neutral copy and no confirmed-success affordances', () => {
    const wrapper = mountResult({
      result_state: 'review',
      result_copy: {
        eyebrow: 'Verification review',
        title: 'Payment under review',
        message: 'The payment was verified and is being reviewed by our team.',
        note: 'Keep this reference while our team checks the selected payment method.',
      },
      message: 'Compatibility message must not override the review copy.',
      transaction: transaction(),
      redirect_url: '/donate',
    });

    expect(wrapper.get('[data-result-state]').attributes('data-result-state')).toBe('review');
    expect(wrapper.get('[data-test="payment-result-icon"] i').classes()).toContain('fa-clock');
    expect(wrapper.text()).toContain('Verification review');
    expect(wrapper.text()).toContain('Payment under review');
    expect(wrapper.text()).toContain('being reviewed by our team');
    expect(wrapper.text()).not.toContain('Payment confirmed');
    expect(wrapper.text()).not.toContain('Thank you for standing with communities.');
    expect(wrapper.text()).not.toContain('has been received');
    expect(wrapper.find('.fa-check').exists()).toBe(false);
  });

  test('preserves the verified success presentation for an explicit success state', () => {
    const wrapper = mountResult({
      result_state: 'success',
      result_copy: {
        eyebrow: 'Unused controller heading',
        title: 'Unused controller title',
        message: 'Unused controller message',
        note: 'Unused controller note',
      },
      message: 'Thank you! Your donation of BDT 500.00 has been received.',
      transaction: transaction(),
      redirect_url: '/donate',
    });

    expect(wrapper.get('[data-result-state]').attributes('data-result-state')).toBe('success');
    expect(wrapper.get('[data-test="payment-result-icon"] i').classes()).toContain('fa-check');
    expect(wrapper.text()).toContain('Payment confirmed');
    expect(wrapper.text()).toContain('Thank you for standing with communities.');
    expect(wrapper.text()).toContain('has been received');
    expect(wrapper.text()).toContain('successful contribution');
  });

  test('fails closed to neutral review copy when the result state is missing or unknown', () => {
    const wrapper = mountResult({
      result_state: 'held',
      result_copy: {
        eyebrow: 'Payment confirmed',
        title: 'Successful donation',
        message: 'Your donation has been received.',
        note: 'Success note',
      },
      message: 'Your donation has been received.',
      transaction: transaction(),
      redirect_url: '/donate',
    });

    expect(wrapper.get('[data-result-state]').attributes('data-result-state')).toBe('review');
    expect(wrapper.text()).toContain('Payment under review');
    expect(wrapper.text()).toContain('not a final donation receipt');
    expect(wrapper.text()).not.toContain('Payment confirmed');
    expect(wrapper.text()).not.toContain('has been received');
    expect(wrapper.find('.fa-check').exists()).toBe(false);
  });
});

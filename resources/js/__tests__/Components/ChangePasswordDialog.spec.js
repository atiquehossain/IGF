import { mount } from '@vue/test-utils';
import ChangePasswordDialog from '@/component/change-password-dialog.vue';

const slotStub = { template: '<div><slot /></div>' };
const formStub = { template: '<form @submit.prevent="$emit(\'submit\')"><slot /></form>' };
const textFieldStub = {
  inheritAttrs: false,
  props: ['modelValue', 'error'],
  template: '<input v-bind="$attrs" :value="modelValue" :data-error="String(error)">',
};

describe('change password dialog feedback', () => {
  test('renders flash and field errors from the standard Inertia error bag', () => {
    const wrapper = mount(ChangePasswordDialog, {
      props: { showDialog: true, toggleDialog: vi.fn() },
      global: {
        mocks: {
          $page: {
            props: {
              errors: {
                current_password: 'The current password is incorrect.',
                password: 'The password confirmation does not match.',
                password_confirmation: ['Confirm the new password.'],
              },
              flash: { message: { type: 'error', text: 'Please correct the highlighted fields.' } },
            },
          },
        },
        stubs: {
          'v-dialog': slotStub,
          'v-card': slotStub,
          'v-card-title': slotStub,
          'v-card-text': slotStub,
          'v-form': formStub,
          'v-text-field': textFieldStub,
        },
      },
    });

    expect(wrapper.get('.form-feedback').attributes('role')).toBe('alert');
    expect(wrapper.get('.form-feedback').text()).toBe('Please correct the highlighted fields.');

    const fields = [
      ['#change-current-password', 'change-current-password-error', 'The current password is incorrect.'],
      ['#change-new-password', 'change-new-password-error', 'The password confirmation does not match.'],
      ['#change-password-confirmation', 'change-password-confirmation-error', 'Confirm the new password.'],
    ];
    for (const [selector, errorId, message] of fields) {
      const input = wrapper.get(selector);
      expect(input.attributes('aria-invalid')).toBe('true');
      expect(input.attributes('aria-describedby')).toBe(errorId);
      expect(input.attributes('data-error')).toBe('true');
      expect(wrapper.get(`#${errorId}`).text()).toBe(message);
    }
  });
});

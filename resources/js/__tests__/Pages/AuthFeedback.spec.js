import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Login from '@/Pages/auth/login.vue';
import Register from '@/Pages/auth/register.vue';
import LoginTwoFactor from '@/Pages/auth/login-2fa.vue';
import VerifyTwoFactor from '@/Pages/auth/login-2fa-verify.vue';

const cases = [
  {
    name: 'member login',
    component: Login,
    errors: { phone_no: 'Enter a valid phone number.', password: 'Enter your password.' },
    fields: [
      ['#login-phone', 'login-phone-error'],
      ['#login-password', 'login-password-error'],
    ],
  },
  {
    name: 'member registration',
    component: Register,
    errors: {
      name: 'Enter your name.',
      phone_no: 'Enter a valid phone number.',
      email: 'Enter a valid email.',
      org: 'Enter your organization.',
      designation: 'Enter your role.',
      password: 'Choose a stronger password.',
    },
    fields: [
      ['#registration-name', 'registration-name-error'],
      ['#registration-phone', 'registration-phone-error'],
      ['#registration-email', 'registration-email-error'],
      ['#registration-organization', 'registration-organization-error'],
      ['#registration-designation', 'registration-designation-error'],
      ['#registration-password', 'registration-password-error'],
    ],
  },
  {
    name: 'two-factor login',
    component: LoginTwoFactor,
    errors: { email: 'Enter your email.', password: 'Enter your password.' },
    fields: [
      ['#secure-email', 'secure-email-error'],
      ['#secure-password', 'secure-password-error'],
    ],
  },
  {
    name: 'two-factor verification',
    component: VerifyTwoFactor,
    errors: { code: 'Enter the six-digit code.' },
    fields: [
      ['#verification-code', 'verification-code-error'],
    ],
  },
];

describe('member authentication field feedback', () => {
  beforeEach(() => {
    usePage().props = {
      access_token: 'test-token',
      enrollment_required: false,
      siteSettings: {
        branding: {
          site_name: 'Ignite Global Foundation',
          logo: '/image/logo.png',
          footer_logo: '/image/logo-footer.png',
          logo_alt: 'Ignite Global Foundation',
        },
        member_area: { registration_enabled: false },
      },
    };
    vi.stubGlobal('route', vi.fn(name => `/${name}`));
  });

  afterEach(() => vi.unstubAllGlobals());

  test.each(cases)('$name associates every rendered server error with its field', async ({ component, errors, fields }) => {
    const wrapper = mount(component, {
      global: {
        stubs: { GuestLayout: { template: '<main><slot /></main>' } },
      },
    });

    Object.assign(wrapper.vm.form.errors, errors);
    await nextTick();

    for (const [selector, errorId] of fields) {
      const input = wrapper.get(selector);
      const message = wrapper.get(`#${errorId}`);
      expect(input.attributes('aria-invalid')).toBe('true');
      expect(input.attributes('aria-describedby')).toBe(errorId);
      expect(message.attributes('role')).toBe('alert');
      expect(message.text()).not.toBe('');
    }

    wrapper.unmount();
  });
});

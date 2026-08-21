import { mount } from '@vue/test-utils';
import { usePage } from '@inertiajs/vue3';
import Register from '@/Pages/auth/register.vue';

const memberCopy = {
  story_eyebrow: 'Member community',
  story_title: 'Work alongside Ignite.',
  story_body: 'Approved members receive secure access.',
  form_eyebrow: 'Administrator reviewed',
  registration_title: 'Request member access',
  registration_introduction: 'Tell the team who you are.',
  registration_name_label: 'Your full name',
  phone_label: 'Mobile number',
  phone_placeholder: '01XXXXXXXXX',
  registration_email_label: 'Work email',
  registration_organization_label: 'Organization name',
  registration_designation_label: 'Your role',
  password_label: 'Choose a password',
  hide_password_label: 'Hide password',
  show_password_label: 'Show password',
  registration_approval_note: 'An administrator must approve this request.',
  registration_submit_label: 'Send request',
  registration_sending_label: 'Sending request...',
  registration_login_label: 'Return to sign in',
};

describe('member registration page', () => {
  beforeEach(() => {
    usePage().props = {
      siteSettings: {
        branding: {
          site_name: 'Community Hope Network',
          logo: '/storage/media/community-logo.png',
          logo_alt: 'Community Hope Network logo',
        },
        member_area: memberCopy,
      },
    };
    vi.stubGlobal('route', vi.fn(name => `/${name}`));
  });

  afterEach(() => vi.unstubAllGlobals());

  test('uses administrator-managed branding and plain-language field copy', () => {
    const wrapper = mount(Register, {
      global: {
        stubs: { GuestLayout: { template: '<main><slot /></main>' } },
      },
    });

    expect(wrapper.get('img').attributes()).toMatchObject({
      src: '/storage/media/community-logo.png',
      alt: 'Community Hope Network logo',
    });
    expect(wrapper.get('#registration-title').text()).toBe('Request member access');
    expect(wrapper.text()).toContain('An administrator must approve this request.');
    expect(wrapper.get('#registration-phone').attributes('maxlength')).toBe('11');
    expect(wrapper.get('button[type="submit"]').text()).toContain('Send request');
    expect(wrapper.findAll('input')).toHaveLength(6);
  });
});

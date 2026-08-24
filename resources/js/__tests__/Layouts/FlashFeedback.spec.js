import { shallowMount } from '@vue/test-utils';
import { usePage } from '@inertiajs/vue3';
import App from '@/layouts/App.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';

function toastMock() {
  return {
    success: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
    error: vi.fn(),
  };
}

function setPage(message) {
  const page = usePage();
  page.component = 'Home/home';
  page.props = {
    appName: 'Ignite Global Foundation',
    locale: 'en',
    flash: { message },
    meta_tag: {},
    siteSettings: {},
  };
}

function installToast($toast) {
  return {
    install(app) {
      app.config.globalProperties.$toast = $toast;
    },
  };
}

describe('layout flash feedback', () => {
  afterEach(() => {
    usePage().props = {};
  });

  test('App announces an error that is already present when the layout mounts', () => {
    const $toast = toastMock();
    setPage({ type: 'error', text: 'The request could not be completed.' });

    const wrapper = shallowMount(App, { global: { plugins: [installToast($toast)] } });

    expect($toast.error).toHaveBeenCalledOnce();
    expect($toast.error).toHaveBeenCalledWith('The request could not be completed.');
    wrapper.unmount();
  });

  test('GuestLayout announces a success that is already present when the layout mounts', () => {
    const $toast = toastMock();
    setPage({ type: 'success', text: 'Your account is ready.' });

    const wrapper = shallowMount(GuestLayout, { global: { plugins: [installToast($toast)] } });

    expect($toast.success).toHaveBeenCalledOnce();
    expect($toast.success).toHaveBeenCalledWith('Your account is ready.');
    wrapper.unmount();
  });
});

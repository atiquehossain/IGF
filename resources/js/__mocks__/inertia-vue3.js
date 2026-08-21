import { reactive } from 'vue';

const currentPage = reactive({
  component: '',
  props: {},
  url: '/',
  version: '',
});

export const usePage = () => currentPage;

export const useForm = (values = {}) => reactive({
  ...values,
  errors: {},
  hasErrors: false,
  processing: false,
  recentlySuccessful: false,
  clearErrors() {},
  reset() {},
  post() {},
  put() {},
  patch() {},
  delete() {},
});

export const Head = { name: 'Head', template: '<span />' };
export const Link = { name: 'Link', template: '<a><slot /></a>' };

export const router = {
  delete() {},
  get() {},
  on() {},
  patch() {},
  post() {},
  put() {},
  reload() {},
  replace() {},
  visit() {},
};

export const plugin = {
  install(app) {
    app.config.globalProperties.$inertia = router;
    app.config.globalProperties.$page = currentPage;
  },
};

export const createInertiaApp = () => Promise.resolve();

import { createApp, h } from 'vue';
import { createInertiaApp, Link, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

import vuetify from './vuetify';  // Import your Vuetify configuration

import Toast, { POSITION, useToast } from 'vue-toastification';
import 'vue-toastification/dist/index.css';

import Datepicker from 'vue3-datepicker';
import { createBootstrap, modalManagerPlugin } from 'bootstrap-vue-next';
import * as BootstrapVueNext from 'bootstrap-vue-next';

import VueCookies from 'vue-cookies';
import AOS from 'aos';
import 'aos/dist/aos.css';

import mixin from './mixin/mixin.js';
import baseMixin from './base';
import ChangeFontSize from './libs/dynamic-font-size-changer';

import 'bootstrap';
import './bootstrap';

function executeFontSizeChange() {
  const savedFontSize = JSON.parse(localStorage.getItem('dynamicFontSize'));
  const changeFontSize = ChangeFontSize();
  if (savedFontSize !== undefined || savedFontSize !== 0) {
    const status = savedFontSize > 0 ? '+' : '-';
    changeFontSize(status, savedFontSize);
  }
}

router.on('navigate', () => {
  window.scrollTo(0, 0);
  if (typeof window.gtag === 'function' && window.igfAnalyticsId) {
    window.gtag('config', window.igfAnalyticsId);
  }
  executeFontSizeChange();
});

createInertiaApp({
  progress: {
    color: '#e87121',
    showSpinner: false,
  },
  resolve: name => resolvePageComponent(
    `./Pages/${name}.vue`,
    import.meta.glob('./Pages/**/*.vue'),
  ),
  setup({ el, App, props, plugin }) {
    const app = createApp({ render: () => h(App, props) });

    app.use(plugin);
    app.use(vuetify);
    app.use(Toast, { position: POSITION.TOP_RIGHT });
    app.use(createBootstrap);
    app.use(modalManagerPlugin);
    app.use(VueCookies, { expire: '7d' });

    app.component('Link', Link);
    app.component('Datepicker', Datepicker);

    Object.entries(BootstrapVueNext).forEach(([name, component]) => {
      if (name.startsWith('B')) app.component(name, component);
    });

    app.mixin(mixin);
    app.mixin(baseMixin);
    app.mixin({
      methods: {
        route,
        asset(path) {
          const basePath = window._asset || '';
          return basePath + path;
        },
      },
    });

    app.config.globalProperties.$toast = useToast();

    app.mount(el);
    AOS.init();
  },
});

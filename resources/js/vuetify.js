// resources/js/vuetify.js
import 'vuetify/styles';
import { createVuetify } from 'vuetify';
import {
  VApp,
  VAvatar,
  VBtn,
  VCard,
  VCardActions,
  VCardText,
  VCardTitle,
  VCarousel,
  VCarouselItem,
  VCheckbox,
  VChip,
  VCol,
  VContainer,
  VDialog,
  VDivider,
  VForm,
  VIcon,
  VImg,
  VList,
  VListGroup,
  VListItem,
  VListItemTitle,
  VMenu,
  VPagination,
  VProgressCircular,
  VRow,
  VSelect,
  VTextField,
  VTextarea,
} from 'vuetify/components';
import * as directives from 'vuetify/directives';
import '@mdi/font/css/materialdesignicons.css';

const components = {
  VApp,
  VAvatar,
  VBtn,
  VCard,
  VCardActions,
  VCardText,
  VCardTitle,
  VCarousel,
  VCarouselItem,
  VCheckbox,
  VChip,
  VCol,
  VContainer,
  VDialog,
  VDivider,
  VForm,
  VIcon,
  VImg,
  VList,
  VListGroup,
  VListItem,
  VListItemTitle,
  VMenu,
  VPagination,
  VProgressCircular,
  VRow,
  VSelect,
  VTextField,
  VTextarea,
};

// For testing, we need to mock the icon sets
// The actual imports would be:
// import { aliases, mdi } from 'vuetify/iconsets/mdi';
// But we'll use empty objects for testing
const aliases = {};
const mdi = {};

const vuetify = createVuetify({
  components,
  directives,
  icons: {
    defaultSet: 'mdi',
    aliases,
    sets: {
      mdi,
    },
  },
  // Add any other Vuetify configuration you need
  theme: {
    defaultTheme: 'light',
    themes: {
      light: {
        colors: {
          primary: '#FF7500',
          secondary: '#5CBBF6',
          // Add other colors as needed
        },
      },
    },
  },
});

export default vuetify;

// Also export Vuetify components for direct component registration
export { components as vuetifyComponents };

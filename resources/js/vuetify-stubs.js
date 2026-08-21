// resources/js/vuetify-stubs.js
/**
 * Stubs for Vuetify components in tests
 * This helps prevent "Failed to resolve component" errors 
 * when using Vuetify components in tests
 */

// List of common Vuetify components that might need stubbing
export const vuetifyStubs = {
  // Layout components
  'v-app': true,
  'v-app-bar': true,
  'v-navigation-drawer': true,
  'v-footer': true,
  'v-main': true,
  'v-system-bar': true,
  'v-toolbar': true,
  
  // Grid system
  'v-container': true,
  'v-row': true,
  'v-col': true,
  'v-spacer': true,
  
  // Common UI components
  'v-btn': true,
  'v-card': true,
  'v-card-title': true,
  'v-card-subtitle': true,
  'v-card-text': true,
  'v-card-actions': true,
  'v-list': true,
  'v-list-item': true,
  'v-list-item-title': true,
  'v-list-item-subtitle': true,
  'v-list-item-action': true,
  'v-list-item-icon': true,
  'v-list-item-content': true,
  'v-list-item-group': true,
  'v-list-group': true,
  
  // Form components
  'v-form': true,
  'v-text-field': true,
  'v-select': true,
  'v-checkbox': true,
  'v-radio': true,
  'v-radio-group': true,
  'v-switch': true,
  'v-file-input': true,
  'v-textarea': true,
  'v-combobox': true,
  'v-autocomplete': true,
  
  // Other common components
  'v-icon': true,
  'v-img': true,
  'v-menu': true,
  'v-dialog': true,
  'v-tooltip': true,
  'v-divider': true,
  'v-chip': true,
  'v-tabs': true,
  'v-tab': true,
  'v-tab-item': true,
  'v-tabs-items': true,
};

/**
 * Creates stub implementations for Vuetify components
 * @param {Object} options - Options to customize stub behavior
 * @returns {Object} - Stubs object to use in tests
 */
export const createVuetifyStubs = (options = {}) => {
  // Start with the base vuetify stubs
  const stubs = { ...vuetifyStubs };
  
  // Add custom stubs from options
  if (options.customStubs) {
    Object.assign(stubs, options.customStubs);
  }
  
  // Remove any stubs that should be skipped
  if (options.skipStubs && Array.isArray(options.skipStubs)) {
    options.skipStubs.forEach(skipStub => {
      delete stubs[skipStub];
    });
  }
  
  return stubs;
};
// resources/js/test.setup.js
import { config } from '@vue/test-utils';
import { vi } from 'vitest';
import * as BootstrapVueNext from 'bootstrap-vue-next';
import { createVuetifyStubs } from './vuetify-stubs';

// Mock Vuetify's iconsets functionality (prevents iconset import errors)
vi.mock('vuetify/iconsets/mdi', () => ({
  mdi: {},
  aliases: {}
}));

// Set up global Vue Test Utils configuration
config.global.mocks = {
  // Add global mocks that should be available in all tests
  $t: (msg) => msg, // Mock translation function
  route: (name) => name, // Mock route function
};

// Set up global stubs if needed
config.global.stubs = {
  ...createVuetifyStubs(),
};

// Mock browser APIs that might be used in components
globalThis.ResizeObserver = vi.fn().mockImplementation(() => ({
  observe: vi.fn(),
  unobserve: vi.fn(),
  disconnect: vi.fn(),
}));

globalThis.scrollTo = vi.fn();

globalThis.IntersectionObserver = vi.fn(function IntersectionObserverMock() {
  this.observe = vi.fn();
  this.unobserve = vi.fn();
  this.disconnect = vi.fn();
});

// Set up BootstrapVueNext components for global use
config.global.components = {
  ...BootstrapVueNext
};

// Add any global mixins that should be available in all tests
config.global.mixins = [
  {
    methods: {
      asset(path) {
        return `/assets${path}`;
      },
      cssStyle() {
        // Mock implementation for cssStyle
      },
    }
  }
];

// Add console error if a test produces Vue warnings
// This helps catch issues during testing
const originalConsoleError = console.error;
console.error = (...args) => {
  // Log the original error
  originalConsoleError(...args);

  // Only throw for Vue warnings during tests
  if (args[0] && typeof args[0] === 'string' && args[0].includes('[Vue warn]')) {
    // Uncomment to make Vue warnings fail tests
    // throw new Error(args[0]);
  }
};

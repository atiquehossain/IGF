// vue-inertial.helper.js (Vue 3 + Vuetify + Inertia Global Setup)
import { mount, shallowMount } from "@vue/test-utils";
import { vi } from 'vitest';
import { plugin as InertiaPlugin } from '@inertiajs/vue3';
import vuetify from './vuetify'; // Import your vuetify instance
import { vuetifyStubs } from './vuetify-stubs'; // Import Vuetify stubs
import * as components from 'vuetify/components';

// Create a mock Inertia object that can be used across tests
const mockInertia = {
  visit: vi.fn(),
  replace: vi.fn(),
  reload: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  patch: vi.fn(),
  delete: vi.fn(),
  get: vi.fn(),
};

// Create a mock page object
const mockPage = {
  component: '',
  props: {},
  url: '',
  version: '',
};

// Method to register Vuetify components
const registerVuetifyComponents = (app) => {
  Object.entries(components).forEach(([key, component]) => {
    app.component(key, component);
  });
};

/**
 * Mounts a Vue 3 component with Inertia and Vuetify for testing.
 * @param {Object} Component - The Vue component to mount.
 * @param {Object} options - Options for mounting (props, global config).
 * @returns {Wrapper} - The mounted component wrapper.
 */
export const mountComponent = (Component, options = {}) => {
  // Extract mocks and merge with defaults
  const mocks = {
    $inertia: mockInertia,
    $page: mockPage,
    ...(options.global?.mocks || {}),
  };

  // Merge stubs with Vuetify stubs
  const stubs = {
    Link: true,
    Head: true,
    ...vuetifyStubs, // Add all Vuetify stubs by default
    ...(options.global?.stubs || {}),
  };

  // Prepare plugin list
  const plugins = [
    ...(options.global?.plugins || []),
    InertiaPlugin, 
    vuetify,
    // Register Vuetify components as a plugin
    (app) => {
      registerVuetifyComponents(app);
    }
  ];

  return mount(Component, {
    global: {
      mocks,
      stubs,
      ...(options.global || {}),
      plugins, // Use our prepared plugins list
    },
    ...options
  });
};

/**
 * Shallow mounts a Vue 3 component with Inertia and Vuetify for testing.
 * @param {Object} Component - The Vue component to shallow mount.
 * @param {Object} options - Options for shallow mounting (props, global config).
 * @returns {Wrapper} - The shallow mounted component wrapper.
 */
export const shallowMountComponent = (Component, options = {}) => {
  // Extract mocks and merge with defaults
  const mocks = {
    $inertia: mockInertia,
    $page: mockPage,
    ...(options.global?.mocks || {}),
  };

  // Merge stubs with Vuetify stubs
  const stubs = {
    Link: true,
    Head: true,
    ...vuetifyStubs, // Add all Vuetify stubs by default
    ...(options.global?.stubs || {}),
  };

  // Prepare plugin list
  const plugins = [
    ...(options.global?.plugins || []),
    InertiaPlugin, 
    vuetify,
    // Register Vuetify components as a plugin
    (app) => {
      registerVuetifyComponents(app);
    }
  ];

  return shallowMount(Component, {
    global: {
      mocks,
      stubs,
      ...(options.global || {}),
      plugins, // Use our prepared plugins list
    },
    ...options
  });
};

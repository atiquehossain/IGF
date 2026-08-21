const { defineConfig } = require('cypress');

module.exports = defineConfig({
  e2e: {
    baseUrl: 'http://127.0.0.1:8001/',
    env: {
      APP_LOCALIZATION: true
    },
    video: false,
    screenshotOnRunFailure: true,
    retries: {
      runMode: 1,
      openMode: 0
    },
    setupNodeEvents (on, config) {
      // implement node event listeners here
    }
  }
});

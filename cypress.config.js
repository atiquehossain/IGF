const { defineConfig } = require('cypress');
const { existsSync } = require('node:fs');
const { resolve } = require('node:path');
const { loadEnvFile } = require('node:process');

const cypressEnvFile = resolve('.env.cypress');

if (existsSync(cypressEnvFile)) {
  loadEnvFile(cypressEnvFile);
}

module.exports = defineConfig({
  allowCypressEnv: false,
  e2e: {
    baseUrl: 'http://127.0.0.1:8001/',
    env: {
      APP_LOCALIZATION: true,
      ADMIN_USERNAME: process.env.CYPRESS_ADMIN_USERNAME || process.env.LOCAL_ADMIN_USERNAME,
      ADMIN_PASSWORD: process.env.CYPRESS_ADMIN_PASSWORD || process.env.LOCAL_ADMIN_PASSWORD
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

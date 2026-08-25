/* eslint-disable no-undef */

const { randomBytes } = require('node:crypto');
const { closeSync, existsSync, openSync, readFileSync, writeFileSync } = require('node:fs');
const { spawn, spawnSync } = require('node:child_process');
const { resolve } = require('node:path');
const { parseEnv } = require('node:util');

const projectRoot = resolve(__dirname, '..');
const environmentFile = resolve(projectRoot, '.env.cypress');
const environmentExampleFile = resolve(projectRoot, '.env.cypress.example');
const expectedDatabase = resolve(projectRoot, 'database', 'cypress.sqlite');
const mode = process.argv[2];

if (mode === 'init') {
  if (existsSync(environmentFile)) {
    throw new Error('.env.cypress already exists. Remove it explicitly before generating a replacement.');
  }

  if (!existsSync(environmentExampleFile)) {
    throw new Error('The validated .env.cypress.example template is missing.');
  }

  const appKey = `base64:${randomBytes(32).toString('base64')}`;
  const username = `cypress-admin-${randomBytes(6).toString('hex')}`;
  const password = `Cy!${randomBytes(18).toString('base64url')}9aA`;
  const paymentStoreId = `cypress-store-${randomBytes(8).toString('hex')}`;
  const paymentStorePassword = randomBytes(24).toString('base64url');
  const configuredEnvironment = readFileSync(environmentExampleFile, 'utf8')
    .replace(/^APP_KEY=.*$/m, `APP_KEY=${appKey}`)
    .replace(/^LOCAL_ADMIN_USERNAME=.*$/m, `LOCAL_ADMIN_USERNAME=${username}`)
    .replace(/^LOCAL_ADMIN_PASSWORD=.*$/m, `LOCAL_ADMIN_PASSWORD=${password}`)
    .replace(/^LOCAL_SEED_DEMO=.*$/m, 'LOCAL_SEED_DEMO=true')
    .concat(`\nSSLCOMMERZ_STORE_ID=${paymentStoreId}\nSSLCOMMERZ_STORE_PASSWORD=${paymentStorePassword}\n`);

  writeFileSync(environmentFile, configuredEnvironment, {
    encoding: 'utf8',
    flag: 'wx',
    mode: 0o600
  });
  console.log('Created an isolated Cypress environment with generated local-only credentials.');
  process.exit(0);
}

if (!existsSync(environmentFile)) {
  throw new Error('Run "node scripts/cypress-environment.js init" before running Cypress.');
}

Object.assign(process.env, parseEnv(readFileSync(environmentFile, 'utf8')));

if (process.env.APP_ENV !== 'testing') {
  throw new Error('Cypress may only run with APP_ENV=testing.');
}

if (process.env.DB_CONNECTION !== 'sqlite' || resolve(projectRoot, process.env.DB_DATABASE || '') !== expectedDatabase) {
  throw new Error('Cypress must use the isolated database/cypress.sqlite database.');
}

function resolvePhpBinary() {
  const candidates = [
    process.env.PHP_BINARY,
    ...(process.platform === 'win32'
      ? ['C:\\xampp\\php\\php.exe', 'C:\\xampp\\php\\windowsXamppPhp\\php.exe']
      : []),
    'php'
  ].filter(Boolean);

  for (const candidate of [...new Set(candidates)]) {
    const probe = spawnSync(candidate, ['--version'], {
      cwd: projectRoot,
      env: process.env,
      stdio: 'ignore',
      windowsHide: true
    });

    if (!probe.error && probe.status === 0) {
      return candidate;
    }
  }

  throw new Error('PHP was not found. Add PHP to PATH or set PHP_BINARY before running Cypress.');
}

const phpBinary = resolvePhpBinary();
const phpArguments = [];
const gdProbe = spawnSync(phpBinary, ['-r', "exit(extension_loaded('gd') ? 0 : 1);"], {
  cwd: projectRoot,
  env: process.env,
  stdio: 'ignore',
  windowsHide: true
});

if (gdProbe.status !== 0) {
  const temporaryGdProbe = spawnSync(phpBinary, ['-d', 'extension=gd', '-r', "exit(extension_loaded('gd') ? 0 : 1);"], {
    cwd: projectRoot,
    env: process.env,
    stdio: 'ignore',
    windowsHide: true
  });

  if (temporaryGdProbe.status === 0) {
    phpArguments.push('-d', 'extension=gd');
  }
}

function runArtisan(args) {
  const result = spawnSync(phpBinary, [...phpArguments, 'artisan', ...args], {
    cwd: projectRoot,
    env: process.env,
    stdio: 'inherit',
    windowsHide: true
  });

  if (result.error) {
    throw result.error;
  }

  if (result.status !== 0) {
    process.exit(result.status || 1);
  }
}

if (mode === 'seed') {
  closeSync(openSync(expectedDatabase, 'a'));
  runArtisan(['migrate:fresh', '--force']);
  runArtisan(['db:seed', '--class=LocalDevelopmentSeeder', '--force']);
  runArtisan(['config:clear']);
  process.exit(0);
}

if (mode === 'server') {
  const appUrl = new URL(process.env.APP_URL || '');
  if (!['127.0.0.1', 'localhost'].includes(appUrl.hostname)) {
    throw new Error('Cypress APP_URL must use 127.0.0.1 or localhost.');
  }

  const server = spawn(phpBinary, [
    ...phpArguments,
    '-S',
    `${appUrl.hostname}:${appUrl.port || '8001'}`,
    '-t',
    'public',
    'server.php'
  ], {
    cwd: projectRoot,
    env: process.env,
    stdio: 'inherit',
    windowsHide: true
  });

  server.on('error', (error) => {
    throw error;
  });

  server.on('exit', (code) => {
    process.exit(code || 0);
  });

} else {
  throw new Error('Use "init", "seed", or "server" when running scripts/cypress-environment.js.');
}

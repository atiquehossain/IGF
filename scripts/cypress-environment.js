const { closeSync, existsSync, openSync, readFileSync } = require('node:fs');
const { spawn, spawnSync } = require('node:child_process');
const { resolve } = require('node:path');
const { parseEnv } = require('node:util');

const projectRoot = resolve(__dirname, '..');
const environmentFile = resolve(projectRoot, '.env.cypress');
const expectedDatabase = resolve(projectRoot, 'database', 'cypress.sqlite');

if (!existsSync(environmentFile)) {
  throw new Error('Create .env.cypress from .env.cypress.example before running Cypress.');
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

const mode = process.argv[2];

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

  return;
}

throw new Error('Use "seed" or "server" when running scripts/cypress-environment.js.');

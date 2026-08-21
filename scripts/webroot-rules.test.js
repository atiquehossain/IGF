const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '..');
const rootRules = fs.readFileSync(path.join(root, '.htaccess'), 'utf8');
const publicRules = fs.readFileSync(path.join(root, 'public', '.htaccess'), 'utf8');

function extractIfModuleBlock(source, opener) {
  const start = source.indexOf(opener);
  assert.ok(start >= 0, `${opener} is missing`);

  const tags = /<IfModule\b[^>]*>|<\/IfModule>/g;
  tags.lastIndex = start;
  let depth = 0;
  for (let match = tags.exec(source); match; match = tags.exec(source)) {
    depth += match[0].startsWith('</') ? -1 : 1;
    if (depth === 0) {
      return source.slice(start, tags.lastIndex);
    }
  }

  assert.fail(`${opener} is not closed`);
}

test('legacy root webroot maps only existing public files and directories', () => {
  assert.match(rootRules, /RewriteCond %\{DOCUMENT_ROOT\}\/public\/\$1 -f \[OR\]/);
  assert.match(rootRules, /RewriteCond %\{DOCUMENT_ROOT\}\/public\/\$1 -d/);
  assert.match(rootRules, /RewriteRule \^\(\.\+\)\$ public\/\$1 \[L,NE\]/);
  assert.ok(rootRules.indexOf('DOCUMENT_ROOT}/public/$1') < rootRules.indexOf('Deny every known non-public project tree'));
});

test('root webroot denies every current non-public project directory', () => {
  const denyRule = rootRules.split(/\r?\n/).find(line => line.trimStart().startsWith('RewriteRule ^(?:app|'));
  assert.ok(denyRule, 'private-directory deny rule is missing');
  for (const directory of [
    'app', 'bootstrap', 'config', 'cypress', 'database', 'deliverables',
    'design-reference', 'docs', 'ignite-community-stories-tutorial',
    'ignite-homepage-tutorial', 'node_modules', 'output', 'outputs',
    'resources', 'routes', 'scripts', 'storage', 'tests', 'tmp', 'vendor',
  ]) {
    assert.ok(denyRule.includes(directory), `${directory} is not denied`);
  }
  assert.match(rootRules, /RewriteCond %\{REQUEST_FILENAME\} -f \[OR\][\s\S]*RewriteCond %\{REQUEST_FILENAME\} -d[\s\S]*RewriteRule \^ - \[F,L\]/);
});

test('private paths are denied by modern and legacy authorization without mod_rewrite', () => {
  const authorizationStart = rootRules.indexOf('# Deny repository-only trees');
  const filesStart = rootRules.indexOf('<FilesMatch', authorizationStart);
  assert.ok(authorizationStart >= 0 && filesStart > authorizationStart, 'authorization block is missing');

  const authorizationRules = rootRules.slice(authorizationStart, filesStart);
  const fallbackRules = extractIfModuleBlock(authorizationRules, '<IfModule !mod_rewrite.c>');
  assert.equal(authorizationRules.slice(authorizationRules.indexOf('<IfModule !mod_rewrite.c>')).trim(), fallbackRules.trim(), 'fallback access rules must be scoped entirely to hosts without mod_rewrite');
  assert.doesNotMatch(fallbackRules, /^\s*RewriteRule/m);
  assert.match(fallbackRules, /<IfModule mod_authz_core\.c>[\s\S]*<If "%\{REQUEST_URI\} =~[\s\S]*Require all denied/);
  assert.match(fallbackRules, /<IfModule !mod_authz_core\.c>[\s\S]*SetEnvIfNoCase Request_URI[\s\S]*Order Allow,Deny[\s\S]*Deny from env=repository_private_path/);
  assert.ok(fallbackRules.includes('^/\\.(?!well-known'), 'hidden paths are not denied independently of rewriting');

  const pathRules = fallbackRules.split(/\r?\n/).filter(line => line.includes('deliverables|design-reference|docs'));
  assert.equal(pathRules.length, 2, 'modern and legacy private-path lists must both be present');
  for (const rule of pathRules) {
    for (const directory of [
      'app', 'bootstrap', 'config', 'cypress', 'database', 'deliverables',
      'design-reference', 'docs', 'ignite-community-stories-tutorial',
      'ignite-homepage-tutorial', 'node_modules', 'output', 'outputs',
      'resources', 'routes', 'scripts', 'storage', 'tests', 'tmp', 'vendor',
    ]) {
      assert.ok(rule.includes(directory), `${directory} is missing from a non-rewrite access rule`);
    }
  }
});

test('rewrite-capable legacy roots retain public storage and vendor asset mapping', () => {
  for (const directory of ['storage', 'vendor']) {
    assert.ok(fs.statSync(path.join(root, 'public', directory)).isDirectory(), `public/${directory} fixture is missing`);
  }

  const mapping = rootRules.indexOf('RewriteRule ^(.+)$ public/$1 [L,NE]');
  const privateDeny = rootRules.indexOf('RewriteRule ^(?:app|bootstrap|config');
  assert.ok(mapping >= 0 && privateDeny > mapping, 'public mapping must run before rewrite-based private-tree denial');
});

test('directory indexes are disabled independently of optional modules', () => {
  for (const [name, rules] of [['root', rootRules], ['public', publicRules]]) {
    const indexes = rules.split(/\r?\n/).filter(line => line.trim() === 'Options -Indexes');
    assert.equal(indexes.length, 1, `${name} webroot must disable indexes exactly once`);
    assert.ok(rules.indexOf('Options -Indexes') < rules.indexOf('<IfModule'), `${name} index protection is module-dependent`);

    const negotiation = rules.match(/<IfModule mod_negotiation\.c>([\s\S]*?)<\/IfModule>/);
    assert.ok(negotiation, `${name} MultiViews compatibility block is missing`);
    assert.match(negotiation[1], /Options -MultiViews/);
    assert.doesNotMatch(negotiation[1], /Indexes/);
  }
});

test('root and public rules deny sensitive root filenames', () => {
  for (const rules of [rootRules, publicRules]) {
    for (const marker of ['\\.env', 'readme', 'php\\.ini', 'composer\\.', 'package', 'sqlite', 'server\\.php']) {
      assert.ok(rules.toLowerCase().includes(marker), `${marker} is missing from sensitive filename rules`);
    }
  }
});

test('Laravel routes and ACME challenges retain fallthrough paths', () => {
  assert.match(rootRules, /RewriteRule \^\$ index\.php \[L\]/);
  assert.match(rootRules, /# Send application routes to the front controller\.[\s\S]*RewriteRule \^ index\.php \[L\]/);
  assert.match(rootRules, /well-known/);
  assert.match(publicRules, /well-known/);
  assert.match(rootRules, /ACME challenges[\s\S]*public\/\.well-known/);
});

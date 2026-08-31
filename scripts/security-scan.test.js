const assert = require('node:assert/strict');
const test = require('node:test');

const {
  containsSensitiveDebugCapture,
  containsSensitiveSeedData,
  findCredentialAssignments,
  inspectContent,
  isApprovedSanitizedDatabaseArtifact,
  isPlaceholder,
  sensitiveArtifactReason,
} = require('./security-scan');

test('accepts empty, referenced, and explicit test placeholders', () => {
  assert.equal(isPlaceholder(''), true);
  assert.equal(isPlaceholder('${API_KEY}'), true);
  assert.equal(isPlaceholder('base64:' + 'A'.repeat(43) + '='), true);
});

test('detects populated dotenv and structured credential assignments', () => {
  const oauthSecret = ['GOC', 'SPX-', 'x'.repeat(28)].join('');
  assert.equal(findCredentialAssignments(`GOOGLE_CLIENT_SECRET=${oauthSecret}`).length, 1);
  assert.equal(findCredentialAssignments(`http_headers={ "X-Goog-Api-Key" = "${'q'.repeat(40)}" }`).length, 1);
  assert.equal(findCredentialAssignments('GOOGLE_CLIENT_SECRET=').length, 0);
});

test('detects database, Debugbar, and framework runtime artifacts by path', () => {
  assert.match(sensitiveArtifactReason('database/database.sqlite'), /database artifact/);
  assert.match(sensitiveArtifactReason('database/database.sqlite.pre-final-20260821'), /database artifact/);
  assert.match(sensitiveArtifactReason('database/database.sqlite.seo3-backup-20260820'), /database artifact/);
  assert.match(sensitiveArtifactReason('database/production.sql.gz'), /database artifact/);
  assert.match(sensitiveArtifactReason('database/database.sqlite-wal'), /database artifact/);
  assert.equal(sensitiveArtifactReason('tests/SqliteFeatureTest.php'), null);
  assert.match(sensitiveArtifactReason('storage/debugbar/request.json'), /Debugbar/);
  assert.match(sensitiveArtifactReason('storage/framework/cache/data/aa/bb/challenge'), /runtime cache/);
  assert.equal(sensitiveArtifactReason('storage/framework/cache/data/.gitignore'), null);
  assert.match(sensitiveArtifactReason('.rnd'), /entropy/);
  assert.match(sensitiveArtifactReason('phpunit-events.log'), /runtime log/);
});

test('allows only the reviewed sanitized database artifact and its checksum', () => {
  const artifact = 'database/seeders/seed-data/igf-public-content.sqlite';
  const checksum = `${artifact}.sha256`;

  assert.equal(isApprovedSanitizedDatabaseArtifact(artifact), true);
  assert.equal(isApprovedSanitizedDatabaseArtifact(checksum), true);
  assert.equal(sensitiveArtifactReason(artifact), null);
  assert.equal(sensitiveArtifactReason(checksum), null);

  for (const unsafePath of [
    'database/seeders/seed-data/igf-public-content-copy.sqlite',
    'database/seeders/seed-data/igf-public-content.sqlite.candidate',
    'database/seeders/seed-data/igf-public-content.sqlite-wal',
    'database/database.sqlite',
  ]) {
    assert.equal(isApprovedSanitizedDatabaseArtifact(unsafePath), false);
    assert.match(sensitiveArtifactReason(unsafePath), /database artifact/);
  }
});

test('detects credential-bearing administrator seed JSON', () => {
  const seed = JSON.stringify([{ username: 'legacy-admin', password: '$2y$' + 'x'.repeat(56) }]);
  assert.equal(containsSensitiveSeedData('database/seeders/seed-data/admins.seed-data.json', seed), true);
  assert.equal(containsSensitiveSeedData('database/seeders/seed-data/admins.seed-data.json', '[]'), false);
});

test('detects sensitive values inside Debugbar-shaped JSON', () => {
  const capture = JSON.stringify({ request: { data: { request_request: { password: 'sensitive-value' } } } });
  assert.equal(containsSensitiveDebugCapture(capture), true);
  assert.ok(inspectContent('capture.json', capture).some(item => item.includes('Debugbar')));
});

const { spawnSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const MAX_CONTENT_SIZE = 5 * 1024 * 1024;
const alwaysExcludedDirectories = new Set(['.git', 'vendor', 'node_modules']);
const localRuntimeDirectories = new Set(['.codex', 'storage']);
const excludedFiles = new Set([path.resolve(__filename), path.join(root, 'package-lock.json')]);

const knownCredentialPatterns = [
  { name: 'Google API key', pattern: /AIza[0-9A-Za-z_-]{30,}/ },
  { name: 'Google OAuth client secret', pattern: /GOCSPX-[0-9A-Za-z_-]{20,}/ },
  { name: 'AWS access key', pattern: /AKIA[0-9A-Z]{16}/ },
  { name: 'GitHub token', pattern: /gh[pousr]_[A-Za-z0-9]{30,}/ },
  { name: 'Slack token', pattern: /xox[baprs]-[0-9A-Za-z-]{20,}/ },
  { name: 'Stripe secret key', pattern: /sk_(?:live|test)_[0-9A-Za-z]{16,}/ },
  { name: 'private key material', pattern: /-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/ },
];

const normalizeRelative = filePath => path.relative(root, filePath).replaceAll('\\', '/');

function isPlaceholder(rawValue) {
  const value = String(rawValue ?? '').trim().replace(/^(['"])(.*)\1$/, '$2').trim();
  if (value === '' || /^(?:null|false|true|~)$/i.test(value)) return true;
  if (/^\$\{?[A-Z_][A-Z0-9_]*\}?$/i.test(value)) return true;
  if (/^<[^>]+>$/.test(value)) return true;
  if (/^(?:base64:)?A{32,}={0,2}$/.test(value)) return true;
  return /(?:change[-_ ]?me|placeholder|replace[-_ ]?me|your[-_ ]|example|dummy|test[-_ ]?only)/i.test(value);
}

function hasSensitiveKeyName(key) {
  return /(?:^|[-_.])(?:password|secret|token|api[-_]?key|private[-_]?key|access[-_]?key|app[-_]?key|client[-_]?id|client[-_]?secret)(?:$|[-_.])/i.test(key);
}

function findCredentialAssignments(content, includeStructured = true) {
  const findings = [];
  for (const [index, line] of content.split(/\r?\n/).entries()) {
    const dotenv = line.match(/^\s*(?:export\s+)?([A-Z][A-Z0-9_.-]*)\s*=\s*(.*?)\s*$/);
    if (dotenv && hasSensitiveKeyName(dotenv[1]) && !isPlaceholder(dotenv[2])) {
      findings.push(`line ${index + 1}: populated credential assignment (${dotenv[1]})`);
      continue;
    }
    if (!includeStructured) continue;
    const structured = line.match(/["']?([A-Za-z0-9_.-]*(?:password|secret|token|api[-_]?key|private[-_]?key|client[-_]?secret)[A-Za-z0-9_.-]*)["']?\s*[:=]\s*["']([^"']{8,})["']/i);
    if (structured && hasSensitiveKeyName(structured[1]) && !isPlaceholder(structured[2])) {
      findings.push(`line ${index + 1}: populated credential assignment (${structured[1]})`);
    }
  }
  return findings;
}

function sensitiveArtifactReason(relativePath) {
  const normalized = relativePath.replaceAll('\\', '/');
  const basename = path.posix.basename(normalized);
  if (normalized === '.rnd') return 'runtime entropy artifact';
  if (/^[^/]+\.log$/i.test(normalized)) return 'root runtime log artifact';
  if (/^service-account.*\.json$/i.test(basename)) return 'service-account credential artifact';
  if (/\.(?:p12|pfx|key|pem)$/i.test(basename)) return 'private key or certificate artifact';
  // Reject database files and renamed/compressed backup variants such as
  // database.sqlite.pre-final, dump.sql.gz, and database.sqlite-wal.
  if (/\.(?:sql|sqlite|sqlite3)(?:$|[-._])/i.test(basename)) return 'database artifact';
  if (/^storage\/debugbar\/.*\.json$/i.test(normalized)) return 'Debugbar request capture';
  if (/^storage\/framework\/cache\/(?!\.gitignore$|data\/\.gitignore$).+/i.test(normalized)) return 'runtime cache artifact';
  if (/^storage\/framework\/sessions\/(?!\.gitignore$).+/i.test(normalized)) return 'runtime session artifact';
  if (/^storage\/logs\/(?!\.gitignore$).+/i.test(normalized)) return 'runtime log artifact';
  if (/^\.env(?:$|\.)/i.test(normalized) && !/^\.env\.(?:example|testing|cypress\.example)$/i.test(normalized)) return 'runtime environment file';
  return null;
}

function containsSensitiveSeedData(relativePath, content) {
  if (!/^database\/seeders\/seed-data\/.*\.json$/i.test(relativePath)) return false;
  let parsed;
  try { parsed = JSON.parse(content); } catch { return false; }
  const records = Array.isArray(parsed) ? parsed : [parsed];
  return records.some(record => record && typeof record === 'object'
    && Object.entries(record).some(([key, value]) => hasSensitiveKeyName(key) && !isPlaceholder(value))
    && Object.keys(record).some(key => /^(?:user(?:name)?|email|phone|mobile|name)$/i.test(key)));
}

function containsSensitiveDebugCapture(content) {
  let parsed;
  try { parsed = JSON.parse(content); } catch { return false; }
  const data = parsed?.request?.data;
  if (!data || typeof data !== 'object') return false;
  const visit = value => {
    if (!value || typeof value !== 'object') return false;
    return Object.entries(value).some(([key, nested]) => {
      if (hasSensitiveKeyName(key) || /^(?:authorization|cookie|set-cookie|http_cookie)$/i.test(key)) {
        return !isPlaceholder(nested) && !/^\*+$/.test(String(nested));
      }
      return visit(nested);
    });
  };
  return [data.request_request, data.request_headers, data.request_cookies, data.session_attributes].some(visit);
}

function inspectContent(relativePath, content) {
  const findings = [];
  for (const check of knownCredentialPatterns) if (check.pattern.test(content)) findings.push(check.name);
  const configLike = /(?:^|\/)\.env(?:\.|$)|\.(?:toml|ya?ml)$/i.test(relativePath);
  findings.push(...findCredentialAssignments(content, configLike));
  if (containsSensitiveSeedData(relativePath, content)) findings.push('credential-bearing seed data');
  if (containsSensitiveDebugCapture(content)) findings.push('Debugbar capture contains sensitive request/session data');
  return findings;
}

function gitTrackedFiles() {
  const result = spawnSync('git', ['ls-files', '-z'], { cwd: root, encoding: 'utf8', windowsHide: true });
  if (result.status !== 0 || !result.stdout) return null;
  return result.stdout.split('\0').filter(Boolean).map(file => path.join(root, file));
}

function isLocalRuntimeFile(relativePath) {
  if (localRuntimeDirectories.has(relativePath.split('/')[0])) return true;
  if (relativePath === '.rnd' || /^[^/]+\.log$/i.test(relativePath)) return true;
  if (/^database\/.*\.(?:sql|sqlite|sqlite3)(?:$|[-._])/i.test(relativePath)) return true;
  return /^\.env(?:$|\.)/i.test(relativePath) && !/^\.env\.(?:example|testing|cypress\.example)$/i.test(relativePath);
}

function filesystemFiles(releaseMode) {
  const files = [];
  function visit(directory) {
    for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
      if (entry.isDirectory() && alwaysExcludedDirectories.has(entry.name)) continue;
      const fullPath = path.join(directory, entry.name);
      const relativePath = normalizeRelative(fullPath);
      if (!releaseMode && entry.isDirectory() && localRuntimeDirectories.has(relativePath.split('/')[0])) continue;
      if (entry.isDirectory()) visit(fullPath);
      else if (entry.isFile() && (releaseMode || !isLocalRuntimeFile(relativePath))) files.push(fullPath);
    }
  }
  visit(root);
  return files;
}

function scanFiles(files) {
  const findings = [];
  for (const fullPath of files) {
    if (!fs.existsSync(fullPath) || !fs.statSync(fullPath).isFile() || excludedFiles.has(fullPath)) continue;
    const relativePath = normalizeRelative(fullPath);
    const artifactReason = sensitiveArtifactReason(relativePath);
    if (artifactReason) {
      findings.push(`${relativePath}: ${artifactReason} must not be included in source/release artifacts`);
      continue;
    }
    if (fs.statSync(fullPath).size > MAX_CONTENT_SIZE) continue;
    const buffer = fs.readFileSync(fullPath);
    if (buffer.includes(0)) continue;
    for (const finding of inspectContent(relativePath, buffer.toString('utf8'))) findings.push(`${relativePath}: ${finding}`);
  }
  return findings;
}

function main() {
  const releaseMode = process.argv.includes('--release');
  const tracked = releaseMode ? null : gitTrackedFiles();
  const findings = scanFiles(tracked ?? filesystemFiles(releaseMode));
  const scope = releaseMode ? 'release archive' : (tracked ? 'Git-tracked files' : 'source files (local runtime excluded)');
  if (findings.length) {
    process.stderr.write(`Security scan failed for ${scope} with ${findings.length} finding(s):\n${findings.map(item => `- ${item}`).join('\n')}\n`);
    process.exitCode = 1;
    return;
  }
  process.stdout.write(`Security scan passed for ${scope}: no checked credential or personal-data artifacts found.\n`);
}

if (require.main === module) main();

module.exports = { containsSensitiveDebugCapture, containsSensitiveSeedData, findCredentialAssignments, inspectContent, isPlaceholder, scanFiles, sensitiveArtifactReason };

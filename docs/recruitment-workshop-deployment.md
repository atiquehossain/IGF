# Recruitment and workshop deployment runbook

This runbook is for deploying the Recruitment and Workshop Registration module into the existing Laravel application. Read it with the [privacy and security notes](recruitment-workshop-privacy-security.md) and the project’s normal release procedure.

## Production prerequisites

- PHP 8.2 or newer with the extensions required by the locked Composer graph. In particular, `fileinfo`, `iconv`, and `zlib` are used for PDF inspection, and `sodium` is mandatory because the locked `lcobucci/jwt` dependency requires `ext-sodium`. Check both the CLI and web-SAPI PHP installations; do not deploy with `--ignore-platform-req=ext-sodium`.
- The web runtime must be allowed to launch `PHP_BINARY` as a child process. Applicant PDF parsing runs there with a 128 MiB memory limit and a five-second default timeout (`APPLICATION_PDF_PARSER_TIMEOUT_SECONDS`, bounded by the application to 1–10 seconds). A timeout or worker failure rejects the upload without storing it.
- MySQL 8.x (or the organization’s validated compatible MySQL release), InnoDB tables, `utf8mb4`, and real transaction/row-lock support. Do not run this capacity-sensitive module on SQLite in production.
- A web server that points only to the application’s public web root and denies direct access to `.env`, `storage`, source, and backup files.
- Writable Laravel `storage` and `bootstrap/cache` directories owned by the application service account, not broadly world-writable.
- HTTPS, production cookies, trusted-proxy configuration, and `APP_DEBUG=false`.

The application timezone is currently fixed to `Asia/Dhaka` in `config/app.php`. Confirm the database and operations team understand that displayed listing boundaries follow application/server time; keep host clocks synchronized with NTP.

## Pre-deployment backup and checks

1. Put the release artifact and reviewed `.env` in place without changing the live symlink/current directory yet.
2. Back up the database and both private trees as one recovery point:
   - `storage/app/private/applicant-documents`
   - `storage/app/private/applicant-imports`
3. Verify the backup can be listed/read by the recovery account and is encrypted at rest.
4. Record the current release identifier, database schema/migration list, and backup identifier.
5. Run the project’s release test, lint, security-scan, and production-build gates in the target-like environment. The authoritative gate list is in [the verification matrix](recruitment-workshop-verification.md).
6. Run `composer check-platform-reqs --no-dev` with the production PHP binary and require a clean result. Also confirm that `php -m` for both the CLI and web SAPI includes `sodium`, `fileinfo`, `iconv`, and `zlib`.
7. Preserve [the third-party notices](THIRD_PARTY_NOTICES.md) and dependency license files in the release artifact. The PDF parser is LGPL-3.0 software; do not strip `vendor/smalot/pdfparser/LICENSE.txt` from a distributed artifact.

A database-only backup is incomplete: it contains private file metadata but not the CV/CSV bytes. A private-files-only backup is also incomplete. Restore them to the same recovery point.

## Install and migrate

Use the deployment’s PHP binary in place of `php` where necessary.

```sh
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan db:seed --class=AdminPermissionRegistrySeeder --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The module’s additive migrations are the `2026_08_26_090000` through `2026_08_26_090600` application/job/workshop foundations, including the private-file cleanup outbox, plus `2026_08_26_090000_register_recruitment_workshop_permissions.php`. Review `php artisan migrate:status` before and after migration.

The permission migration/registry uses stable IDs. Do not manually renumber its menu/action IDs. The seeder synchronizes permission definitions and compatibility grants; role assignments still require owner review.

For a zero/low-downtime release, run additive migrations before switching traffic, then switch the application release and caches together. Test this sequence on a production-like copy first. MySQL DDL may auto-commit, so do not rely on a wrapping transaction to undo a failed migration.

## Private storage

Two local private disks are configured:

| Disk | Path | Purpose |
| --- | --- | --- |
| `applicant_documents` | `storage/app/private/applicant-documents` | CVs and protected form attachments |
| `applicant_imports` | `storage/app/private/applicant-imports` | Original uploaded historical CSV files |

Create the directories through the normal deployment account if the filesystem driver does not create them automatically. Give the application account read/write/delete access and deny the web server any static URL mapping to them.

Do not add either directory to `public/storage`, an Nginx `alias`, an Apache `Alias`, a CDN origin, or object-storage public ACL. `php artisan storage:link` links only `storage/app/public`; verify the private trees remain outside that link. Private documents are served only through permission-gated controllers with non-cacheable/nosniff headers.

If replacing the local disks with encrypted object storage, preserve the disk names, private ACL behavior, random object paths, read-after-write integrity behavior, authorized-download path, and delete semantics. Validate it with the full file security tests before rollout.

## Request and PHP limits

Application limits are 5 MiB per PDF document and 10 MiB per CSV import. Web-server and PHP limits must be higher than the application limits so Laravel can return its own validation errors. The repository's PHP profile uses a 64 MiB upload / 70 MiB POST envelope:

```ini
upload_max_filesize = 64M
post_max_size = 70M
max_input_vars = 5000
memory_limit = 256M
max_execution_time = 120
```

Nginx example inside the applicable `server` block:

```nginx
client_max_body_size 70m;
```

Apache example in the applicable virtual host/directory (bytes):

```apache
LimitRequestBody 73400320
```

Reload the service after configuration changes and verify the effective PHP configuration used by the web SAPI, not only the CLI. A reverse proxy, WAF, load balancer, or CDN may impose another body limit; align it as well. If a form designer adds several simultaneously visible PDF fields, keep their worst-case aggregate plus multipart overhead below the POST/proxy limit, or deliberately raise and security-review that limit before publishing the form.

## Scheduler and queue

This module does not require a queue worker and does not send automatic applicant/registrant email. `QUEUE_CONNECTION=sync` is sufficient for its current behavior.

It also adds no scheduled job and has no automatic application/registration retention. The application has unrelated scheduled tasks; continue the project’s existing scheduler policy if those features are used. `PRIVACY_RETENTION_AUTOMATION_ENABLED` does not include recruitment/workshop records in the configured retention map.

## Durable private-file cleanup

Replacing a submission, anonymizing a record, or permanently deleting it writes a contained private-file cleanup intent to `private_file_cleanup_jobs` in the same database transaction that removes the document metadata. Physical deletion starts only after the outermost transaction commits. If storage is temporarily unavailable, the applicant data remains removed from the live record while the cleanup row is retained without a name, email address, original filename, or answer content.

The module deliberately does not add a scheduler entry. Operations must monitor pending cleanup rows and retry them with:

```sh
php artisan applications:cleanup-private-files --limit=100
```

`--limit` must be between 1 and 1,000. The command returns a failure exit code when any claimed deletion fails, releases the failed row for a later retry, and prints only counts. Run it after deployment, after a storage incident, and during the normal operations review until the pending count is zero. Do not manually delete outbox rows or construct filesystem deletion commands from database values; the service accepts only the configured `applicant_documents` disk and randomized `documents/<48-hex>.pdf` paths.

## Database and concurrency validation

Workshop seat allocation relies on parent-row and record locks. Before production traffic:

1. Confirm all new tables use InnoDB.
2. Confirm the database user can perform normal transactions and that no proxy silently changes isolation/locking behavior.
3. Run `tests/Feature/MySqlWorkshopConcurrencyTest.php` against a dedicated disposable MySQL database whose name contains `test`, using the test’s documented `IGF_MYSQL_CONCURRENCY_DSN` harness.
4. Record the successful genuine-concurrency result in the verification matrix.

Never point the concurrency harness or `migrate:fresh` at production or a shared database.

## Post-deployment smoke checks

- `php artisan migrate:status` shows every new migration as run.
- `php artisan route:list` contains the public Careers/Workshops routes and permission-wrapped admin routes.
- An owner can see Recruitment and Workshops menus; a deliberately limited role sees only its assigned area.
- Create draft job/workshop/form records, preview both locales, publish with near-future dates, and verify boundary behavior.
- Submit a valid test PDF, then verify unauthorized download is denied and authorized download is non-cacheable.
- Verify automatic, manual, and waitlist modes in a test workshop; include final-seat behavior.
- Preview a harmless CSV, then cancel without confirming to prove preview is non-writing.
- Confirm no mail was sent and no applicant account was created.
- Confirm the public success page shows an opaque reference once, does not put it in a URL, and does not create a later applicant lookup/dashboard surface.
- Resubmit the same normalized email to one test listing and confirm that the latest answers/document replace the prior submission while its staff workflow and assignment remain intact.
- Run `php artisan applications:cleanup-private-files --limit=100`; require a successful exit and no pending cleanup rows before reopening normal operations.
- Review logs and audit rows for PII leakage; use synthetic test identities only.

Remove or anonymize smoke-test applicant records according to the approved test-data procedure.

## Rollback and recovery

Prefer rolling the application code forward. Once real applications/registrations exist, rolling migrations back would remove data or break file/database references and must not be used as an ordinary rollback.

If failure occurs before public writes:

1. Take the site/module out of write traffic.
2. Capture logs and current database/private-file state.
3. Switch back to the compatible prior release only if its schema is forward-compatible.
4. Clear/rebuild caches for that release.

If a destructive schema rollback is unavoidable, obtain explicit owner approval, take a fresh coupled database/private-files backup, restore the pre-deployment recovery point rather than improvising table drops, and verify audit/permission integrity. Never run `migrate:fresh`, broad deletion, or recursive cleanup against a production path.

After any restore, reconcile every document metadata row with the private object it names, check import-source checksums, inspect and process restored cleanup-outbox rows, confirm roles/permissions, and rerun listing/submission/capacity smoke checks before reopening traffic.

## Production restore drill

Before the first rollout, and periodically thereafter, prove recovery in an isolated environment:

1. Restore the database and both private storage trees from the same recorded recovery point.
2. Verify migration state, row counts, private-object checksums, import-source checksums, role grants, and the cleanup outbox before running application traffic.
3. Run the cleanup retry command and reconcile every remaining outbox row; do not delete unresolved rows to make the check pass.
4. Exercise owner/limited-role access, English/Bangla listing boundaries, anonymous one-time receipts, same-email latest-submission replacement, protected PDF download, all three free-workshop registration modes, and genuine final-seat allocation.
5. Record recovery-point identifiers, timings, command outcomes, exceptions, and the owner who approved reopening. Keep applicant PII out of the drill record.

A local test-suite pass does not replace this deployment-time restore drill, web-SAPI extension check, private-storage ACL check, TLS/proxy review, or a concurrency run against the target database topology.

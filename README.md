# Ignite Global Foundation CMS

Laravel 12, Inertia 2, and Vue 3 application for the Ignite Global Foundation public website and content-management platform.

## Requirements

- PHP 8.2 or newer with the Sodium extension enabled
- Composer 2
- Node.js 22 LTS and npm
- MySQL 8+ for normal operation (the automated suite uses SQLite)

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan passport:keys
npm ci
npm run dev
```

Run the application in a second terminal:

```bash
php artisan serve
```

The default local URL is `http://localhost:8000`. Link public uploads once with `php artisan storage:link`.

For optional local demonstration content, set a unique temporary `LOCAL_ADMIN_PASSWORD` (12+ characters) and `LOCAL_SEED_DEMO=true`, then run `php artisan db:seed --class=LocalDevelopmentSeeder`. The local content package includes the complete Ignite homepage carousel, program and project pages, events, awards, testimonials, gallery, official annual reports, legal pages, and the nested public navigation. The seeders are idempotent and intentionally blocked outside local/test environments.

Do not commit `.env`, service-account files, database exports, Passport keys, or payment credentials. Configure SSLCommerz and third-party integrations only through environment variables.

## Hosted donation payments

The donation form offers bKash, Nagad, and Visa/American Express card choices, but all wallet and card details remain on SSLCommerz's hosted checkout. The application must never request or store a card number, CVV, expiry date, wallet PIN, or OTP.

bKash and card filtering can be enabled independently. Nagad is fail-closed because SSLCommerz's public v4 gateway-key table does not document a universal Nagad filter key; keep it disabled until SSLCommerz confirms the exact channel key and activation for the production merchant account:

```dotenv
SSLCOMMERZ_BKASH_ENABLED=true
SSLCOMMERZ_CARD_ENABLED=true
SSLCOMMERZ_NAGAD_ENABLED=false
SSLCOMMERZ_NAGAD_GATEWAY_KEY=
```

Before launch, complete real SSLCommerz sandbox journeys for bKash, the confirmed Nagad channel, Visa, and American Express, including success, failure, cancellation, IPN, retry, reordered callback, and browser-close scenarios. Automated fakes do not certify merchant-channel activation.

## First production administrator and content

`php artisan migrate --seed` installs only the permission registry and roles. It never creates a default user or password. Provision the first owner interactively on a secured production console:

```bash
php artisan igf:provision-admin --name="Site Owner" --username="site-owner" --email="owner@example.org"
```

The password prompt is hidden, requires a strong temporary password, and the new owner must change it on first sign-in. The command refuses to create another administrator after the first one; later accounts must be managed from the authenticated admin UI.

Production content must be created through the Content Hub/page builder or imported from a reviewed, sanitized source using a deployment-specific importer. Never run `LocalDevelopmentSeeder` in production and never import a production database dump into source control. Before launch, publish the required home/about/zakat/legal pages, navigation, donation causes and site settings, then review every item in the SEO manager.

## SEO 3.0 and production indexing

Administrators use one **Search & Sharing** workspace for readiness checks, per-content search fields, live Google and social previews, a searchable Media Library picker, search visibility, localized permalink changes with automatic `301` redirects, generated Schema templates, expert JSON-LD, English/Bangla completion, review and approval, bulk editing/CSV export, revision differences, and permission-controlled restore. Page Builder links to this workspace instead of maintaining a second SEO form. Annual reports are first-class SEO content with a public HTML landing page at `/annual-report/{slug}` before the visitor opens the document.

Public SEO output includes server-rendered canonical, robots, Open Graph, X/Twitter, structured data, and language-alternate metadata. Enabled languages receive stable URLs, complete `hreflang` clusters, and locale-specific sitemaps exposed through `/sitemap-index.xml`. Event records emit `Event` structured data only when an editor explicitly selects **Scheduled event** and supplies a real start date; news/publications remain `Article`. The structured-data graph can also describe the organization, website search, breadcrumbs, archive collections, reports, and donation action without inventing missing facts.

Paginated Events & News, Projects, Gallery, and Annual Reports archives follow one canonical policy: a clean page 2 or later self-canonicalizes and receives a page-numbered title; search/filter, malformed-page, and unknown-query variants are `noindex,follow` and canonicalize to the clean archive; an out-of-range page returns `404`. Sitemap entries are generated only from safe, public, indexable content, and the generated `/robots.txt` advertises the sitemap while enforcing the environment's indexing policy.

The permission-separated **Technical SEO & 404 Center** runs a bounded, same-origin scan through the application—never an arbitrary external crawler. It checks public/sitemap URLs for response errors, broken links and images, links that unnecessarily redirect, orphan pages, heading/head/canonical/language/Schema mismatches, and duplicate canonical ownership. Its 404 inbox aggregates normalized public paths and counts; it discards query strings and fragments, redacts sensitive-looking path segments, and never stores an IP address, session, user agent, or per-visit record. An authorized administrator can ignore one exact finding, dismiss a 404, or create a locale-scoped redirect to a managed public destination.

Indexing is fail-closed. Keep the default in every local, test, preview, and staging environment:

```dotenv
SEO_INDEXING_ENABLED=false
```

After the client has approved production launch—and only after confirming the final HTTPS domain, canonical URLs, public visibility, translations, and sitemap—a production operator may set:

```dotenv
SEO_INDEXING_ENABLED=true
```

SEO redirects remain same-origin by default:

```dotenv
SEO_REDIRECT_ALLOW_EXTERNAL=false
SEO_REDIRECT_ALLOWED_HOSTS=
```

Do not enable external redirects for normal editorial work. If a business requirement is reviewed and approved, a production operator must enable them explicitly and provide a finite HTTPS hostname allowlist.

Google Search Console and performance-monitoring integrations are intentionally not included in this implementation. Dashboard completion scores describe configured content; they are not live ranking, traffic, Core Web Vitals, or page-speed data. See `docs/NO_CODE_ADMIN_GUIDE.md` for the editor workflow.

### SEO 3.0 upgrade and operating runbook

Treat an existing installation as an in-place data migration. Back up the database and the entire `storage/app` tree—including `storage/app/public`, `storage/app/uploads/admin`, `storage/app/uploads/users`, `storage/app/annual-reports`, `storage/app/notice-attachments`, import manifests, and the interrupted-delete recovery area `storage/app/content-purge-quarantine`—verify those files and the database can be restored, and keep `SEO_INDEXING_ENABLED=false` during deployment. The production `APP_KEY`, Passport key material, and other server-held secrets must also have a tested, access-controlled recovery path (for example, a versioned secret manager); never bundle them into the release archive. Then use the normal release artifact and run:

```bash
php artisan down
php artisan optimize:clear
php artisan migrate:status
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
php artisan seo:audit
```

Never use `migrate:fresh`, `migrate:refresh`, `migrate:reset`, or a destructive database rebuild on an existing environment. If `migrate --force` fails, leave indexing disabled, preserve the database, inspect the application log and failed migration, and restore from the verified backup if rollback is required.

The Bitbucket production host must provide an operator-owned executable backup
script at `/usr/local/sbin/ignite-release-backup`. It must back up and verify the
database plus all persistent upload roots listed above, verify that the server's
application and Passport keys have a recoverable protected copy, return non-zero
on any missing or unverified artifact, and be covered by a recorded restore drill. The
pipeline passes the exact quality-tested `BITBUCKET_COMMIT` and protected health
URL into the remote process; it never deploys a later moving `main`. The deployment enters
maintenance mode before updating source or dependencies, records the previous
revision, and leaves the site in maintenance mode if migration, cache warming,
or the external health check fails. The already-completed quality stage is the
test gate; production installs deliberately exclude PHPUnit and other
development packages.

After the upgrade, confirm the role assignments for `seo.metadata.view`, `seo.metadata.edit`, `seo.metadata.review`, `seo.metadata.restore`, redirect create/delete, Technical SEO access, scan, ignore, and 404-redirect permissions. Canonical URLs are same-origin by default. Grant the additive `seo.canonical.external` capability only to an SEO specialist who is authorized to credit a URL with a different scheme, hostname, or port; the specialist must explicitly confirm every external-canonical save and every revision restore that would reintroduce one. Verify `/robots.txt`, `/sitemap-index.xml`, one English and Bangla page, a clean page-2 archive, a filtered archive, an annual-report landing page, and a known `404`. Run the safe scan either from **Technical SEO & 404 Center** or with `php artisan seo:audit`; do not run scans concurrently. The default scan is capped at 120 URLs, 20 seconds, 1 MiB per response, and 250 links per page, and retains 20 snapshots. Deployment owners may lower those bounds through the `TECHNICAL_SEO_*` environment settings, but should increase them only after capacity review.

Resolve high-priority scan findings, review open 404s, complete required and recommended readiness items, request/approve the final SEO versions, and obtain content-owner sign-off before enabling production indexing. Search Console submission, ranking/traffic reporting, Core Web Vitals collection, Lighthouse/PageSpeed dashboards, and other performance monitoring remain explicitly out of scope.

Production must run Laravel's scheduler once per minute (`php artisan
schedule:run`). Interrupted content-purge media is recovered automatically.
Scheduled Technical SEO scans remain off until an operator sets
`TECHNICAL_SEO_SCHEDULE_ENABLED=true`. Privacy retention is stricter: first
obtain the client's written periods, set each applicable `PRIVACY_*_DAYS`
value, preview with `php artisan privacy:apply-retention`, and only then set
`PRIVACY_RETENTION_AUTOMATION_ENABLED=true`. Unconfigured policies never
delete or anonymize data.

## Production assets

```bash
npm run production
```

Build deployments from reviewed source files—never zip the live development
folder. A release artifact must exclude `.env`, `.codex/`, `storage/` runtime
contents, local SQLite/SQL databases, logs, sessions, `.rnd`, Passport keys,
and other ignored developer files. After staging that clean artifact, run
`npm run security:scan:release` from its root; this stricter scan is expected to
reject an unclean live workspace. The project-local Google Stitch credential is
development tooling and must never be copied to a web server or client archive.

## Quality checks

```bash
composer validate --strict
composer audit
php artisan test
npm audit
npm run security:scan
npm run security:scan:release # run inside the clean staged release artifact
npm run lint
npm run test:unit
npm run production
```

The CI quality gate also runs an isolated Cypress administrator smoke test after
the PHP and Vue suites pass. To reproduce it locally, copy
`.env.cypress.example` to `.env.cypress`, set a unique 12+ character
`LOCAL_ADMIN_PASSWORD`, export the same value as `CYPRESS_ADMIN_PASSWORD`, and
set `CYPRESS_ADMIN_USERNAME` to the configured `LOCAL_ADMIN_USERNAME`. Then run
`npm run cypress:seed`, start `php artisan serve --env=cypress --port=8001`, and
run `npm run cypress:smoke`. This uses only `database/cypress.sqlite`; never
point the Cypress environment at a development, staging, or production database.

The CI workflow in `.github/workflows/quality.yml` runs the same release gates. Independent QA plans and reviews are under `docs/qa/`.

## API documentation

Generate the OpenAPI documentation with:

```bash
php artisan l5-swagger:generate
```

It is served at `/api/documentation` when enabled for the environment.

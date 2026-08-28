# Production readiness audit — 2026-08-28

## Verdict

The settled application is a **locally verified release candidate**. No confirmed application-layer P0/P1 security, data-loss, payment-integrity, accessibility, SEO, responsive, or primary-journey defect remains in the tested repository state.

This is **not production launch authorization**. Payment/OTP provider onboarding, the real HTTPS host, a supported patched PHP runtime, production database and restore rehearsal, private-storage controls, clean release packaging, and owner content/privacy/indexing approval are external blocking gates described below.

## Scope and implemented remediation

- No-code administration was checked across pages and reusable sections, presentation choices, three-level navigation, header/footer/contact/social settings, English/Bangla translation, SEO metadata, teams, awards, annual reports, donation causes/groups, donation attribution/reporting, careers, workshops, forms, applications, registrations, and Website Customizer copy.
- Careers and Workshops now expose localized hero, listing, search, empty-state, pagination, navigation, and card-call-to-action copy through Website Customizer.
- Donation causes are first-class managed content. An administrator can create, edit, publish/hide, group, order, and delete safe causes. The public catalog uses accessible dynamic tabs and dedicated immutable cause URLs.
- Donation causes now own locale-specific SEO records and sitemap entries. A stale wildcard SEO record cannot bleed metadata from one cause to another.
- Annual Reports have an administrator-managed subtitle, summary, publisher, HTTPS source, order, private PDF, and separate managed cover image. Covers cannot replace or expose private PDFs, and referenced media is protected from permanent deletion.
- The legacy `/category/career` route permanently redirects to `/careers`, preserves only the locale query, and is excluded from the sitemap.
- Navigation description contrast meets WCAG AA and nested panels use stable visible labels for `aria-labelledby` on desktop and mobile.
- The local demo/Cypress seeder now reuses stable donation identities and reapplies cause grouping, so isolated tests match an upgraded installation instead of creating detached duplicate causes.
- A fail-closed `php artisan igf:production-preflight` command now blocks startup when the environment, debug flag, application key/cipher, HTTPS URL, cookie policy, CORS origins, database driver, developer-route inventory, or PHP patch baseline is unsafe. The deployment pipeline runs it against cached configuration/routes before any migration.

Content, layout, navigation, presentation, and public wording are deliberately editable. Security invariants are deliberately not no-code settings: credentials, application key, authentication/permissions, upload limits, payment callback verification, stable public identifiers, database schema, currency, and the protected Zakat calculation rate remain operator/developer controlled.

## Final settled-tree evidence

| Gate | Result |
| --- | --- |
| Full Laravel suite | **PASS — 934 passed, 1 skipped, 13,645 assertions** |
| Frontend unit/component suite | **PASS — 37 files, 330 tests** |
| Isolated Chrome Cypress suite | **PASS — 16 specs completed, 37 active tests passed** |
| Cypress journeys | Admin login/mobile, password redirect, settings publish/restore, analytics, CRUD/publish/delete, recruitment, workshops, forms, imports/exports, donation catalog/detail, team directory, responsive/keyboard flows |
| ESLint | **PASS** for application JavaScript/Vue and the changed Cypress specs |
| Production Vite build | **PASS — 1,433 modules transformed** |
| PHP syntax | **PASS** for application, config, routes, migrations, and changed seeder |
| Composer platform script | **PASS** for installed production requirements |
| npm dependency audit | **PASS — 0 vulnerabilities** for full and production-only graphs |
| Source security scan | **PASS — no checked credential or personal-data artifacts** |
| Security scanner regression suite | **PASS — 12/12** |
| Locked Composer advisory review | **PASS — no matched Packagist production advisory in this audit** |
| Additive migration | **PASS** through `2026_08_28_120000`; no destructive rebuild was used on the shared local database |
| Laravel cache compilation | **PASS** for config, routes, and views; development caches cleared afterward |
| Route inventory | **PASS — 549 routes, no duplicate method/URI registrations** |
| Live responsive smoke | **PASS** on Home, About, Contact, Annual Reports, Donate, Zakat, Careers, and Workshops at 1440×900 and 390×844: one main H1, canonical present, no horizontal overflow |
| Local index safety | **PASS** — pages and response headers remain `noindex,nofollow,noarchive` outside approved production launch |
| English sitemap | **PASS — 73 URLs, all 17 active donation causes, Careers, Workshops, and Annual Reports; no legacy Career alias** |

The first full Cypress run exposed two order/contract defects in isolated data and navigation assertions. Both were fixed, the affected specs passed 12/12, and the complete suite was then rerun from a fresh isolated database with all 37 active tests passing. Generated Cypress CSV/PDF download artifacts were removed after verification.

## Donation and OTP boundary

The donation catalog, dedicated cause pages, amount selection, donor fields, attribution snapshots, idempotency, callback validation, administrator records, filters, accessible cause chart/table, allocation safety, and privacy protections are implemented.

Live payment and OTP are intentionally unavailable because an approved provider API, production merchant credentials, and certification evidence were not supplied. bKash, Nagad, and card controls remain disabled and submission cannot proceed. The application must never collect a mobile-wallet PIN, card number/CVV, or provider OTP. Those secrets belong only on the approved SSLCommerz/bank/mobile-wallet hosted checkout. Enable payment only after provider sandbox/UAT, signed callback, idempotency, amount/currency, failure/cancel, replay, and settlement-reconciliation evidence passes on the real merchant account.

## Blocking production/owner gates

1. **Patched runtime:** the local PHP 8.2.12 runtime is below the audit baseline of 8.2.33. Deploy a currently supported, fully patched PHP branch and keep the per-branch preflight constants current using [PHP Supported Versions](https://www.php.net/supported-versions.php) and the [PHP 8 changelog](https://www.php.net/ChangeLog-8.php).
2. **Production configuration:** provide a protected APP_KEY, `APP_ENV=production`, `APP_DEBUG=false`, final HTTPS `APP_URL`, Secure/HttpOnly/SameSite cookies, explicit HTTPS CORS origins, production cache/session/queue/mail drivers, and no Cypress/Debugbar/Ignition routes. Require the cached preflight to pass before migrations or startup.
3. **Infrastructure security:** confirm TLS/HSTS and trusted-proxy behavior, public webroot isolation, private object ACLs, malware/backup controls, least-privilege database credentials, database TLS where required, log/monitoring/alerting, scheduler, workers, and effective web-SAPI PHP settings (including `expose_php=Off`).
4. **Database/recovery:** test the additive upgrade, representative concurrency, backup, and full restore on the exact approved MySQL 8+/compatible production topology. Keep the application offline if migration or restore proof fails.
5. **Clean release artifact:** never upload the live working folder. Build from reviewed source, exclude `.env`, `.codex`, local databases, runtime storage/log/session/cache material, keys and test output, then require `npm run security:scan:release` to pass inside the staged artifact.
6. **Payment/OTP:** complete merchant onboarding, credentials, hosted-checkout integration, callback allowlisting/signature validation, sandbox/UAT/certification, reconciliation, refund and incident procedures before enabling any payment method.
7. **Owner/legal/content:** approve final English/Bangla copy, donation restrictions, Zakat policy/Nisab sources, annual-report documents/covers, privacy/retention periods, safeguarding/refund/terms content, accessibility visual sign-off, and SEO metadata. Keep indexing disabled until the final domain, canonical URLs, redirects, robots, sitemaps, and content have written approval.
8. **Performance and delivery:** run target-network Core Web Vitals/Lighthouse and load/capacity checks on production-like staging. The local build is valid, but the large global UI/CSS/PDF vendor bundles remain an optimization opportunity rather than a correctness blocker.

## Release command boundary

Use the reviewed runbook in the project README. In particular, cache configuration and routes, run `php artisan igf:production-preflight`, and proceed to migration/startup only when every check passes. The local environment correctly fails eight production-only checks and therefore must never be treated as the deployment host.

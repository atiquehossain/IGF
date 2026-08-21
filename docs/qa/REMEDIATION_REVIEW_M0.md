# Ignite Global Foundation - Milestone 0 Remediation Review

**Review date:** 2026-08-14  
**Reviewer:** Senior QA (independent re-audit)  
**Scope:** The baseline P0/P1 credential, privacy, authentication, operational-route, payment, CMS deletion/revision, HTTP 404, SEO, migration, and automated-test findings.  
**Decision:** **FAIL - do not release or mark M0 complete.**

The checkout is materially safer than the baseline: both sensitive local artifacts are gone, hard-coded SSLCommerz credentials and the duplicate generic payment controller/routes are gone, the public cache-clear route is gone, the original admin reset vulnerability is closed, real 404 status behavior is present, and the new service-level tests pass when PHPUnit is invoked directly. M0 still has payment, CMS restore, migration-contract, test-runner, and incident-closure blockers.

## Status legend

- **Pass:** The original defect is remediated and direct evidence supports closure in this checkout.
- **Partial:** Useful remediation exists, but the control or proof is incomplete.
- **Fail:** The required behavior remains broken or a new release-blocking regression prevents closure.

## Remediation scorecard

| Area | Status | Evidence and disposition |
| --- | --- | --- |
| Project-local service credential | **Pass** | No `service-account*.json` file is present; no private-key material was found outside the scanner's own detection source. `/service-account-credentials.json` is now ignored. |
| External service-account incident closure | **Partial** | This archive has no `.git` directory and QA has no cloud/IAM access. Revocation/rotation, removal from other copies/history, access-log review, and least-privilege review remain unverified. Local deletion does not invalidate an exposed key. |
| Project-local SQL/PII dump | **Pass** | No `database/**/*.sql` file is present; `/database/*.sql` is now ignored. |
| External PII incident closure | **Partial** | Removal from this checkout cannot prove quarantine/removal from prior archives or repositories, provenance/retention review, owner notification, or required data-subject/incident response. |
| SSLCommerz hard-coded credentials and duplicate generic surface | **Pass** | `config/sslcommerz.php` reads blank-by-default environment variables; `.env.example` contains no values. The legacy `SSLCommerzController` and `/payment/sslcommerz/*` routes are absent. The service fails closed when credentials are empty. |
| Donation callback verification/idempotency | **Fail** | Signature, validation-API, local transaction, amount/currency, row lock, unknown-ID rejection, and terminal gateway-state protections exist. However, the three browser callback routes use `donation/payment/*` while CSRF exempts `donate/payment/*`; POST callbacks can receive 419. Payment/donation writes are not atomic, a rejected failure transition is not communicated to the controller, and initiation can report success after local transaction persistence fails. |
| Sponsorship payment routes | **Fail** | Three public callback routes target `SponsorController::success/fail/cancel`, but those methods do not exist. `SponsorController::store()` also omits the migration's required `transaction_id`, so a fresh-schema sponsorship submission fails and exposes the caught exception message to the client. |
| Payment privacy | **Partial** | New gateway audit payloads are allowlisted, card numbers are set to null on update, and callback logs contain only IDs/status. The upgrade migration does not scrub historical `card_no` or `raw_response` values, and customer PII columns still have no documented retention/encryption cleanup. Production data therefore needs a separate remediation/reconciliation step. |
| Original admin reset vulnerability | **Pass** | GET now displays confirmation only; mutation is POST+CSRF inside `auth:admin`/permission middleware. Reset requires role 1, blocks self-reset, generates a random temporary password, forces a 12-character confirmed change, and logs actor/target IDs. The literal `123456` reset is gone. |
| Admin login/logout session handling and throttling | **Pass** | Successful login regenerates the session; logout is POST and invalidates the session/regenerates the CSRF token; admin login is limited to 5 attempts/minute and uses a generic failure message. Frontend login/2FA routes are also throttled and frontend login/logout session handling was improved. |
| Public `/clear` route | **Pass** | Route is absent and the feature test confirms `/clear` returns 404. |
| Page trash/restore and safe deletion | **Fail** | Pages/SEO use soft deletion and permanent deletion now uses numeric page IDs. But trash still hard-deletes every `PageTagModule`; restore restores only page and SEO. Revisions do not snapshot tags. A restored page silently loses its tag relationships, violating the recovery contract. |
| Page revisions and block restore | **Pass** | A pre-change revision is captured, blocks use soft deletion, revision restore backs up the current state and reconstructs deleted blocks. The direct test covers page content and deleted-block recovery. Concurrency/audit/relationship coverage is still a later hardening item. |
| Real 404 behavior | **Pass** | Public page lookup uses `firstOrFail`; the fallback explicitly returns status 404. Feature coverage confirms an unknown route is 404. Add a missing-page-slug head/status test when expanding the SEO suite. |
| SEO foundation | **Partial** | Per-page title/description/focus keyword, canonical, robots, OG/Twitter, JSON-LD and sitemap preference fields/editor/output exist. There is no sitemap endpoint/generator, robots sitemap declaration, hreflang, redirect execution/admin flow, slug-change redirect, cross-content coverage, or complete HTTP `<head>` contract test. Legacy thumbnail fallbacks may emit non-absolute social image values. |
| Migration portability and compatibility | **Fail** | A fresh full migration succeeds on SQLite, including the driver-specific search expression. However, migration `2026_08_14_000004` replaces `search`, `order_by`, and `view_type` with `search_text` and `source_type`; `SearchController` and `search.vue` still use the old contract. Search therefore errors after migration and the broad catch returns a misleading empty success page. Upgrade/rollback on MySQL remains unproven. |
| Automated tests and security scan | **Partial** | `npm run security:scan` passes; direct PHPUnit passes 13 tests/36 assertions; changed PHP files are syntax-clean. The normal `php artisan test` command fails because Collision 7 requires PHPUnit 10 while `composer.json`/lock install PHPUnit 9.6.21. Tests do not cover admin reset/permissions, callback HTTP+CSRF, sponsorship routes, donation cross-table atomicity, tag preservation through trash/restore, search after migration, or final SEO output. No CI workflow is present. |

## Remaining release blockers

### M0-INC-001 - P0 evidence hold - external credential response is not proven

The service-account file is absent locally, but a release owner must supply evidence that the exposed key was revoked/rotated, other copies/history were handled, permissions were reviewed, and access logs were checked. Do not treat `.gitignore` or local deletion as incident closure.

### M0-INC-002 - P0 evidence hold - external PII dump response is not proven

The SQL file is absent locally, but this checkout cannot prove handling of prior copies or any required privacy/owner response. Record provenance, quarantine/removal, retention decision, and notifications before closing the original P0.

### M0-PAY-001 - P1 - donation POST callback CSRF exemption is wrong

- Routes: `routes/web.php:355-361` register `donation/payment/success|fail|cancel` and `donate/payment/ipn`.
- Middleware: `app/Http/Middleware/VerifyCsrfToken.php:15-16` exempts `sponsorship/payment/*` and `donate/payment/*`.

Only the donation IPN path matches. The explicitly supported POST success/fail/cancel paths do not. Use one consistent prefix and add HTTP tests proving signed POST callbacks reach each controller without a CSRF token while unrelated POST routes remain protected.

### M0-PAY-002 - P1 - sponsorship payment endpoints and submission are broken

`routes/web.php:348-353` registers three callbacks against methods absent from `app/Http/Controllers/Vue/SponsorController.php`. The same controller creates sponsorships without `transaction_id` (`SponsorController.php:71-79`), while `database/migrations/2025_10_27_051329_create_sponsorships_table.php` defines it as non-null. The catch returns the raw exception text (`SponsorController.php:91`), creating an information-disclosure path.

Remove the unused payment routes/schema requirement if sponsorship is only a request form, or implement one complete, verified, idempotent payment flow. Do not leave half-wired public routes.

### M0-PAY-003 - P1 - donation persistence is not atomic or fail-closed

`SSLCommerzService::initializePayment()` ignores the nullable return from `createPendingTransaction()` (`app/Services/SSLCommerzService.php:126,327-355`) and can return gateway success with no local gateway row. `DonateController` then creates the donation separately (`app/Http/Controllers/Vue/DonateController.php:102-111`). Callback handlers also update the gateway and donation tables in separate transactions and do not verify that a donation row was affected.

Additionally, `updateTransaction()` returns the existing transaction when it rejects a state transition. A fail/cancel controller cannot distinguish accepted from rejected, so a split-brain `VALID` gateway row plus `Pending` donation row can be changed to Failed/Cancelled. Introduce an explicit transition result and a single transactional local state update, plus reconciliation/compensation for the external gateway boundary.

### M0-PRIV-001 - P1 until production data is assessed - historical payment payloads are not scrubbed

New writes are substantially safer, but migration `2026_08_14_000004` only adds donation constraints/indexes and rebuilds the search view. It does not clear/tokenize historical `ssl_commerz_transactions.card_no` or sanitize existing `raw_response`. Run an approved, backed-up data migration and verify retention/access policy; do not assume deploying the new service retroactively removes sensitive data.

### M0-CMS-001 - P1 - page trash permanently loses tag relationships

`PageController::destroy()` deletes tag rows before soft-deleting the page (`app/Http/Controllers/Admin/PageController.php:429-442`), but `restore()` restores only page and SEO (`PageController.php:470-486`). `PageRevisionService::capture()` snapshots page, blocks, and SEO only (`app/Services/PageRevisionService.php:14-28`).

Trash must retain tag rows or snapshot and restore them. Add an endpoint-level test that creates a tagged multi-locale page, trashes it, restores it, and compares page, translations, tags, blocks, SEO, slug, media references, and public visibility before/after.

### M0-MIG-001 - P1 - the payment migration breaks public search

The original search view exposes `search`, `order_by`, and `view_type` (`database/migrations/2023_02_26_050749_create_search_view.php:39,54-55`). The new migration exposes `search_text` and `source_type` instead (`database/migrations/2026_08_14_000004_harden_payment_integrity_and_refresh_search_view.php:68-69`). Existing consumers still query/render the old names (`app/Http/Controllers/Vue/SearchController.php:19-21`; `resources/js/Pages/search.vue:22-33`). The controller catches the SQL error and returns a success-shaped empty page, hiding the regression.

Preserve the established view contract or update every consumer in the same change. Add a post-migration feature test that seeds a published page, searches it, and asserts the visible result/link type.

### M0-AUTO-001 - P1 gate - the standard test command is not runnable

`composer.json` requires Collision `^7.0` with PHPUnit `^9.3.3`; the lock contains Collision 7.11.0 and PHPUnit 9.6.21. `php artisan test` exits before executing tests because Collision 7's Artisan adapter requires PHPUnit 10. Direct `vendor/bin/phpunit` passes, but a clean, documented default CI command is an M0 requirement.

Align dependencies/test commands, add a CI workflow, and cover the HTTP/controller/recovery boundaries listed above. Service-only tests are not sufficient for route and cross-table defects.

### M0-SEO-001 - P1 product-release gap - SEO pack is foundation only

The admin can now edit core metadata and the page controller produces a real canonical/robots/social/JSON-LD payload. The database-only sitemap and redirect fields are not functional SEO features. Complete sitemap/robots, redirects and slug history, hreflang, absolute social images, unique metadata constraints, other public content types/locales, and server-rendered head/crawl tests before claiming self-managed per-page SEO.

## Verification executed

| Check | Result |
| --- | --- |
| Sensitive filename/content count | 0 service-account files; 0 database SQL files; only the scanner source matched its own private-key detection string |
| `npm run security:scan` | **Pass** - no checked credential or personal-data artifacts found |
| PHP syntax check on changed security/CMS/payment/SEO/migration files | **Pass** |
| `php artisan migrate:fresh --force` with SQLite `:memory:` | **Pass** - full migration chain completed |
| `php artisan test` | **Fail before tests** - Collision 7 / PHPUnit 9 incompatibility |
| `vendor/bin/phpunit --testdox` | **Pass** - 13 tests, 36 assertions |
| Payment route inventory | Seven routes; three broken sponsorship targets and three donation browser callbacks with the CSRF prefix mismatch |

## Automated coverage assessment

The new tests are valuable but insufficient to close Tier 0 risks:

- `SecurityRouteIntegrityTest` covers `/clear` and an unknown fallback route only.
- `PaymentStateIntegrityTest` covers the service table, not HTTP CSRF, controller state, donation/sponsorship records, replay order across both tables, or gateway persistence failure.
- `PageBuilderIntegrityTest` covers scheduling and revision block recovery, not trash/restore relations, permissions, media, multi-locale pages, or permanent deletion.
- `SeoMetadataIntegrityTest` covers service fallbacks/overrides, not final server-rendered head tags, missing-page slug status, sitemap, robots, redirects, hreflang, or crawlers.

## Exit criteria for the next re-review

1. Provide owner evidence for service-account rotation/revocation and SQL/PII incident handling.
2. Repair or remove sponsorship payment routes; make donation callback paths CSRF-correct and HTTP-tested.
3. Make local payment state persistence fail-closed, transactionally consistent, and reconciliable; remediate historical sensitive payloads.
4. Preserve all page relationships through trash/restore and add endpoint/permission regression coverage.
5. Restore the search view/consumer contract and test search on a post-migration database.
6. Make the documented standard test command and CI gate green.
7. Implement and crawl-test the remaining SEO pack before product release.

Until those items are complete, the M0 branch is a useful remediation draft, not a releasable security/CMS foundation.

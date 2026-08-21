# Ignite Global Foundation - Milestone 0 Remediation Review R2

**Review date:** 2026-08-14  
**Reviewer:** Senior QA (independent re-audit)  
**Scope:** Every open item in `docs/qa/REMEDIATION_REVIEW_M0.md`, the Laravel/Passport and Vite/Vitest upgrades, and security/quality gates requested for R2.  
**Implementation decision:** **PASS - the reviewed M0 P0/P1 implementation blockers are closed in this checkout.**  
**Production-release decision:** **FAIL / OWNER HOLD - do not deploy until the two external incident-response actions are evidenced, and production-like database/payment rehearsals pass.**

The distinction is deliberate. The code and repository gates reviewed in R2 are green. QA cannot revoke a previously exposed external credential, prove removal of earlier PII copies, complete notifications, or inspect cloud/repository history from this archive. Those are owner-only release holds rather than failed code remediations.

This is an M0 foundation decision, not final product acceptance. Pixel fidelity, full WCAG/manual browser coverage, all-content-type CMS deletion, all-content-type SEO/localization, and payment sandbox certification remain later milestone gates in `docs/qa/QA_PLAN.md`.

## Status definitions

- **PASS:** The required implementation is present and direct static/runtime evidence supports closure.
- **FAIL:** A required implementation or verification gate is not satisfied.
- **OWNER HOLD:** The repository-side control passes, but required evidence/action exists outside this checkout and cannot be performed by code QA.

## R2 blocker disposition

| Area | Status | Evidence and disposition |
| --- | --- | --- |
| Project-local service-account credential | **PASS** | No `service-account*.json`, private-key content, or project key/certificate artifact was found outside excluded dependencies/reference material. The former filename is ignored. This archive has no `.git`, so tracking/history cannot be proven here. |
| External service-account incident response | **FAIL - OWNER HOLD** | Revocation/rotation, removal from every earlier copy/history, IAM least-privilege review, and access-log review remain unverified. Local deletion and `.gitignore` do not invalidate an exposed key. |
| Project-local SQL/PII dump | **PASS** | No `database/**/*.sql` file is present; the former dump pattern is ignored; the security scan reports no checked credential/PII artifact. |
| External PII incident response | **FAIL - OWNER HOLD** | QA cannot prove quarantine/removal from prior archives/repositories, provenance and retention review, owner/data-subject/regulatory decisions, or required notification. |
| SSLCommerz configuration and route surface | **PASS** | Store credentials are environment-only and blank by default. The legacy generic controller/routes and broken sponsorship callbacks are absent. The service fails closed when not configured. |
| Donation callback/CSRF alignment | **PASS** | Registered POST-capable callbacks are `donation/payment/success|fail|cancel` and `donate/payment/ipn`; CSRF exclusions cover both prefixes. `PaymentCallbackRouteIntegrityTest` proves every callback matches the allowlist and unsigned gateway-style POSTs reach the controller rather than returning 419. |
| Sponsorship flow | **PASS** | Sponsorship is now consistently a request flow, not a half-wired payment flow. Submission creates a bounded, validated request transaction/status; broken callback routes are absent; internal exception text and PII are not returned/logged. `SponsorshipIntegrityTest` passes. |
| Payment local atomicity/fail-closed behavior | **PASS** | Gateway and donation pending records are created together before contacting SSLCommerz. A local write failure rolls back both and prevents the gateway request. Gateway-init failure marks local records failed. Tests cover both success preparation and rollback/fail-closed paths. |
| Callback idempotency and cross-table consistency | **PASS** | Callback processing locks the gateway and donation rows inside one transaction, rejects unknown transactions and terminal-state downgrades, validates signature/gateway record/amount/currency, and rolls back if the matching donation is missing. Seven payment-state tests cover replay, split-state prevention, unknown IDs, missing donation, privacy, and init failure. |
| Gateway payment-data minimization and historical scrub | **PASS** | New audit payloads use an allowlist and do not store card numbers or donor identity in gateway transaction columns. Migration `000006` irreversibly nulls legacy card/customer columns and allowlists historical JSON. A synthetic legacy-row R2 probe confirmed the migration removes disallowed PII while retaining permitted reconciliation fields. |
| Business-record retention lifecycle | **PASS for M0 implementation; residual owner/product gate** | The specific gateway-payload defect is closed. Donation/sponsorship business records still need an approved legal basis, retention period, erasure/anonymization rules, backups/log treatment, and then any required scheduled implementation. No policy was supplied to QA, so this cannot be truthfully certified as a final privacy program. |
| Admin reset | **PASS** | GET is confirmation-only; POST performs the CSRF-protected mutation behind admin authentication and permission middleware. The controller restricts reset to role 1, blocks self-reset, creates a random temporary password, forces change, and audits actor/target IDs. No predictable reset secret remains. |
| Admin sessions/password/throttling | **PASS** | Successful login regenerates the session; logout is POST and invalidates the session/regenerates CSRF; admin login is throttled; password change requires a 12-character confirmed value and the current password unless a forced reset is active. |
| API auth/Passport operational flow | **PASS** | The `$user->status` paths are correct; local/social/2FA auth endpoints are throttled. A permanent operational probe creates a numeric Passport 13 personal-access client and obtains a real API token. Unexpected login failures are reported internally and return a generic 500 rather than a false HTTP-200 user error. |
| 2FA credential safety | **PASS** | During R2 QA found both web/API 2FA were returning a reversible Base64 blob containing the submitted password. This was replaced with an opaque 64-character, five-minute, server-side challenge containing only user ID/pending enrollment secret. Challenges are pulled once; persisted 2FA secrets use Laravel's encrypted cast; raw social-auth exception text was removed. Tests prove password-free/opaque/single-use behavior. |
| Broken public controller actions | **PASS** | R2 reflection found six dead controller targets across API/social/story, district, password, and notice routes. Dead aliases were removed; story and guarded notice download/PDF actions were implemented. Permanent `RouteActionIntegrityTest` reflects every application controller route and now passes. |
| Public `/clear` | **PASS** | The operational cache-clear route is absent and `/clear` returns 404 in `SecurityRouteIntegrityTest`. |
| Permission enforcement | **PASS** | Admin permission middleware now fails closed when a capability is unmapped/missing, maps SEO/settings/reusable/media/file-manager capabilities to existing anchors, and rejects restricted roles. Regression tests cover unregistered, denied, and authorized mapped capabilities. |
| Page trash/restore and tag safety | **PASS** | Trash retains page-tag rows while soft-deleting the page and SEO; restore restores the recoverable records; permanent deletion removes tag rows. `PageTrashRelationshipIntegrityTest` passes. |
| Revision/tag/block restoration | **PASS** | Revision snapshots include page tags; restore replaces current tag links with the snapshot and reconstructs deleted blocks. Permanent block/page revision tests pass, and an independent transient R2 probe verified the old tag link is restored and the replacement removed. |
| Real 404 behavior | **PASS** | The public page path uses not-found semantics and the fallback response is a true HTTP 404. The repaired API story endpoint also returns 404 for a missing category. Security tests cover the public fallback. |
| Search view/controller contract | **PASS** | Migrations retain the consumer fields `search`, `order_by`, and `view_type`, exclude deleted content, and now apply draft/private/unlisted/future publication rules. Controller/Vue contracts match. Search tests cover a published result, trash exclusion, and non-public publication states. |
| SEO foundation | **PASS for M0** | Page/route metadata, canonical/robots, OG/Twitter, JSON-LD, sitemap, dynamic robots, redirect execution/hit tracking, uniqueness constraints, and admin management exist. The static `public/robots.txt` that shadowed Laravel was removed; tests prove the dynamic response includes sitemap/admin rules and that sitemap inclusion/exclusion works. |
| SEO product completeness | **FAIL for final product, not M0** | Reciprocal locale `hreflang`/`x-default`, comprehensive server-rendered head crawl assertions, slug-history automation, redirect cycle/chain prevention, and complete coverage of every public content type are not demonstrated. Do not market the final SEO pack as complete until the M1 SEO matrix passes. |
| Fresh migrations and search portability | **PASS in the verified environment** | A fresh SQLite migration completed through `000011`. Search SQL selects driver-appropriate concatenation. No migration failed. A production-engine MySQL/MariaDB upgrade/rollback rehearsal remains required before deployment. |
| Passport 13 migration compatibility | **PASS** | Historic numeric client schema is preserved, `Passport::$clientUuids = false`, and additive migration `000009` adds/backfills Passport 13 redirect/grant metadata without changing client IDs or issued-token references. An independent synthetic upgrade probe retained numeric client ID 41 and its access-token reference (1 test/6 assertions); fresh real token issuance also passes. |
| Laravel 12 / PHP dependency upgrade | **PASS** | Runtime reports Laravel 12.66.0; lock includes Passport 13.7.6, Inertia Laravel 2.0.25, Ziggy 2.6.3, PHPUnit 11.5.56, and Collision 8.9.5. Composer metadata validates, the locked install is internally consistent, and the Composer advisory audit is clean. |
| Vite/Vitest upgrade | **PASS** | Installed versions include Vue 3.5.13, Inertia Vue3 2.3.27, Vite 7.3.6, Vitest 4.1.10, plugin-vue 6.0.8, and laravel-vite-plugin 2.1.0. ESLint, four Vitest files/tests, npm high-severity audit, and the Vite production build pass. |
| Default test runner and CI definition | **PASS** | With the documented PHP 8.2 + Sodium requirement enabled, `php artisan test` runs normally: 43 tests/145 assertions pass. `.github/workflows/quality.yml` installs PHP/Sodium and Node 22 and defines Composer validation/audit/tests plus npm scan/audit/lint/unit/build. The workflow definition was inspected; no remote GitHub run is available in this archive. |
| Route/build optimization | **PASS** | `route:cache` and `artisan optimize` pass. The final route table contains 374 routes and zero duplicate non-empty names. LFM auto-routes no longer duplicate the manually secured admin routes. Caches were cleared after verification. |

## Implementation gates versus external owner actions

### M0 implementation gates

**PASS.** No open P0/P1 implementation defect remains among the R1 blockers or the additional R2 defects found during independent testing. The callback, sponsorship, payment consistency/privacy, CMS relationship recovery, search, test-runner, route authorization/cache/action, Passport, 2FA, robots, and dependency upgrade gates are green.

The following are not reasons to reopen a closed R1 code defect, but they are required before a real deployment:

1. Run the complete migration chain as an **upgrade and fresh install on the exact production MySQL/MariaDB version**, using a sanitized clone; verify rollback/restore and search/Passport schema/data.
2. Run **SSLCommerz sandbox** initiation plus signed success/fail/cancel/IPN in retry and reordered-callback scenarios, including a concurrency/reconciliation check. Unit/feature fakes cannot certify the external gateway.
3. Define and approve the **business-record retention/privacy policy**; implement any resulting purge/anonymization/export controls before collecting production data outside that policy.

### Owner-only external actions

These remain release-blocking and cannot be satisfied by another code change in this checkout:

1. **Credential incident:** prove the previously exposed service credential has been revoked/rotated, all known copies/history handled, IAM scope reviewed, and access logs investigated.
2. **PII dump incident:** document provenance, quarantine/removal from all prior copies/history, retention decision, owner/privacy/legal review, and any required notification or data-subject action.

Until evidence for both is attached to the release record, the production release decision remains **FAIL / OWNER HOLD**, even though M0 implementation is green.

## Verification record

| Check | R2 result |
| --- | --- |
| `php artisan test --display-warnings` with PHP 8.2/Sodium | **PASS - 43 tests, 145 assertions** |
| Real Passport numeric client + API token probe | **PASS - included in the full suite** |
| Synthetic pre-v13 numeric client/token additive-upgrade probe | **PASS - 1 transient test, 6 assertions** |
| Historical gateway payload scrub probe | **PASS - synthetic legacy row sanitized; transient probe removed** |
| `php artisan migrate:fresh --env=testing --force` | **PASS - all migrations through `000011`** |
| `php artisan route:cache` | **PASS** |
| `php artisan optimize` | **PASS** |
| Route inventory | **PASS - 374 routes, 0 duplicate non-empty names** |
| Controller action reflection | **PASS - every application controller route resolves to a real method** |
| PHP syntax scan | **PASS - 284 files, 0 failures** |
| `composer validate --strict --no-check-publish` | **PASS** |
| `composer audit --locked` | **PASS - no advisories** |
| `npm run security:scan` | **PASS** |
| `npm audit --audit-level=high` | **PASS - 0 vulnerabilities** |
| `npm run lint` | **PASS** |
| `npm run test:unit` | **PASS - 4 files, 4 tests** |
| `npm run production` | **PASS - Vite 7.3.6, 1,833 modules transformed** |
| Sensitive artifact scan | **PASS locally - 0 service-account files, 0 database SQL dumps, 0 private-key content matches** |
| Static public robots shadow | **PASS - `public/robots.txt` absent; dynamic route regression-tested** |

## Residual risks and later-milestone coverage

- MySQL/MariaDB production-version upgrade and rollback have not been exercised by this Windows/SQLite audit.
- No live SSLCommerz sandbox, network failure, concurrent callback, or production reconciliation run was available.
- Payment gateway audit retention is remediated, but organization-wide donor/sponsor/contact retention and erasure still require an approved policy.
- Admin reset has strong route/controller evidence but still needs a dedicated end-to-end permission/CSRF/audit test in the expanding auth suite.
- SEO M0 foundation passes; locale alternates, full crawl/head coverage, all public content types, redirect chains/cycles, and final SSR behavior remain M1 work.
- The final WordPress-like CMS promise is not yet certified across every content type, bulk action, dependency, media deletion, and concurrent edit/delete case.
- Accessibility, localization, responsive/browser coverage, performance, and Stitch pixel-diff acceptance were not part of this remediation review and remain mandatory later gates.
- The ignored `.codex/config.toml` project connector configuration exists and is intentionally excluded from the security scanner. It must remain private and must not be included in a distributable archive; rotate its API credential if it has been shared beyond the intended project environment.
- The archive has no `.git` metadata. QA cannot verify tracked-file history, remote CI execution, or whether removed secrets/PII remain in earlier commits or releases.

## Final QA recommendation

Accept the **M0 implementation remediation** and continue to the next build milestone. Do **not** authorize a production release yet. Close the two owner-only incident holds, complete production-engine migration and payment-sandbox rehearsals, and then proceed through the remaining CMS/SEO/accessibility/responsive/pixel-fidelity gates in the living QA plan.

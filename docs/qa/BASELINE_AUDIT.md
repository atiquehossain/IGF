# Ignite Global Foundation — Baseline QA Audit

**Audit date:** 2026-08-14  
**Auditor:** Senior QA  
**Assessment type:** Static repository/design-reference audit  
**Release recommendation:** **Do not release this baseline**

## 1. Executive summary

The project has a broad Laravel/Vue CMS foundation, public content modules, admin roles/permissions, per-page title/keyword/description fields, and SSLCommerz-oriented donation code. It is not yet the WordPress-like, independently SEO-manageable, pixel-matched system described by the target.

The baseline contains release-blocking security/data-handling issues, known broken routes/payment code, hard deletion without recovery, incorrect page relationship cleanup, soft-404 behavior, placeholder social metadata, and almost no meaningful automated regression protection. The implementation is also materially different from the approved Stitch public/admin UI: current global fonts are Arial/Century Gothic, while Stitch specifies Literata/Hanken Grotesk; current public home uses a carousel/top social strip and the current admin uses a legacy dashboard/page form rather than the approved content hub/block editor.

Runtime execution was not possible in this checkout because `.env`, `vendor/`, and `node_modules/` are absent and PHP is not available on PATH. Consequently, all findings below are code/design evidence unless explicitly marked “not run.” No finding is presented as a passed runtime test.

## 2. Audit basis

Reviewed:

- Laravel routes, middleware, controllers, services, models and migrations.
- Vue layouts, homepage, donation form and representative components.
- Blade shell/admin page editor/dashboard.
- PHPUnit, Jest and Cypress configuration/tests.
- `design-reference/stitch/README.md` and the six downloaded Stitch HTML/PNG pairs.
- Root configuration/artifact names and `.gitignore`.

Not executed:

- Application boot, migrations, PHPUnit, Jest, Cypress, lint/build, payment sandbox, axe, Lighthouse or visual screenshot comparison.
- Live database/storage behavior.
- Browser, assistive technology and real-device testing.

The extracted folder is not a Git worktree, so this audit cannot determine whether sensitive root artifacts are tracked elsewhere. Their presence in the project folder remains a release/distribution risk.

## 3. Release-blocking findings

### QA-SEC-001 — P0 — Service-account private key is present in the project root

**Evidence:** `service-account-credentials.json` exists, identifies itself as a service account, and contains a `private_key` and `client_email`. It is not listed in `.gitignore`.

**Impact:** Anyone receiving the project/archive may obtain cloud access granted to that service account. Exposure persists even if the file is deleted later unless the key is revoked.

**Required action:** Revoke/rotate the key immediately, remove the file from all copies/history, add an ignore rule, load credentials from a secret manager/environment, and audit cloud access logs and IAM scope. Add secret scanning to CI. Do not copy the key into tests or documentation.

### QA-PRIV-001 — P0 — Database dump contains personal and privileged records

**Evidence:** `database/ignite.org.bd.sql` contains `INSERT` data for `admins`, `donations`, `sponsorships`, `subscribers`, `users`, and `volunteers`.

**Impact:** The archive can expose donor/user/volunteer/subscriber data and admin credential hashes. It also makes destructive tests unsafe if the dump is reused casually.

**Required action:** Quarantine the dump, determine provenance/consent, remove it from distributable/source artifacts and history, notify the data owner, and replace it with a deterministic synthetic seed. Apply retention/encryption/access policy and CI PII scanning.

### QA-SEC-002 — P0 — Admin password reset is a state-changing GET that sets a predictable password

**Evidence:** `routes/web.php:284` exposes `GET admin/{id}/reset`; `app/Http/Controllers/Admin/AdminController.php:197-203` changes the target password to literal `123456`.

**Impact:** A permitted or tricked authenticated admin can reset another account without CSRF protection to a publicly known password. The action is not a one-time token flow and creates an immediate account-takeover window.

**Required action:** Remove the route/behavior. Use POST with CSRF, explicit high-privilege authorization and audit logging to issue an expiring single-use reset link or force-set a random temporary secret plus mandatory reset/2FA. Add permission/CSRF/replay tests.

### QA-SEC-003 — P1 — Public GET endpoint mutates application caches/configuration

**Evidence:** `routes/web.php:411-422` defines unauthenticated `GET /clear` and calls cache/config/view/route/compiled clearing and `config:cache`.

**Impact:** Any visitor or crawler can trigger privileged operational changes and repeated expensive work, causing availability and configuration risk.

**Required action:** Delete the web route. Perform these operations only through deployment/CLI controls. If an operational endpoint is unavoidable, require strong authentication, authorization, non-GET method, CSRF or signed request, rate limit and audit logging.

### QA-PAY-001 — P1 — Exposed generic SSLCommerz flow calls nonexistent methods/routes

**Evidence:** `routes/web.php` exposes `/payment/sslcommerz/*`. `app/Http/Controllers/SSLCommerzController.php:124,209` calls `$this->sslCommerz->verifyTransaction()`, but the service only declares `verifyTransactionByValId()` and validation helpers. Controller response helpers at lines 278, 284 and 290 redirect to `payment.thank-you`, `payment.failed`, and `payment.cancelled`; those named routes are not defined.

**Impact:** Success/IPN execution can fail with a method error; failed response handling can also fail with missing routes. Payment state and donor feedback become unreliable.

**Required action:** Remove the duplicate unused route/controller surface or consolidate it with the donation flow using one tested API. Cover valid, invalid, replayed and reordered callbacks, plus every redirect target.

### QA-CMS-001 — P1 — Page deletion leaves page-tag relationships orphaned and is unrecoverable

**Evidence:** `app/Http/Controllers/Admin/PageController.php:404` deletes pages by UUID, then line 405 deletes `PageTagModule` using `page_id = $id`; `page_tag_modules.page_id` is an integer page ID (`database/migrations/2025_06_19_100022_create_page_tag_modules_table.php:17`). Pages/models do not use Laravel `SoftDeletes`, and migrations define no foreign keys/cascades.

**Impact:** Tag relationships can remain orphaned after page deletion. Content is hard-deleted without trash/restore/revision recovery, conflicting with the safe WordPress-like requirement and risking irreversible content loss.

**Required action:** Implement transactional trash/restore, stable relationship cleanup by numeric IDs or proper FK cascades, protected dependency checks, audited permanent deletion and full deletion/recovery tests for all translations and files.

### QA-SEO-001 — P1 — Missing content is returned as a soft 404

**Evidence:** `app/Http/Controllers/Vue/PageController.php:17-24` uses `first()`, then dereferences the possibly null page in lines 29-31; the catch renders the Inertia `errors-404` component without an HTTP 404 status at lines 44-50. Similar catch-and-render behavior exists in `Vue/HomeController.php:93-100`. The route fallback returns a 404 Blade view without explicitly preserving a 404 status (`routes/web.php:426-428`).

**Impact:** Missing content can return HTTP 200, harming crawling/index quality, caches, monitoring and client behavior.

**Required action:** Return a real 404 response/exception for missing records and verify status, title, robots noindex, canonical absence and user-facing page in feature/crawl tests.

### QA-SEO-002 — P1 — Social metadata contains placeholder/stale URLs and incomplete SEO controls

**Evidence:** `resources/views/app.blade.php:25` hard-codes `https://abc.scibd.info/en`. `resources/js/layouts/App.vue:52-63` includes placeholder handles, `http://example.com`, `/public/image/logo.svg`, blank site/app values, and Twitter card `product`. Page persistence exposes only `meta_title`, `meta_keyword`, and `meta_description` (`app/Models/Page.php:29-31`). No canonical, robots, Open Graph override, social image, schema, hreflang, redirect or sitemap implementation was found; `public/robots.txt` has no sitemap declaration.

**Impact:** Shared links can advertise the wrong URL/image and the admin cannot independently manage a complete per-page SEO pack.

**Required action:** Build centralized server-rendered SEO output from trusted production configuration and per-content/per-locale fields; implement the full QA matrix in `QA_PLAN.md`. Remove all placeholders and test final HTML.

### QA-AUTO-001 — P1 — Critical flows have no meaningful automated regression coverage

**Evidence:** PHPUnit has only a `true` assertion and one `/` status check. Jest has one sanity `true` and three wrapper-exists tests. Nine Cypress files exist, but six suites—including pages, page menus, galleries and categories—are entirely commented out. Only banner, editor draft and YouTube suites are active; database reset/seed is commented, login uses hard-coded `super_admin`/`123456`, assertions mainly stop after clicks/sleeps, and no delete, permission, SEO, payment, auth-security, accessibility or visual assertions exist. `phpunit.xml` leaves the isolated SQLite settings commented.

**Impact:** The rebuild can silently regress content, authorization, deletion, SEO and donation behavior. Existing Cypress runs can depend on/modify shared state and may pass without proving outcomes.

**Required action:** Establish the isolated test harness and Tier 0 suites before relying on refactors. Replace sleeps with observable assertions, seed synthetic users per role, reset data per test/run, and wire gates into CI.

### QA-UI-001 — P1 — Current public/admin UI is not the approved Stitch design

**Evidence:** Stitch public references specify Hanken Grotesk body + Literata headings, a 1240px content frame, 24px grid margin, 120px desktop/64px mobile section spacing, fixed hero composition, program/donation/project/story/accountability/partner/join/news sections, and orange `#FF7500`. Current `resources/scss/app.scss:27-43` uses Arial/Century Gothic; `resources/js/Pages/Home/component/banner.vue` implements a 600px auto-cycling carousel; `AppHeader.vue` is a social-icon strip; `Home/home.vue` inserts CMS HTML by page index and renders a smaller set of fixed modules. Current admin dashboard only shows comment/like cards, and the page editor is a TinyMCE description plus raw CSS form, unlike the Stitch dashboard, content hub and block editor.

**Impact:** The current implementation cannot pass pixel-fidelity acceptance or the requested admin customization experience.

**Required action:** Treat this as an implementation milestone, not a CSS polish pass. Establish reference rendering and visual baselines first, then rebuild shared design tokens/shell, homepage components and admin information architecture/editor. Pass the controlled visual thresholds in `QA_PLAN.md`.

## 4. Additional high-priority findings and risks

### QA-CMS-002 — P2 now / P1 when a second locale is enabled — Multilingual page create handles media/tags only for the last looped language

`PageController::store()` creates each locale inside a loop (`lines 75-105`) but thumbnail/tag handling occurs after the loop (`lines 108-126`) using `$language` and `$page` from the final iteration. When localization expands beyond English, earlier translations will not receive their submitted thumbnail/tags.

### QA-CMS-003 — P1 requirement gap — No block schema, revisions, trash, restore, reusable sections or global settings model

The current page model stores one HTML `description` and `inline_css`; the admin form is TinyMCE plus raw CSS. Searches found no page block, revision/rollback, reusable section, media usage, trash/restore, scheduled state machine, or audit implementation. `published_at` inputs in the page form are commented out while controller defaults dates, so a genuine scheduled-publish experience is not present.

### QA-CMS-004 — P2 — Database relationship integrity is largely unenforced

No migration in the audit contained foreign-key/cascade definitions. Content IDs are often nullable strings/integers without constraints, while controllers directly hard-delete records. This raises orphaning and partial-cleanup risk across categories, menus, media, pages, comments, tags and donations.

### QA-CMS-005 — P2 — Page update/delete file and locale behavior needs transactional side-effect testing

Database updates use transactions, but file removal/upload occurs inside those transactions and is not automatically rolled back. Updating a thumbnail removes the prior file before the database operation is guaranteed to commit. Page delete removes `/page/{uuid}`, while public image URLs are built under numeric/storage paths; live storage behavior must be verified.

### QA-SEC-004 — P1 — Authentication hardening is incomplete

Admin and frontend login code shows no explicit session ID regeneration after authentication and logout does not explicitly invalidate session/regenerate the CSRF token. No login throttling was found on the custom admin/frontend login routes. Admin validation reveals whether a username exists. Admin logout is a state-changing GET (`routes/web.php:309`). These require runtime security tests and remediation.

### QA-SEC-005 — P1 — Stored rich HTML and CSS need a deliberate sanitization/security model

Public home renders CMS descriptions with `v-html` and injects CMS `inline_css` as a `<style>` element (`resources/js/Pages/Home/home.vue:5,9`). The generic XSS middleware strips tags from incoming form values, but the admin content routes are not in the route group using that middleware and rich HTML intentionally needs tags. No allowlist sanitizer/CSP implementation was found. A malicious or compromised editor could potentially inject active content or deceptive global styles.

### QA-PAY-002 — P1 — Duplicate/reordered callback state transitions are not proven safe

The donation controller validates signed success/IPN callbacks and checks gateway amount/currency when a local transaction exists—good foundations. However, callbacks directly update donation status without an explicit terminal-state transition guard; fail/cancel uses raw callback data with `updateTransaction()` and no signature validation; `updateTransaction()` uses `updateOrCreate`, allowing unknown transaction creation. Success/IPN order, replay, concurrency, unknown IDs and downgrade attempts need idempotency tests and stricter state rules.

### QA-PAY-003 — P2 — Payment/donor privacy and reconciliation are incomplete

Payment callback payloads are logged and stored wholesale in `raw_response`; the schema includes card and customer fields. Retention/redaction/encryption/access controls were not found. Donation `transaction_id` is nullable and not unique/indexed, and there is no database foreign key to the gateway transaction. No reconciliation/receipt/refund audit test suite exists.

### QA-SEO-003 — P2 — Publishing and URL integrity lack constraints

`pages.slug`/`uuid` have no database uniqueness/index constraints in the page migration. Slug is generated from the English title on create and not updated/redirection-managed on edit. Duplicate locale/slug or UUID rows can produce ambiguous public pages. Draft/published dates are not reflected in robust crawl controls.

### QA-SEO-004 — P2 — Metadata coverage is inconsistent across content types

Home and page controllers populate the three legacy meta fields, while donation metadata is hard-coded. A complete per-content/per-locale field/output inventory does not exist. Search/auth/error/admin preview/indexability behavior and structured data are untested.

### QA-A11Y-001 — P1 acceptance risk — Primary accessibility behavior is not tested

No axe dependency/tests or manual accessibility evidence exists. Representative issues visible statically include icon-only mobile navigation/close controls without explicit accessible labels in `AppNav.vue`, click-only `<div>` controls in `AppFontSizeSetting.vue`, labels with empty `for` attributes in dialogs, a cycling carousel with no visible pause mechanism, and forms relying heavily on placeholders. Stitch references explicitly include focus rings, named icon controls and 44–48px targets, so this is both a compliance and fidelity gap.

### QA-RWD-001 — P1 acceptance risk — Responsive implementation has not been validated against Stitch

CSS includes several breakpoints, but there are no screenshot/reflow tests. The approved Stitch mobile design is a distinct composition; current implementation mostly scales the carousel/layout and hides carousel arrows. Long CMS/localized content, drawers, admin tables/editor side panels, 320px reflow, zoom, keyboard and device safe areas remain unverified.

### QA-LOC-001 — P1 requirement gap — Only English is currently supported

`app/Helper/Translation.php:10-14` returns only English and only `resources/lang/en.json` exists. Many public/admin UI labels and validation messages are hard-coded English. There is no `hreflang`, RTL strategy or Bangla date/number test. Stitch admin references visibly include localization/English-Bengali workflows, so the implemented state does not meet that target.

### QA-FUNC-001 — P2 — Editor-draft delete route contains a typo

The named route `editorDraft.destroy` uses URI `edito-draftgory/{id}` (`routes/web.php:157`) while the feature is otherwise `edito-draft`, making the delete endpoint surprising and likely disconnected from conventional client paths.

### QA-FUNC-002 — P2 — Error handling hides failures and can misclassify them

Multiple controllers catch broad exceptions and render a 404 or generic response, including home/page controllers. This can turn database/config/programming errors into user-facing “not found,” impede monitoring and return incorrect status. Exception paths need explicit classification and logged correlation IDs without sensitive details.

### QA-DATA-001 — P2 — Test and migration reproducibility is weak

The checkout has no dependencies or `.env`, PHP is unavailable on PATH, PHPUnit's isolated DB lines are commented, and Cypress DB refresh/seed calls are commented. A fresh, deterministic install/migrate/seed/test path has not been demonstrated.

## 5. Positive baseline capabilities

These are useful foundations, not verified passes:

- Admin routes are grouped behind `auth:admin` and a permission middleware.
- The permission middleware checks menu/action mappings against role permission lists.
- Page/category/banner/menu CRUD and publish toggles already exist.
- Page data has language, UUID, title/subtitle, category/banner, HTML, inline CSS, thumbnail, ordering, status and three legacy meta fields.
- Donation initiation validates amount, donor details and enabled donation cause.
- The newer donation flow signs/verifies callbacks through SSLCommerz validation helpers and compares local amount/currency when a local gateway transaction exists.
- Payment `tran_id` is unique in the gateway transaction table.
- Public components have some responsive CSS and many informative image alt attributes.
- Approved Stitch HTML and image references have been saved locally, enabling deterministic visual baselines.

## 6. Automated-test baseline

| Layer | Present | Effective baseline |
| --- | --- | --- |
| PHPUnit | 2 example tests | One tautology, one homepage 200; no domain regression protection |
| Jest/Vue | 4 tests | One tautology, three existence-only shallow mounts |
| Cypress | 9 spec files | 6 fully commented; 3 active CRUD-click suites with no DB isolation or substantive outcome assertions |
| Accessibility | None found | No automated/manual evidence |
| Visual regression | None found | Stitch references exist but are not wired into comparison |
| SEO crawl/contract | None found | No head/status/sitemap/redirect assertions |
| Security/payment | None found | No authorization matrix, callback/replay or secret gates |

Coverage cannot be meaningfully reported until the application runs. The highest priority is scenario quality and deterministic isolation, not inflating a percentage with shallow assertions.

## 7. Design fidelity baseline

### Approved reference inventory

- Public: `home-desktop-approved`, `home-mobile-approved`, `navigation-footer-approved`.
- Admin: `admin-dashboard`, `content-management-hub`, `page-section-editor`.

### Material differences already evidenced

| Area | Stitch | Current baseline |
| --- | --- | --- |
| Typography | Literata headings, Hanken Grotesk body | Arial/Century Gothic globally |
| Header | Utility/navigation pattern with approved mobile drawer and named controls | Orange social strip + Vuetify navigation; different information hierarchy |
| Hero | Fixed editorial hero with impact cards | Auto-cycling 600px carousel with large arrows |
| Homepage | Defined program, emergency donation, projects, story, events/news, accountability, partners, join/newsletter sections | CMS HTML loop plus causes/events/testimonial/awards components |
| Mobile | Purpose-designed stacked composition and 44–48px controls | Primarily breakpoint adaptation; no visual baseline |
| Admin dashboard | Donation/revenue/content/volunteer/localization/activity overview | Three legacy comment/like cards |
| Content hub | Unified statuses, locale filters, bulk/actions and card/list content management | Separate legacy CRUD modules |
| Page editor | Block canvas, device preview, settings/blocks/page panel, visibility/schedule | TinyMCE description + raw CSS + legacy metadata fields |

No percentage visual score is reported because the running application could not be captured. The first executable UI milestone must establish same-viewport reference/app screenshots before fidelity claims are made.

## 8. Immediate remediation order

1. **Contain P0 exposure:** rotate/remove the service-account key; quarantine/remove the real-data SQL dump; add secret/PII scans.
2. **Remove critical unsafe routes:** predictable GET password reset and public `/clear`; change logout/reset semantics and harden sessions/throttling.
3. **Make the project reproducible:** documented PHP/Composer/Node versions, sanitized env templates, install/migrate/seed/test commands and CI.
4. **Consolidate payment implementation:** remove the broken duplicate controller surface, define one idempotent state machine and build sandbox/replay tests.
5. **Design safe CMS persistence:** blocks, revision history, audit log, soft delete/trash/restore, dependencies/FKs, media usage and transactional behavior.
6. **Implement centralized SEO architecture:** correct HTTP semantics and full per-content/per-locale SEO pack before templating all modules.
7. **Build meaningful tests with the architecture:** permission/action matrix, CMS lifecycle, SEO contracts, payments, localization and deletion recovery.
8. **Establish Stitch design system and visual harness:** reference render, tokens, fonts, viewport matrix and reviewed baselines.
9. **Rebuild public shell/home, then admin surfaces:** continuously gate accessibility, responsiveness and pixel diffs.
10. **Run full staging regression/security/accessibility/performance and migration/restore rehearsal before release.**

## 9. Baseline release gate status

| Gate | Status | Reason |
| --- | --- | --- |
| Security/privacy | **Fail** | Root private key and personal-data dump; unsafe reset/operations routes |
| Build/reproducibility | **Blocked** | Dependencies/env absent and PHP unavailable in audit environment |
| CMS/admin lifecycle | **Fail** | Hard delete/orphan bug; no revisions/trash/restore/block architecture |
| Payment integrity | **Fail** | Exposed broken duplicate flow; no idempotency/replay suite |
| SEO | **Fail** | Soft 404, placeholder URLs, incomplete pack/output |
| Accessibility | **Not demonstrated / high risk** | No test harness or manual evidence; static issues present |
| Responsiveness | **Not demonstrated / high risk** | No reflow/device/visual suite |
| Localization | **Fail target** | English only; hard-coded UI; no localized SEO |
| Pixel fidelity | **Fail baseline** | Material structural/typographic differences from all six approved surfaces |
| Automated regression | **Fail** | Critical flows effectively untested |

## 10. Retest criteria for this baseline

Re-audit after the first foundation milestone only when:

- P0 artifacts have been rotated/removed and scanning is green.
- A clean environment can install, migrate/seed and run all checks without local secrets or real data.
- Critical operational/auth routes are remediated.
- Payment routes are consolidated and callback tests pass.
- CMS deletion/restore and SEO HTTP/head contract tests exist.
- The first controlled Stitch reference/app screenshot set is available.

The living strategy and milestone exit criteria are in `docs/qa/QA_PLAN.md`.

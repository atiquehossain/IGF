# Ignite Global Foundation — Living QA Plan

**Owner:** Senior QA  
**Status:** Active  
**Created:** 2026-08-14  
**Last updated:** 2026-08-14  
**Product stack:** Laravel 10, Inertia, Vue 3, MySQL, SSLCommerz  
**Design authority:** Google Stitch project `18398920452746138956`, with checked-in references under `design-reference/stitch/`

## 1. Quality objective

Release a trustworthy, accessible, responsive, SEO-manageable nonprofit platform whose public and admin experiences match the approved Stitch designs and whose content, permissions, deletion, authentication, and payment workflows do not regress.

“Pixel perfect” means the implementation is compared at controlled viewports against the Stitch HTML rendered at the same viewports, with deterministic data and fonts. “No UI issues” is treated as a release gate supported by automated visual comparisons plus a manual cross-browser and assistive-technology pass; it is not assumed from implementation alone.

## 2. Release principles

1. A release-blocking security, data-loss, payment, accessibility, or primary-journey defect stops release.
2. Destructive CMS actions must be recoverable by default. Permanent deletion must be explicit, restricted, audited, and dependency-aware.
3. Admin authorization is verified on the server for every action; hiding a control in the UI is not authorization.
4. SEO output is verified from the server-rendered document, not only from client-side state.
5. Each supported locale is independently publishable and testable without falling back to mixed-language UI.
6. Visual acceptance uses the downloaded Stitch references as source of truth. Any intentional deviation requires documented product approval.
7. Production secrets and real donor/user data must never be used as test fixtures or committed artifacts.

## 3. Scope and risk tiers

### Tier 0 — must never fail

- Admin authentication, logout, session protection, role/permission enforcement, password reset/change, and 2FA where enabled.
- Donation and sponsorship initiation, gateway validation, idempotent success/IPN processing, failed/cancelled flows, amount/currency integrity, and reconciliation.
- CMS create/edit/publish/unpublish/schedule/preview/revision/restore/trash/permanent-delete behavior.
- Preservation of content relationships and media during update, trash, restore, and permanent deletion.
- Production secret handling, personal-data handling, and privileged operational endpoints.

### Tier 1 — release critical

- Public home, navigation, footer, page, program/project, event/news, contact, resource, donation, sponsorship, and policy routes.
- Per-page SEO pack, status codes, canonical/hreflang, sitemap, robots, Open Graph/Twitter, redirects, and structured data.
- Responsive behavior and Stitch fidelity at desktop, tablet, and mobile.
- WCAG 2.2 AA coverage for keyboard, focus, names/labels, semantics, contrast, reflow, errors, motion, and touch targets.
- Localization of content, navigation, forms, validation, metadata, dates/numbers, and admin translation state.

### Tier 2 — important

- Admin dashboard metrics, filtering/search/pagination/bulk actions, media library, analytics, exports, email/subscriber tools, and non-primary integrations.
- Performance budgets, graceful error states, offline/slow-network behavior, and browser back/forward behavior.

## 4. Test environments and data

### Required environments

| Environment | Purpose | Data policy |
| --- | --- | --- |
| Local/CI isolated test database | Unit, feature, component, API and browser tests | Synthetic seeded data only; reset per run |
| Staging, production-like | Payment sandbox, migrations, caching, queues, storage, mail capture, full regression | Synthetic named QA personas; no production dump |
| Production smoke | Read-only health, metadata, navigation and one controlled gateway smoke when approved | Never mutate content outside a dedicated canary |

### Browser and viewport matrix

The automated primary matrix is Chromium, Firefox, and WebKit. Manual sign-off includes current Chrome and Edge on Windows, Safari on macOS/iOS, and Chrome on Android.

| Class | Viewports |
| --- | --- |
| Desktop | 1440×900, 1280×800 |
| Tablet | 1024×768 landscape, 768×1024 portrait |
| Mobile | 390×844, 360×800, 320×568 |
| Reflow/zoom | Desktop at 200% and 400%; 320 CSS-pixel reflow |

### Deterministic fixtures

- Freeze clock and timezone for visual and schedule tests.
- Seed one item for every publish state, locale state, relationship state, image state, SEO state, and permission boundary.
- Include very short/long titles, unbroken strings, emoji, Unicode, Bangla text, missing images, maximum upload sizes, zero-result lists, and pagination boundaries.
- Stub analytics, email, social login, geolocation, and payment gateway calls in CI.
- Use SSLCommerz sandbox on staging for signed callback and retry scenarios.

## 5. Test strategy

### 5.1 Static and build checks

- PHP syntax, Laravel config/route validation, migration dry run, Composer audit, ESLint, Vue/Jest, production asset build, and npm audit.
- Secret scanning, personal-data artifact scanning, dependency/license review, and debug/config checks.
- Fail CI on committed private keys, credentials, database dumps containing personal data, or production `.env` material.

### 5.2 Backend unit and feature tests

- Policies/middleware: every admin route exercised as guest, insufficient role, permitted role, disabled admin, and super admin.
- Models/services: publish state, locale selection, slug uniqueness, scheduling, revisions, soft deletion, relationship cleanup, payment validation, status transitions, and idempotency.
- Controllers: happy path, validation failure, missing record, duplicate request, unauthorized request, storage failure, transaction rollback, and concurrent update.
- HTTP contract: status code, redirect, session/CSRF behavior, cache headers, response schema, and database/file side effects.
- Migration tests from a sanitized baseline and from a fresh database.

### 5.3 Vue component tests

- Render behavior for empty/loading/error/long-content states.
- Keyboard and focus behavior for navigation, drawer, dialogs, carousels, editors, and forms.
- Validation messages associated with fields; loading state prevents duplicate submission.
- Localized labels and dynamic metadata.
- Block editor operations tested at the component level: add, edit, duplicate, reorder, hide, delete, undo, device visibility, and dirty-state warning.

### 5.4 End-to-end journeys

Automate with stable `data-testid` selectors; avoid sleeps and assertions that only prove an element exists.

1. Admin logs in, creates a localized draft page from blocks, previews it, configures SEO, publishes it, edits it, verifies a revision, trashes it, restores it, and permanently deletes it with dependencies handled.
2. Editor cannot change roles/settings or permanently delete protected/global content.
3. Admin configures header, footer, navigation, reusable section, branding and social/contact settings and sees the public update.
4. Public user navigates every primary route on desktop/mobile and completes contact, subscribe, volunteer, donation, and sponsorship forms.
5. Donor initiates a payment; signed success and IPN callbacks arrive in both orders and on retry; exactly one successful donation is recorded for the original amount/currency.
6. Failed, cancelled, expired, invalid-signature, mismatched-amount/currency, unknown-transaction, and gateway-timeout payment scenarios remain unpaid and show safe recovery messaging.
7. Localized content and SEO are switched, published, unpublished, and fallback behavior is verified.
8. Redirect creation preserves old URLs after slug changes and prevents loops/chains.

### 5.5 CMS deletion and recovery matrix

Every content type must pass this matrix before it is considered WordPress-like and safe.

| Operation | Required result |
| --- | --- |
| Trash | Removed from public queries; retained in admin trash; relations/media retained; audit entry created |
| Restore | Same public URL, content, translations, SEO, blocks, relations and media restored |
| Permanent delete | Permission + explicit confirmation required; protected/global dependencies blocked or reassigned; transactional cleanup completes |
| Bulk trash/restore/delete | Per-item result reported; partial failure is recoverable and auditable |
| Parent/category/menu deletion | Child impact explained before action; no orphan navigation or database rows |
| Media deletion | Usage references shown; in-use media cannot be silently broken |
| Concurrent edit/delete | Optimistic-lock conflict shown; newer work is not silently overwritten |

Verify this for pages, blocks, reusable sections, menus, banners, categories, programs/projects, events/news, galleries/albums, reports/resources, donation causes, testimonials, volunteers, subscribers, users/admins/roles, and settings where deletion is applicable.

### 5.6 SEO pack validation

For every indexable public content type and locale, test:

- Editable SEO title and description with length guidance and SERP/social preview.
- Editable slug with uniqueness validation and automatic redirect from the old URL.
- Canonical URL, robots index/follow directives, sitemap inclusion/priority/update time, and noindex for draft/preview/search/admin/auth/error pages.
- Open Graph title/description/type/image/URL/site name and Twitter card equivalents using absolute production URLs.
- Per-locale canonical and reciprocal `hreflang`, including `x-default` when applicable.
- JSON-LD appropriate to page type (Organization/WebSite, BreadcrumbList, Article, Event, Project/CreativeWork, FAQ only when visible content qualifies).
- Correct 200/301/404/410 status semantics and no soft 404s.
- Server-rendered `<head>` output without placeholder domains, duplicate titles, or conflicting tags.
- XML sitemap and robots responses, redirect loop/chain checks, and broken internal-link crawl.

### 5.7 Accessibility

Target WCAG 2.2 AA. Automated axe scans run on all templates and important component states, followed by manual testing:

- Semantic landmarks, one logical H1, heading order, list/table structure, meaningful alt text, and decorative-image handling.
- Accessible names for icon controls, form labels/instructions, programmatic errors, required state, autocomplete, and status announcements.
- Full keyboard operation, visible focus, logical order, skip link, focus trapping/restoration, escape behavior, and no keyboard traps.
- Contrast in default/hover/focus/disabled/error states; content at 200% zoom and 400% reflow.
- Touch target size, orientation, pointer alternatives, reduced motion, carousel pause/control, and timeout behavior.
- NVDA + Chrome/Firefox and VoiceOver + Safari smoke passes on primary journeys.

Zero critical/serious axe violations is required; automated results never replace manual sign-off.

### 5.8 Responsive and compatibility QA

- No horizontal page scroll at any supported viewport, except deliberate data regions with accessible scrolling.
- Navigation, drawer, dialogs, editor panels, grids, tables, forms, charts, rich text, images and long localized strings reflow without clipping or overlap.
- Sticky headers do not hide anchors/focus; virtual keyboard does not obscure active fields; safe areas are honored on iOS.
- Browser back/forward, deep links, refresh, slow network, image failure, print/PDF links, download routes and external links are checked.

### 5.9 Pixel-fidelity and visual regression

1. Render each approved Stitch `.html` locally at the same viewport, browser, device scale factor, font availability, animation state, and frozen data used for the application capture.
2. Capture full-page and component-level screenshots for home, navigation/footer, admin dashboard, content hub, and page editor.
3. Mask only documented nondeterministic data (timestamps, analytics charts if not fixture-driven); never mask layout or typography.
4. Compare using pixel diff plus structural review. Initial acceptance target: no unexplained geometry shift above 2 CSS pixels, no text clipping/overlap, SSIM ≥ 0.995 in stable regions, and ≤ 0.5% mismatched stable pixels. Human QA approves any antialiasing-only variance.
5. Review at desktop, tablet and both primary mobile widths; add component snapshots for hover, focus, active, open-drawer, validation, loading and empty states.
6. Store approved baselines with the change that intentionally updates them. A baseline update requires design/QA review, not an automatic overwrite.

### 5.10 Security and privacy

- OWASP-oriented checks for authentication, authorization/IDOR, CSRF, XSS in CMS rich text and inline styling, SSRF/file upload, path traversal/downloads, open redirect, mass assignment, injection, rate limiting, session fixation, secure cookies and security headers.
- Restrict operational/cache routes; destructive/reset/logout operations use appropriate non-GET methods and CSRF protection.
- Verify least privilege and an immutable audit trail for publish, delete, permission, payment and settings changes.
- Logs and error responses must not expose credentials, gateway payload secrets, full card data, personal data, SQL, paths or stack traces.
- Consent, retention, export and deletion policies are verified for donors, volunteers, subscribers and contact data.
- Payment callbacks validate signature, gateway response, local transaction existence, amount, currency and terminal-state transition; replay/reordering is idempotent.

### 5.11 Performance and resilience

- Production Lighthouse targets on representative mobile runs: Performance ≥ 90, Accessibility ≥ 95, Best Practices ≥ 95, SEO ≥ 95.
- Core Web Vitals targets: LCP ≤ 2.5 s, INP ≤ 200 ms, CLS ≤ 0.1 at p75 in field data when available.
- Image dimensions/formats/lazy loading, font loading, bundle size, cache headers, database N+1 queries, pagination and large admin lists are profiled.
- Failed storage, queue, mail, analytics and payment dependencies degrade safely; users receive actionable, non-duplicating feedback.

## 6. CI quality gates

Required on each merge request:

1. Secret/PII scan and dependency audit.
2. PHP lint, backend unit/feature tests, migration test and coverage report.
3. ESLint, Jest/component tests and production frontend build.
4. Browser smoke for public home/page/navigation and admin login/content list.
5. Axe scans for changed templates.
6. Visual regression for changed Stitch-governed surfaces.
7. Full Tier 0 end-to-end suite for changes touching auth, permissions, CMS persistence/deletion, payments, routing, middleware, migrations or shared layout.

Coverage is used as a gap signal, not a substitute for assertions. Initial targets after harness stabilization: ≥80% lines/branches for new backend domain code, ≥80% for new Vue business logic, and 100% scenario coverage of Tier 0 state transitions.

## 7. Defect severity and release policy

| Severity | Meaning | Release policy |
| --- | --- | --- |
| P0 Critical | Credential/PII exposure, auth bypass/account takeover, irreversible broad data loss, fraudulent/incorrect payment | Stop work/release; remediate immediately and rotate/notify as applicable |
| P1 High | Primary journey broken, wrong HTTP/SEO semantics at scale, unsafe deletion, serious accessibility blocker, material design break on primary viewport | Must be fixed before release |
| P2 Medium | Important feature degraded with workaround, localized/responsive edge failure, moderate accessibility issue | Fix in milestone or obtain explicit risk acceptance |
| P3 Low | Cosmetic or low-impact issue with no content/task loss | Backlog with design/product triage |

A defect is closed only with a regression test or a documented reason automation is impractical.

## 8. Milestone gates

### M0 — foundation and security baseline

- Runtime/dependencies reproducible; CI green.
- All baseline P0/P1 security and data-handling blockers closed.
- Synthetic seed, secret scan, test database, payment sandbox and route/permission inventory established.

### M1 — CMS architecture, deletion, revisions and SEO

- Block/page CRUD, reusable sections, global settings, revisions, preview, publish/schedule, trash/restore/permanent delete and audit pass the matrices above.
- SEO pack output and crawl tests pass for pages and core content types.
- Permission boundary suite passes for every action.

### M2 — global shell and homepage

- Navigation/footer and desktop/mobile homepage match Stitch acceptance thresholds.
- Keyboard, axe, reflow and cross-browser gates pass.
- Admin changes propagate safely to public output.

### M3 — remaining public modules and donation portal

- Programs/projects, content/events/resources/contact and all forms pass functional, SEO, localization, accessibility and responsive suites.
- Payment sandbox, replay, reconciliation and failure recovery suites pass.

### M4 — admin redesign and full regression

- Dashboard/content hub/editor match Stitch references.
- Complete regression, data migration rehearsal, backup/restore drill, security review, performance pass and stakeholder UAT complete.

## 9. Release exit checklist

- [ ] No open P0/P1 defects; P2 exceptions have owner, expiry and written risk acceptance.
- [ ] Full automated suite green from a clean checkout and fresh database.
- [ ] Fresh install and sanitized upgrade migration both pass; rollback/restore plan tested.
- [ ] All Tier 0 journeys pass in production-like staging.
- [ ] Design comparison approved at the required viewports with recorded diffs.
- [ ] WCAG 2.2 AA automated and manual sign-off complete.
- [ ] SEO crawl/head/status/sitemap/redirect checks complete.
- [ ] Browser/device matrix complete.
- [ ] Secret/PII scan clean; production config/debug/security headers reviewed.
- [ ] Payment reconciliation and duplicate-callback checks complete.
- [ ] Backup restore, monitoring, alerting and rollback are ready.
- [ ] Release notes list known limitations and intentional Stitch deviations.

## 10. QA evidence and reporting

For each milestone, keep:

- Test run link/command, commit identifier, environment/config, browser versions and seed version.
- JUnit/coverage, axe, crawl, Lighthouse and visual diff artifacts.
- Manual exploratory charter notes and device screenshots.
- Defect list by severity, retest evidence, residual risks and release recommendation.

This plan is updated whenever architecture, supported locales/content types, payment behavior, or Stitch approvals change.

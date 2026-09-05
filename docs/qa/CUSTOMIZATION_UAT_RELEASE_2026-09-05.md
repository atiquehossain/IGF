# Customization and UAT release record — 2026-09-05

## Verdict

The repository is a **locally verified code release candidate**. All appropriate public editorial content, localized interface copy, presentation presets, menus, email copy, forms, opportunities, donation content, and public metadata are manageable through permission-scoped Super Admin/Admin screens. The application deliberately does not expose credentials, authentication rules, authorization, security validation, payment verification, infrastructure, or database schema as no-code settings.

This is not approval to accept live donations or launch the production host. Payment/OTP provider onboarding, production credentials, a patched PHP runtime, the exact production database/restore rehearsal, infrastructure configuration, and owner/legal acceptance remain external gates.

## What an authorized non-technical administrator can manage

| Area | No-code capability |
| --- | --- |
| Pages and sections | Create, edit, reorder, duplicate, schedule, publish/hide, trash/restore, reuse sections, choose media, and select a validated presentation for every section |
| Global design | Font pairing, corner radius, shadows, header palette/density/sticky behavior, footer palette/layout, brand assets, theme colors, and responsive preview |
| Header, footer, and menus | Localized utility/navigation/footer links; safe custom links; ordering; publication; three-level menu/submenu trees; contact, social, legal, about, and trust content |
| Public system pages | Localized labels, guidance, status/empty states, search/filter copy, confirmations, receipts, and metadata fallbacks for Contact, Reports, Gallery, Search, Projects, Events, About, Sponsorship, Volunteer, Careers, Workshops, Zakat, and donation journeys |
| Structured content | Programs, projects, events/news, team groups/members, awards, reports/covers/PDFs, gallery, stories, careers, workshops, forms, and reusable content with their safe lifecycle controls |
| Donations | Cause/group cards, ordering, imagery, destination rules, fixed-purpose assignment, direct-donation page content, amounts, impact text, checkout labels, result copy, SEO, reporting, attribution chart/table, and allocations where permitted |
| Languages | English/Bangla content, public language visibility, Translation Center inventory, localized admin UI, validation, dates/numbers, metadata, and email template copy |
| Automatic emails | Localized newsletter, sponsorship, and volunteer subjects/body/button/closing fields using an allowlisted placeholder contract and safe HTML rendering |
| Search and sharing | Titles, descriptions, social images, visibility, canonicals within policy, Schema presets, review workflow, revisions, redirects, 404 workflow, sitemap/robots output, and technical scan |
| Enquiries and operations | Permission-scoped review/workflow for donations, sponsorships, volunteers, contacts, chat, subscribers, applications, registrations, assignments, notes, exports, and privacy actions |

An ordinary Admin sees and changes only capabilities granted to that role. Super Admin/owner controls delegation. View-only roles keep useful lists and guidance without receiving mutation controls.

## Deliberately protected from no-code editing

- application, database, mail, OAuth, analytics, payment, and storage credentials;
- password/session/2FA rules, role hierarchy, permission enforcement, throttles, CSRF, trusted-host/proxy, CORS, and security headers;
- upload type/size/path containment, sanitization, validation limits, and workflow/state-machine semantics;
- payment provider identity, BDT currency, callback/signature/amount verification, idempotency, reconciliation rules, and provider OTP/PIN/card handling;
- protected stable IDs, database tables/migrations/indexes, filesystem paths, queues, scheduler, backups, logs, and deployment configuration;
- the approved Zakat calculation rate and other policy invariants whose wording and source data may be edited but whose security/financial rule must not be silently changed.

These are intentional safety boundaries, not missing CMS features.

## Automated verification

| Gate | Result |
| --- | --- |
| Laravel/PHPUnit | **PASS — 1,071 passed, 1 skipped, 20,888 assertions** |
| Skipped test | Dedicated real-MySQL competing-seat lock test; requires `IGF_MYSQL_CONCURRENCY_DSN` and must run on the approved target topology |
| Vue/Vitest | **PASS — 38 files, 356 tests** |
| Isolated Chrome Cypress | **PASS — 16/16 specs, 38/38 tests, 0 failures, 0 pending/skipped; 5m 46s**; six retained legacy files contain comments only |
| Cypress isolation | Fresh SQLite database, separate `.env.cypress`, port 8001; development/XAMPP data was not modified |
| ESLint | **PASS** |
| Vite production build | **PASS — 1,364 modules transformed** |
| PHP syntax | **PASS — 911 files, 0 failures** |
| Route inventory | **PASS — 566 routes, 0 duplicate names, 0 duplicate method/URI pairs** |
| npm production dependency audit | **PASS — 0 vulnerabilities** |
| Composer manifest/advisory audit | **PASS — strict manifest validation; 0 non-development advisories** |
| Composer platform requirements | **EXTERNAL GATE — local PHP lacks `ext-sodium`; production preflight now fails closed until it is loaded** |
| Git-tracked source security scan | **PASS — no checked credential or personal-data artifacts** |
| Clean staged release scan | **PASS — no runtime environment, credential, personal-data, log/session, or unapproved database artifacts** |
| Security scanner regression | **PASS — 13 tests** |
| Sanitized database guard | **PASS — 12 tests, 799 assertions** |

The browser suite covers admin sign-in/password flow, mobile shell, settings save/reset, CRUD and publication, recruitment/workshops, dynamic forms, import/export, donation catalog/detail, team directory, responsiveness, and keyboard-relevant flows. Its first final run exposed a real Website Customizer defect: the growing form had 1,003 successful controls while the local PHP request parser accepted 1,000 variables. Validation rejected the truncated request before any database write, while restored old input made the attempted value appear saved. The customizer now submits the complete settings tree as JSON, retains all server validation and optimistic-concurrency protection, reports success or actionable errors in place, refreshes the locale-correct preview, and has a browser regression proving that an office-address edit reaches the public footer and can be restored. The all-green rerun was performed after this repair.

## Manual and non-technical-admin UAT

The live local application was inspected interactively in English and Bangla at compact/mobile and desktop layouts.

- Public Contact, Annual Reports, Gallery, Search, Projects, Volunteer, Events, Zakat, About, Sponsorship, and direct Donation pages rendered localized titles and content with no observed horizontal overflow, unlabeled visible controls, or missing image alternatives.
- The donation amount selection and transition to donor details/payment worked. Required donor inputs have associated localized labels and autocomplete. Payment choices correctly remain unavailable without configured provider credentials.
- Three-level navigation was opened through **Our Work → Youth Development → Workshop**, including expanded-state semantics.
- The Bangla Dashboard, mobile sidebar, grouped admin menus, Content Hub, Navigation editor, Translation Center, Email Templates, Website Customizer, and Page Builder were used without observed overflow or unlabeled visible controls.
- Page Builder section selection, presentation choices, content-layout choices, responsive preview, and permission-aware controls worked. Navigation offered accessible move/indent/edit actions; Translation Center search worked; email fields exposed safe labels/placeholders.
- Admin sign-in rendered fully localized English/Bangla validation and used the administrator-managed localized site name.

This UAT proves the exercised local workflows; it is not a retained pixel-diff, screen-reader certification, multi-browser lab result, or formal stakeholder acceptance signature.

## Sanitized database included with the release

`database/seeders/seed-data/igf-public-content.sqlite` is a newly constructed Git-safe SQLite artifact, not a deleted-row copy of the XAMPP/live database.

- Size: **2,031,616 bytes**
- SHA-256: **`6cdc089afee2677c34eee62a5675e8ec9b4b803f68e42ea44fef2f825088c6a8`**
- Schema: **155 migrations**, including localized transactional email templates, editable donation-cause content, utility navigation, and stable event/news card kinds
- Content parity: byte-normalized parity with the **29-table** approved CMS snapshot
- Sensitive data: all operational/private tables are empty; private CMS fields are empty; there are no admin/member accounts, passwords, OAuth records, donations, payment transactions, messages/chat, subscribers, applications/registrations, analytics, jobs, or audit history
- Integrity: SQLite integrity, foreign keys, free-page check, table classification, content field policy, snapshot parity, and companion checksum all pass

Another developer receives the reviewed design/content by copying this artifact to a writable local database and following its companion README. The guide also installs the tracked annual-report PDFs into private storage so restored report downloads do not return 404. They must provision their own administrator securely; no credentials are committed.

## External production gates

1. Upgrade the deployment runtime: local PHP 8.2.12 is below this project's production security baseline of PHP 8.2.33, and the host must load `ext-sodium` as required by the locked production dependencies. Both conditions are enforced by production preflight.
2. Configure the real HTTPS host, protected application key, secure cookies, trusted proxies/hosts, explicit CORS origins, private storage, least-privilege database, production cache/session/queue/mail, scheduler, monitoring, and tested backups; require `php artisan igf:production-preflight` to pass.
3. Rehearse additive migration and full backup restore on the exact production MySQL/MariaDB topology, including the dedicated concurrency test.
4. Complete payment/OTP merchant onboarding and provider sandbox/UAT: hosted checkout, signed callbacks, replay/idempotency, amount/currency, success/fail/cancel, settlement/reconciliation, refund, incident, and finance approval. Never collect wallet PINs, card numbers/CVV, or provider OTP inside this app.
5. Obtain owner/legal/editorial approval for English/Bangla content, donation and Zakat policy, reports, privacy/retention, safeguarding/refund/terms, accessibility, and SEO/indexing.
6. Run authoritative CI plus clean release-package scanning, staging accessibility/cross-browser/200% zoom checks, performance/load tests, and stakeholder acceptance on the final domain.

Until these gates are recorded, the correct status is **code release candidate, not production launch ready**.

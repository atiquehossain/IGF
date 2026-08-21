# Post-M0 Admin UI Milestone — Independent QA Audit

**QA owner:** Senior QA  
**Audit date:** 2026-08-14  
**Build reviewed:** Shared workspace after the Laravel 12 / Passport 13 M0 remediation and the first admin dashboard/content-hub/page-builder milestone  
**Design authority:** `design-reference/stitch/admin-dashboard.png`, `content-management-hub.png`, and `page-section-editor.png`, with their checked-in HTML exports  
**R2 retest:** 2026-08-14  
**R3 retest:** 2026-08-14  
**Overall release verdict:** **FUNCTIONAL RELEASE PASS / VISUAL SIGN-OFF CONDITIONAL — no open P0/P1 implementation defect; this independent task still cannot reproduce the live pixel/overflow run**

## 1. Executive result

The server-side page-builder foundation is materially stronger and its automated integrity suite is green. Create, update, duplicate, exact-list reorder, soft deletion, revision restore, cross-page revision isolation, scheduling, and reusable-section restoration all pass permanent feature tests.

Two data-integrity defects were independently reproduced during this audit and fixed in-cycle:

1. A normal block save cleared existing `available_from` and `available_until` values when those fields were omitted. The controller now distinguishes omitted values from explicit `null`, the editor exposes both controls, and the permanent regression test passes.
2. A linked reusable section was edited globally while its page revision retained only the link to the already-changed library record. Revisions now snapshot linked reusable records and restore/remap them before page blocks. The permanent regression test passes.

R2 resolved the original mobile viewport-switch failure, pointer-only block selection, dynamic label associations, tab semantics/keyboard navigation, duplicate Page tabpanels, top-level dirty-navigation guards, and the missing Content Hub filters/bulk operations. Permanent Content Hub tests now cover draft duplication/translation and recoverable multi-locale deletion.

R3 closes the remaining authoring blocker. The editor now tracks `page`, `block:<uuid>`, and `order` dirty scopes independently; saving one scope does not clear another. Every action that replaces or mutates the current inspector confirms before discarding it. Media upload is bound to its originating block and blocks selection/mutation/save during an active request. Reorder failures remain dirty and produce an explicit retry message.

The approved editor controls are now materially present: a keyboard-operable five-action publishing menu; accessible image dropzone and media-library link; configurable Hero overlay in editor and public rendering; 28-by-28 reorder controls; and live status announcements. Image upload is restricted end-to-end to JPG/PNG/WebP/GIF, and MP4 rejection has a permanent regression test.

No P0/P1 implementation blocker remains in this audit. The primary task reported a successful in-app browser pass for cross-scope persistence, menu state, control size, zero builder overflow, dropzone, and overlay. This independent QA task still receives `No browser is available` with an empty browser enumeration, so it cannot independently reproduce that pass or certify numerical pixel fidelity against Stitch. The final verdict is therefore a functional release pass with visual sign-off conditional on retaining the primary browser evidence and completing stored same-viewport diffs.

## 2. Acceptance findings and disposition

### UI-R1 — Mobile preview selector is unreachable — RESOLVED IN R2

**R2 status:** **PASS by static evidence and permanent render regression; live mobile interaction remains part of VISUAL-R1**  
**Evidence:** `resources/views/admin/page/builder.blade.php:104-125`, `:136-139`, `:527-534`; `PageBuilderIntegrityTest::test_authorized_editor_can_render_the_page_builder`

All three viewport buttons remain visible at `max-width:760px`; the former inactive-button hiding rule is absent. Buttons retain names and pressed state, and the event handler updates the preview viewport.

**Remaining acceptance:** Verify actual Desktop/Tablet/Mobile selection and device-visibility rendering at 390, 768, 1024, and 1440 CSS pixels when a browser is available.

### A11Y-R1 — Block selection is pointer-only — RESOLVED IN R2

**R2 status:** **PASS by static evidence; keyboard browser smoke remains part of VISUAL-R1**  
**Evidence:** `resources/views/admin/page/builder.blade.php:324-335`, `:471-483`

Every block-list selection target is now a native button with visible focus styling and `aria-pressed`; native keyboard activation reaches the existing click handler. Pointer-only preview selection remains as a redundant path, not the sole way to select a block.

**Remaining acceptance:** Verify the entire add/select/edit/reorder/duplicate/delete journey without a pointer in the live browser.

### A11Y-R2 — Inspector and tab semantics are incomplete — RESOLVED IN R3

**R3 status:** **PASS by code and reported primary-browser evidence; independent assistive-technology pass remains desirable**  
**Evidence:** `resources/views/admin/page/builder.blade.php` tab, inspector, reorder, notice, media-picker, and publish-menu implementations

R2 implemented WAI-ARIA tabs, roving tab index, keyboard navigation, and associated generated labels. R3 increases reorder controls to 28 by 28 pixels, exposes transient notices as atomic polite status regions, and adds ArrowUp/ArrowDown/Home/End plus Escape behavior to the publishing menu. The primary browser run reported the expected control size and menu state.

**Residual recommendation:** Store a formal keyboard/screen-reader trace. Menu items are usable with arrow keys and native activation; adopting a strict roving `tabindex` within the ARIA menu would align even more closely with the authoring-practices pattern.

### DATA-R1 — Dirty edits can be discarded — RESOLVED IN R3

**Severity:** P1 / authoring data-loss risk  
**R3 status:** **PASS**  
**Evidence:** scoped dirty/upload/order state and guarded action paths in `resources/views/admin/page/builder.blade.php`; reported primary browser cross-scope pass

The editor uses a `Set` of independent dirty scopes. Block save clears only the originating block; Page save clears only Page/SEO. Block selection, reorder, add, attach, duplicate, promote, detach, and delete all resolve unsaved inspector state first. Locale, same-tab link, refresh, and close guards operate while any scope remains dirty. Reorder is marked dirty before the request and clears only on success; failure retains the dirty scope with a retry message.

**Residual recommendation:** Move the large inline editor script into a linted/testable module and automate the reported primary-browser scenarios so this contract is not dependent on manual evidence.

### DESIGN-R1 — Content Hub approved workflow — RESOLVED FUNCTIONALLY IN R2

**R2 status:** **PASS for static structure and server contracts; pixel comparison remains blocked**  
**Evidence:** Approved controls in `design-reference/stitch/content-management-hub.html:278-352`; current implementation in `resources/views/admin/page/index.blade.php:23-70`; `PageController::bulkCopy`; `PageController::bulkDestroy`; `ContentHubIntegrityTest`

The Content Hub now exposes Content Type, Language, Status, and Needs Translation filters; Select All; a selected count; target language selection; and functional bulk Translate, Duplicate, and recoverable Delete operations. New drafts copy blocks, tags, and safe no-index SEO metadata. Bulk delete captures revisions and soft-deletes every language version. Permission middleware maps copy to page edit and deletion to page destroy.

**Residual risks:** The required-language set is inferred from languages already present in `pages`, rather than an authoritative supported-locale registry; a site containing only one language cannot use this hub to introduce the first page in another language. `target_language` accepts any string up to ten characters instead of an allowed-locale list. Resolve these before promising controlled multilingual governance. Live layout and interaction remain unverified.

### DESIGN-R2 — Editor inspector approved controls — RESOLVED IN R3

**R3 status:** **PASS for feature equivalence; exact pixel diff remains VISUAL-R1**  
**Evidence:** Approved controls in `design-reference/stitch/page-section-editor.html:348-386`; editor media/overlay/publishing implementation; `PageBuilderIntegrityTest`

The editor now provides a keyboard-operable image dropzone, direct upload under page-edit permission, an accessible media-library link, configurable `overlay_opacity`, device/schedule controls, and five real publishing actions. New Hero blocks receive the overlay setting by default; the public Vue renderer and editor preview use the same clamped value. Unsupported video upload is rejected rather than being assigned to an image field.

**Residual recommendation:** Replace generic JSON editing for complex block arrays with schema-driven controls in a later authoring-usability milestone.

### VISUAL-R1 — Independent pixel and overflow reproduction could not run

**Severity:** Evidence limitation; not an implementation defect

The browser-control environment for this independent task returned `No browser is available`, and browser enumeration returned no instances. In accordance with the browser QA workflow, no unrelated standalone browser-control fallback was substituted. The primary task reports zero builder overflow and successful interaction checks, but this QA task has no independently captured evidence for:

- desktop/tablet/mobile horizontal overflow;
- sticky header/sidebar interaction;
- focus visibility and focus order;
- real font loading and layout shifts;
- browser console/network errors;
- screenshot overlays or numerical image-diff thresholds;
- Chrome/Edge/Firefox/Safari or screen-reader behavior.

**Required acceptance:** Preserve the primary browser evidence and repeat the same-viewport reference/application capture in an automated or independently available browser. Store baseline and diff artifacts. Numerical pixel fidelity cannot be inferred from Blade/CSS inspection.

## 3. Stitch comparison matrix

| Surface | Static structure | Reference-aligned details | Material differences | Status |
| --- | --- | --- | --- | --- |
| Admin dashboard | Overview heading; four metrics; large seven-month revenue chart; localization tracker; recent activity | Typography intent, orange/brown palette, card hierarchy, chart/side-column composition | Seeded counts and rolling month labels do not match the fixed reference; no deterministic visual-regression fixture; no live render | **Partial / visual gate blocked** |
| Content Hub | Overview/filter rail, search, content rows, status badges, create and row actions | Content-type/language/translation filters, selected count, and bulk Translate/Duplicate/Delete align with the approved workflow | Required languages are inferred from existing content; independent pixel diff unavailable | **Functional pass; visual conditional** |
| Page section editor | Full-height editor, canvas, right inspector, Settings/Blocks/Page tabs, device controls, preview/publish actions, seeded hero/stats content | Viewport controls, semantic tabs, scoped dirty protection, media dropzone, overlay, schedule/device controls, and five publishing actions | Complex arrays still use generic JSON; independent pixel diff unavailable | **Functional pass; visual conditional** |

The dashboard remains the closest static match. Its markup also has the strongest accessibility baseline: semantic regions, an image-labelled chart, and labelled progress bars. Content Hub implements the approved bulk workflow with permanent server tests. R3 closes the editor's previously blocking workflow gaps.

## 4. Responsive and overflow review

### Static findings

- Dashboard CSS collapses four metrics to two at 1200px and one at 640px; the chart/side panels collapse by 900px. No definite horizontal overflow source was found statically.
- Content Hub collapses the side rail at 1100px and rows to a compact three-column layout at 680px. Its bulk actions and quick tools intentionally use horizontal scrolling. Live long-title/localized-text testing remains required.
- Page builder constrains the right panel and builder to `100%` on mobile and removes fixed-height overflow. All viewport choices now remain visible. The mobile layout still puts a minimum-1000px preview before the inspector, producing a long scroll to reach editing controls; browser validation is required before accepting this interaction.
- At desktop the inspector appears to the right through CSS grid while it precedes the canvas in DOM order. At mobile, CSS order puts the canvas before the inspector while DOM/focus order remains inspector first. This requires browser/keyboard review for meaningful focus-order conformance.

### Required viewport set

Run at minimum 390x844, 768x1024, 1024x768, and 1440x900. Add 320px width, 200% text zoom, long English/Bangla titles, empty content, 50+ content rows, 20 revisions, and large JSON fields. Acceptance requires no clipped controls, page-level horizontal scrolling, unreachable actions, or content hidden behind sticky chrome.

## 5. Functional integrity matrix

| Contract | Evidence | Result |
| --- | --- | --- |
| Authorized editor renders | `PageBuilderIntegrityTest` | PASS |
| Create block | Permanent create/update chain | PASS |
| Update content/settings/visibility | Permanent create/update chain | PASS |
| Preserve omitted schedule; explicitly clear schedule | New permanent regression | PASS |
| Duplicate | Permanent create/update chain | PASS |
| Reorder | Exact set required; foreign/missing/duplicate UUID payload rejected | PASS |
| Soft delete | Block removed without force deletion; pre-delete revision captured | PASS |
| Restore page, SEO, tags, and deleted blocks | Revision service test | PASS |
| Restore linked reusable content | New permanent regression | PASS |
| Cross-page revision boundary | Foreign page revision returns 404 | PASS |
| Public scheduling/device visibility query | Visibility/scheduling test | PASS |
| Content Hub filter controls | Render and query implementation | PASS with locale-registry residual risk |
| Bulk duplicate and translation drafts | `ContentHubIntegrityTest` | PASS |
| Bulk recoverable multi-language delete | `ContentHubIntegrityTest` | PASS |
| Dirty browser/link/locale navigation protection | Scoped R3 implementation; reported browser pass | PASS |
| Dirty cross-scope save and block-switch protection | Independent `page`, `block:<uuid>`, and `order` scopes | PASS |
| Media upload authorization and image validation | Page-edit permission; valid image + MP4 rejection tests | PASS |
| Hero overlay default/editor/public rendering | Create/render regression plus shared clamped value | PASS |
| Publishing actions/menu states | Five server-backed states; keyboard menu implementation | PASS functional; independent browser unavailable |
| Mobile viewport switching | All controls remain rendered; reported browser pass | PASS |

### Residual integrity risks

- `PageRevisionService::capture()` calculates `max(revision) + 1` without locking. Concurrent edits to the same page can race the unique revision sequence. Add a concurrency-safe allocator or retry policy before high-concurrency production use.
- Restoring a revision that contains reusable content intentionally changes that library item globally. The confirmation currently says only “Restore this revision”; it should disclose the cross-page impact and the test should include a second consuming page.
- Inline builder JavaScript is not covered by ESLint or Vitest because it lives in a Blade template. Server render tests prove the page returns HTML, not that the browser script executes without errors.
- Content Hub derives the required-language set from existing pages and accepts an arbitrary `target_language` string. Use one authoritative allow-list so “Needs Translation” and translation creation remain correct on a new or sparsely translated site.
- The primary browser run is reported rather than independently reproduced here. Store screenshots, viewport metadata, console output, and diffs in the repository or CI so acceptance evidence survives the browser session.

## 6. Local demo seeder review

`LocalDevelopmentSeeder` correctly refuses non-local environments and requires a minimum 12-character `LOCAL_ADMIN_PASSWORD`. A disposable in-memory probe ran the seeder twice and verified that the visual-editor fixture remained idempotent: exactly two blocks, `Hero Banner` followed by `Impact Grid`, with the referenced hero image present. The probe file was removed after the run and no implementation code was changed by QA.

The fixture is useful for manual review but is not a stable pixel baseline. Dashboard donations use current dates and rolling months, and the seeded totals/counts differ from the approved screenshot. Content Hub counts/rows also differ from the reference. Visual regression needs a reference-normalized fixture or explicit dynamic-region masking.

## 7. Automated gate evidence

Final R3 runs after scoped dirty state, media upload, overlay, publishing, and R3 review remediations:

| Gate | Result |
| --- | --- |
| `php artisan test --filter=PageBuilderIntegrityTest` | PASS — 11 tests, 52 assertions |
| `php artisan test --filter=ContentHubIntegrityTest` | PASS — 3 tests, 25 assertions |
| Full `php artisan test` | PASS — 57 tests, 254 assertions |
| Fresh SQLite migrations through `2026_08_14_000011` | PASS |
| `php artisan optimize` | PASS |
| Route-name uniqueness | PASS — 390 routes, 390 named, 0 duplicate names |
| `php artisan optimize:clear` | PASS |
| ESLint | PASS |
| Vitest | PASS — 4 files, 4 tests |
| Vite production build | PASS |
| Project credential/PII security scan | PASS |
| `npm audit --audit-level=high` | PASS — 0 vulnerabilities |
| Production-only npm audit | PASS — 0 vulnerabilities |
| Composer audit | REPORTED PASS by primary task; not independently rerun because this QA shell has no Composer executable |
| Primary-task browser interaction/overflow checks | REPORTED PASS — cross-scope persistence, menu states, 28px controls, zero builder overflow, dropzone, overlay |
| Independent browser/pixel/a11y reproduction | BLOCKED — this QA task enumerated no browser instance |

The backend/frontend green gates are meaningful but do not replace the missing browser and visual checks. Permanent Content Hub tests cover endpoint results but do not execute its inline browser JavaScript, select-all behavior, responsive layout, or error/reload states.

## 8. Exit criteria for this milestone

The admin UI implementation has no open P0/P1 defect in this audit and passes the functional milestone. Before using the stronger “pixel perfect / no UI issues” release claim:

1. Store the primary browser evidence and run same-viewport application/reference image diffs at 390, 768, 1024, and 1440 CSS pixels.
2. Add a deterministic visual fixture and automated browser coverage for scoped dirty state, viewport switching, block CRUD/reorder/delete/restore, publishing states, upload success/rejection, and Content Hub bulk operations.
3. Complete and record keyboard/screen-reader checks rather than relying only on markup inspection.
4. Resolve or explicitly accept the P2 residual integrity/localization risks above.
5. Rerun the full green gates after any further UI patch.

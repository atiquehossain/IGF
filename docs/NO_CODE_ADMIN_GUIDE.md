# Ignite no-code administrator guide

The website is designed so a non-technical editor can manage normal website work without editing code.

## Start on the Dashboard

The Dashboard opens with common tasks such as **Edit home or a page**, **Update header or footer**, **Add event or news**, **Add a gallery photo**, and **Review applications**. Setup alerts explain anything that would prevent an important visitor journey, such as a missing footer or no active donation cause.

The main menu is organized by the part of the website you are responsible for:

- **Website** contains Home & Pages, Header & Footer, Brand & Appearance, and Media Library.
- **Content** contains Programs, Projects, Events & News, Team Members, Community Stories, Gallery, and Reports.
- **Get Involved** contains donation, sponsorship, volunteer, subscriber, contact, and chat work.
- **Search & Languages** contains search previews, performance reporting, internal-link guidance, redirects, technical checks and translations.
- **Users & Access** is available only to administrators with permission to manage people and roles.

Older or technical managers are kept under **Advanced & Legacy Tools** and should not be needed for everyday editing.

## Customize the whole website

Open **Website → Brand & Appearance**. This is the central place for branding, public contact details, page wording, form labels, suggested donation amounts, Zakat calculator wording, header actions, footer content, theme colors, social profiles, and analytics.

1. Choose the public page in **Website preview**.
2. Choose desktop, tablet, or mobile.
3. Open only the settings section you need, or use **Find a setting**.
4. Choose images from the Media Library; image paths are hidden under an advanced option.
5. Save changes, then refresh the preview.

## Edit a page

Open **Pages & sections**, choose a page, and select **Edit page**. The Simple Editor lets you:

- click a section or its preview to edit it;
- add one of 16 guided section types;
- drag or use arrow buttons to reorder sections;
- duplicate, hide, or delete a section;
- choose images from the Media Library;
- preview desktop, tablet, and mobile layouts;
- undo, redo, recover an unsaved local draft, and save all changes together.

Use **Advanced mode** only for scheduling, private visibility, translations, revisions, or specialist page controls. Use the direct **Search & Sharing** button for SEO; you do not need to enter Advanced mode first.

Automatic sections such as Programs, Projects, Events, Team, Stories, and Gallery provide guided controls for **Content source**, **Category or filter**, **Sort order**, **Number to show**, **Featured items**, **Button text**, **Button destination**, and **Empty message**. Choose a managed source when cards should stay synchronized with their original content. Choose manual items only for one-off editorial cards.

## Change menus and submenus

Open **Website → Header & Footer** and choose the menu location. Add a built-in page, CMS page, or safe custom link. Give submenu items a short optional description, choose a parent while adding, or use the right-arrow control to make an existing item a submenu. Drag or use the arrow controls to reorder, then select **Save menu**.

## Images and files

Upload images and documents in **Media Library**. Page, Customizer, and Search & Sharing image controls use a visual picker. The SEO picker shows alternative-text, size, and likely social-card crop warnings and links back to the Media Library for correction. The library reports where media is used so an editor can avoid removing an active asset.

## Search & Sharing (SEO 3.0)

Open **Search & Languages → Search & Sharing** to enter the single **Search & Sharing** workspace. This replaces the older separate SEO forms. It is the normal place to manage search appearance, social sharing, page addresses, structured data, language completion, review, bulk changes, restore points, redirects, and technical findings.

### Start with the website checklist

The top of the workspace separates live, draft/scheduled, **Ready**, **Needs attention**, and **Hidden from search** content. The average score describes progress on live, indexable content. **Ready** means there are no required or recommended editor actions; a high percentage alone does not override a warning. Draft, private, scheduled, unpublished, and missing-translation states remain visible without being presented as live-ready content. The English and Bangla tabs show language-specific completion.

1. Choose English or Bangla.
2. Filter by page name, content type, or issue. Useful issue filters include missing titles, descriptions, social images, translations, duplicate titles, and pages hidden from search.
3. Select **Improve** beside an item. The checklist contains curated public pages and content only; admin, payment, and other system endpoints are never offered as SEO targets.
4. For a website feature that is not in the current filtered list, use **Choose a website feature** and select its friendly name.

Pages, categories, events/publications, annual reports, projects, and curated website features use the same guided editor. When editing a page in Page Builder, open its **Search & sharing** card to continue in this workspace. Page Builder intentionally hands this work off instead of showing a second, conflicting SEO form.

The spreadsheet-style **Translation Center** translates visitor copy, not SEO fields. Translate the content there, then complete that language’s title, description, sharing image and search visibility in **Search & Sharing**. This prevents two editors from silently overwriting each other.

### Edit search and sharing details

For normal page work:

1. Leave **Automatically use the page content** selected when the page title and summary are already clear. Turn it off only to write custom search wording.
2. Review the search title and description. Character counters and the writing checklist provide guidance, but do not guarantee a search ranking.
3. Watch the live desktop or mobile Google preview while typing. Google can still choose different wording in a real result.
4. Choose a social image from the searchable Media Library picker, then review the live social-card preview.
5. Keep **Show in search results** for finished public content. **Hide from search results** adds `noindex` and removes the item from the sitemap automatically; it does not stop a visitor who already has the direct link.
6. Select **Save search & sharing**.

The optional **Focus phrase** is an internal writing check. It is not published as a meta-keywords tag and is not a ranking promise.

### Bulk editing and export

Choose **Bulk editor** to compare English and Bangla metadata in a spreadsheet-style view. Filter to the content that needs work, then edit up to 25 selected rows per save. The bulk editor covers search title/description, social image, search visibility, and generated Schema template. It uses the same validation, revision history, permissions, and review reset as the single-item editor. Export the current filtered result as CSV when an editor or client needs an offline review copy; CSV export does not import changes back into the website.

### Editorial review and approval

An editor with SEO edit permission can request review after every required checklist item is complete. A reviewer with the separate review permission can approve the exact submitted version or request changes with a note. If metadata or its effective page content changes after submission, the pending approval is invalidated and the current version must be submitted again. Approval is an editorial sign-off; it does not publish draft content, enable search indexing, or promise a search ranking.

### English and Bangla

Search details are saved separately for English and Bangla. The language completion counts make missing translations visible. On a Bangla item, **Copy English** starts a Bangla draft from the English values; review and translate every copied field before saving.

When Bangla is enabled, the public site provides stable language links and matching canonical and `hreflang` information. The sitemap index also publishes a sitemap for each enabled language. Editors do not need to construct these URLs or tags manually.

### Page addresses and automatic redirects

Editable content includes a friendly **Permalink** field. Keep it short, descriptive, and language-appropriate. Saving a changed permalink creates a permanent `301` redirect from the old address to the new one so bookmarks and existing search links continue to work. English and Bangla redirects are scoped independently when their addresses differ; a language-specific redirect wins for that language and a global redirect remains the fallback. Important primary website addresses are protected from editing to prevent broken navigation.

Open **Manage redirects** for a deliberate redirect that is not caused by a permalink change. Enter the old address and destination, choose **Global**, **English only**, or **Bangla only**, then choose permanent or temporary behavior. A redirect can be edited, paused, enabled again, moved to trash, and restored from redirect trash. Use a language scope whenever translated pages intentionally use different addresses; do not create two global rules for the same old address.

Redirects are same-origin by default: an admin can send visitors to another address on this website, but not to an arbitrary external host. Allowing an external destination requires a developer-managed production allowlist; it is not a normal editor setting. Protected system paths, self-redirects, redirect chains, loops, and duplicate old addresses are rejected.

### Advanced settings and structured data

Open **Advanced settings** only when the content owner or an SEO specialist needs to:

- provide a same-origin canonical URL for intentionally duplicated content;
- change whether search engines may follow links;
- override Open Graph or X/Twitter text; or
- choose a structured-data (Schema) template.

The recommended Schema template is generated from the final page title, description, image, and URL. Public pages can also receive managed organization/website search, breadcrumb, archive collection, report, event/publication, and donation structured data. **Expert: custom JSON** accepts reviewed JSON-LD when a specialist supplies it; invalid or unsupported JSON will not save. Everyday editors should use a generated template rather than raw markup.

Canonical URLs normally stay on this website. A URL with a different scheme, hostname, or port is an external canonical and can cause search engines to credit the other website instead. Only an authorized SEO specialist with the separate `seo.canonical.external` capability can save one, and the specialist must select the explicit confirmation for every external-canonical save. Normal SEO edit permission alone is not sufficient.

For an item under **Events & News**, choose **Scheduled event** only when a real event exists, then enter its actual start (and optional end), status, attendance mode, and required physical/online location details. A publication date is never treated as an event start. News and publications remain `Article` structured data. This prevents the website from publishing made-up event facts.

Each active annual report has its own public HTML landing page at `/annual-report/{slug}` with its title, summary, publishing facts, sharing metadata, language alternatives, breadcrumb/report structured data, and a controlled document/source action. Manage its search details under the **Annual reports** content type rather than linking search results straight to an unlabelled PDF.

### Restore an earlier SEO version

The editor creates a restore point before each save and shows which fields changed between versions. Under **Recent changes**, an SEO editor—or a user explicitly granted **Restore SEO revisions**—can choose **Restore this version** to return to an earlier state. The current state is kept as another restore point, so the restore itself can be undone. A revision containing an external canonical may be restored only by a user who can restore and also has the separate `seo.canonical.external` capability, after explicitly confirming that restoration. SEO edit access includes revision restore, while restore-only access can be delegated without allowing other metadata changes; read-only users cannot change, restore, or approve metadata.

### Technical SEO & 404 Center

Open **Technical SEO** for broken website journeys and markup consistency. Select **Run safe scan** only when no other scan is running. The scan stays inside this Laravel application and uses capped, anonymous requests; it never submits website content to an outside crawler. It checks a bounded set of curated and sitemap URLs for response errors, broken links/images, links through redirects, orphan pages, heading/head/canonical/language/Schema mismatches, duplicate canonical ownership, and oversized responses.

Filter the latest findings by path, type, priority, or visibility. Fix the source content where possible. **Ignore exact finding** suppresses only that precise fingerprint and can be reversed; it is not a general exception for similar errors.

The **404 inbox** groups repeated missing public paths by language. It stores only a normalized path, optional same-site referrer path, language, counts, and timestamps. Query strings/fragments are discarded, sensitive-looking path segments are replaced, and IP addresses, sessions, device details, user agents, and individual visit rows are never stored. Choose a suggested managed destination to create a language-scoped `301`, or dismiss the item when no redirect is appropriate. A privacy-redacted path cannot be turned into a redirect.

Technical access, running scans, managing ignore rules, and creating 404 redirects are separate permissions. A view-only administrator can inspect findings without receiving mutation controls.

### Pagination and filtered archives

Events & News, Projects, Gallery, and Annual Reports apply the archive policy automatically. A clean page 2 or later has its own canonical URL and page-numbered title. Search, filter, malformed-page, and unknown-query versions remain followable for link discovery but are marked `noindex` and canonicalized to the clean archive. Requests beyond the last page return `404`. Editors should link to clean archive URLs and should not create manual canonicals for filter results.

### Sitemap, robots, and launch approval

The sitemap is generated automatically from safe, public, indexable content. Use **View sitemap** to inspect it; do not maintain a manual list or sitemap priority values. Canonical URLs, language alternatives, `hreflang`, and sitemap membership follow the saved page and language settings.

Search indexing is fail-closed, but public crawling is not blocked. The generated `/robots.txt` references the sitemaps, allows public pages to be crawled, and keeps `/admin` disallowed. In local, test, staging, or any environment where indexing is disabled, every page also sends `noindex,nofollow,noarchive` in its robots metadata and matching `X-Robots-Tag` response header so a crawler can read the instruction. A developer may set `SEO_INDEXING_ENABLED=true` in the production environment **only after the client has approved launch** and the final domain, HTTPS, canonical URLs, public visibility, and content have been checked. Do not enable it on a preview or staging domain; use authentication, an IP allowlist, or a VPN when the preview itself must be private.

Open **Search & Languages → Search Performance** to view first-party Google data after a deployment owner connects it. Google Search Console supplies clicks, impressions, click-through rates, queries, top pages, opportunities, and submitted-sitemap status; Google Analytics 4 supplies organic landing-page sessions, engagement, and views. The deployment owner must enable `SEO_PERFORMANCE_ENABLED` and configure the approved Search Console property, GA4 property, and read-only service-account credentials on the server. The screen remains safe and useful when either source is not configured, and it never displays credentials. This reporting does not use AI, scrape Google results, track keyword positions, collect Core Web Vitals, or run Lighthouse/PageSpeed tests. Search & Sharing readiness scores still measure content setup rather than live performance.

### Safe launch checklist

1. Confirm the final HTTPS domain and keep `SEO_INDEXING_ENABLED=false` in local, preview, staging, and during deployment.
2. Complete all accepted required and recommended readiness items in English and Bangla; confirm publication and search-visibility states.
3. Have the assigned reviewer approve the current SEO versions, including annual-report and real-event details.
4. Inspect `/robots.txt`, `/sitemap-index.xml`, representative language alternatives, a clean page-2 archive, a filtered archive, and an annual-report landing page.
5. Run **Technical SEO & 404 Center**, resolve high-priority findings, and review open 404s and locale-scoped redirects.
6. Obtain content-owner approval. Only a production operator may then enable indexing.

Database upgrades are an operator task: take and verify a backup, run `php artisan migrate:status`, then `php artisan migrate --force` through the release process. Never use `migrate:fresh`, `migrate:refresh`, `migrate:reset`, or reseed an existing client database. The full deployment and scan commands are in the project `README.md`.

## Follow up public enquiries

Open **Get Involved** and choose Sponsorship Enquiries, Volunteer Applications, or Contact Enquiries. Every submission uses the same workflow:

1. Set the status to **New**, **Contacted**, **In progress**, **Completed**, or **Spam**.
2. Assign a team member.
3. Add private notes that website visitors cannot see.
4. Set a follow-up date when another action is required.
5. Filter or export the same visible result set when a report is needed.

Use **Translations** for shared interface wording such as buttons, validation, empty messages, chat, reports, and gallery labels. Page-specific editorial copy remains beside the page or section it belongs to.

## Recovery

Normal editorial deletion moves content to **Trash & recovery**. Restore it when a deletion was accidental. Permanent deletion is a separate, confirmed action. Page revisions provide an additional restore point for page content and reusable sections.

## Protected areas

Payment credentials and callback verification, authentication, permission enforcement, validation bounds, upload security, the BDT payment currency, the 2.5% Zakat calculation rule, and database structure are intentionally not editable in the no-code interface. Editors can change public wording and presentation, but cannot accidentally weaken these systems.

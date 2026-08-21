# Client Meeting Additional-Requirements Analysis

**Meeting recording:** `Recording 2026-08-17 161548.mp4`  
**Duration:** 53:22  
**Speaker assumption supplied by the developer:** Atique Hossain is the developer/presenter; all other participants are clients.  
**Analysis date:** 19 August 2026

## Outcome at a glance

| Confidence | Requirement | Current repository status |
|---|---|---|
| **Confirmed** | Expand the static FAQ popup into a stored, two-way website chat | Two-way chat is implemented. English/Bangla chat content exists, but the certified public UI is currently English-only. Production decisions remain open. |
| **Strong inference** | Simplify and regroup the primary navigation into six roots | Implemented in code for English. The change appeared the day after the meeting, but the damaged audio prevents transcript confirmation. |
| **Not confirmed** | Changes to the other pages, forms, CMS screens, or reference-site designs shown during the walkthrough | Treat as review/demo material unless the client confirms a specific requested change. |

No reliable deadline, priority statement, approval, rejection, or speaker-specific quotation could be recovered from the recording.

## Evidence and reliability limitation

The video is visually usable and clearly shows Atique presenting the Ignite Global Foundation website, local redesign, admin/CMS, and external reference sites.

The audio track exists (AAC LC, stereo, 48 kHz, about 192 kbps), but it repeatedly clips at full scale and contains dense broadband distortion. The following transcription checks were performed:

- A full multilingual Whisper pass.
- Higher-accuracy `large-v3-turbo` checks with Bangla and automatic language selection.
- Voice-activity detection, denoising, filtering, channel validation, reversal checks, and current/older FFmpeg decoder comparison.
- Independent spot checks at `00:00–02:00`, `13:30–15:30`, `38:00–40:00`, and `48:00–50:00`.

Every pass produced decoder loops, repeated filler, or mixed-script nonsense rather than coherent dialogue. These outputs were rejected. Therefore:

- No generated transcript is treated as meeting evidence.
- No spoken statement is attributed to Atique or a client.
- Timestamps below describe visible screen context only.
- Confidence comes from the combination of the visible walkthrough and repository evidence created immediately after the meeting.

## R1 — Confirmed client request: managed two-way website chat

### Evidence

The repository's `docs/qa/MANAGED_CHAT_UAT_ADDENDUM.md:3` explicitly calls this the **client-requested expansion from a static FAQ popup to a stored, two-way website chat**. The video shows the existing Contact/FAQ experience around `38:00`, which supplies the visual context.

### Required behavior

1. Show a floating, keyboard-accessible chat widget on normal public pages.
2. Provide English and Bangla suggested questions with administrator-managed answers, ordering, visibility, welcome copy, and optional privacy copy.
3. Return a saved answer immediately when a suggested question is selected. Do not create a conversation for that interaction; retain only an anonymous aggregate click count.
4. For a guest's custom question:
   - Preserve the typed question.
   - Reveal the contact step only after the guest chooses Send.
   - Require a name and at least one of email or phone.
   - Mark supplied guest identity as unverified.
5. For an approved signed-in member:
   - Use the authenticated server-side identity.
   - Skip the duplicate guest-contact form.
   - Mark the member identity as verified.
6. Never generate an automatic answer to a custom question. Create a staff-inbox conversation in **Waiting** status.
7. Give permitted administrators an inbox containing identity, source page, language, status, timestamps, and the complete conversation.
8. Support administrator replies, status changes, unread indicators, search, pagination, and audit events.
9. Poll for replies every eight seconds while the widget is open. Do not advertise this as real-time live-agent availability.
10. Keep the feature plain-text only. Do not accept attachments, payment credentials, passwords, NID, medical information, emergency reports, or safeguarding reports.
11. Enforce CSRF protection, per-identity/IP throttling, guest-session isolation, member checks, private/no-store responses, safe logging, and granular permissions.

### Current implementation status

The two-way chat workflow, storage, public widget, admin inbox, replies, statuses, permissions, audit trail, FAQ analytics, and polling are present in the repository.

The strict **public bilingual** acceptance condition is not complete:

- `config/localization.php:12` currently exposes only `['en']` as public locales.
- `config/localization.php:13` disables the public locale switcher.
- `WebsiteChat.vue` derives its language from the page's document language and does not provide its own language selector.

Bangla settings, copy, and FAQ data exist, but a normal visitor cannot reach them through the currently certified public UI. Report the status as **two-way implemented; public Bangla activation incomplete**.

### Client/owner decisions still required

- Approve final privacy wording.
- Define retention, deletion/anonymization, legal hold, access/export, and encrypted-backup handling.
- Assign which administrator roles may read transcripts and which may only manage public questions/settings.
- Approve response-time wording; do not claim live availability without a staffed SLA.
- Decide whether Bangla requires a whole-site language switcher or a chat-local language selector.
- Confirm whether eight-second polling is acceptable or true push/WebSocket behavior is required.
- Confirm whether staff reply manually in the visitor's language or automatic translation is expected.

## R2 — Strongly inferred request: simplify the primary navigation

### Why it is likely

- The meeting repeatedly shows or compares the public header and nested navigation, especially around `04:00–12:00`, with the live menu visible around `08:00`.
- The recording is dated 17 August 2026.
- `database/migrations/2026_08_18_110000_simplify_primary_navigation.php` was created the next day.
- A matching desktop navigation screenshot was created shortly afterward at `output/navigation-redesign-desktop.png`.
- The migration, frontend component, seed data, and tests consistently implement the same six-root structure.

The archive has no Git metadata, so filesystem timing shows strong correlation but cannot prove causation. This item must be confirmed in client UAT and must not be described as a verbatim quote.

### Inferred hierarchy

1. **Home**
2. **About Us**
   - Who We Are
   - Founder's Letter
   - Awards & Recognition
   - Photo Gallery
   - Annual Reports
   - Contact Us
3. **Our Work**
   - Program Overview
   - Inclusive Education
   - Visit Ignite School
   - Youth Development
   - Disaster Resilience
   - Current Projects
   - Completed Projects
4. **Get Involved**
   - Volunteer
   - Careers
   - Sponsor a Child
5. **News & Stories**
   - Stories
   - Events & News
6. **Donate**
   - Make a Donation
   - Give Zakat

### Acceptance checks

- Show exactly the six enabled roots in the approved order on desktop and mobile.
- Keep one submenu level and ensure every child resolves to the intended public route.
- Remove retired legacy roots from the active menu.
- Support accessible desktop dropdowns, Escape behavior, active states, and mobile accordion behavior.
- Preserve safe CMS menu editing/reordering without allowing an unsupported third level.

### Confirmation still required

- Confirm child labels and ordering.
- Confirm whether the separate orange Donate call-to-action remains alongside the Donate root.
- Confirm whether this hierarchy must also be created in Bangla; the migration currently targets English.

## Screens reviewed, but not proven to be new requirements

| Visible time | Topic shown | Safe interpretation |
|---|---|---|
| `00:00–06:00` | Local redesign, accountability section, Zakat calculator, Inclusive Education | Review/demo only |
| `06:00–14:00` | Live/local Disaster Response, Visit Ignite School, navigation, Current Projects | Navigation comparison is relevant; page changes are not confirmed |
| `16:00–18:00` | IHF School reference, local About page/footer | Possible design reference; no copying requirement is proven |
| `20:00–28:00` | Awards, PushpaFund team hover/LinkedIn reference, Annual Reports | Review/reference only |
| `30:00–38:00` | Donation form, homepage stats/donation module, events, Contact FAQ/form | Review/demo; Contact FAQ supplies chat context |
| `40:00–46:00` | Translation Center, SEO manager, 3% Bangla localization, donor/member list | Existing CMS review; incomplete Bangla content is visible but no spoken assignment is recoverable |
| `48:00–50:00` | Sponsor a Child and old member/social-login flow | Review/demo only |
| `52:00–53:22` | Local homepage, sponsorship footer, privacy/follow-up notes | Final walkthrough only |

Do not automatically create tickets to copy IHF/Pushpa layouts, add LinkedIn hover cards, redesign awards/reports, enable recurring donation, change sponsorship/login, alter SEO, or complete every Bangla translation. Ask the client to identify the desired outcome for each item first.

## Recommended developer/client action list

### Client confirmation

1. Approve or correct the six-root navigation hierarchy and decide its Bangla scope.
2. Resolve the chat privacy, retention, roles, language UX, response-time, and polling-versus-real-time decisions.
3. Identify any page-specific change from the walkthrough using the page name plus the desired result; do not treat screen exposure as approval or a requirement.
4. Provide a clean audio export or another recording if exact wording, speaker attribution, priorities, or deadlines are needed.

### Developer/UAT

1. UAT the navigation on desktop/mobile, routes, keyboard behavior, and CMS editing.
2. UAT the complete guest/member/admin chat lifecycle, isolation, permissions, closing, auditing, and eight-second reply retrieval.
3. Keep chat labelled as polling-based support, not live chat, unless the client approves and staffs a real-time service.
4. Do not mark bilingual chat complete until Bangla is publicly reachable and all public labels/validation/SEO defaults are certified.

## Repository evidence used

- `docs/qa/MANAGED_CHAT_UAT_ADDENDUM.md`
- `database/migrations/2026_08_18_110000_simplify_primary_navigation.php`
- `database/migrations/2026_08_18_120000_create_managed_chat_system.php`
- `resources/js/Shared/WebsiteChat.vue`
- `resources/js/layouts/AppNav.vue`
- `config/localization.php`
- `tests/Feature/ManagedWebsiteChatIntegrityTest.php`
- `tests/Feature/NavigationEditorIntegrityTest.php`


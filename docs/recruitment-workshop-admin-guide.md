# Recruitment and workshop admin guide

This guide covers the day-to-day operation of the integrated Recruitment and Workshops areas. Applicants and registrants do not log in. They submit a public form and receive an on-screen reference; staff decide whether and how to contact them outside the system.

Related documents:

- [Deployment and configuration runbook](recruitment-workshop-deployment.md)
- [CSV migration and import guide](recruitment-workshop-csv-import.md)
- [Privacy and security notes](recruitment-workshop-privacy-security.md)
- [Verification matrix](recruitment-workshop-verification.md)

## Roles and access

The module uses the existing admin login and permission system. There is no separate HR login.

An Owner/Super Admin should create roles from **Users & Access > Roles & Permissions**, then create or disable staff accounts from **Users & Access > Administrators**. A single account may be assigned a combined role containing both recruitment and workshop permissions.

Recommended role profiles:

| Role | Suggested access |
| --- | --- |
| Recruitment viewer | View Jobs and Job Applications only |
| HR staff | View/create/edit/publish Jobs; view/edit Job Applications; download and export only when needed |
| Workshop staff | View/create/edit/publish Workshops; view/edit Workshop Registrations; download and export only when needed |
| Combined manager | Both HR and Workshop staff capabilities |
| Owner/Super Admin | Role/account administration plus explicitly owner-only anonymize and permanent-delete operations |

Imports, exports, private file downloads, form-template management, anonymization, and permanent deletion are separate capabilities. Grant the minimum necessary. Disabling an administrator or their role removes access through the existing admin guard. The system prevents disabling, deleting, or demoting the final active deployment owner.

## Jobs

Open **Recruitment > Jobs**.

1. Choose **Create job**.
2. Enter English and Bangla titles, descriptions, requirements, responsibilities, department/location content, and slugs. Complete employment type, work arrangement, vacancy count, visibility time, and application open/close times.
3. Select an existing published form or let the job receive its own default form.
4. Save the draft and use preview/review before publishing.
5. Publish only after both language versions, the schedule, and form are correct.

Public behavior is time-based using the application timezone (`Asia/Dhaka`):

- Draft, withdrawn, not-yet-visible, and closed jobs do not appear in the active Careers list.
- A published job accepts submissions only from its application opening time until its closing time.
- A once-published closed job keeps its direct detail page, shows that applications are closed, and does not expose a usable form.

Use **Close** to stop a currently published job immediately while retaining its detail and records. Use **Withdraw** when the public listing should no longer be a published detail. **Duplicate** creates an independent draft and independently duplicated form. Only a never-published draft with no applications can be deleted; otherwise close or withdraw it.

## Google Forms-style form builder

Open **Recruitment > Form Templates** or **Workshops > Form Templates**. Job and workshop forms share the builder but remain separate by purpose.

The builder supports short text, long text, email, phone, number, date, dropdown, radio, checkboxes, yes/no, and PDF file fields. Each field may have English and Bangla label/help text, required state, bounded validation, selectable options, ordering, and conditional visibility/requirement rules.

Operational rules:

- Full name and email are protected required system fields. Phone is a protected optional system field.
- Every job form has a protected required CV field: PDF only, maximum 5 MiB.
- Preview a draft in both languages before publishing it.
- Publishing makes a version immutable. Later edits create/use a draft; existing submissions continue to reference their original coherent published version.
- Duplicate a template when a new listing needs an independent starting point.
- Test conditional paths, including the path that reveals and requires a dependent field.

## Job application workflow

Open **Recruitment > Applications**, choose a job, then use filters, sorting, private search, pagination, and configurable answer columns. Search text is held in the authenticated session for a short period and is not put in the URL. Clear it when finished.

Application statuses are **New**, **Under review**, **Shortlisted**, **Interview**, **Offered**, **Hired**, **Rejected**, and **Withdrawn**. The detail page supports assignment, private notes, per-reviewer scorecard ratings, allowed status transitions, and permission-gated document downloads. Bulk status or assignment changes are limited to 100 records and validate the complete selection before changing it.

The same normalized email can submit only one active application per job. A later submission replaces the current answers and CV, increments the submission count, and preserves the workflow status and assignment. Review the “last submitted” time before acting.

No email is sent automatically. Use the copy-email control or export a reviewed recipient set, then send messages manually through the approved organizational mail account. Do not expose other applicants in To/CC fields.

## Workshops

Open **Workshops > Workshops**. Workshops are always free: do not request payment, price, attendance, QR, certificate, or feedback data in this module.

Create English and Bangla content, choose offline/online/hybrid attendance, provide venue/instructions as appropriate, and set visibility, registration opening/closing, and event start/end times in chronological order. Private meeting URLs must be HTTPS and remain admin-only.

Choose one registration mode:

| Mode | New valid registration |
| --- | --- |
| Automatic | Confirmed when capacity is unlimited or a seat is available; the submission is refused as full when no seat remains |
| Manual approval | Pending until staff confirms or rejects it; confirmation still enforces capacity |
| Waitlist | Confirmed while seats are available, otherwise waitlisted; capacity is mandatory |

Closing, withdrawing, duplicating, publishing, and draft deletion follow the same safety model as jobs. Closed, once-published workshop details remain directly visible but non-submittable.

## Workshop registration workflow

Open **Workshops > Registrations** and choose a workshop. Registrations can be **Pending**, **Confirmed**, **Waitlisted**, **Rejected**, or **Cancelled**. Staff may filter/sort, use private session search, assign records, add private notes, change allowed statuses, download permitted files, and perform validated bulk status/assignment actions.

Capacity is checked inside database transactions. In waitlist mode, cancelling a confirmed registration promotes the oldest eligible waitlisted registration. Do not promise a seat based only on a stale browser page; save the status change and use the result shown by the system.

As with jobs, a later submission from the same normalized email for the same workshop replaces the current answers/documents while preserving staff workflow and assignment. The system does not email the registrant.

## Imports and exports

Use **CSV Imports** within the applicable Recruitment or Workshops group only for reviewed historical records. The process is upload, map, preview, resolve errors, confirm, and result. Nothing is imported during upload or preview. See the [CSV migration and import guide](recruitment-workshop-csv-import.md) before the first production import.

Exports apply the selected listing, current filters, private session search, sort, and requested columns. They stream a UTF-8 CSV and neutralize spreadsheet formulas. Treat every export as a new sensitive copy: save it only in approved storage, share it only with authorized staff, and delete working copies when the business task ends.

## Anonymize and permanently delete

There is no automatic retention rule for job applications or workshop registrations. An Owner/Super Admin must make each decision manually from the record detail page and type the exact displayed confirmation phrase.

- **Anonymize** removes documents and answers, replaces the direct identity, redacts private note bodies, removes job score comments, and preserves non-identifying status/history and an audit record.
- **Permanently delete** removes the record and its dependent application/registration data and private files. The audit entry retains only a hash of the reference, not applicant content.

Before either action, confirm the correct listing, reference number, legal/business authorization, and backup policy. Anonymization is normally preferable when aggregate operational history is still required. Both actions are irreversible through the admin interface.

The system records private-file cleanup before committing either action and deletes the bytes after the outermost database transaction commits. If an action succeeds but the deployment reports a storage-cleanup failure, do not repeat the privacy action and do not manipulate files manually. Give operations the non-sensitive record reference and time; they can retry the durable outbox with the documented cleanup command.

## End-of-task checklist

- Clear private search state.
- Confirm bulk actions affected the intended listing and record count.
- Close downloaded CVs and remove unneeded local/export copies.
- Never paste applicant answers, email addresses, CV names, or private notes into tickets or chat.
- Report unexpected access, download, import, or capacity behavior to the deployment owner with reference numbers only.

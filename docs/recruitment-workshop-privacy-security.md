# Recruitment and workshop privacy/security notes

Job applications, workshop registrations, uploaded PDFs, private notes, scores, searches, imports, and exports are confidential. These notes describe the module’s controls and the operational responsibilities around them; they are not a substitute for the organization’s legal privacy policy.

## Data-flow summary

- A public visitor views a scheduled English/Bangla listing and submits without creating an account.
- The server validates the current immutable form version, timing, anti-spam token/honeypot, identity/IP rate limit, and any PDF content.
- The database stores a normalized identity for within-listing duplicate control, typed answers, workflow metadata, and an opaque reference number.
- PDFs and CSV sources are stored under randomized paths on private Laravel disks outside the public storage link.
- Authorized administrators review records through the existing admin guard. Private searches remain short-lived session state rather than URL parameters.
- The system shows a submission reference but does not email the applicant/registrant automatically.

## Access control

Recruitment and workshop permissions are independent. Viewing, changing workflow, exporting, importing, downloading private documents, and managing templates are separate grants. An account may hold both domain sets, but staff must not receive the other domain merely for convenience.

Owner-only anonymization and permanent deletion are enforced in both route capability and service/controller authority checks. Role hierarchy prevents managers from granting capabilities they do not possess, managing peers/superiors, or removing the final active owner.

Review access at onboarding, role change, and departure. Disable unused administrators immediately. Use unique accounts; never share an Owner/Super Admin credential. Protect the admin login with the application’s existing password/2FA controls and production session-cookie settings.

## Public submission controls

- Listing visibility and submission eligibility are rechecked inside the database transaction, not trusted from the browser.
- One normalized email has one active record per listing; a resubmission replaces current answers/documents instead of exposing multiple “active” versions.
- Public writes use combined IP/normalized-email throttling plus a signed, time-bound form token, honeypot, and minimum-completion timing check.
- Conditions and required state are evaluated on the server. Hidden answers are not trusted.
- No applicant dashboard, magic link, automatic email, or private file URL is created.

Rate limiting and honeypots reduce abuse but do not replace monitoring. Alert on repeated validation failures, unusual download/export volume, storage failures, and capacity conflicts without logging submitted PII.

## PDF and private-file controls

Applicant PDFs must use a `.pdf` extension, be 5 MiB or smaller, have `application/pdf` MIME, valid `%PDF-`/`%%EOF` structure, and no configured active/embedded PDF markers. Stored bytes are hash-checked. Paths contain a random 48-hex filename and are accepted only on the configured `applicant_documents` disk.

Structural parsing occurs in a no-shell child PHP process with a fixed 128 MiB memory limit and a bounded timeout. Malformed documents, parser crashes, memory exhaustion, and timeouts fail closed before storage; downloads repeat the isolated inspection after size and checksum verification.

The worker receives PDF bytes on standard input, can run only under CLI with the application-set worker marker, and has no applicant filename or database credentials in its command arguments. `APPLICATION_PDF_PARSER_TIMEOUT_SECONDS` defaults to five seconds and is application-bounded to 1–10 seconds. Production must permit the web PHP process to launch its own `PHP_BINARY`; disabling child processes without an equivalent reviewed design makes uploads fail closed.

Downloads require record-scoped authorization, return private/no-store/nosniff/sandbox headers, and are audited without recording the original applicant data in audit context. Operators must still use a patched PDF reader, avoid enabling scripts/external content, and remove downloaded working copies.

Original import CSVs are equally private and use a separate `applicant_imports` disk. Neither disk may be exposed through `storage:link`, direct web-server aliases, public object ACLs, or backups accessible to general staff.

## Workflow, capacity, and integrity

Published form versions are immutable, preserving the meaning of historical answers. Listing edits use an editor version to reject stale writes. Sensitive mutations lock the parent listing first and then records in a stable order.

Workshop seat checks and waitlist promotion execute under database locks. Production must use the validated MySQL/InnoDB configuration; SQLite tests are not evidence of genuine concurrent final-seat safety. Bulk actions validate the entire selected set and listing ownership before mutation.

CSV import sources are bounded and checksum-verified. Mapping cannot target protected file fields, and formulas, HTML, paths, or external links do not become executable/trusted content. Confirmation expires/revalidates the preview and is transactional. CSV exports and error reports are streamed, private/no-store, UTF-8, and formula-neutralized.

## Audit and logging

Auditable activity includes listing/form changes, submissions/resubmissions, status and assignment changes, notes/scores, bulk actions, private searches (scope/expiry only), imports, exports, document downloads, anonymization, and deletion.

Audit context should contain record/listing identifiers, counts, state changes, and safe hashes—not raw email addresses, names, phone numbers, answers, note bodies, CV filenames/content, CSV row values, or search terms. Do not add request-body logging around these routes. Configure application/proxy access logs to avoid query/body capture and ensure error reporting services redact request payloads.

## Manual contact and exports

HR/workshop staff copy an email address or export a reviewed set and contact people manually. Use BCC or individual messages, the approved organizational mail account, and a reviewed template. Status values do not trigger mail.

Exports create additional uncontrolled copies. Limit columns and rows to the task, store exports only in approved encrypted locations, do not upload them to personal drives, and remove them when the task is complete. Treat formula neutralization as defense-in-depth, not permission to trust workbook behavior.

## Retention, anonymization, and deletion

There is deliberately no automatic retention/deletion policy for recruitment or workshop records. `config/privacy.php` does not list these models. Owners make manual, case-specific decisions and type a reference-specific confirmation phrase.

Anonymization removes stored documents and submitted answers, replaces identity values, redacts note bodies, clears job score comments, and preserves non-identifying workflow/status history plus the audit record. Permanent deletion removes the application/registration and its dependent data and files; the audit event keeps only a reference hash.

Document metadata removal and the corresponding private-file cleanup intent commit atomically. Physical deletion happens only after the outermost database transaction commits. A temporary storage failure leaves a PII-free row in `private_file_cleanup_jobs` for bounded manual retry; it does not restore the removed applicant identity or answers. Operators retry with `php artisan applications:cleanup-private-files --limit=100`, investigate any non-zero exit, and must not delete pending rows merely to clear an alert. There is no automatic cleanup scheduler in this module.

Before acting, verify legal authority, holds, complaint/investigation needs, and coupled backups. A backup may still contain data removed from the live system; document backup expiry and restoration handling separately. If an old backup is restored, reapply authorized privacy actions before normal access resumes.

The module creates no applicant/registrant account, login, magic link, dashboard, or automatic email. The opaque receipt is shown on the immediate success screen only. Staff may copy a reviewed address or export a scoped CSV and contact people manually through an approved organizational channel.

## Incident checklist

If private data may have been exposed:

1. Preserve audit/log evidence without copying more PII.
2. Disable the affected account or route/access path and rotate relevant secrets.
3. Identify records using safe references and time ranges.
4. Check document/export/import access events and public storage/CDN/object ACLs.
5. Notify the organization’s privacy/security owner and follow its breach procedure.
6. Correct permissions or storage exposure, test negative authorization, and record the remediation.

Do not permanently delete suspected evidence as an improvised containment step.

## Periodic review

At least quarterly, review role grants, inactive admins, owner count, private-disk ACLs, backup access, export/download audit volume, public rate limits, PHP/proxy upload limits, MySQL transaction settings, and restoration drills. Re-run the verification matrix after framework, database, proxy, filesystem, or form-engine changes.

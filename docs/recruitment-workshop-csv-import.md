# Recruitment and workshop CSV migration/import guide

The CSV importer is a reviewed historical-data tool, not a background synchronization feed. It accepts Google Forms-compatible UTF-8 CSV files for one selected job or workshop, stores the source privately, and requires mapping plus preview before confirmation.

See also the [admin guide](recruitment-workshop-admin-guide.md) and [privacy/security notes](recruitment-workshop-privacy-security.md).

## Before exporting from Google Forms

1. Freeze or record the source response range so late Google Form responses are not silently missed.
2. Export the linked Google Sheet as comma-separated values (`.csv`), preferably with one clear header row.
3. Keep the original in restricted organizational storage and calculate/record a checksum if required by policy.
4. Review which destination listing owns the responses. Never combine different jobs/workshops in one file.
5. Remove columns that are not needed for the approved migration. Do not place passwords, payment data, external file-share links, or unrelated personal data into the import.

The parser accepts UTF-8 (including an optional BOM), a comma delimiter, RFC-style quoting, and up to:

- 10 MiB per CSV;
- 20,000 non-blank data rows;
- 100 columns;
- 20,000 bytes per cell.

Headers must be non-empty and unique case-insensitively. Rows must have the same number of columns as the header. NUL bytes and invalid UTF-8 are rejected.

## Destination preparation

Create the job/workshop and publish its form before upload. Import is pinned to the listing’s current published form version and schema hash. Decide which CSV columns map to protected **Full name** and **Email**; both are mandatory. Phone and compatible custom fields may also be mapped.

File/CV fields and external attachment-link columns cannot be imported as trusted files. Historical job rows are allowed without a CV; the application record will identify the import source. If the organization must retain a historical CV, attach it only through a separately reviewed, authorized process—never map a Google Drive URL as a document.

For workshops, imported rows enter as pending historical registrations. Confirmation and capacity allocation remain a staff workflow; importing does not reserve/overbook seats.

## Upload, map, and preview

1. Open **Recruitment > CSV Imports** or **Workshops > CSV Imports**.
2. Select exactly one listing and upload the `.csv`.
3. Map each source header either to Full name, Email, Phone, a compatible current form field, or Ignore.
4. Choose a duplicate policy:
   - **Update**: replace the latest data for the same normalized email in this listing and increment its submission count while preserving workflow/assignment.
   - **Skip**: leave the existing record unchanged and report the row as skipped.
   - **Reject**: treat duplicates as validation errors so confirmation cannot proceed.
5. Generate the preview and review totals, every invalid/duplicate decision, and representative normalized values.

Duplicate detection uses normalized email within the selected listing, including duplicates earlier in the same CSV. It does not merge applicants across different jobs or workshops.

Preview is non-writing with respect to applications/registrations. It stores a private review batch and row decisions. A preview expires after 24 hours. Confirmation also rebuilds the preview and refuses to proceed if the source checksum, form schema, mapping, duplicate state, or preview digest changed.

## Correct errors safely

Download the formula-safe error report when needed. Correct the source in the restricted working copy, then upload a new batch or remap and preview again. Common failures include:

- missing/invalid name or email;
- duplicate headers or wrong row width;
- required current-form fields not mapped or blank;
- values outside number/date/text/choice validation;
- checkbox/select values not matching current options;
- duplicate emails under the Reject policy;
- changed form or records after preview.

Do not “fix” errors by mapping a column to the wrong destination, weakening required fields without approval, or editing stored private import files on disk.

## Confirm and reconcile

Confirmation is available only when the refreshed preview has no invalid rows and the operator checks the explicit review confirmation. The batch is processed transactionally; a failure prevents a partial committed application/registration set. Completed batches cannot be confirmed again.

After confirmation:

1. Record the batch UUID, listing, source checksum, operator, duplicate policy, total/imported/skipped counts, and completion time.
2. Compare counts against the frozen Google Forms source. Explain blank rows, filtered headers, and skipped duplicates.
3. Spot-check records using reference numbers and non-sensitive attributes. Do not paste raw applicant data into the reconciliation log.
4. Confirm workflow states are appropriate: imported jobs begin as New unless updating an existing record; imported workshops are Pending unless updating an existing record whose status is preserved.
5. Keep or dispose of the original/exported CSV according to the approved manual retention decision. The application has no automatic cleanup for import sources.

## Spreadsheet and content safety

Imported values are data, never formulas, paths, commands, or attachment URLs. HTML-like input is normalized as text for stored responses. CSV exports and error reports neutralize cells beginning with spreadsheet formula markers; nevertheless, open exports only in patched, trusted spreadsheet software and do not re-enable formula execution.

Original uploaded CSVs live on the private `applicant_imports` disk. They must not be placed in public storage or sent as email attachments unless the recipient and channel are specifically approved.

## Trial migration procedure

Before the production historical import, restore a production-like schema into a dedicated test environment, create synthetic listings/forms matching production, and run the full process with a sanitized sample. Verify Bangla text, quoted commas/newlines, duplicates under all three policies, maximum-size boundaries, invalid rows, expired/stale preview behavior, authorization separation, safe error CSV, and rollback on a forced error.

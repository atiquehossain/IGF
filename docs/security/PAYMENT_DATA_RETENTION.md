# Payment data retention and access

Ignite stores the minimum operational gateway audit fields needed to reconcile a donation. Full card numbers, gateway credentials, raw donor email/phone/name copies, and non-allowlisted callback fields must never be stored in `ssl_commerz_transactions` or logs.

The `2026_08_14_000006_scrub_historical_gateway_payloads` migration irreversibly clears historical card numbers and redundant gateway-side customer fields, and replaces historical raw payloads with an operational allowlist. A production owner must back up the database under the approved encrypted backup policy, run the migration, verify row counts and a sample of sanitized rows, then retire the pre-scrub backup according to the incident response decision.

Donor contact information remains in `donations` because it supports receipts, reconciliation, statutory reporting, and donor support. Access is restricted to authorized administrators. The data owner must document the applicable retention period and approve deletion/anonymization rules before automatic purging is enabled; code must not invent a legal retention period.

Operational rules:

- Never log full callback bodies or customer records.
- Never store CVV, PIN, full PAN/card number, or gateway passwords.
- Treat successful gateway states as terminal against failure/cancel replay.
- Update the gateway transaction and donation status in one local database transaction.
- Reconcile orphaned gateway sessions and pending local records using transaction IDs; do not create records from callbacks for unknown transactions.
- Restrict exports and database access, record administrative access, and encrypt backups.

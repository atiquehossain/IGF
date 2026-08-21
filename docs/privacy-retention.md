# Privacy retention operations

Retention is disabled by default. Set a positive number of days only after the
client approves a written schedule for that record class:

- `PRIVACY_CONTACT_ENQUIRY_DAYS`
- `PRIVACY_SPONSORSHIP_ENQUIRY_DAYS`
- `PRIVACY_VOLUNTEER_APPLICATION_DAYS`
- `PRIVACY_CLOSED_CHAT_DAYS`
- `PRIVACY_SUBSCRIBER_DAYS`

Preview eligible counts without changing data:

```bash
php artisan privacy:apply-retention
```

After an owner reviews the preview, apply enabled policies explicitly:

```bash
php artisan privacy:apply-retention --execute
```

Completed/spam enquiries and closed chats are anonymized; their workflow state
and non-personal operational facts remain. Subscriber records are deleted
because the email address is the record. Financial sponsorship fields such as
amount, transaction reference, and status are preserved. Every executed policy
creates an administrator audit event containing only policy names and counts.

Content purge media is first moved outside the public disk into recoverable
quarantine. The request restores it if the database transaction fails. Run the
following recovery command periodically to resolve files left by an interrupted
process:

```bash
php artisan content:recover-purge-quarantine --age=15
```

Unknown quarantine directories are never deleted automatically. Inspect them
manually. Backups and replicas must follow the client's separately approved
retention and legal-hold rules.

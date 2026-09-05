# Sanitized public-content database

`igf-public-content.sqlite` is a Git-safe development artifact containing the
complete application schema, the permission registry, and the approved,
sanitized CMS design/content snapshot. The snapshot includes reviewed draft,
pending-review, and unlisted CMS entries needed by administrators; “public
content” here means Git-approved content fields, not that every entry is
currently published on the website. It also retains the fixed, non-secret
`seo_redirect_locks.id = 1` mutex required for safe redirect editing. It was built in a brand-new SQLite file
from the project migrations, `DatabaseSeeder`, and
`CmsContentSnapshotSeeder`; it is not a copy of the live database.

The artifact deliberately contains no administrator or member accounts,
password reset or OAuth records, donations, payment transactions,
sponsorships, messages, comments, chat conversations, subscribers,
applications, registrations, private upload contents, audit history,
analytics, failed jobs, or other operational records. Uploaded files from
`storage/app/public` are not embedded; reviewed media rows contain only their
managed paths and metadata.

The companion `igf-public-content.sqlite.sha256` file records the verified
artifact checksum and is enforced by the automated guard.

## Use it for local development

1. Back up any existing local `database/database.sqlite` file.
2. Copy `igf-public-content.sqlite` to a writable local database path (normally
   `database/database.sqlite`). Never edit the tracked artifact in place.
3. Set `.env` to `DB_CONNECTION=sqlite` and set `DB_DATABASE` to the absolute
   path of that writable copy.
4. Install the two tracked annual-report PDFs into private application storage.
   They are intentionally not inside the SQLite file and must remain outside
   `public/storage`:

   ```powershell
   New-Item -ItemType Directory -Force storage/app/annual-reports | Out-Null
   Copy-Item database/seeders/assets/annual-reports/*.pdf storage/app/annual-reports/
   ```

   On Linux/macOS, use `mkdir -p storage/app/annual-reports` followed by
   `cp database/seeders/assets/annual-reports/*.pdf storage/app/annual-reports/`.
5. Run `php artisan migrate --force`, `php artisan storage:link`, and the normal
   frontend install/build commands for the project.
6. Create a new local administrator through the secure interactive command:

   ```shell
   php artisan igf:provision-admin --name="Your Name" --username="your-username" --email="you@example.com"
   ```

   The password is requested securely and the administrator must change it at
   first sign-in. Do not add credentials to seed files, documentation, or Git.

## Verify it

Run the artifact guard before publishing a replacement:

```shell
php artisan test tests/Feature/PublicContentDatabaseArtifactTest.php
```

The guard checks SQLite integrity, foreign keys, free pages, the requested
sensitive-table denylist, every otherwise-unclassified nonempty table,
public-field constraints, the published checksum, and byte-exact normalized
parity with the 29-table `cms-content.snapshot.json` manifest. Future snapshot
exports must receive editorial approval because unpublished CMS copy is also
eligible for this sanitized artifact. Never replace it with a live database
that has merely had rows deleted: deleted SQLite pages may retain recoverable
private data.

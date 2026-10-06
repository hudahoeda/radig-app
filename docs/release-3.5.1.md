# Developer release 3.5.1 integration

The developer archives `update_VersiUpdate3.1.zip` and `update_3.5.1.zip` were
extracted separately and compared. The newer package contains the older app
files; 3.5.1 takes precedence. Existing report fixes and KKTP template guidance
were merged. Database connection settings, Docker configuration, uploaded files,
existing logs, and backups are preserved. Bundled diagnostic/test/repair scripts
and the outdated update-server manifest are excluded.

The OTA checker is included. TLS verification is enabled. Installation inside a
Docker container is blocked: apply developer releases to this repository and
redeploy through Coolify so code changes survive container replacement.
Schema changes run through CLI migrations instead of an automatic header patch
that deletes assignment rows and alters tables during normal page requests.

## Schema comparison and Deep Sync

The master schema was extracted from `deepsync OTA.sql` without importing its
student, teacher, assessment, or school records. Compared with production:

- All 31 tables and 208 master columns already exist.
- Column defaults, nullability, auto-increment settings, collations, indexes,
  and foreign keys match, apart from the assessment subtype enum below.
- Production also has `tujuan_pembelajaran.kktp`; preserve this required column.
- `penilaian.subjenis_penilaian` needs the `Sumatif Tengah Semester` enum value.

Back up the database, then run:

```sh
php scripts/deep_sync_351.php
```

The migration preserves existing enum choices/defaults/nullability, checks the
previous additive columns, and can be rerun safely. Do not restore the supplied
full SQL dump into the school database. Historical report fields are not filled
from students' current classes, since that could mislabel older report records.

Production backup on `srv1`:
`/home/huda/backups/radig-20261006-release-3-5-1/database.sql`.

## Validation

Validated against an isolated copy of production: syntax for 133 app PHP files;
admin/teacher pages; OTA menu and central manifest check; KKTP imports including
old templates; TP copying and duplicate prevention; student report, ledger, and
PTS PDF generation; blocked in-container OTA installation; CLI migration HTTP
protection; and migration rerun safety. Fixed an undefined school-year variable
in the merged ledger query discovered by PDF generation.

The production Deep Sync was applied on 2026-10-06. All 31 tables retained
identical logical row counts and SHA-256 data fingerprints. The final pre-sync
backup is `database-before-sync.sql` in the backup directory above. App releases are committed to this repository and deployed through Coolify.
Runtime logs and database backups are excluded from Git and the deployment
image. Uploads and application backups use persistent storage.

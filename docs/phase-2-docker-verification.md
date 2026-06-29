# Phase 2 Docker Verification

This checklist verifies the Phase 2 plain PHP CLI hardening slice through Docker.

Run commands from the repository root. Each command copies the CLI into the container's `/tmp` directory before running it, so the checked-in workspace data is not modified.

## Boundary Check

Phase 2 remains a single-file plain PHP CLI with JSON persistence:

- no physical Log Entry deletion command
- no Archived Log Entry workflows
- no Log Categories
- no SQLite or PDO
- no Composer or PSR-4 autoloading
- no OOP restructuring

Verification command:

```bash
docker compose run --rm -w /tmp php sh -lc '
cp -R /var/www/html/apps/timeline-cli timeline-cli
cd timeline-cli
php -l bin/app.php
rm -rf data
php bin/app.php log:list >/dev/null
! grep -R "log:delete\|archived_at\|category\|SQLite\|PDO\|composer\|autoload\|namespace\|class " -n bin data
test -f bin/app.php
test -f data/log_entries.json
test -f data/contexts.json
'
```

## Major Phase 2 Flows

Verification command:

```bash
docker compose run --rm -w /tmp php sh -lc '
set -eu
cp -R /var/www/html/apps/timeline-cli timeline-cli
cd timeline-cli
rm -rf data exports
mkdir -p exports

php bin/app.php context:add learn-laravel
php bin/app.php context:add private
php bin/app.php context:switch LEARN-LARAVEL
php bin/app.php context:list

php bin/app.php log:add "Read Laravel docs" --content "Policies and gates" --recorded-at "2026-06-28 09:00"
php bin/app.php log:add "Private note" --recorded-at "2026-06-28 11:00" --context PRIVATE
php bin/app.php log:add "No context note" --content "temporary" --recorded-at "2026-06-29 08:30" --no-context

php bin/app.php log:end 1 --ended-at "2026-06-28 10:00"
php bin/app.php log:edit 2 --title "Compare Gate and Policy" --content "Check examples later" --recorded-at "2026-06-28T12:00:00+09:00" --context learn-laravel
php bin/app.php log:edit 3 --clear-content --clear-ended-at

php bin/app.php log:list
php bin/app.php log:list --date 2026-06-28
php bin/app.php log:list --from 2026-06-28 --to 2026-06-29 --order asc
php bin/app.php log:list --context LEARN-LARAVEL
php bin/app.php log:list --no-context
TZ=Asia/Tokyo php bin/app.php log:today --no-context

php bin/app.php log:export-csv
php bin/app.php log:export-csv --from 2026-06-28 --to 2026-06-29 --context learn-laravel --output exports/phase-2.csv
test -s exports/phase-2.csv
'
```

This covers update flows, review filters, the today view, CSV export, Context case-insensitive lookup, and JSON persistence.

## Invalid Input Failures

Verification command:

```bash
docker compose run --rm -w /tmp php sh -lc '
set -eu
cp -R /var/www/html/apps/timeline-cli timeline-cli
cd timeline-cli
rm -rf data

must_fail() {
    if "$@" >/tmp/phase-2-out 2>/tmp/phase-2-err; then
        cat /tmp/phase-2-out
        cat /tmp/phase-2-err
        exit 1
    fi
    cat /tmp/phase-2-err
}

php bin/app.php context:add learn-laravel
php bin/app.php log:add "Seed" --recorded-at "2026-06-28 09:00" --context learn-laravel

must_fail php bin/app.php nope
must_fail php bin/app.php log:list --bad-option
must_fail php bin/app.php log:add
must_fail php bin/app.php log:add "Bad time" --recorded-at tomorrow
must_fail php bin/app.php log:list --from 2026-06-30 --to 2026-06-01
must_fail php bin/app.php log:end 999
must_fail php bin/app.php log:list --context unknown

printf "{broken json\n" > data/log_entries.json
must_fail php bin/app.php log:list

printf "[{\"id\":\"bad\"}]\n" > data/log_entries.json
must_fail php bin/app.php log:list
'
```

Expected result: each invalid command exits non-zero and prints a direct error message for invalid command, invalid option, missing argument, invalid date/time, invalid date range, unknown Log Entry, unknown Context, broken JSON, and invalid stored record shape cases.

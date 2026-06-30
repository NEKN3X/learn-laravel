# Phase 3 Local Verification

This checklist verifies the Phase 3 Composer and OOP CLI restructure through local WSL PHP commands.

Run commands from the repository root. Each command copies the CLI into a temporary directory before running it, so the checked-in workspace data is not modified.

Prerequisites:

- PHP 8.5 or newer is available as `php`.
- Composer is available as `composer`.

## Boundary Check

Phase 3 keeps the Phase 2 command contract while replacing the internal implementation with Composer autoloading and classes:

- Composer is local to `apps/timeline-cli`
- `bin/app.php` is a thin bootstrap
- domain, application, infrastructure, and console classes live under `src/`
- no physical Log Entry deletion command
- no Archived Log Entry workflows
- no Log Categories
- no SQLite or PDO
- no automated test tooling
- no CLI framework such as `symfony/console`

Verification command:

```bash
set -eu
tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT
cp -R apps/timeline-cli "$tmpdir/timeline-cli"
cd "$tmpdir/timeline-cli"

composer install
composer dump-autoload

php -l bin/app.php
find src -name "*.php" -print -exec php -l {} \;

test -f composer.json
test -f composer.lock
test -f vendor/autoload.php
test -d src
test "$(wc -l < bin/app.php)" -le 40

php bin/app.php log:list >/dev/null

! grep -R "log:delete\|archived_at\|category\|SQLite\|PDO" -n bin src data
! composer show symfony/console >/dev/null 2>&1
```

## Major Phase 2 Compatibility Flows

Verification command:

```bash
set -eu
tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT
cp -R apps/timeline-cli "$tmpdir/timeline-cli"
cd "$tmpdir/timeline-cli"
composer install
export TZ=Asia/Tokyo
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
php bin/app.php log:edit 1 --clear-ended-at
php bin/app.php log:edit 1 --ended-at "2026-06-28 10:00"
php bin/app.php log:edit 2 --title "Compare Gate and Policy" --content "Check examples later" --recorded-at "2026-06-28T12:00:00+09:00" --context LEARN-LARAVEL
php bin/app.php log:edit 3 --clear-content

php bin/app.php log:list
php bin/app.php log:list --date 2026-06-28
php bin/app.php log:list --from 2026-06-28 --to 2026-06-29 --order asc
php bin/app.php log:list --context LEARN-LARAVEL
php bin/app.php log:list --no-context
TZ=Asia/Tokyo php bin/app.php log:today --context LEARN-LARAVEL
TZ=Asia/Tokyo php bin/app.php log:today --no-context

php bin/app.php log:export-csv
php bin/app.php log:export-csv --from 2026-06-28 --to 2026-06-29 --context LEARN-LARAVEL --output exports/phase-3.csv
test -s exports/phase-3.csv
grep -Fx "id,title,content,recorded_at,ended_at,context_id,context_name" exports/phase-3.csv
grep -n "2026-06-28T09:00:00+09:00" exports/phase-3.csv | grep -F "2:"
grep -n "2026-06-28T12:00:00+09:00" exports/phase-3.csv | grep -F "3:"
```

## `.env` Configuration

Verification command:

```bash
set -eu
tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT
cp -R apps/timeline-cli "$tmpdir/timeline-cli"
cd "$tmpdir/timeline-cli"
composer install
rm -rf custom-data custom-logs

cat > .env <<EOF
TIMELINE_DATA_DIR=custom-data
TIMELINE_LOG_FILE=custom-logs/app.log
EOF

php bin/app.php context:add learn-laravel
php bin/app.php context:switch learn-laravel
php bin/app.php log:add "Configured data dir" --context learn-laravel

test -f custom-data/contexts.json
test -f custom-data/log_entries.json
test -f custom-data/current_context.json
```

## Logging Operational Failures

Verification command:

```bash
set -eu
tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT
cp -R apps/timeline-cli "$tmpdir/timeline-cli"
cd "$tmpdir/timeline-cli"
composer install
rm -rf data logs

php bin/app.php log:add "Seed"
printf "{broken json\n" > data/log_entries.json

if php bin/app.php log:list >/tmp/phase-3-out 2>/tmp/phase-3-err; then
    cat /tmp/phase-3-out
    exit 1
fi

grep -F "Storage file contains broken JSON" /tmp/phase-3-err
test -s logs/app.log
grep -F "broken JSON" logs/app.log
```

## Invalid Input Failures

Verification command:

```bash
set -eu
tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT
cp -R apps/timeline-cli "$tmpdir/timeline-cli"
cd "$tmpdir/timeline-cli"
composer install
rm -rf data logs

must_fail() {
    if "$@" >/tmp/phase-3-out 2>/tmp/phase-3-err; then
        cat /tmp/phase-3-out
        cat /tmp/phase-3-err
        exit 1
    fi
    cat /tmp/phase-3-err
}

php bin/app.php context:add learn-laravel
php bin/app.php log:add "Seed" --recorded-at "2026-06-28 09:00" --context learn-laravel

must_fail php bin/app.php nope
must_fail php bin/app.php log:list --bad-option
must_fail php bin/app.php log:add
must_fail php bin/app.php log:add "Missing option value" --content
must_fail php bin/app.php log:add "Too many" "arguments"
must_fail php bin/app.php log:list --order asc --order desc
must_fail php bin/app.php log:list --context learn-laravel --no-context
must_fail php bin/app.php log:add "Bad time" --recorded-at tomorrow
must_fail php bin/app.php log:list --from 2026-06-30 --to 2026-06-01
must_fail php bin/app.php log:end abc
must_fail php bin/app.php log:end 999
must_fail php bin/app.php log:list --context unknown
must_fail php bin/app.php context:add LEARN-LARAVEL

test ! -e logs/app.log
```

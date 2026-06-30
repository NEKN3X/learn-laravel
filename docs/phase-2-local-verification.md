# Phase 2 Local Verification

This checklist verifies the Phase 2 plain PHP CLI hardening slice through local WSL PHP commands.

Run commands from the repository root when the checked-out tree is at the Phase 2 implementation. Each command copies the CLI into a temporary directory before running it, so the checked-in workspace data is not modified.

Prerequisite:

- PHP is available as `php`.

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
set -eu
tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT
cp -R apps/timeline-cli "$tmpdir/timeline-cli"
cd "$tmpdir/timeline-cli"

php -l bin/app.php
rm -rf data
php bin/app.php log:list >/dev/null
! grep -R "log:delete\|archived_at\|category\|SQLite\|PDO\|composer\|autoload\|namespace\|class " -n bin data
test -z "$(find . \( -iname composer.json -o -iname composer.lock \) -print -quit)"
test -f bin/app.php
test -f data/log_entries.json
test -f data/contexts.json
```

Verified result on 2026-06-29:

- `php -l bin/app.php` reported no syntax errors.
- The CLI initialized JSON storage under `data/`.
- The boundary grep found no physical deletion command, Archived Log Entry workflow, Log Categories, SQLite/PDO, autoloading, namespaces, or classes in the CLI runtime files.
- No Composer manifest or lock file was present in the copied CLI app.

## Major Phase 2 Flows

Verification command:

```bash
set -eu
tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT
cp -R apps/timeline-cli "$tmpdir/timeline-cli"
cd "$tmpdir/timeline-cli"
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
php bin/app.php log:export-csv --from 2026-06-28 --to 2026-06-29 --context LEARN-LARAVEL --output exports/phase-2.csv
test -s exports/phase-2.csv
grep -Fx "id,title,content,recorded_at,ended_at,context_id,context_name" exports/phase-2.csv
grep -n "2026-06-28T09:00:00+09:00" exports/phase-2.csv | grep -F "2:"
grep -n "2026-06-28T12:00:00+09:00" exports/phase-2.csv | grep -F "3:"
```

This covers update flows, review filters, the today view, CSV export, Context case-insensitive lookup, and JSON persistence.

Verified result on 2026-06-29:

- Context creation, listing, and case-insensitive `context:switch LEARN-LARAVEL` succeeded.
- Log Entry creation with explicit Recorded Time, explicit Context, Current Context, and `--no-context` succeeded.
- Update flows succeeded through `log:end --ended-at` and `log:edit` for title, content, Recorded Time, Span Entry to Point Entry End Time clearing, End Time resetting, and Context changes.
- Review flows succeeded through `log:list`, `--date`, `--from/--to`, `--order asc`, `--context LEARN-LARAVEL`, `--no-context`, `log:today --context LEARN-LARAVEL`, and `log:today --no-context`.
- CSV export succeeded to stdout and to `exports/phase-2.csv`; the output file had the expected header and Recorded Time ascending row order.
- Case-insensitive Context lookup succeeded for `context:switch`, `log:add --context`, `log:edit --context`, `log:list --context`, `log:today --context`, and `log:export-csv --context`.

## Usage Text

Verification command:

```bash
set -eu
tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT
cp -R apps/timeline-cli "$tmpdir/timeline-cli"
cd "$tmpdir/timeline-cli"

if php bin/app.php >/tmp/phase-2-usage 2>&1; then
    cat /tmp/phase-2-usage
    exit 1
fi

grep -F "php bin/app.php log:add" /tmp/phase-2-usage
grep -F "php bin/app.php log:end" /tmp/phase-2-usage
grep -F "php bin/app.php log:edit" /tmp/phase-2-usage
grep -F "php bin/app.php log:list" /tmp/phase-2-usage
grep -F "php bin/app.php log:today" /tmp/phase-2-usage
grep -F "php bin/app.php log:export-csv" /tmp/phase-2-usage
grep -F "php bin/app.php context:add" /tmp/phase-2-usage
grep -F "php bin/app.php context:list" /tmp/phase-2-usage
grep -F "php bin/app.php context:switch" /tmp/phase-2-usage
grep -F "php bin/app.php context:clear" /tmp/phase-2-usage
grep -F -- "--content" /tmp/phase-2-usage
grep -F -- "--clear-content" /tmp/phase-2-usage
grep -F -- "--recorded-at" /tmp/phase-2-usage
grep -F -- "--ended-at" /tmp/phase-2-usage
grep -F -- "--clear-ended-at" /tmp/phase-2-usage
grep -F -- "--date" /tmp/phase-2-usage
grep -F -- "--from" /tmp/phase-2-usage
grep -F -- "--to" /tmp/phase-2-usage
grep -F -- "--context" /tmp/phase-2-usage
grep -F -- "--no-context" /tmp/phase-2-usage
grep -F -- "--order" /tmp/phase-2-usage
grep -F -- "--output" /tmp/phase-2-usage
```

Verified result on 2026-06-29:

- Missing-command usage output included all Phase 2 commands.
- Missing-command usage output included supported Phase 2 options for content, content clearing, explicit date/time, End Time clearing, filters, Context override, ordering, and CSV output.

## Invalid Input Failures

Verification command:

```bash
set -eu
tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT
cp -R apps/timeline-cli "$tmpdir/timeline-cli"
cd "$tmpdir/timeline-cli"
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

printf "{broken json\n" > data/log_entries.json
must_fail php bin/app.php log:list

printf "[{\"id\":\"bad\"}]\n" > data/log_entries.json
must_fail php bin/app.php log:list

printf "[{\"id\":\"bad\"}]\n" > data/contexts.json
must_fail php bin/app.php context:list

printf "{\"current_context_id\":\"bad\"}\n" > data/current_context.json
must_fail php bin/app.php context:clear
```

Expected result: each invalid command exits non-zero and prints a direct error message for invalid command, invalid option, missing argument, missing option value, extra positional argument, duplicate option, mutually exclusive options, invalid date/time, invalid date range, invalid Log Entry ID, unknown Log Entry, unknown Context, duplicate Context name, broken JSON, and invalid stored record shape cases.

Verified result on 2026-06-29:

- Invalid command: `Unknown command: nope`, followed by full usage text.
- Invalid option: `Unknown option for log:list: --bad-option`.
- Missing argument: `Missing required title.`, followed by command usage.
- Missing option value: `Missing value for --content.`
- Extra positional argument: `Command log:add accepts exactly one title.`
- Duplicate option: `Duplicate option for log:list: --order`
- Mutually exclusive options: `Cannot provide both --context and --no-context.`
- Invalid date/time: `Invalid date/time for --recorded-at: tomorrow. Use Y-m-d H:i or ISO 8601.`
- Invalid date range: `--from must be on or before --to.`
- Invalid Log Entry ID: `Invalid Log Entry ID: abc`.
- Unknown Log Entry: `Log Entry #999 was not found.`
- Unknown Context: `Context not found: unknown`.
- Duplicate Context name: `Context already exists: LEARN-LARAVEL`.
- Broken JSON: `Storage file contains broken JSON: ... Fix the file before running this command.`
- Invalid stored record shape: `Invalid Log Entry record at index 0 ... expected fields id, title, content, recorded_at, ended_at, context_id.`
- Invalid stored Context shape: `Invalid Context record at index 0 ... expected fields id, name.`
- Invalid stored Current Context shape: `Current Context ID must be a positive integer or null: ...`

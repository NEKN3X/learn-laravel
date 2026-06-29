# Phase 2 CLI Spec: Plain PHP Hardening

## Goal

Make the plain PHP CLI more robust while staying in a single-file, non-Composer, non-OOP shape.

This phase should deepen command parsing, validation, date handling, filtering, and export behavior without introducing the architectural tools reserved for later phases.

## Scope

Included:

- Improve validation and error messages.
- Support editing Log Entries.
- Support explicit Recorded Time on Log Entry creation.
- Support explicit End Time when ending a Log Entry.
- Add filters by date and Context.
- Export Log Entries to CSV.

Excluded:

- Deleting Log Entries.
- Archiving Log Entries.
- Log Categories.
- SQLite.
- Composer.
- OOP.
- Automated tests.

## Decisions

### Treat invalid input as an error

Phase 2 should fail clearly for invalid input instead of silently ignoring it, guessing intent, or returning an empty result for likely mistakes.

General validation rules:

- Unknown commands fail.
- Unknown options fail.
- Missing required arguments fail.
- Missing option values fail.
- Extra positional arguments fail unless the command explicitly accepts them.
- Duplicate options fail.
- Mutually exclusive options fail.
- Invalid IDs fail.
- Unknown Log Entry IDs fail.
- Unknown Context names fail.
- Invalid date/time values fail.
- Invalid date ranges fail.
- Invalid stored JSON and invalid stored record shapes fail.

Commands should validate the final intended state, not only individual option values.

### Do not add physical deletion in Phase 2

Phase 2 will not add `log:delete`.

Deletion is less aligned with the roadmap's domain model than the later Archived Log Entry concept. Keeping deletion out avoids teaching a destructive workflow that will be replaced by archive/unarchive behavior in later phases.

### Edit Log Entries through one command

Phase 2 will add `log:edit` as a single command for updating existing Log Entries.

Examples:

```bash
php bin/app.php log:edit 1 --title "新しいタイトル"
php bin/app.php log:edit 1 --content "本文を更新"
php bin/app.php log:edit 1 --content ""
php bin/app.php log:edit 1 --clear-content
php bin/app.php log:edit 1 --recorded-at "2026-06-28 09:00"
php bin/app.php log:edit 1 --ended-at "2026-06-28 10:00"
php bin/app.php log:edit 1 --clear-ended-at
php bin/app.php log:edit 1 --context learn-laravel
php bin/app.php log:edit 1 --no-context
```

Editable fields:

- `title`
- `content`
- `recorded_at`
- `ended_at`
- `context_id`

Rules:

- `title` cannot be empty.
- `content` may be an empty string or `null`.
- Whitespace-only `content` input is normalized to an empty string.
- `--content ""` stores an empty string.
- `--clear-content` sets `content` to `null`.
- `--content` and `--clear-content` cannot be used together.
- `recorded_at` must be a valid date/time.
- `ended_at` must be a valid date/time when provided.
- `ended_at` cannot be earlier than `recorded_at`.
- `--clear-ended-at` turns a Span Entry back into a Point Entry.
- `--ended-at` and `--clear-ended-at` cannot be used together.
- `--context` and `--no-context` cannot be used together.
- At least one change option is required.
- The same option cannot be provided more than once.
- The final Log Entry state must be valid after applying all requested changes.
- Changing `recorded_at` fails if the final `ended_at` would be earlier than it.
- Changing `recorded_at` and `ended_at` together is allowed when the final range is valid.

### Support two explicit date/time input formats

Phase 2 will support explicit date/time input for `--recorded-at` and `--ended-at`.

Supported formats:

- `Y-m-d H:i`, such as `2026-06-28 09:00`
- ISO 8601, such as `2026-06-28T09:00:00+09:00`

Examples:

```bash
php bin/app.php log:add "Laravel docs" --recorded-at "2026-06-28 09:00"
php bin/app.php log:add "Laravel docs" --recorded-at "2026-06-28T09:00:00+09:00"
php bin/app.php log:end 1 --ended-at "2026-06-28 10:30"
php bin/app.php log:edit 1 --recorded-at "2026-06-28T09:00:00+09:00"
```

Rules:

- Values are normalized to ISO 8601 for storage.
- Natural-language values such as `tomorrow` or `next monday` are not supported.

### Keep `log:end` as a Point-to-Span command

Phase 2 will add `--ended-at` to `log:end`, but `log:end` will still fail when the Log Entry already has an End Time.

Examples:

```bash
php bin/app.php log:end 1 --ended-at "2026-06-28 10:00"
```

Rules:

- `log:end` sets End Time only when `ended_at` is currently `null`.
- Existing End Time corrections use `log:edit --ended-at`.
- `log:end` fails if the resulting End Time would be earlier than Recorded Time.

### Filter by Recorded Time

Phase 2 filters Log Entries by Recorded Time only.

Examples:

```bash
php bin/app.php log:list --date 2026-06-28
php bin/app.php log:list --from 2026-06-01 --to 2026-06-30
php bin/app.php log:list --context learn-laravel
php bin/app.php log:list --no-context
```

Rules:

- `--date` includes Log Entries whose Recorded Time falls on that local date.
- `--from` includes Log Entries whose Recorded Time is on or after that local date.
- `--to` includes Log Entries whose Recorded Time is on or before that local date.
- `--date`, `--from`, and `--to` accept dates only, using `Y-m-d`.
- `--date` cannot be combined with `--from` or `--to`.
- `--from` must be on or before `--to`.
- `--context` fails if the named Context does not exist.
- `--context` cannot be combined with `--no-context`.
- A Span Entry that crosses midnight is still filtered by Recorded Time, not by overlap with the date range.

### Order Log Entry lists by Recorded Time

Phase 2 will order human-readable Log Entry lists by Recorded Time descending by default.

Examples:

```bash
php bin/app.php log:list
php bin/app.php log:list --order asc
php bin/app.php log:list --order desc
```

Rules:

- `log:list` defaults to `--order desc`.
- `log:today` defaults to `--order desc`.
- `--order` accepts only `asc` or `desc`.
- The sort key is Recorded Time.
- CSV export uses Recorded Time ascending.

### Keep `log:today` as a shortcut

Phase 2 keeps `log:today`.

`log:today` is equivalent to `log:list --date <today's local date>`.

Examples:

```bash
php bin/app.php log:today
php bin/app.php log:today --context learn-laravel
php bin/app.php log:today --no-context
```

Rules:

- `log:today` accepts Context filters.
- `log:today` does not accept `--date`, `--from`, or `--to`.

### Export CSV through a dedicated command

Phase 2 will add `log:export-csv`.

Examples:

```bash
php bin/app.php log:export-csv
php bin/app.php log:export-csv --date 2026-06-28
php bin/app.php log:export-csv --from 2026-06-01 --to 2026-06-30 --context learn-laravel
php bin/app.php log:export-csv --output exports/june.csv
```

Rules:

- Without `--output`, CSV is written to stdout.
- With `--output`, CSV is written to the given file.
- `log:export-csv` accepts the same date and Context filters as `log:list`.
- CSV output is machine-oriented and separate from the human-readable `log:list` output.

Columns:

```txt
id,title,content,recorded_at,ended_at,context_id,context_name
```

### Treat Context names as case-insensitively unique

Phase 2 will reject Context names that differ only by letter case.

Example:

```bash
php bin/app.php context:add learn-laravel
php bin/app.php context:add Learn-Laravel
```

The second command fails because `learn-laravel` already exists.

Rules:

- Duplicate checks are case-insensitive.
- Lookup by `--context` and `context:switch` is case-insensitive.
- The originally registered display name is preserved in storage and output.

Commands that resolve Context names case-insensitively:

- `log:add --context`
- `log:edit --context`
- `log:list --context`
- `log:today --context`
- `log:export-csv --context`
- `context:switch`

### Validate stored record shape on load

Phase 2 will validate stored JSON records after decoding.

Invalid stored data should fail clearly instead of being skipped or repaired automatically.

Examples:

```txt
Invalid Log Entry at index 0: id must be an integer.
Invalid Log Entry #1: title must be a non-empty string.
Invalid Context at index 0: name must be a non-empty string.
```

Rules:

- `log_entries.json` must be a JSON array.
- Each Log Entry must have a valid `id`, `title`, `content`, `recorded_at`, `ended_at`, and `context_id`.
- `contexts.json` must be a JSON array.
- Each Context must have a valid `id` and `name`.
- `current_context.json` must contain `current_context_id` as an integer or `null`.
- Existing invalid records are not silently migrated in Phase 2.

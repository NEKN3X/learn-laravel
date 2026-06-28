# Phase 1 CLI Spec: JSON Timeline Logging

## Goal

Build a small PHP CLI application that lets the user place Log Entries on a timeline and work with Contexts, using JSON files for persistence.

This phase is for learning PHP basics while keeping the CLI natural to use across separate command invocations.

## Scope

Included:

- Create Log Entries.
- List Log Entries.
- Show today's Log Entries.
- Add an end time to a Log Entry by ID.
- Create Contexts.
- List Contexts.
- Switch Current Context.
- Clear Current Context.
- Override Context per Log Entry.
- Create a Log Entry with no Context even when Current Context is set.
- Save and load data from JSON files.
- Initialize missing data files.

Excluded:

- SQLite.
- Composer.
- OOP.
- tests.
- Log Categories.
- archiving.
- editing Log Entries.
- deleting Log Entries.
- authentication.
- web UI.

## Domain Shape

### Log Entry

```txt
id: int
title: string
content: string|null
recorded_at: string
ended_at: string|null
context_id: int|null
```

Rules:

- `title` is required.
- `content` is optional.
- `recorded_at` is set when the Log Entry is created.
- `ended_at` is optional.
- `ended_at` must not be earlier than `recorded_at`.
- `context_id` is optional.
- A Log Entry has no built-in type.

### Context

```txt
id: int
name: string
```

Rules:

- `name` is required.
- Contexts are flat.
- Duplicate names are rejected.

### Current Context

```txt
current_context_id: int|null
```

Rules:

- Current Context is the default destination for new Log Entries.
- A Log Entry can override Current Context.
- A Log Entry can explicitly use no Context.

## Storage

Use JSON files under `data/`.

```txt
apps/
  timeline-cli/
    bin/
      app.php
    data/
      log_entries.json
      contexts.json
      current_context.json
```

Suggested JSON shapes:

```json
[
  {
    "id": 1,
    "title": "Laravelの認可を調べる",
    "content": null,
    "recorded_at": "2026-06-28T14:30:00+09:00",
    "ended_at": null,
    "context_id": 1
  }
]
```

```json
[
  {
    "id": 1,
    "name": "learn-laravel"
  }
]
```

```json
{
  "current_context_id": 1
}
```

Rules:

- Missing files are initialized with empty data.
- Broken JSON should produce a clear error.
- New IDs can be generated from the current maximum ID plus one.

## Commands

### `log:add`

Creates a Log Entry at the current time.

```bash
php bin/app.php log:add "Laravelの認可を調べる"
php bin/app.php log:add "PolicyとGateの違いが気になる" --content "後で公式を確認する"
php bin/app.php log:add "個人アプリのUI案" --context private
php bin/app.php log:add "買い物を思い出した" --no-context
```

Behavior:

- Uses Current Context if no Context option is given.
- Uses the named Context if `--context` is given.
- Uses no Context if `--no-context` is given.
- Fails if both `--context` and `--no-context` are given.
- Fails if `--context` refers to a missing Context.

### `log:end`

Adds an end time to an existing Log Entry.

```bash
php bin/app.php log:end 1
```

Behavior:

- Sets `ended_at` to the current time.
- Fails if the ID does not exist.
- Fails if the Log Entry already has `ended_at`.

### `log:list`

Lists Log Entries in recorded-time order.

```bash
php bin/app.php log:list
```

Output should include:

- ID
- recorded time
- end time if present
- Context name if present
- title

### `log:today`

Lists Log Entries recorded today.

```bash
php bin/app.php log:today
```

Behavior:

- Uses the local date.
- Shows both Point Entries and Span Entries.

### `context:add`

Creates a Context.

```bash
php bin/app.php context:add learn-laravel
```

Behavior:

- Fails if name is empty.
- Fails if name already exists.

### `context:list`

Lists Contexts.

```bash
php bin/app.php context:list
```

Output should indicate the Current Context.

### `context:switch`

Sets Current Context.

```bash
php bin/app.php context:switch learn-laravel
```

Behavior:

- Fails if the Context does not exist.

### `context:clear`

Clears Current Context.

```bash
php bin/app.php context:clear
```

Behavior:

- New Log Entries default to no Context unless `--context` is provided.

## Suggested File Layout

```txt
apps/
  timeline-cli/
    bin/
      app.php
```

Phase 1 should stay in a single PHP file unless the file becomes hard to follow. OOP and Composer come later.

## Learning Targets

- PHP CLI execution.
- Reading `$argv`.
- Command dispatch with `if` or `switch`.
- Variables.
- Indexed arrays.
- Associative arrays.
- Functions.
- Return values.
- `null`.
- Basic date/time formatting.
- File reads and writes.
- JSON encode/decode.
- String interpolation.
- Basic validation.
- Reading error messages.

## Completion Criteria

Phase 1 is complete when:

- Every command in this spec exists.
- Invalid commands show a clear error.
- Missing required arguments show a clear error.
- `log:add` can create Point Entries.
- `log:end` can turn an existing Point Entry into a Span Entry.
- `context:switch` changes the default Context for later Log Entries within the process.
- `--context` overrides Current Context for one Log Entry.
- `--no-context` records one Log Entry without Context.
- `log:list` and `log:today` display readable output.
- Data persists across separate CLI invocations.
- Missing JSON files are initialized automatically.
- Broken JSON fails with a clear error.

# Phase 3 CLI Spec: OOP and Composer CLI

## Goal

Restructure the existing Timeline Logging CLI with classes and Composer while preserving the Phase 2 user-visible behavior.

Phase 3 should teach object-oriented PHP, namespaces, Composer autoloading, interfaces, dependency injection basics, `.env` configuration, and logging without changing the domain workflow.

## Scope

Included:

- Introduce Composer autoloading.
- Introduce classes for Log Entries and Contexts.
- Introduce repository interfaces and JSON-backed implementations.
- Introduce services for Log Entry and Context operations.
- Introduce a Console Application entry point.
- Add `.env` configuration for runtime paths and logging behavior.
- Add logging for operational failures where useful.

Excluded:

- New Log Entry commands.
- New Context commands.
- Physical Log Entry deletion.
- Archived Log Entry workflows.
- Log Categories.
- SQLite or PDO.
- Automated tests.
- Web routes, HTML, or HTTP handling.

## Decisions

### Preserve the Phase 2 CLI contract

Phase 3 will keep the Phase 2 CLI as the external contract and change only the internal structure.

The following should remain compatible with Phase 2:

- command names
- supported arguments and options
- validation rules
- stdout and stderr behavior
- exit code meaning
- JSON storage shape
- Local verification scenarios

This keeps the phase focused on learning OOP and Composer instead of mixing architecture changes with feature changes.

### Replace the existing CLI app in place

Phase 3 will restructure `apps/timeline-cli` in place instead of creating a second Composer-based CLI app.

The Phase 2 implementation is treated as the previous internal shape of the same learning app. Phase 3 keeps the command contract stable while replacing the single-file implementation with Composer autoloading and classes.

### Use `TimelineCli` as the PHP namespace

Phase 3 will use `TimelineCli` as the root PHP namespace for the Composer-based CLI app.

Example class names:

```txt
TimelineCli\Domain\LogEntry
TimelineCli\Domain\Context
TimelineCli\Domain\LogEntryRepository
TimelineCli\Domain\ContextRepository
TimelineCli\Application\LogEntryService
TimelineCli\Application\ContextService
TimelineCli\Infrastructure\JsonLogEntryRepository
TimelineCli\Infrastructure\JsonContextRepository
TimelineCli\Console\ConsoleApplication
```

The namespace follows the existing `apps/timeline-cli` app name while staying specific to the CLI implementation.

### Keep domain objects responsible for their invariants

Phase 3 will model `LogEntry` and `Context` as lightweight domain objects rather than passive array wrappers.

`LogEntry` should protect its own state rules:

- `title` cannot be empty.
- `ended_at` cannot be earlier than `recorded_at`.
- `end()` turns a Point Entry into a Span Entry.
- `clearEndTime()` turns a Span Entry back into a Point Entry.
- title, content, Recorded Time, End Time, and Context assignment changes go through explicit methods.

`LogEntry` should not handle CLI parsing, JSON persistence, Context name lookup, CSV output, stdout, or stderr.

`Context` should protect its own state rules, such as requiring a non-empty name. Case-insensitive uniqueness remains a repository/service-level rule because it depends on the set of existing Contexts.

### Put repository interfaces in the Domain namespace

Repository interfaces belong to the Domain namespace because they describe how the application works with collections of Log Entries and Contexts, independent of the storage technology.

```txt
TimelineCli\Domain\LogEntryRepository
TimelineCli\Domain\ContextRepository
TimelineCli\Domain\CurrentContextStore
```

Storage-specific implementations belong to Infrastructure:

```txt
TimelineCli\Infrastructure\JsonLogEntryRepository
TimelineCli\Infrastructure\JsonContextRepository
TimelineCli\Infrastructure\JsonCurrentContextStore
```

This keeps Phase 4's SQLite migration focused on replacing repository implementations rather than changing application or domain code.

Repository interfaces are distinct from application ports. If Phase 3 introduces ports for use-case-specific concerns such as logging, clock access, or CSV-oriented reads, those ports should live with the Application code instead of being named as domain repositories.

### Keep IDs as integers

Phase 3 will keep Log Entry IDs and Context IDs as integers to preserve the Phase 2 JSON storage shape.

`LogEntryId` and `ContextId` value objects are out of scope for this phase. Input validation for ID strings belongs in the Console or Application layer before domain objects are loaded or changed.

### Use `DateTimeImmutable` inside domain objects

Phase 3 will represent Recorded Time and End Time as `DateTimeImmutable` values inside domain objects.

JSON storage will remain compatible with Phase 2 by storing date/time values as ISO 8601 strings. Repository implementations are responsible for converting between stored strings and domain date/time objects.

### Use a minimal repository API

`LogEntryRepository` should use a small collection-style API:

```php
interface LogEntryRepository
{
    public function nextId(): int;
    public function findById(int $id): ?LogEntry;

    /** @return list<LogEntry> */
    public function all(): array;

    public function save(LogEntry $entry): void;
}
```

`save()` handles both new and existing Log Entries based on ID. `delete()` is out of scope for Phase 3.

`ContextRepository` should follow the same shape, with `findByName()` supporting case-insensitive lookup while preserving the stored display name.

### Keep CLI argument parsing in the Console layer

`ConsoleApplication` is responsible for reading `argv`, dispatching commands, validating command arguments and options, and producing stdout/stderr messages.

Application services should receive normalized values instead of CLI syntax. They should not depend on option names such as `--recorded-at`, `--context`, or `--no-context`.

This keeps CLI-specific parsing separate from Log Entry and Context use cases while avoiding a larger command framework in Phase 3.

### Filter and sort Log Entries in the Application layer

Phase 3 will keep filtering and sorting for `log:list`, `log:today`, and `log:export-csv` in the Application layer.

JSON repositories should load Log Entry and Context collections. Application services should apply date filters, Context filters, and ordering in memory.

This keeps Phase 3 aligned with JSON persistence. Phase 4 may push filtering into SQLite-specific queries if that becomes useful.

### Write CSV output in the Console layer

Application services may return export row data for Log Entries, but CSV formatting and writing belong to the Console layer.

`ConsoleApplication` should handle `fputcsv()`, stdout output, and `--output` file writing. The domain model should not know about CSV.

### Keep `.env` configuration minimal

Phase 3 will introduce `.env` configuration only for runtime file locations:

```env
TIMELINE_DATA_DIR=data
TIMELINE_LOG_FILE=logs/app.log
```

Timezone should continue to come from the execution environment. CSV output paths remain command arguments. Storage driver configuration is deferred until Phase 4 introduces SQLite.

### Log operational failures, not ordinary input errors

Phase 3 logging should focus on operational failures:

- unreadable JSON files
- broken JSON
- failed writes
- `.env` loading failures
- unexpected exceptions

Ordinary user input errors should be reported to stderr without being written to the application log:

- unknown commands
- missing arguments
- invalid dates
- unknown Context names
- unknown Log Entry IDs
- duplicate or mutually exclusive options

### Add only minimal Composer dependencies

Phase 3 will use Composer for PSR-4 autoloading and a small number of runtime dependencies:

```json
{
  "require": {
    "vlucas/phpdotenv": "^5.6",
    "monolog/monolog": "^3.0"
  }
}
```

`vlucas/phpdotenv` is used for `.env` loading. `monolog/monolog` is used for application logging.

CLI frameworks such as `symfony/console` are out of scope for Phase 3 so the existing command parsing learning path remains visible.

### Use typed exceptions for domain and application failures

Phase 3 will use typed exceptions for expected failures below the Console layer, following common PHP and Laravel practice.

Examples:

- `InvalidLogEntry`
- `InvalidContext`
- `LogEntryNotFound`
- `ContextNotFound`
- `DuplicateContextName`
- `StorageFailure`

`ConsoleApplication` should catch these exceptions and convert them to Phase 2-compatible stderr messages and non-zero exit codes.

CLI syntax errors such as unknown commands, missing arguments, duplicate options, and mutually exclusive options may be handled directly in the Console layer before reaching Application or Domain code.

### Use mutable domain objects with explicit change methods

`LogEntry` and `Context` should use private properties and explicit methods for state changes.

Phase 3 will not model domain objects as immutable `with...()` value-returning objects. Mutable entities are closer to common PHP and Laravel practice and keep the OOP learning step easier to follow.

State changes should still go through methods that enforce domain invariants instead of exposing public writable properties.

### Group use cases by domain service

Phase 3 will use `LogEntryService` and `ContextService` rather than one class per command.

`LogEntryService` should expose command-aligned methods such as add, end, edit, list, today, and export. `ContextService` should expose add, list, switch, and clear.

This keeps the transition from the Phase 2 single-file CLI understandable while still separating Console, Application, Domain, and Infrastructure responsibilities.

### Keep console dispatch inside `ConsoleApplication`

Phase 3 will keep command dispatch in `ConsoleApplication` using explicit `switch` or `match` logic.

Individual command classes such as `AddLogEntryCommand` or `EndLogEntryCommand` are out of scope. `bin/app.php` should become a thin bootstrap that loads Composer autoloading, builds dependencies, and calls `ConsoleApplication::run($argv)`.

### Defer automated tests until Phase 5

Phase 3 will not introduce PHPUnit, Pest, static analysis, formatters, or CI.

The phase should include local WSL PHP verification documentation instead:

```txt
docs/phase-3-local-verification.md
```

The verification should confirm Composer installation, PSR-4 autoloading, syntax checks, Phase 2 command compatibility, minimal `.env` behavior, logging behavior for operational failures, and Phase 3 boundary exclusions.

### Keep Composer local to `apps/timeline-cli`

Phase 3 will place Composer files under the existing CLI app:

```txt
apps/timeline-cli/composer.json
apps/timeline-cli/composer.lock
apps/timeline-cli/vendor/
```

The app directory remains `apps/timeline-cli`. Names such as `timeline-log-cli` or a separate Composer CLI app are not used in Phase 3.

This keeps dependencies for the CLI phase separate from future plain PHP web, Laravel, API, or frontend apps.

### Use `nekn3x/timeline-cli` as the Composer package name

`apps/timeline-cli/composer.json` should use:

```json
{
  "name": "nekn3x/timeline-cli",
  "type": "project",
  "autoload": {
    "psr-4": {
      "TimelineCli\\": "src/"
    }
  }
}
```

The Composer package name follows the app directory name. The PSR-4 namespace is `TimelineCli\\`.

## Implementation Plan

Phase 3 should replace the single-file CLI incrementally:

1. Add `composer.json`, PSR-4 autoloading, and `src/` while keeping the existing CLI behavior intact.
2. Add domain objects and typed exceptions.
3. Add JSON repositories and replace direct load/save functions.
4. Add application services and move command behavior behind them.
5. Add `ConsoleApplication` and move dispatch, parsing, stdout, and stderr handling into it.
6. Add minimal `.env` configuration and logging.
7. Run the Phase 3 local verification against the Phase 2-compatible command contract.

During the migration, existing functions in `bin/app.php` may be kept temporarily. By the end of Phase 3, `bin/app.php` should be a thin bootstrap that loads Composer autoloading, builds the application, runs `ConsoleApplication`, and exits with its returned status code.

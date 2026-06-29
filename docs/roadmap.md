# PHP to Laravel Learning Roadmap: Timeline Logging

This roadmap uses one domain model across multiple learning applications. The goal is not to evolve one codebase from CLI to Laravel, but to reimplement the same domain model through different interfaces and frameworks.

## Product Direction

The application is a personal timeline logging tool. Users place lightweight records on a timeline, similar to committing actions and thoughts to a personal history.

The core domain model is:

- **Log Entry**: a lightweight record on the user's timeline.
- **Point Entry**: a Log Entry with only a recorded time.
- **Span Entry**: a Log Entry with a recorded time and an end time.
- **Context**: a branch-like stream such as work, private, a client project, or a learning project.
- **Current Context**: the default Context for new Log Entries.
- **Log Category**: a user-defined classification for Log Entries.
- **Archived Log Entry**: a Log Entry hidden from active review without being deleted.

Important constraints:

- A Log Entry has `title` and optional `content`, like a commit message.
- A Log Entry has no built-in `type`.
- A Log Entry starts as a point and becomes readable as a span when `ended_at` is added.
- Context is optional and can be overridden per Log Entry.
- Contexts are flat.
- Log Categories are many-to-many.
- Reflection is a user behavior, not an initial domain entity.
- Timeline views are read models, not domain entities.
- Event Sourcing is deferred.

## Repository Shape

```txt
CONTEXT.md
docs/
  adr/
  roadmap.md
apps/
  timeline-cli/
  timeline-plain-web/
  timeline-laravel-inertia/
  timeline-laravel-api/
  timeline-next/
```

## Phase 1: PHP CLI with JSON Persistence

Build a small CLI app that persists data to JSON files.

Example commands:

```bash
php bin/app.php log:add "Laravelの認可を調べる"
php bin/app.php log:add "PolicyとGateの違いが気になる" --content "後で公式を確認する"
php bin/app.php log:end 1
php bin/app.php log:list
php bin/app.php log:today

php bin/app.php context:add learn-laravel
php bin/app.php context:list
php bin/app.php context:switch learn-laravel
php bin/app.php context:clear
php bin/app.php log:add "個人アプリのUI案" --context private
php bin/app.php log:add "買い物を思い出した" --no-context
```

Scope:

- Log Entry with `title`, optional `content`, `recorded_at`, optional `ended_at`.
- Context and Current Context.
- Context override per Log Entry.
- JSON persistence.
- Missing file initialization.
- Broken JSON detection.
- No categories.
- No archive.

Learn:

- PHP environment setup
- CLI arguments
- stdout/stderr
- variables and arrays
- associative arrays
- functions
- conditionals
- loops
- file I/O
- JSON encode/decode
- simple date handling
- basic error messages

## Phase 2: PHP CLI Hardening

Make the plain PHP CLI more robust before introducing OOP and Composer.

Scope:

- improve validation and error messages
- support editing Log Entries
- add explicit recorded/end time options
- add filters by date and Context
- Export Log Entries to CSV.

Out of scope:

- physical Log Entry deletion
- Archived Log Entry workflows
- Log Categories
- SQLite or PDO
- Composer or PSR-4 autoloading
- OOP restructuring

Learn:

- CSV output
- exceptions
- validation
- null checks
- date formatting
- command option parsing
- directory layout

## Phase 3: PHP OOP and Composer CLI

Restructure the CLI app with classes and Composer.

Scope:

- `LogEntry`
- `Context`
- `LogEntryRepository`
- `ContextRepository`
- `JsonLogEntryRepository`
- `JsonContextRepository`
- `CurrentContextStore`
- `LogEntryService`
- `ContextService`
- `ConsoleApplication`
- Composer autoload
- `.env`
- logging

Learn:

- classes and objects
- constructors
- visibility
- namespaces
- PSR-4 autoloading
- interfaces
- dependency injection basics
- Composer
- external libraries
- logging

## Phase 4: SQLite and PDO CLI

Replace JSON storage with SQLite.

Scope:

- `log_entries`
- `contexts`
- `log_entry_context_id`
- Current Context persistence
- ID-based `log:end`
- update and delete commands
- filtering by date and Context

Add Log Categories here:

- `log_categories`
- `log_entry_log_category`
- multiple categories per Log Entry

Learn:

- SQL
- SQLite
- PDO
- prepared statements
- CRUD
- joins
- many-to-many relationships
- indexes
- transactions

## Phase 5: Quality Tooling

Add automated quality checks to the PHP CLI app.

Scope:

- Unit tests for Log Entry behavior.
- Repository tests.
- CLI command tests where practical.
- Static analysis.
- Formatting.
- GitHub Actions.

Example tests:

- title is required.
- `content` may be null.
- `ended_at` may be null.
- `ended_at` cannot be before `recorded_at`.
- Context override beats Current Context.
- `--no-context` clears the default for that entry only.
- broken JSON raises an error.
- unknown ID for `log:end` fails clearly.

Learn:

- PHPUnit or Pest
- boundary tests
- exception tests
- PHPStan or Psalm
- PHP-CS-Fixer or Pint
- CI
- testable design

## Phase 6: Plain PHP Web App

Reimplement the domain as a plain PHP web app.

Scope:

- Log Entry list
- Log Entry create/edit/end/delete
- Context list/create/switch/clear
- Context override on Log Entry creation
- date filters
- simple timeline read model
- HTML escaping
- CSRF protection

Learn:

- HTTP
- GET and POST
- forms
- request/response
- routing
- views
- sessions
- CSRF
- XSS protection
- MVC shape
- why Laravel exists

## Phase 7: Laravel + React + Inertia

Build the main Laravel web app with React and Inertia.

Scope:

- Log Entry CRUD
- add end time by ID
- Context CRUD
- Current Context
- Context override
- Log Categories
- archive/unarchive
- filters by date, Context, and category
- paginated timeline
- validation errors
- flash messages

Stack:

- Laravel
- React
- TypeScript
- Inertia
- Vite
- Tailwind CSS
- SQLite or MySQL
- Pest
- Laravel Pint
- Larastan

Learn:

- routes
- controllers
- requests
- validation
- migrations
- Eloquent
- relationships
- factories
- seeders
- pagination
- sessions
- Inertia responses
- React pages and components

## Phase 8: Laravel Auth and Authorization

Make the app multi-user.

Scope:

- register/login/logout
- each user owns their Log Entries, Contexts, and Categories
- users cannot edit other users' records
- policies for Log Entry, Context, and Category
- authenticated feature tests

Learn:

- Laravel starter kits
- authentication
- authorization
- middleware
- policies
- gates
- 401/403/404 behavior
- authenticated tests

## Phase 9: Timeline Read Models and Review Workflows

Improve the experience of looking back over records without introducing Reflection as a domain entity.

Scope:

- today view
- week view
- month view
- Context-specific history
- category filters
- active vs archived records
- duration summaries for Span Entries
- counts for Point Entries
- export CSV

Learn:

- query scopes
- aggregate queries
- date ranges
- read models
- eager loading
- N+1 detection
- indexes

## Phase 10: Laravel API

Expose the Laravel app as an API.

Scope:

- Log Entry API
- Context API
- Current Context API
- Log Category API
- archive/unarchive API
- filtering, sorting, pagination
- validation errors
- API resources
- Sanctum

Learn:

- REST API design
- JSON responses
- status codes
- API Resources
- Resource Collections
- Sanctum
- CORS
- API feature tests

## Phase 11: Next.js Frontend

Build a separate frontend against the Laravel API.

Scope:

- timeline view
- create Log Entry
- end Log Entry by ID
- switch Current Context
- override Context per entry
- category filters
- archive/unarchive
- auth state

Stack:

- Next.js
- React
- TypeScript
- Tailwind CSS
- TanStack Query
- Zod
- Playwright

Learn:

- separated frontend/backend
- API integration
- cookie auth
- CSRF
- CORS
- Server Components vs Client Components
- frontend validation
- E2E tests

## Phase 12: Queue, Scheduler, Mail, and Export

Add background workflows that support review and export.

Scope:

- CSV export job
- export completion notification
- weekly timeline digest email
- scheduled weekly summary generation
- failed job retry
- chunked exports

Learn:

- jobs
- queues
- workers
- notifications
- mail
- scheduler
- custom Artisan commands
- batch processing
- locks
- Horizon

## Phase 13: Events and Listeners

Use Laravel events for side effects, without adopting Event Sourcing.

Scope:

- `LogEntryCreated`
- `LogEntryEnded`
- `LogEntryArchived`
- `ContextSwitched`
- listeners for logs, notifications, and projections
- queued listeners

Learn:

- Laravel events
- listeners
- observers
- side-effect separation
- async listeners
- difference between events and Event Sourcing

## Phase 14: OpenAPI, E2E, and CI

Harden the API and delivery pipeline.

Scope:

- OpenAPI spec
- Swagger UI
- API schemas
- error response schema
- API tests
- Playwright E2E tests
- GitHub Actions for backend and frontend

Learn:

- OpenAPI
- schema design
- contract thinking
- E2E testing
- CI design
- linting
- formatting
- static analysis

## Phase 15: Design Improvements

Refactor the Laravel app once the real pressure points are visible.

Scope:

- Actions for command-like operations
- Services for report/export behavior
- DTOs for write inputs if useful
- Value Objects for time ranges if useful
- avoid premature Repository abstraction over Eloquent

Learn:

- Laravel service container
- dependency injection
- service providers
- Action classes
- Value Objects
- DTOs
- avoiding Fat Controllers
- avoiding premature DDD ceremony

## Phase 16: Performance

Make the app work with larger histories.

Scope:

- indexes for timeline queries
- category and Context filters
- aggregate query optimization
- pagination
- lazy collections
- caching summaries
- queue heavy exports
- optional Octane experiment

Learn:

- N+1 prevention
- eager loading
- index design
- query plans
- caching
- cache invalidation
- OPcache
- Octane trade-offs

## Phase 17: Optional Event Sourcing Experiment

Build a separate experiment only after the normal CRUD implementations are understood.

Scope:

- `LogEntryRecorded`
- `LogEntryEnded`
- `LogEntryCategorized`
- `LogEntryArchived`
- projections for timeline and summaries

Learn:

- Event Sourcing trade-offs
- projections
- replay
- read models
- why CRUD may still be the better default

## Phase 18: Laravel Package

Extract a small reusable piece only if a real reusable boundary appears.

Possible package candidates:

- timeline duration formatting
- Log Entry date range utilities
- category filtering helpers

Learn:

- package structure
- service providers
- config publishing
- Orchestra Testbench
- package tests

# Start CLI with JSON Persistence

The first CLI application will persist Log Entries, Contexts, and Current Context to JSON files instead of using in-memory storage. This makes the CLI natural to use across separate command invocations while still keeping the first implementation in plain PHP without SQLite, Composer, OOP, or Laravel.

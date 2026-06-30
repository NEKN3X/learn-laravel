# Restructure Timeline CLI in Place

Phase 3 will replace the internals of `apps/timeline-cli` in place instead of creating a parallel Composer-based CLI app. The CLI phases are a single learning line through JSON persistence, OOP and Composer, SQLite, and quality tooling, so keeping one app avoids splitting later Phase 4 and Phase 5 work across competing CLI implementations.

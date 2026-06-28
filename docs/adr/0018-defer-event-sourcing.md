# Defer Event Sourcing

Activity tracking maps naturally to event-like user actions such as starting and ending an activity, but the initial learning applications will store Activity Logs directly with state such as `started_at` and optional `ended_at`. Event Sourcing remains a possible later experiment after the CRUD, Laravel, API, and frontend implementations are understood well enough to compare the trade-offs clearly.

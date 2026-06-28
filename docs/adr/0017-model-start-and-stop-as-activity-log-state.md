# Model Start and Stop as Activity Log State

Starting and stopping an activity will be modeled as state changes on Activity Log rather than as separate Activity Checkpoint concepts. This keeps the initial model focused on activity intervals with `started_at` and `ended_at`, without introducing checkpoint history before it is needed.

# Timeline Logging

This context describes a personal logging application used to place lightweight records on a timeline without interrupting the current activity.

## Language

**Log Entry**:
A lightweight record placed on the user's timeline, with a short title and optional detailed content. A log entry starts as a point in time and can become a span when an end time is added.
_Avoid_: Activity log, quick note, study log, task completion

**Context**:
The work or life stream a log entry belongs to, such as work, private, a client project, or a learning project.
_Avoid_: Branch, category, tag

**Current Context**:
The context selected as the default destination for new log entries, unless a specific entry overrides it.
_Avoid_: Active branch, selected category

**Point Entry**:
A log entry with only a recorded time.
_Avoid_: Quick note, thought log

**Span Entry**:
A log entry with both a recorded time and an end time, making it readable as a duration on the timeline.
_Avoid_: Activity log, task, session

**Recorded Time**:
The timestamp where a log entry is placed on the timeline.
_Avoid_: Created time, scheduled time

**End Time**:
An optional timestamp that turns a point entry into a span entry.
_Avoid_: Completed time, deadline

**Log Category**:
A user-defined classification for log entries, such as idea, question, debugging, learning, observation, or exercise.
_Avoid_: Activity category, quick note category, context

**Archived Log Entry**:
A log entry hidden from active review because it no longer needs regular attention.
_Avoid_: Reviewed entry, completed entry, task

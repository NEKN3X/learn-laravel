# End Log Entries by ID

The CLI should add an end time to a Log Entry by explicit ID rather than implicitly ending the most recent open entry. This avoids ambiguity when users forget to add end times or have multiple point entries that could later become spans.

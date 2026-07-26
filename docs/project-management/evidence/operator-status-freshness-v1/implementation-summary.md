# Implementation summary

- Authorized schedule list and detail operations resolve the opaque last job id
  through the registered scheduled-operation handler.
- A changed status, result size or sanitized failure reason is persisted into
  Scheduler's projection before it is returned.
- A removed historical Queue job does not make the schedule unreadable; the
  last durable projection remains available.
- No route, schema, recurrence, dispatch, authorization or Audit contract changed.

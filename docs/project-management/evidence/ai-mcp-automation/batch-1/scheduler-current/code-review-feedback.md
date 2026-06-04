# Code Review Feedback

Accepted clarifications:

- Schedule descriptors must include timezone, trigger, queue, lock and
  missed-run policy refs.
- Trigger decisions store due timestamps as UTC and keep project timezone
  explicit for human scheduling semantics.
- Signed/web tick is degraded and must not select arbitrary work.
- Lock decisions block duplicate or stale runs.
- Dispatch plans describe future Queue/Core Operation Runtime handoff, not
  direct execution.

Deferred to implementation planning:

- `scheduler.yaml` schema examples and descriptor validation fixtures.
- Trigger expression parser and timezone policy fixtures.
- Lock persistence and stale lock recovery policy.
- Signed web tick hardening: signature, nonce, TTL, rate limit and admin warning.
- Queue dispatch handoff DTOs and failure visibility.

No canonical graph/spec promotion is required from this batch.

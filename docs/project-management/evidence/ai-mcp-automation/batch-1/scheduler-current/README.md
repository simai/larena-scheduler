# Larena Scheduler Batch 1 Evidence

This evidence package records the interface-first contract skeleton batch for
`larena/scheduler`.

Scope:

- Schedule descriptor contract.
- Trigger/due-run decision contract.
- Missed-run policy decision contract.
- Lock and idempotency decision contract.
- Dispatch plan contract for queue/core handoff.
- Scheduler gateway and diagnostics contracts.

Out of scope:

- Cron runner runtime.
- Signed web tick endpoint runtime.
- Queue dispatch runtime.
- Schedule persistence runtime.
- Persistence, routes, migrations, admin UI or production service providers.

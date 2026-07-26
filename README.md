# Larena Scheduler

Shared system package for declarative schedules, triggers, missed-run policy, locks, queue dispatch and schedule observability across Larena.

The package is being promoted from its accepted contract skeleton to the first
durable runtime slice. It owns persistent schedule descriptors, timezone-aware
recurrence, missed-run decisions, idempotent tick claims, single-flight overlap
protection and a registry that dispatches heavy operations to `larena/queue`.

The first accepted consumer is complete backup creation. Scheduler decides
when work is due; it never captures databases, copies files or executes backup
work inline. SitePack, remote storage, encryption and public signed web ticks
are outside this slice.

Canonical specifications are in `simai/larena-specs`.

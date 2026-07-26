# Tests

Package verification on 2026-07-26 passed:

- `composer validate --strict`;
- package metadata, syntax lint and PHPStan;
- four package test programs including the focused runtime regression;
- evidence and launch-scope checks.

Root exact-revision regression remains part of parent acceptance.

The focused runtime regression covers a completed backup Queue job whose
schedule is disabled before another due tick, then verifies fresh list and
detail projections.

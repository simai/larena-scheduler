# Tests

Package-local verification on 2026-07-26:

- PHP syntax lint: passed.
- PHPStan level 5 with 1 GiB analysis memory: passed.
- contract and fail-closed unit tests: passed.
- recurrence test: passed.
- runtime SQLite test: passed, including role denial, timezone persistence,
  repeated tick, overlap, missed run, dispatch failure and migration rollback/reapply.

MySQL and sealed-runtime evidence belongs to the root integration acceptance and
is not claimed by this package-local packet.

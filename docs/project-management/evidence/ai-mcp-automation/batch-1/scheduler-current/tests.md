# Tests

Package-local checks required for this batch:

- `composer validate --strict`
- `composer dump-autoload`
- `composer run validate:larena`
- `composer run lint`
- `composer run analyse`
- `composer run test`
- `composer run quality:gate`
- `git diff --check`
- `.githooks/pre-commit`
- `.githooks/pre-push`

Semantic coverage:

- schedule descriptors carry timezone, trigger, queue, lock and missed-run refs;
- due decisions create work only when due;
- UTC timestamps and explicit project timezone are represented;
- web tick is degraded and can be rejected;
- stale locks block dispatch;
- heavy work is represented as dispatch plan, not direct scheduler execution.

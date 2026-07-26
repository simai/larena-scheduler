<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Larena\Access\Contracts\ActorOperationAuthorizer;
use Larena\Access\Exceptions\AccessMutationRejected;
use Larena\Audit\Runtime\AuditEventPipeline;
use Larena\Audit\Runtime\DefaultAuditRedactor;
use Larena\Audit\Runtime\InMemoryAuditSink;
use Larena\Queue\Data\DispatchResult;
use Larena\Queue\Data\QueueJobSnapshot;
use Larena\Queue\Enums\JobStatus;
use Larena\Queue\Enums\QueuePriority;
use Larena\Scheduler\Audit\SchedulerAuditRecorder;
use Larena\Scheduler\Contracts\ScheduledOperationHandler;
use Larena\Scheduler\Contracts\SchedulerClock;
use Larena\Scheduler\Data\ScheduleMutation;
use Larena\Scheduler\Data\ScheduledDispatchContext;
use Larena\Scheduler\Data\SchedulerOperationContext;
use Larena\Scheduler\Enums\MissedRunStrategy;
use Larena\Scheduler\Enums\ScheduleFrequency;
use Larena\Scheduler\Persistence\DatabaseScheduleStore;
use Larena\Scheduler\Runtime\ScheduleRecurrenceCalculator;
use Larena\Scheduler\Runtime\ScheduledOperationRegistry;
use Larena\Scheduler\Services\ScheduleOperations;
use Larena\Scheduler\Services\SchedulerTickService;

require dirname(__DIR__, 2).'/vendor/autoload.php';

function scheduler_runtime_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$databasePath = sys_get_temp_dir().'/larena-scheduler-'.bin2hex(random_bytes(8)).'.sqlite';
touch($databasePath);
$capsule = new Capsule();
$capsule->addConnection(['driver' => 'sqlite', 'database' => $databasePath]);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$application = new Application(dirname(__DIR__, 2));
$application->instance('db', $capsule->getDatabaseManager());
$application->instance('db.schema', $capsule->getConnection()->getSchemaBuilder());
Facade::setFacadeApplication($application);
$migration = require dirname(__DIR__, 2).'/database/migrations/2026_07_26_010000_create_larena_scheduler_tables.php';
$migration->up();

$clock = new class implements SchedulerClock {
    public DateTimeImmutable $value;

    public function __construct()
    {
        $this->value = new DateTimeImmutable('2026-07-26T07:59:00Z');
    }

    public function now(): DateTimeImmutable
    {
        return $this->value;
    }
};
$sink = new InMemoryAuditSink();
$audit = new SchedulerAuditRecorder(
    new AuditEventPipeline(new DefaultAuditRedactor(), [$sink]),
    $clock,
);
$authorizer = new class implements ActorOperationAuthorizer {
    public function assertAllowed(string $actor, string $operation): void
    {
        if ($actor !== 'user:admin' || !str_starts_with($operation, 'scheduler.schedule.')) {
            throw new AccessMutationRejected('access_denied');
        }
    }
};
$recurrence = new ScheduleRecurrenceCalculator();
$store = new DatabaseScheduleStore($capsule->getConnection(), $recurrence);
$operations = new ScheduleOperations($authorizer, $audit, $store, $clock);
$registry = new ScheduledOperationRegistry();
$handler = new class implements ScheduledOperationHandler {
    /** @var array<string, QueueJobSnapshot> */
    public array $jobs = [];
    public bool $failDispatch = false;
    /** @var array<string, string> */
    private array $idempotency = [];

    public function operationRef(): string
    {
        return 'backup.complete.create';
    }

    public function dispatch(ScheduledDispatchContext $context): DispatchResult
    {
        if ($this->failDispatch) {
            throw new RuntimeException('injected_dispatch_failure');
        }
        if (isset($this->idempotency[$context->idempotencyKey])) {
            return new DispatchResult($this->idempotency[$context->idempotencyKey], true);
        }
        $jobId = str_pad((string) (count($this->jobs) + 1), 32, '0', STR_PAD_LEFT);
        $this->idempotency[$context->idempotencyKey] = $jobId;
        $this->jobs[$jobId] = $this->snapshot($jobId, JobStatus::Queued);

        return new DispatchResult($jobId, false);
    }

    public function status(string $jobId): QueueJobSnapshot
    {
        return $this->jobs[$jobId] ?? throw new RuntimeException('job_not_found');
    }

    public function setStatus(string $jobId, JobStatus $status): void
    {
        $this->jobs[$jobId] = $this->snapshot($jobId, $status);
    }

    private function snapshot(string $jobId, JobStatus $status): QueueJobSnapshot
    {
        $now = new DateTimeImmutable('2026-07-26T08:00:00Z');

        return new QueueJobSnapshot(
            $jobId,
            'backup.create_complete',
            'test-handler',
            [],
            hash('sha256', '{}'),
            'test-idempotency-'.$jobId,
            'scheduler-test',
            QueuePriority::High,
            $status,
            0,
            3,
            3600,
            30,
            60,
            $now,
            null,
            null,
            null,
            $status === JobStatus::Failed ? 'test_failure' : null,
            $status === JobStatus::Completed ? ['size_bytes' => 1234] : [],
            $now,
            $now,
        );
    }
};
$registry->register($handler);
$tick = new SchedulerTickService($store, $registry, $recurrence, $clock, $audit, 100, 60);
$admin = new SchedulerOperationContext('user:admin', 'scheduler-runtime-test');

try {
    foreach (['user:editor', 'user:reader'] as $actor) {
        try {
            $operations->create(
                new SchedulerOperationContext($actor, 'scheduler-denied-test'),
                new ScheduleMutation('Denied backup', 'Europe/Istanbul', ScheduleFrequency::Daily, '11:00', null, MissedRunStrategy::Skip, 7),
            );
            throw new RuntimeException('Forbidden role created a schedule.');
        } catch (AccessMutationRejected) {
        }
    }
    scheduler_runtime_assert($capsule->getConnection()->table('larena_scheduler_schedules')->count() === 0, 'Denied schedule mutations must have no side effects.');

    $schedule = $operations->create(
        $admin,
        new ScheduleMutation('Daily complete backup', 'Europe/Istanbul', ScheduleFrequency::Daily, '11:00', null, MissedRunStrategy::CatchUpSingle, 5),
    );
    scheduler_runtime_assert($schedule->nextRunAt->format(DATE_ATOM) === '2026-07-26T08:00:00+00:00', 'Timezone-aware next run must be persisted in UTC.');
    $restartedStore = new DatabaseScheduleStore($capsule->getConnection(), new ScheduleRecurrenceCalculator());
    scheduler_runtime_assert($restartedStore->require($schedule->id)->retentionCount === 5, 'Schedule must survive service restart.');

    $clock->value = new DateTimeImmutable('2026-07-26T08:00:00Z');
    $first = $tick->tick('tick-runtime-1');
    scheduler_runtime_assert(count($first->decisions) === 1 && $first->decisions[0]['decision'] === 'dispatched', 'Due schedule must dispatch exactly one Queue job.');
    $sameTick = $tick->tick('tick-runtime-1-repeat');
    scheduler_runtime_assert($sameTick->decisions === [] && count($handler->jobs) === 1, 'Repeated tick must not duplicate work.');

    $jobId = (string) $first->decisions[0]['job_id'];
    $handler->setStatus($jobId, JobStatus::Running);
    $clock->value = new DateTimeImmutable('2026-07-27T08:00:00Z');
    $overlap = $tick->tick('tick-runtime-overlap');
    scheduler_runtime_assert(count($overlap->decisions) === 1 && $overlap->decisions[0]['decision'] === 'overlap', 'Single-flight must reject overlap while previous job is active.');

    $handler->setStatus($jobId, JobStatus::Completed);
    $clock->value = new DateTimeImmutable('2026-07-28T08:00:00Z');
    $second = $tick->tick('tick-runtime-2');
    scheduler_runtime_assert(count($second->decisions) === 1 && $second->decisions[0]['decision'] === 'dispatched', 'Terminal previous job must allow the next period.');
    scheduler_runtime_assert($operations->find($admin, $schedule->id)->lastResultSizeBytes === 1234, 'Last result size must be projected into schedule diagnostics.');
    scheduler_runtime_assert($capsule->getConnection()->table('larena_scheduler_runs')->count() === 3, 'Every due evaluation must be durably recorded once.');

    $operations->disable($admin, $schedule->id);
    scheduler_runtime_assert($operations->find($admin, $schedule->id)->state->value === 'disabled', 'Admin must disable a schedule.');
    $operations->enable($admin, $schedule->id);
    scheduler_runtime_assert($operations->find($admin, $schedule->id)->state->value === 'enabled', 'Admin must re-enable a schedule.');

    $operations->disable($admin, $schedule->id);
    $missed = $operations->create(
        $admin,
        new ScheduleMutation('Missed backup', 'Europe/Istanbul', ScheduleFrequency::Daily, '11:00', null, MissedRunStrategy::Skip, 3),
    );
    $clock->value = new DateTimeImmutable('2026-07-29T10:00:00Z');
    $missedReport = $tick->tick('tick-runtime-missed');
    scheduler_runtime_assert(count($missedReport->decisions) === 1 && $missedReport->decisions[0]['decision'] === 'skipped', 'Missed-run skip policy must advance without dispatching a burst.');
    scheduler_runtime_assert($operations->find($admin, $missed->id)->lastDueAt !== null, 'Missed-run decision must persist diagnostics.');

    $failing = $operations->create(
        $admin,
        new ScheduleMutation('Failing backup', 'UTC', ScheduleFrequency::Hourly, null, null, MissedRunStrategy::CatchUpSingle, 2),
    );
    $handler->failDispatch = true;
    $clock->value = new DateTimeImmutable('2026-07-29T11:00:00Z');
    $failedReport = $tick->tick('tick-runtime-failed');
    scheduler_runtime_assert(
        array_filter($failedReport->decisions, static fn (array $decision): bool => $decision['schedule_id'] === $failing->id && $decision['decision'] === 'failed') !== [],
        'Dispatch failure must be persisted and returned without rolling back diagnostics.',
    );
    $handler->failDispatch = false;
    scheduler_runtime_assert(count($sink->events()) >= 15, 'Schedule decisions and denied operations must be audited.');

    $migration->down();
    scheduler_runtime_assert(!$capsule->getConnection()->getSchemaBuilder()->hasTable('larena_scheduler_schedules'), 'Scheduler rollback must remove package tables.');
    $migration->up();
    scheduler_runtime_assert($capsule->getConnection()->getSchemaBuilder()->hasTable('larena_scheduler_schedules'), 'Scheduler migrations must reapply cleanly.');
} finally {
    try {
        $migration->down();
    } catch (Throwable) {
    }
    Facade::clearResolvedInstances();
    @unlink($databasePath);
}

echo "SchedulerRuntimeTest passed.\n";

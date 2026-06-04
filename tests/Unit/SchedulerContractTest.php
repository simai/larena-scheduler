<?php

declare(strict_types=1);

use Larena\Scheduler\Contracts\DispatchPlan;
use Larena\Scheduler\Contracts\LockDecision;
use Larena\Scheduler\Contracts\ScheduleDescriptor;
use Larena\Scheduler\Contracts\TriggerDecision;
use Larena\Scheduler\Enums\MissedRunStrategy;
use Larena\Scheduler\Enums\ScheduleDecisionStatus;
use Larena\Scheduler\Enums\ScheduleState;
use Larena\Scheduler\Enums\TickSource;

$descriptor = new ScheduleDescriptor(
    id: 'search.reindex',
    package: 'larena/search',
    operationRef: 'search.reindex',
    state: ScheduleState::Enabled,
    timezone: 'Europe/Istanbul',
    trigger: ['type' => 'cron', 'expression' => '*/5 * * * *'],
    queueProfileRef: 'maintenance',
    lockPolicyRef: 'single-flight',
    missedRunStrategy: MissedRunStrategy::CatchUpSingle,
);

assert($descriptor->enabled() === true);

$decision = new TriggerDecision(
    status: ScheduleDecisionStatus::Due,
    scheduleId: $descriptor->id,
    dueAtUtc: '2026-06-04T11:00:00Z',
    projectTimezone: $descriptor->timezone,
    tickSource: TickSource::Cron,
);

assert($decision->canCreateWork() === true);

$lock = new LockDecision(true, 'scheduler:search.reindex', 'idem-1', false);
assert($lock->allowsDispatch() === true);

$plan = new DispatchPlan($descriptor->id, $descriptor->operationRef, $descriptor->queueProfileRef, 'corr-1');
assert($plan->heavyWork === true);

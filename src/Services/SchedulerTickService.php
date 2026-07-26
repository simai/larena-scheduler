<?php

declare(strict_types=1);

namespace Larena\Scheduler\Services;

use DateTimeImmutable;
use Larena\Scheduler\Audit\SchedulerAuditRecorder;
use Larena\Scheduler\Contracts\SchedulerClock;
use Larena\Scheduler\Data\ScheduledDispatchContext;
use Larena\Scheduler\Data\SchedulerOperationContext;
use Larena\Scheduler\Data\SchedulerTickReport;
use Larena\Scheduler\Data\ScheduleSnapshot;
use Larena\Scheduler\Enums\MissedRunStrategy;
use Larena\Scheduler\Enums\ScheduleState;
use Larena\Scheduler\Persistence\DatabaseScheduleStore;
use Larena\Scheduler\Runtime\ScheduledOperationRegistry;
use Larena\Scheduler\Runtime\ScheduleRecurrenceCalculator;
use Throwable;

final readonly class SchedulerTickService
{
    public function __construct(
        private DatabaseScheduleStore $store,
        private ScheduledOperationRegistry $operations,
        private ScheduleRecurrenceCalculator $recurrence,
        private SchedulerClock $clock,
        private SchedulerAuditRecorder $audit,
        private int $maxSchedules = 100,
        private int $missedGraceSeconds = 60,
    ) {
    }

    public function tick(?string $tickId = null): SchedulerTickReport
    {
        $now = $this->clock->now();
        $tickId ??= 'tick-'.bin2hex(random_bytes(12));
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9:._-]{2,63}\z/D', $tickId) !== 1) {
            throw new \InvalidArgumentException('scheduler_tick_id_invalid');
        }
        $decisions = [];
        foreach ($this->store->dueIds($now, $this->maxSchedules) as $scheduleId) {
            $decision = $this->process($scheduleId, $tickId, $now);
            if ($decision !== null) {
                $decisions[] = $decision;
            }
        }

        return new SchedulerTickReport($tickId, $decisions);
    }

    /** @return array{schedule_id:string,decision:string,job_id:?string,reason_code:?string}|null */
    private function process(string $scheduleId, string $tickId, DateTimeImmutable $now): ?array
    {
        return $this->store->withLocked($scheduleId, function (ScheduleSnapshot $schedule) use ($tickId, $now): ?array {
            if ($schedule->state !== ScheduleState::Enabled || $schedule->nextRunAt > $now) {
                return null;
            }
            $handler = $this->operations->handler($schedule->operationRef);
            $dueAt = $schedule->nextRunAt;
            $correlation = 'sch:'.substr(hash('sha256', $schedule->id.'|'.$dueAt->format('U.u')), 0, 32);
            $context = new SchedulerOperationContext('system:scheduler', $correlation);

            if (!$this->store->claimRun($schedule, $dueAt, $tickId, $correlation, $now)) {
                $this->audit->record($context, 'tick', 'evaluate', 'duplicate', ['schedule_id' => $schedule->id]);

                return $this->decision($schedule->id, 'duplicate', null, 'duplicate_tick');
            }

            if ($schedule->lastJobId !== null) {
                $job = $handler->status($schedule->lastJobId);
                $size = isset($job->result['size_bytes']) ? (int) $job->result['size_bytes'] : null;
                $this->store->refreshJob($schedule->id, $job->status->value, $size, $job->failureReason, $now);
                if (!$job->isTerminal()) {
                    $this->store->recordDecision(
                        $schedule,
                        $dueAt,
                        $this->recurrence->nextAfter($schedule, $now),
                        'overlap',
                        null,
                        'previous_job_active',
                        $now,
                    );
                    $this->audit->record($context, 'tick', 'evaluate', 'overlap', [
                        'schedule_id' => $schedule->id,
                        'job_id' => $job->id,
                        'reason_code' => 'previous_job_active',
                    ]);

                    return $this->decision($schedule->id, 'overlap', $job->id, 'previous_job_active');
                }
            }

            $missed = ($now->getTimestamp() - $dueAt->getTimestamp()) > $this->missedGraceSeconds;
            if ($missed && $schedule->missedRunStrategy !== MissedRunStrategy::CatchUpSingle) {
                $decision = match ($schedule->missedRunStrategy) {
                    MissedRunStrategy::Skip => 'skipped',
                    MissedRunStrategy::WarnOnly => 'warned',
                    MissedRunStrategy::RejectBurst => 'rejected',
                };
                $reason = 'missed_'.$schedule->missedRunStrategy->value;
                $this->store->recordDecision(
                    $schedule,
                    $dueAt,
                    $this->recurrence->nextAfter($schedule, $now),
                    $decision,
                    null,
                    $reason,
                    $now,
                );
                $this->audit->record($context, 'tick', 'evaluate', 'skipped', [
                    'schedule_id' => $schedule->id,
                    'reason_code' => $reason,
                ]);

                return $this->decision($schedule->id, $decision, null, $reason);
            }

            try {
                $dispatch = $handler->dispatch(new ScheduledDispatchContext(
                    $schedule,
                    $dueAt,
                    'scheduler:'.$schedule->id.':'.$dueAt->format('YmdHis'),
                    $correlation,
                ));
                $decision = $dispatch->duplicate ? 'duplicate' : 'dispatched';
                $this->store->recordDecision(
                    $schedule,
                    $dueAt,
                    $this->recurrence->nextAfter($schedule, $now),
                    $decision,
                    $dispatch->jobId,
                    null,
                    $now,
                );
                $this->audit->record($context, 'tick', 'dispatch', $dispatch->duplicate ? 'duplicate' : 'succeeded', [
                    'schedule_id' => $schedule->id,
                    'job_id' => $dispatch->jobId,
                ]);

                return $this->decision($schedule->id, $decision, $dispatch->jobId, null);
            } catch (Throwable) {
                $this->store->recordDecision(
                    $schedule,
                    $dueAt,
                    $this->recurrence->nextAfter($schedule, $now),
                    'failed',
                    null,
                    'scheduled_dispatch_failed',
                    $now,
                );
                $this->audit->record($context, 'tick', 'dispatch', 'failed', [
                    'schedule_id' => $schedule->id,
                    'reason_code' => 'scheduled_dispatch_failed',
                ]);
                return $this->decision($schedule->id, 'failed', null, 'scheduled_dispatch_failed');
            }
        });
    }

    /** @return array{schedule_id:string,decision:string,job_id:?string,reason_code:?string} */
    private function decision(string $id, string $decision, ?string $jobId, ?string $reason): array
    {
        return ['schedule_id' => $id, 'decision' => $decision, 'job_id' => $jobId, 'reason_code' => $reason];
    }
}

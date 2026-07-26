<?php

declare(strict_types=1);

namespace Larena\Scheduler\Services;

use Larena\Access\Contracts\ActorOperationAuthorizer;
use Larena\Access\Exceptions\AccessMutationRejected;
use Larena\Scheduler\Audit\SchedulerAuditRecorder;
use Larena\Scheduler\Contracts\SchedulerClock;
use Larena\Scheduler\Data\ScheduleMutation;
use Larena\Scheduler\Data\ScheduleSnapshot;
use Larena\Scheduler\Data\SchedulerOperationContext;
use Larena\Scheduler\Enums\ScheduleState;
use Larena\Scheduler\Persistence\DatabaseScheduleStore;
use Throwable;

final readonly class ScheduleOperations
{
    public const BACKUP_OPERATION = 'backup.complete.create';

    public function __construct(
        private ActorOperationAuthorizer $authorizer,
        private SchedulerAuditRecorder $audit,
        private DatabaseScheduleStore $store,
        private SchedulerClock $clock,
    ) {
    }

    /** @return list<ScheduleSnapshot> */
    public function all(SchedulerOperationContext $context): array
    {
        $this->authorize($context, 'list');
        $this->audit->record($context, 'schedule', 'list', 'requested');
        try {
            $schedules = $this->store->all(self::BACKUP_OPERATION);
            $this->audit->record($context, 'schedule', 'list', 'succeeded', [
                'result_count' => count($schedules),
            ]);

            return $schedules;
        } catch (Throwable $exception) {
            $this->audit->record($context, 'schedule', 'list', 'failed', [
                'reason_code' => 'scheduler_schedule_operation_failed',
            ]);
            throw $exception;
        }
    }

    public function find(SchedulerOperationContext $context, string $id): ScheduleSnapshot
    {
        $this->authorize($context, 'view', $id);
        $this->audit->record($context, 'schedule', 'view', 'requested', ['schedule_id' => $id]);
        try {
            $schedule = $this->requireBackupSchedule($id);
            $this->audit->record($context, 'schedule', 'view', 'succeeded', ['schedule_id' => $id]);

            return $schedule;
        } catch (Throwable $exception) {
            $this->audit->record($context, 'schedule', 'view', 'failed', [
                'schedule_id' => $id,
                'reason_code' => 'scheduler_schedule_operation_failed',
            ]);
            throw $exception;
        }
    }

    public function create(SchedulerOperationContext $context, ScheduleMutation $mutation): ScheduleSnapshot
    {
        return $this->mutate($context, 'create', null, fn (): ScheduleSnapshot => $this->store->create(
            $mutation,
            self::BACKUP_OPERATION,
            $context->actorRef,
            $this->clock->now(),
        ));
    }

    public function update(
        SchedulerOperationContext $context,
        string $id,
        ScheduleMutation $mutation,
    ): ScheduleSnapshot {
        return $this->mutate($context, 'update', $id, function () use ($id, $mutation): ScheduleSnapshot {
            $this->requireBackupSchedule($id);

            return $this->store->update($id, $mutation, $this->clock->now());
        });
    }

    public function enable(SchedulerOperationContext $context, string $id): ScheduleSnapshot
    {
        return $this->state($context, $id, ScheduleState::Enabled, 'enable');
    }

    public function disable(SchedulerOperationContext $context, string $id): ScheduleSnapshot
    {
        return $this->state($context, $id, ScheduleState::Disabled, 'disable');
    }

    private function state(
        SchedulerOperationContext $context,
        string $id,
        ScheduleState $state,
        string $operation,
    ): ScheduleSnapshot {
        return $this->mutate($context, $operation, $id, function () use ($id, $state): ScheduleSnapshot {
            $this->requireBackupSchedule($id);

            return $this->store->setState($id, $state, $this->clock->now());
        });
    }

    /** @param callable():ScheduleSnapshot $callback */
    private function mutate(
        SchedulerOperationContext $context,
        string $operation,
        ?string $id,
        callable $callback,
    ): ScheduleSnapshot {
        $this->authorize($context, $operation, $id);
        $this->audit->record($context, 'schedule', $operation, 'requested', ['schedule_id' => $id]);
        try {
            $schedule = $callback();
            $this->audit->record($context, 'schedule', $operation, 'succeeded', [
                'schedule_id' => $schedule->id,
                'state' => $schedule->state->value,
                'frequency' => $schedule->frequency->value,
                'retention_count' => $schedule->retentionCount,
            ]);

            return $schedule;
        } catch (Throwable $exception) {
            $this->audit->record($context, 'schedule', $operation, 'failed', [
                'schedule_id' => $id,
                'reason_code' => 'scheduler_schedule_operation_failed',
            ]);
            throw $exception;
        }
    }

    private function authorize(
        SchedulerOperationContext $context,
        string $operation,
        ?string $id = null,
    ): void {
        try {
            $this->authorizer->assertAllowed($context->actorRef, 'scheduler.schedule.'.$operation);
        } catch (AccessMutationRejected $exception) {
            $this->audit->record($context, 'schedule', $operation, 'denied', [
                'schedule_id' => $id,
                'reason_code' => 'scheduler_access_denied',
            ]);
            throw $exception;
        }
    }

    private function requireBackupSchedule(string $id): ScheduleSnapshot
    {
        $schedule = $this->store->require($id);
        if ($schedule->operationRef !== self::BACKUP_OPERATION) {
            throw new \RuntimeException('scheduler_schedule_not_found');
        }

        return $schedule;
    }
}

<?php

declare(strict_types=1);

namespace Larena\Scheduler\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Larena\Scheduler\Data\ScheduleMutation;
use Larena\Scheduler\Data\ScheduleSnapshot;
use Larena\Scheduler\Enums\MissedRunStrategy;
use Larena\Scheduler\Enums\ScheduleFrequency;
use Larena\Scheduler\Enums\ScheduleState;
use Larena\Scheduler\Runtime\ScheduleRecurrenceCalculator;

final readonly class DatabaseScheduleStore
{
    private const DATE_FORMAT = 'Y-m-d H:i:s.u';

    public function __construct(
        private ConnectionInterface $database,
        private ScheduleRecurrenceCalculator $recurrence,
    ) {
    }

    public function create(
        ScheduleMutation $mutation,
        string $operationRef,
        string $actorRef,
        DateTimeImmutable $now,
    ): ScheduleSnapshot {
        return $this->database->transaction(function () use ($mutation, $operationRef, $actorRef, $now): ScheduleSnapshot {
            $id = bin2hex(random_bytes(16));
            $timestamp = $this->date($now);
            $this->database->table('larena_scheduler_schedules')->insert([
                'id' => $id,
                'name' => $mutation->name,
                'operation_ref' => $operationRef,
                'actor_ref' => $actorRef,
                'timezone' => $mutation->timezone,
                'frequency' => $mutation->frequency->value,
                'local_time' => $mutation->localTime,
                'weekday' => $mutation->weekday,
                'missed_run_strategy' => $mutation->missedRunStrategy->value,
                'retention_count' => $mutation->retentionCount,
                'state' => ScheduleState::Enabled->value,
                'next_run_at' => $this->date($this->recurrence->firstAfter($mutation, $now)),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            return $this->require($id);
        });
    }

    public function update(string $id, ScheduleMutation $mutation, DateTimeImmutable $now): ScheduleSnapshot
    {
        return $this->database->transaction(function () use ($id, $mutation, $now): ScheduleSnapshot {
            $this->lock($id);
            $updated = $this->database->table('larena_scheduler_schedules')->where('id', $id)->update([
                'name' => $mutation->name,
                'timezone' => $mutation->timezone,
                'frequency' => $mutation->frequency->value,
                'local_time' => $mutation->localTime,
                'weekday' => $mutation->weekday,
                'missed_run_strategy' => $mutation->missedRunStrategy->value,
                'retention_count' => $mutation->retentionCount,
                'next_run_at' => $this->date($this->recurrence->firstAfter($mutation, $now)),
                'updated_at' => $this->date($now),
            ]);
            if ($updated !== 1) {
                throw new \RuntimeException('scheduler_schedule_update_failed');
            }

            return $this->require($id);
        });
    }

    public function setState(string $id, ScheduleState $state, DateTimeImmutable $now): ScheduleSnapshot
    {
        return $this->database->transaction(function () use ($id, $state, $now): ScheduleSnapshot {
            $current = $this->lock($id);
            $values = ['state' => $state->value, 'updated_at' => $this->date($now)];
            if ($state === ScheduleState::Enabled) {
                $values['next_run_at'] = $this->date($this->recurrence->nextAfter($current, $now));
            }
            $this->database->table('larena_scheduler_schedules')->where('id', $id)->update($values);

            return $this->require($id);
        });
    }

    /** @return list<ScheduleSnapshot> */
    public function all(string $operationRef): array
    {
        return array_map(
            fn (object $row): ScheduleSnapshot => $this->snapshot($row),
            $this->database->table('larena_scheduler_schedules')
                ->where('operation_ref', $operationRef)->orderBy('created_at')->get()->all(),
        );
    }

    public function find(string $id): ?ScheduleSnapshot
    {
        $row = $this->database->table('larena_scheduler_schedules')->where('id', $id)->first();

        return $row === null ? null : $this->snapshot($row);
    }

    public function require(string $id): ScheduleSnapshot
    {
        return $this->find($id) ?? throw new \RuntimeException('scheduler_schedule_not_found');
    }

    /** @return list<string> */
    public function dueIds(DateTimeImmutable $now, int $limit): array
    {
        return array_map(
            static fn (object $row): string => (string) $row->id,
            $this->database->table('larena_scheduler_schedules')
                ->where('state', ScheduleState::Enabled->value)
                ->where('next_run_at', '<=', $this->date($now))
                ->orderBy('next_run_at')->orderBy('id')->limit($limit)->get(['id'])->all(),
        );
    }

    /**
     * @template T
     * @param callable(ScheduleSnapshot): T $callback
     * @return T
     */
    public function withLocked(string $id, callable $callback): mixed
    {
        return $this->database->transaction(fn (): mixed => $callback($this->lock($id)), 3);
    }

    public function claimRun(
        ScheduleSnapshot $schedule,
        DateTimeImmutable $dueAt,
        string $tickId,
        string $correlationId,
        DateTimeImmutable $now,
    ): bool {
        return $this->database->table('larena_scheduler_runs')->insertOrIgnore([
            'schedule_id' => $schedule->id,
            'due_at' => $this->date($dueAt),
            'decision' => 'claimed',
            'tick_id' => $tickId,
            'correlation_id' => $correlationId,
            'created_at' => $this->date($now),
        ]) === 1;
    }

    public function recordDecision(
        ScheduleSnapshot $schedule,
        DateTimeImmutable $dueAt,
        DateTimeImmutable $nextRunAt,
        string $decision,
        ?string $jobId,
        ?string $reasonCode,
        DateTimeImmutable $now,
    ): void {
        $this->database->table('larena_scheduler_runs')
            ->where('schedule_id', $schedule->id)->where('due_at', $this->date($dueAt))->update([
                'decision' => $decision,
                'job_id' => $jobId,
                'reason_code' => $reasonCode,
            ]);
        $values = [
            'next_run_at' => $this->date($nextRunAt),
            'last_due_at' => $this->date($dueAt),
            'updated_at' => $this->date($now),
        ];
        if ($jobId !== null) {
            $values += [
                'last_dispatched_at' => $this->date($now),
                'last_job_id' => $jobId,
                'last_job_status' => 'queued',
                'last_failure_reason' => null,
            ];
        }
        $this->database->table('larena_scheduler_schedules')->where('id', $schedule->id)->update($values);
    }

    public function refreshJob(
        string $scheduleId,
        string $status,
        ?int $sizeBytes,
        ?string $failureReason,
        DateTimeImmutable $now,
    ): void {
        $this->database->table('larena_scheduler_schedules')->where('id', $scheduleId)->update([
            'last_job_status' => $status,
            'last_result_size_bytes' => $sizeBytes,
            'last_failure_reason' => $failureReason,
            'updated_at' => $this->date($now),
        ]);
    }

    private function lock(string $id): ScheduleSnapshot
    {
        $row = $this->database->table('larena_scheduler_schedules')
            ->where('id', $id)->lockForUpdate()->first();

        return $row === null
            ? throw new \RuntimeException('scheduler_schedule_not_found')
            : $this->snapshot($row);
    }

    private function snapshot(object $row): ScheduleSnapshot
    {
        return new ScheduleSnapshot(
            (string) $row->id,
            (string) $row->name,
            (string) $row->operation_ref,
            (string) $row->actor_ref,
            (string) $row->timezone,
            ScheduleFrequency::from((string) $row->frequency),
            $row->local_time === null ? null : (string) $row->local_time,
            $row->weekday === null ? null : (int) $row->weekday,
            MissedRunStrategy::from((string) $row->missed_run_strategy),
            (int) $row->retention_count,
            ScheduleState::from((string) $row->state),
            $this->parse((string) $row->next_run_at),
            $row->last_due_at === null ? null : $this->parse((string) $row->last_due_at),
            $row->last_dispatched_at === null ? null : $this->parse((string) $row->last_dispatched_at),
            $row->last_job_id === null ? null : (string) $row->last_job_id,
            $row->last_job_status === null ? null : (string) $row->last_job_status,
            $row->last_result_size_bytes === null ? null : (int) $row->last_result_size_bytes,
            $row->last_failure_reason === null ? null : (string) $row->last_failure_reason,
            $this->parse((string) $row->created_at),
            $this->parse((string) $row->updated_at),
        );
    }

    private function date(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format(self::DATE_FORMAT);
    }

    private function parse(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}

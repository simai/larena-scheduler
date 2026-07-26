<?php

declare(strict_types=1);

namespace Larena\Scheduler\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Larena\Scheduler\Data\ScheduleMutation;
use Larena\Scheduler\Data\ScheduleSnapshot;
use Larena\Scheduler\Data\SchedulerOperationContext;
use Larena\Scheduler\Enums\MissedRunStrategy;
use Larena\Scheduler\Enums\ScheduleFrequency;
use Larena\Scheduler\Services\ScheduleOperations;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class BackupScheduleController
{
    public function __construct(private ScheduleOperations $operations)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return new JsonResponse(['schedules' => array_map(
            fn (ScheduleSnapshot $schedule): array => $this->schedule($schedule),
            $this->operations->all($this->context($request)),
        )]);
    }

    public function show(Request $request, string $scheduleId): JsonResponse
    {
        return new JsonResponse($this->schedule(
            $this->operations->find($this->context($request), $scheduleId),
        ));
    }

    public function create(Request $request): JsonResponse
    {
        return new JsonResponse($this->schedule(
            $this->operations->create($this->context($request), $this->mutation($request)),
        ), 201);
    }

    public function update(Request $request, string $scheduleId): JsonResponse
    {
        return new JsonResponse($this->schedule(
            $this->operations->update($this->context($request), $scheduleId, $this->mutation($request)),
        ));
    }

    public function enable(Request $request, string $scheduleId): JsonResponse
    {
        return new JsonResponse($this->schedule(
            $this->operations->enable($this->context($request), $scheduleId),
        ));
    }

    public function disable(Request $request, string $scheduleId): JsonResponse
    {
        return new JsonResponse($this->schedule(
            $this->operations->disable($this->context($request), $scheduleId),
        ));
    }

    private function mutation(Request $request): ScheduleMutation
    {
        try {
            return new ScheduleMutation(
                $request->string('name')->toString(),
                $request->string('timezone')->toString(),
                ScheduleFrequency::from($request->string('frequency')->toString()),
                $request->input('local_time') === null ? null : (string) $request->input('local_time'),
                $request->input('weekday') === null ? null : (int) $request->input('weekday'),
                MissedRunStrategy::from($request->string('missed_run_strategy')->toString()),
                (int) $request->input('retention_count', 7),
            );
        } catch (\ValueError|\InvalidArgumentException) {
            throw new HttpException(422, 'scheduler_schedule_payload_invalid');
        }
    }

    private function context(Request $request): SchedulerOperationContext
    {
        $actor = $request->attributes->get('larena_access_actor');
        if (!is_string($actor)
            || preg_match('/\A[a-z][a-z0-9_-]*:[A-Za-z0-9][A-Za-z0-9:._-]{0,191}\z/D', $actor) !== 1) {
            throw new HttpException(401, 'Unauthenticated.');
        }
        $correlation = $request->headers->get('X-Correlation-ID');
        if (!is_string($correlation) || preg_match('/\A[A-Za-z0-9][A-Za-z0-9:._-]{0,127}\z/D', $correlation) !== 1) {
            $correlation = 'scheduler:'.bin2hex(random_bytes(12));
        }

        return new SchedulerOperationContext($actor, $correlation);
    }

    /** @return array<string, mixed> */
    private function schedule(ScheduleSnapshot $schedule): array
    {
        return [
            'schedule_id' => $schedule->id,
            'name' => $schedule->name,
            'operation_ref' => $schedule->operationRef,
            'timezone' => $schedule->timezone,
            'frequency' => $schedule->frequency->value,
            'local_time' => $schedule->localTime,
            'weekday' => $schedule->weekday,
            'missed_run_strategy' => $schedule->missedRunStrategy->value,
            'retention_count' => $schedule->retentionCount,
            'state' => $schedule->state->value,
            'next_run_at' => $schedule->nextRunAt->format(DATE_ATOM),
            'last_due_at' => $schedule->lastDueAt?->format(DATE_ATOM),
            'last_dispatched_at' => $schedule->lastDispatchedAt?->format(DATE_ATOM),
            'last_job_id' => $schedule->lastJobId,
            'last_job_status' => $schedule->lastJobStatus,
            'last_result_size_bytes' => $schedule->lastResultSizeBytes,
            'last_failure_reason' => $schedule->lastFailureReason,
        ];
    }
}

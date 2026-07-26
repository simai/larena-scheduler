<?php

declare(strict_types=1);

namespace Larena\Scheduler\Audit;

use Larena\Audit\Contracts\AuditEvent;
use Larena\Audit\Runtime\AuditEventPipeline;
use Larena\Scheduler\Contracts\SchedulerClock;
use Larena\Scheduler\Data\SchedulerOperationContext;

final readonly class SchedulerAuditRecorder
{
    public function __construct(private AuditEventPipeline $pipeline, private SchedulerClock $clock)
    {
    }

    /** @param array<string, scalar|null> $payload */
    public function record(
        SchedulerOperationContext $context,
        string $surface,
        string $operation,
        string $outcome,
        array $payload = [],
    ): AuditEvent {
        $descriptor = new SchedulerAuditEventDescriptor("scheduler.$surface.$operation.$outcome");
        $subject = isset($payload['schedule_id']) && is_string($payload['schedule_id'])
            ? 'schedule:'.$payload['schedule_id'] : 'schedule:new';

        return $this->pipeline->route($descriptor, AuditEvent::create(
            sourcePackage: $descriptor->sourcePackage(),
            category: $descriptor->category(),
            type: $descriptor->type(),
            actor: $context->actorRef,
            subject: $subject,
            severity: $descriptor->severity(),
            retentionClass: $descriptor->retentionClass(),
            correlationId: $context->correlationRef,
            occurredAt: $this->clock->now(),
            payload: ['operation' => $operation, 'outcome' => $outcome] + $payload,
        ));
    }
}

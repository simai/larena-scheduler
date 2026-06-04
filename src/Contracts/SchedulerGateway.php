<?php

declare(strict_types=1);

namespace Larena\Scheduler\Contracts;

interface SchedulerGateway
{
    public function evaluate(ScheduleDescriptor $descriptor, string $currentTimeUtc): TriggerDecision;

    public function planDispatch(ScheduleDescriptor $descriptor, TriggerDecision $decision): DispatchPlan;

    public function diagnose(): ScheduleDiagnostic;
}

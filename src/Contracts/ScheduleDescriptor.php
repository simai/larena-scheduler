<?php

declare(strict_types=1);

namespace Larena\Scheduler\Contracts;

use Larena\Scheduler\Enums\MissedRunStrategy;
use Larena\Scheduler\Enums\ScheduleState;

final readonly class ScheduleDescriptor
{
    /**
     * @param array<string, mixed> $trigger
     */
    public function __construct(
        public string $id,
        public string $package,
        public string $operationRef,
        public ScheduleState $state,
        public string $timezone,
        public array $trigger,
        public string $queueProfileRef,
        public string $lockPolicyRef,
        public MissedRunStrategy $missedRunStrategy,
    ) {
    }

    public function enabled(): bool
    {
        return $this->state === ScheduleState::Enabled;
    }
}

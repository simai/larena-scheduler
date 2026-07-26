<?php

declare(strict_types=1);

namespace Larena\Scheduler\Data;

use Larena\Scheduler\Enums\MissedRunStrategy;
use Larena\Scheduler\Enums\ScheduleFrequency;

final readonly class ScheduleMutation
{
    public function __construct(
        public string $name,
        public string $timezone,
        public ScheduleFrequency $frequency,
        public ?string $localTime,
        public ?int $weekday,
        public MissedRunStrategy $missedRunStrategy,
        public int $retentionCount,
    ) {
        if (
            preg_match('/\A[^\x00-\x1F\x7F]{3,100}\z/u', $name) !== 1
            || strlen($timezone) > 64
            || $retentionCount < 1
            || $retentionCount > 100
            || ($frequency !== ScheduleFrequency::Hourly
                && preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/D', (string) $localTime) !== 1)
            || ($frequency === ScheduleFrequency::Weekly && ($weekday === null || $weekday < 1 || $weekday > 7))
            || ($frequency !== ScheduleFrequency::Weekly && $weekday !== null)
        ) {
            throw new \InvalidArgumentException('scheduler_schedule_mutation_invalid');
        }

        try {
            new \DateTimeZone($timezone);
        } catch (\Throwable) {
            throw new \InvalidArgumentException('scheduler_timezone_invalid');
        }
    }
}

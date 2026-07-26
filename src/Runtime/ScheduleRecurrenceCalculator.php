<?php

declare(strict_types=1);

namespace Larena\Scheduler\Runtime;

use DateTimeImmutable;
use DateTimeZone;
use Larena\Scheduler\Data\ScheduleMutation;
use Larena\Scheduler\Data\ScheduleSnapshot;
use Larena\Scheduler\Enums\ScheduleFrequency;

final readonly class ScheduleRecurrenceCalculator
{
    public function firstAfter(ScheduleMutation $schedule, DateTimeImmutable $afterUtc): DateTimeImmutable
    {
        return $this->calculate(
            $schedule->frequency,
            $schedule->timezone,
            $schedule->localTime,
            $schedule->weekday,
            $afterUtc,
        );
    }

    public function nextAfter(ScheduleSnapshot $schedule, DateTimeImmutable $afterUtc): DateTimeImmutable
    {
        return $this->calculate(
            $schedule->frequency,
            $schedule->timezone,
            $schedule->localTime,
            $schedule->weekday,
            $afterUtc,
        );
    }

    private function calculate(
        ScheduleFrequency $frequency,
        string $timezone,
        ?string $localTime,
        ?int $weekday,
        DateTimeImmutable $afterUtc,
    ): DateTimeImmutable {
        $zone = new DateTimeZone($timezone);
        $local = $afterUtc->setTimezone($zone);
        if ($frequency === ScheduleFrequency::Hourly) {
            $candidate = $local->setTime((int) $local->format('H'), 0, 0)->modify('+1 hour');

            return $candidate->setTimezone(new DateTimeZone('UTC'));
        }

        [$hour, $minute] = array_map('intval', explode(':', (string) $localTime));
        $candidate = $local->setTime($hour, $minute, 0);
        if ($frequency === ScheduleFrequency::Daily) {
            if ($candidate <= $local) {
                $candidate = $candidate->modify('+1 day');
            }

            return $candidate->setTimezone(new DateTimeZone('UTC'));
        }

        $days = ((int) $weekday - (int) $candidate->format('N') + 7) % 7;
        $candidate = $candidate->modify('+'.$days.' days');
        if ($candidate <= $local) {
            $candidate = $candidate->modify('+7 days');
        }

        return $candidate->setTimezone(new DateTimeZone('UTC'));
    }
}

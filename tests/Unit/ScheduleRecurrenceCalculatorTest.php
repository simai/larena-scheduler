<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';

use Larena\Scheduler\Data\ScheduleMutation;
use Larena\Scheduler\Enums\MissedRunStrategy;
use Larena\Scheduler\Enums\ScheduleFrequency;
use Larena\Scheduler\Runtime\ScheduleRecurrenceCalculator;

$calculator = new ScheduleRecurrenceCalculator();
$after = new DateTimeImmutable('2026-07-26T07:30:00Z');

$hourly = new ScheduleMutation(
    'Hourly backup',
    'Europe/Istanbul',
    ScheduleFrequency::Hourly,
    null,
    null,
    MissedRunStrategy::CatchUpSingle,
    7,
);
assert($calculator->firstAfter($hourly, $after)->format(DATE_ATOM) === '2026-07-26T08:00:00+00:00');

$daily = new ScheduleMutation(
    'Daily backup',
    'Europe/Istanbul',
    ScheduleFrequency::Daily,
    '11:00',
    null,
    MissedRunStrategy::Skip,
    14,
);
assert($calculator->firstAfter($daily, $after)->format(DATE_ATOM) === '2026-07-26T08:00:00+00:00');

$weekly = new ScheduleMutation(
    'Weekly backup',
    'Europe/Istanbul',
    ScheduleFrequency::Weekly,
    '03:00',
    1,
    MissedRunStrategy::WarnOnly,
    30,
);
assert($calculator->firstAfter($weekly, $after)->format(DATE_ATOM) === '2026-07-27T00:00:00+00:00');

echo "ScheduleRecurrenceCalculatorTest passed.\n";

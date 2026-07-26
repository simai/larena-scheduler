<?php

declare(strict_types=1);

namespace Larena\Scheduler\Enums;

enum ScheduleFrequency: string
{
    case Hourly = 'hourly';
    case Daily = 'daily';
    case Weekly = 'weekly';
}

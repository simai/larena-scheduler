<?php

declare(strict_types=1);

namespace Larena\Scheduler\Enums;

enum ScheduleDecisionStatus: string
{
    case Due = 'due';
    case Skipped = 'skipped';
    case Locked = 'locked';
    case Missed = 'missed';
    case Rejected = 'rejected';
    case NotImplemented = 'not_implemented';
}

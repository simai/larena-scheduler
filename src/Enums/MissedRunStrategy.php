<?php

declare(strict_types=1);

namespace Larena\Scheduler\Enums;

enum MissedRunStrategy: string
{
    case Skip = 'skip';
    case CatchUpSingle = 'catch_up_single';
    case WarnOnly = 'warn_only';
    case RejectBurst = 'reject_burst';
}

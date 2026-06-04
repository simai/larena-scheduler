<?php

declare(strict_types=1);

namespace Larena\Scheduler\Enums;

enum ScheduleState: string
{
    case Enabled = 'enabled';
    case Paused = 'paused';
    case Disabled = 'disabled';
}

<?php

declare(strict_types=1);

namespace Larena\Scheduler\Enums;

enum TickSource: string
{
    case Cron = 'cron';
    case Worker = 'worker';
    case Web = 'web';

    public function degraded(): bool
    {
        return $this === self::Web;
    }
}

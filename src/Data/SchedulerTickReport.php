<?php

declare(strict_types=1);

namespace Larena\Scheduler\Data;

final readonly class SchedulerTickReport
{
    /** @param list<array{schedule_id:string,decision:string,job_id:?string,reason_code:?string}> $decisions */
    public function __construct(public string $tickId, public array $decisions)
    {
    }
}

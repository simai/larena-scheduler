<?php

declare(strict_types=1);

namespace Larena\Scheduler\Commands;

use Illuminate\Console\Command;
use Larena\Scheduler\Services\SchedulerTickService;

final class SchedulerTickCommand extends Command
{
    protected $signature = 'larena:scheduler-tick {--tick-id=}';
    protected $description = 'Evaluate due Larena schedules and dispatch heavy work to Queue.';

    public function handle(SchedulerTickService $scheduler): int
    {
        $report = $scheduler->tick(is_string($this->option('tick-id')) ? $this->option('tick-id') : null);
        $this->line('Tick: '.$report->tickId);
        $this->line('Decisions: '.count($report->decisions));

        return self::SUCCESS;
    }
}

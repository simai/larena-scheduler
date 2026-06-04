<?php

declare(strict_types=1);

namespace Larena\Scheduler\Contracts;

final readonly class ScheduleDiagnostic
{
    /**
     * @param list<string> $warnings
     * @param list<string> $blockers
     */
    public function __construct(
        public bool $healthy,
        public array $warnings = [],
        public array $blockers = [],
    ) {
    }
}

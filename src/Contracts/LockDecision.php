<?php

declare(strict_types=1);

namespace Larena\Scheduler\Contracts;

final readonly class LockDecision
{
    /**
     * @param list<string> $reasons
     */
    public function __construct(
        public bool $acquired,
        public string $lockKey,
        public string $idempotencyKey,
        public bool $stale,
        public array $reasons = [],
    ) {
    }

    public function allowsDispatch(): bool
    {
        return $this->acquired && !$this->stale;
    }
}

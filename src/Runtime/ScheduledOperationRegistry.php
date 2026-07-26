<?php

declare(strict_types=1);

namespace Larena\Scheduler\Runtime;

use Larena\Scheduler\Contracts\ScheduledOperationHandler;

final class ScheduledOperationRegistry
{
    /** @var array<string, ScheduledOperationHandler> */
    private array $handlers = [];

    public function register(ScheduledOperationHandler $handler): void
    {
        $ref = $handler->operationRef();
        if (preg_match('/\A[a-z][a-z0-9_.-]{2,99}\z/D', $ref) !== 1) {
            throw new \InvalidArgumentException('scheduler_operation_ref_invalid');
        }
        if (isset($this->handlers[$ref]) && $this->handlers[$ref] !== $handler) {
            throw new \RuntimeException('scheduler_operation_handler_conflict');
        }
        $this->handlers[$ref] = $handler;
    }

    public function handler(string $operationRef): ScheduledOperationHandler
    {
        return $this->handlers[$operationRef]
            ?? throw new \RuntimeException('scheduler_operation_handler_missing');
    }

    public function has(string $operationRef): bool
    {
        return isset($this->handlers[$operationRef]);
    }
}

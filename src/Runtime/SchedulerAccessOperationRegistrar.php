<?php

declare(strict_types=1);

namespace Larena\Scheduler\Runtime;

use Larena\Access\Runtime\AccessOperationRegistry;
use Larena\Access\ValueObjects\AccessOperationDescriptor;

final readonly class SchedulerAccessOperationRegistrar
{
    /** @return list<AccessOperationDescriptor> */
    public function descriptors(): array
    {
        return array_map(
            static fn (string $operation): AccessOperationDescriptor => new AccessOperationDescriptor(
                'scheduler.schedule.'.$operation,
                'larena/scheduler',
                'larena-scheduler::operations.schedule_'.$operation,
                'scheduler.schedule:system',
                $operation,
                in_array($operation, ['create', 'update', 'enable', 'disable'], true) ? 'critical' : 'high',
                true,
            ),
            ['list', 'view', 'create', 'update', 'enable', 'disable'],
        );
    }

    public function register(AccessOperationRegistry $registry): void
    {
        foreach ($this->descriptors() as $descriptor) {
            $existing = $registry->get($descriptor->code);
            if ($existing === null) {
                if (!$registry->register($descriptor)) {
                    throw new \RuntimeException('scheduler_access_operation_registration_failed');
                }
            } elseif ($existing->toArray() !== $descriptor->toArray()) {
                throw new \RuntimeException('scheduler_access_operation_conflict');
            }
        }
    }
}

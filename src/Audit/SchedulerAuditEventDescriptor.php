<?php

declare(strict_types=1);

namespace Larena\Scheduler\Audit;

use Larena\Audit\Contracts\AuditEventDescriptor;
use Larena\Audit\Enums\AuditRetentionClass;
use Larena\Audit\Enums\AuditSeverity;

final readonly class SchedulerAuditEventDescriptor implements AuditEventDescriptor
{
    public function __construct(private string $eventType)
    {
        if (preg_match('/\Ascheduler\.(?:schedule|tick)\.[a-z_]+\.(?:requested|succeeded|denied|failed|duplicate|overlap|skipped)\z/D', $eventType) !== 1) {
            throw new \InvalidArgumentException('scheduler_audit_type_invalid');
        }
    }

    public function sourcePackage(): string { return 'larena/scheduler'; }
    public function category(): string { return 'scheduler'; }
    public function type(): string { return $this->eventType; }
    public function severity(): AuditSeverity { return AuditSeverity::Security; }
    public function retentionClass(): AuditRetentionClass { return AuditRetentionClass::Security; }
    public function redactedPayloadFields(): array { return []; }
    public function isExperimental(): bool { return false; }

    public function forbiddenPayloadFields(): array
    {
        return [
            'dsn', 'host', 'port', 'socket', 'username', 'password', 'credential',
            'database', 'schema', 'path', 'storage_disk', 'storage_key', 'content',
            'sql', 'query', 'row', 'token', 'secret', 'exception', 'trace', 'stdout', 'stderr',
        ];
    }
}

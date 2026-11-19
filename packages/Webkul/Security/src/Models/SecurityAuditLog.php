<?php

namespace Webkul\Security\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;
use Webkul\Security\Contracts\SecurityAuditLog as SecurityAuditLogContract;

/**
 * Immutable append-only enterprise security audit record.
 *
 * @property int $id
 * @property int|null $actor_id
 * @property string|null $actor_email
 * @property string $event
 * @property string|null $auditable_type
 * @property string|null $auditable_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 */
class SecurityAuditLog extends Model implements SecurityAuditLogContract
{
    public const UPDATED_AT = null;

    protected $table = 'security_audit_logs';

    protected $fillable = [
        'actor_id',
        'actor_email',
        'event',
        'auditable_type',
        'auditable_id',
        'ip_address',
        'user_agent',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'actor_id' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Security audit logs are immutable and cannot be modified.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Security audit logs are append-only and cannot be deleted.');
        });
    }
}

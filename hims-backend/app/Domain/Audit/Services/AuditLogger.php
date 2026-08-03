<?php

namespace App\Domain\Audit\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     * @param  array<string, mixed>|null  $metadata
     */
    public function record(
        Request $request,
        string $module,
        string $event,
        ?Model $auditable = null,
        ?array $old = null,
        ?array $new = null,
        ?array $metadata = null,
    ): AuditLog {
        $hospitalId = null;
        $branchId = null;

        if (app()->bound(TenantContext::class)) {
            $context = app(TenantContext::class);
            $hospitalId = $context->hospitalId();
            $branchId = $context->branchId();
        }

        $requestId = $request->header('X-Request-Id') ?: $request->attributes->get('request_id');
        $occurredAt = now();

        $previousHash = AuditLog::query()
            ->when(
                $hospitalId !== null,
                fn ($query) => $query->where('hospital_id', $hospitalId),
                fn ($query) => $query->whereNull('hospital_id'),
            )
            ->latest('id')
            ->value('hash');

        $payload = [
            'hospital_id' => $hospitalId,
            'branch_id' => $branchId,
            'user_id' => $request->user()?->id,
            'module' => $module,
            'event' => $event,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'metadata' => $metadata,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'request_id' => $requestId,
            'occurred_at' => $occurredAt->toISOString(),
        ];

        $hash = hash('sha256', json_encode([...$payload, 'previous_hash' => $previousHash], JSON_THROW_ON_ERROR));

        return AuditLog::create([
            ...$payload,
            'occurred_at' => $occurredAt,
            'previous_hash' => $previousHash,
            'hash' => $hash,
        ]);
    }
}

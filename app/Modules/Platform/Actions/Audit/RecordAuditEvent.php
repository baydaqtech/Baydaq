<?php

namespace App\Modules\Platform\Actions\Audit;

use App\Modules\Platform\Models\AuditEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class RecordAuditEvent
{
    /**
     * Write one audit row for a change to a tenant record. Actions call this explicitly so the
     * row carries the business event ("salary_changed"), not just "updated".
     *
     * @param  array<string, mixed>|null  $changes  old/new pairs, already masked where sensitive
     */
    public function handle(Model $auditable, string $event, ?array $changes = null, ?Authenticatable $actor = null): AuditEvent
    {
        $actor ??= auth()->user();

        $audit = new AuditEvent;
        $audit->forceFill([
            'client_id' => $auditable->getAttribute('client_id'),
            'actor_id' => $actor?->getAuthIdentifier(),
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'event' => $event,
            'changes' => $changes,
            'ip' => request()?->ip(),
            'created_at' => now(),
        ])->save();

        return $audit;
    }
}

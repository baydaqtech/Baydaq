<?php

namespace App\Modules\Platform\Adapters;

use App\Modules\Platform\Actions\Audit\RecordAuditEvent;
use App\Modules\Platform\Models\AuditEvent;
use App\Modules\Platform\Ports\Audit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class AuditTrail implements Audit
{
    public function __construct(private RecordAuditEvent $record) {}

    public function record(Model $auditable, string $event, ?array $changes = null, ?Authenticatable $actor = null): void
    {
        $this->record->handle($auditable, $event, $changes, $actor);
    }

    public function history(Model $auditable, int $limit = 50): array
    {
        return AuditEvent::query()
            ->where('auditable_type', $auditable->getMorphClass())->where('auditable_id', $auditable->getKey())
            ->with('actor:id,name')->latest('created_at')->latest('id')->limit($limit)->get()
            ->map(fn (AuditEvent $event): array => [
                'id' => $event->id,
                'event' => $event->event,
                'changes' => $event->changes,
                'actor' => $event->actor?->name,
                'at' => $event->created_at->toIso8601String(),
            ])->all();
    }
}

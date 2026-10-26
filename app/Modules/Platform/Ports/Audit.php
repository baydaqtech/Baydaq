<?php

namespace App\Modules\Platform\Ports;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * The audit trail. Modules record their own business events here ("salary_changed") with
 * already-masked before/after values.
 */
interface Audit
{
    /**
     * @param  array<string, mixed>|null  $changes
     */
    public function record(Model $auditable, string $event, ?array $changes = null, ?Authenticatable $actor = null): void;

    /**
     * The latest events of one record, newest first.
     *
     * @return list<array{id: int, event: string, changes: array<string, mixed>|null, actor: string|null, at: string}>
     */
    public function history(Model $auditable, int $limit = 50): array;
}

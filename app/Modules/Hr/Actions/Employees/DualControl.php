<?php

namespace App\Modules\Hr\Actions\Employees;

use App\Modules\Hr\Enums\ChangeRequestStatus;
use App\Modules\Hr\Models\ChangeRequest;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Support\HrSettings;
use App\Modules\Platform\Ports\Audit;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Two-person rule for IBAN and salary changes (H13). When the tenant turns it on, the saving
 * actions hand such changes here instead of applying them; a different person approves them
 * later. It remembers what it held during this request so the controller can say so.
 */
#[Scoped]
class DualControl
{
    /** @var list<string> */
    private array $held = [];

    public function __construct(private HrSettings $settings, private Audit $audit) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('dual_control');
    }

    /**
     * @param  'iban'|'salary'  $kind
     * @param  array<string, mixed>  $payload  the new values (stored encrypted)
     * @param  array<string, mixed>  $summary  masked before/after, safe to show
     */
    public function hold(Employee $employee, string $kind, array $payload, array $summary, ?int $contractId, ?Authenticatable $actor): ChangeRequest
    {
        // A newer request replaces an older pending one of the same kind.
        ChangeRequest::query()->where('employee_id', $employee->id)->where('kind', $kind)->where('status', ChangeRequestStatus::Pending->value)
            ->update(['status' => ChangeRequestStatus::Rejected->value, 'decided_at' => now(), 'decided_by' => $actor?->getAuthIdentifier()]);

        $request = (new ChangeRequest)->forceFill([
            'client_id' => $employee->client_id,
            'employee_id' => $employee->id,
            'kind' => $kind,
            'contract_id' => $contractId,
            'payload' => $payload,
            'summary' => $summary,
            'status' => ChangeRequestStatus::Pending->value,
            'requested_by' => $actor?->getAuthIdentifier(),
        ]);
        $request->save();
        $this->audit->record($employee, 'change_requested', ['kind' => $kind, ...$summary], $actor);
        $this->held[] = $kind;

        return $request;
    }

    /**
     * Kinds held for approval during this request.
     *
     * @return list<string>
     */
    public function held(): array
    {
        return $this->held;
    }
}

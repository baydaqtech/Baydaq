<?php

namespace App\Modules\Hr\Actions\Employees;

use App\Modules\Hr\Actions\Contracts\SaveContract;
use App\Modules\Hr\Enums\ChangeRequestStatus;
use App\Modules\Hr\Models\ChangeRequest;
use App\Modules\Hr\Models\Contract;
use App\Modules\Platform\Ports\Audit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The second person's decision on a held IBAN or salary change. Whoever asked for the change
 * can't approve it.
 */
class DecideChangeRequest
{
    public function __construct(private SaveContract $saveContract, private Audit $audit) {}

    public function approve(ChangeRequest $request, Authenticatable $actor): ChangeRequest
    {
        $this->expectDecidable($request, $actor);

        return DB::transaction(function () use ($request, $actor): ChangeRequest {
            $employee = $request->employee;

            if ($request->kind === 'iban') {
                $employee->forceFill(['iban' => $request->payload['iban']])->save();
                $this->audit->record($employee, 'updated', ['iban' => $request->summary['iban'] ?? null], $actor);
            } else {
                $contract = $request->contract_id ? Contract::query()->findOrFail($request->contract_id) : new Contract;
                $this->saveContract->handle($employee, $contract, $request->payload['data'], $request->payload['allowances'], $actor, approved: true);
            }

            return $this->close($request, ChangeRequestStatus::Approved, $actor);
        });
    }

    public function reject(ChangeRequest $request, Authenticatable $actor): ChangeRequest
    {
        $this->expectDecidable($request, $actor, allowRequester: true);

        return $this->close($request, ChangeRequestStatus::Rejected, $actor);
    }

    private function expectDecidable(ChangeRequest $request, Authenticatable $actor, bool $allowRequester = false): void
    {
        if ($request->status !== ChangeRequestStatus::Pending) {
            throw ValidationException::withMessages(['change' => __('hr.changes.errors.decided')]);
        }

        if (! $allowRequester && $request->requested_by === $actor->getAuthIdentifier()) {
            throw ValidationException::withMessages(['change' => __('hr.changes.errors.same_person')]);
        }
    }

    private function close(ChangeRequest $request, ChangeRequestStatus $status, Authenticatable $actor): ChangeRequest
    {
        $request->forceFill(['status' => $status->value, 'decided_by' => $actor->getAuthIdentifier(), 'decided_at' => now()])->save();
        $this->audit->record($request->employee, 'change_'.$status->value, ['kind' => $request->kind], $actor);

        return $request;
    }
}

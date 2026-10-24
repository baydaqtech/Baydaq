<?php

namespace App\Modules\Hr\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Leave\LeaveBalance;
use App\Modules\Hr\Actions\Leave\RequestLeave;
use App\Modules\Hr\Http\Controllers\Api\Concerns\ResolvesEmployee;
use App\Modules\Hr\Http\Controllers\SelfServiceController;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Hr\Models\LeaveType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The employee app's leave screen: balances with their working, requests, and asking for leave.
 * Enum values travel as values; the app translates them.
 */
class LeaveController extends Controller
{
    use ResolvesEmployee;

    public function index(Request $request, LeaveBalance $balance): JsonResponse
    {
        $employee = $this->employee($request);

        return response()->json([
            'balances' => SelfServiceController::balances($employee, $balance),
            'requests' => $employee->leaveRequests()->with('type')->latest('starts_on')->limit(30)->get()->map(fn (LeaveRequest $leave): array => [
                'id' => $leave->id, 'type' => $leave->type->localized('name'), 'starts_on' => $leave->starts_on->toDateString(), 'ends_on' => $leave->ends_on->toDateString(),
                'days' => (string) $leave->days, 'status' => $leave->status->value, 'reason' => $leave->reason, 'decision_note' => $leave->decision_note,
            ])->all(),
            'types' => LeaveType::query()->where('is_active', true)->orderBy('sort_order')->get()->map(fn (LeaveType $type): array => ['id' => $type->id, 'name' => $type->localized('name'), 'needs_balance' => $type->needs_balance])->all(),
        ]);
    }

    public function store(Request $request, RequestLeave $requestLeave): JsonResponse
    {
        $employee = $this->employee($request);
        $data = $request->validate([
            'leave_type_id' => ['required', Rule::exists('hr__leave_types', 'id')->where('client_id', $employee->client_id)->where('is_active', true)],
            'starts_on' => ['required', 'date', 'after_or_equal:today'], 'ends_on' => ['required', 'date'], 'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $leave = $requestLeave->handle($employee, LeaveType::query()->findOrFail($data['leave_type_id']), $data, $request->user());

        return response()->json(['id' => $leave->id, 'days' => (string) $leave->days, 'status' => $leave->status->value], 201);
    }
}

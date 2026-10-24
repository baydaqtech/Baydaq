<?php

namespace App\Modules\Hr\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Attendance\RecordPunch;
use App\Modules\Hr\Enums\PunchType;
use App\Modules\Hr\Http\Controllers\Api\Concerns\ResolvesEmployee;
use App\Modules\Hr\Http\Requests\Api\PunchRequest;
use App\Modules\Hr\Http\Resources\Api\PunchResource;
use App\Modules\Hr\Models\AttendanceDevice;
use App\Modules\Hr\Models\AttendancePunch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class PunchController extends Controller
{
    use ResolvesEmployee;

    public function index(Request $request): AnonymousResourceCollection
    {
        $employee = $this->employee($request);

        return PunchResource::collection(
            AttendancePunch::query()->where('employee_id', $employee->id)->latest('punched_at')->limit(60)->get(),
        );
    }

    public function store(PunchRequest $request, RecordPunch $recordPunch): JsonResponse
    {
        $employee = $this->employee($request);
        $device = AttendanceDevice::query()->firstWhere('employee_id', $employee->id);

        if ($device === null || ! hash_equals($device->device_id, $request->validated('device_id'))) {
            throw ValidationException::withMessages(['device_id' => __('hr.attendance.errors.other_device')]);
        }

        $punch = $recordPunch->handle($employee, PunchType::from($request->validated('type')), $request->validated());

        return (new PunchResource($punch))->response()->setStatusCode(201);
    }
}

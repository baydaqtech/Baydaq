<?php

namespace App\Modules\Hr\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Http\Controllers\Api\Concerns\ResolvesEmployee;
use App\Modules\Hr\Http\Resources\Api\PunchResource;
use App\Modules\Hr\Models\AttendancePunch;
use App\Modules\Platform\Ports\Entitlements;
use App\Modules\Platform\Ports\OrgDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    use ResolvesEmployee;

    /**
     * Self-service: who I am, where I work, my contract outline, my documents, and today's punches.
     */
    public function __invoke(Request $request, OrgDirectory $directory, Entitlements $entitlements): JsonResponse
    {
        $employee = $this->employee($request)->load(['activeContract', 'documents.type']);
        $branch = $directory->branch($employee->branch_id);
        $company = $directory->company($employee->company_id);

        return response()->json([
            // The app shows only the screens in the client's package (feature flags).
            'features' => $entitlements->mobileFeatures(),
            'employee' => [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'name_ar' => $employee->name_ar,
                'name_en' => $employee->name_en,
                'job' => $employee->job?->localized('name'),
                'section' => $employee->section?->localized('name'),
                'status' => $employee->status->value,
                'hired_on' => $employee->hired_on?->toDateString(),
                'company' => $company ? ['id' => $company->id, 'name' => $company->name, 'timezone' => $company->timezone] : null,
                'branch' => $branch ? ['id' => $branch->id, 'name' => $branch->name] : null,
            ],
            'contract' => $employee->activeContract ? [
                'type' => $employee->activeContract->type->value,
                'starts_on' => $employee->activeContract->starts_on->toDateString(),
                'ends_on' => $employee->activeContract->ends_on?->toDateString(),
                'probation_days' => $employee->activeContract->probation_days,
            ] : null,
            'documents' => $employee->documents->map(fn ($document): array => [
                'id' => $document->id,
                'type' => $document->type->localized('name'),
                'expires_on' => $document->expires_on?->toDateString(),
                'days_left' => $document->expires_on ? (int) today()->diffInDays($document->expires_on, false) : null,
            ])->values()->all(),
            'today' => PunchResource::collection(
                AttendancePunch::query()->where('employee_id', $employee->id)->whereDate('work_date', today($company?->timezone ?? 'Asia/Riyadh'))->orderBy('punched_at')->get(),
            ),
        ]);
    }
}

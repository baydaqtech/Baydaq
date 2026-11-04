<?php

namespace App\Modules\Hr\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Letters\IssueLetter;
use App\Modules\Hr\Actions\Payroll\Payslips;
use App\Modules\Hr\Enums\EmployeeStatus;
use App\Modules\Hr\Http\Controllers\Api\Concerns\ResolvesEmployee;
use App\Modules\Hr\Http\Controllers\MyDocumentsController;
use App\Modules\Hr\Models\Letter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Payslips and letters in the app. Each PDF comes as a signed link that works for a few minutes,
 * opened in the phone's browser.
 */
class DocumentController extends Controller
{
    use ResolvesEmployee;

    public function payslips(Request $request, Payslips $payslips): JsonResponse
    {
        return response()->json(['data' => array_map(
            fn (array $payslip): array => [...$payslip, 'pdf_url' => MyDocumentsController::payslipLink($payslip['id'])],
            $payslips->forEmployee($this->employee($request)),
        )]);
    }

    public function letters(Request $request): JsonResponse
    {
        $employee = $this->employee($request)->loadMissing('activeContract');

        return response()->json([
            'data' => Letter::query()->where('employee_id', $employee->id)->latest('issued_at')->limit(30)->get()
                ->map(fn (Letter $letter): array => [...MyDocumentsController::letterArray($letter), 'pdf_url' => MyDocumentsController::letterLink($letter->id)])->all(),
            // A salary certificate needs an active contract; an experience letter can always be asked for.
            'kinds' => array_values(array_filter(['salary', 'experience'], fn (string $kind): bool => $kind !== 'salary' || ($employee->activeContract !== null && $employee->status === EmployeeStatus::Active))),
        ]);
    }

    public function requestLetter(Request $request, IssueLetter $issue): JsonResponse
    {
        $letter = MyDocumentsController::issue($request, $this->employee($request), $issue);

        return response()->json(['data' => [...MyDocumentsController::letterArray($letter), 'pdf_url' => MyDocumentsController::letterLink($letter->id)]], 201);
    }
}

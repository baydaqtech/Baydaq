<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Employees\DecideChangeRequest;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Http\Controllers\Concerns\ScopesEmployees;
use App\Modules\Hr\Models\ChangeRequest;
use App\Modules\Hr\Support\HrSettings;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Second-person decisions on held IBAN/salary changes, and the tenant's HR rules.
 */
class ChangeRequestController extends Controller
{
    use RendersSuitePages, ScopesEmployees;

    public function decide(Request $request, ChangeRequest $change, DecideChangeRequest $decide): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])]]);
        $employee = $change->employee;

        if ($data['decision'] === 'approve') {
            abort_unless($this->able($employee, HrFeature::Employees, 'approve_changes'), 403);
            $decide->approve($change, $request->user());
            $this->toast(__('hr.changes.approved'));
        } else {
            abort_unless($this->able($employee, HrFeature::Employees, 'approve_changes') || $change->requested_by === $request->user()->id, 403);
            $decide->reject($change, $request->user());
            $this->toast(__('hr.changes.rejected'));
        }

        return back();
    }

    public function settings(Request $request, HrSettings $settings): RedirectResponse
    {
        $numbers = [
            'ramadan_hours' => 12, 'loan_max_months' => 24, 'loan_max_installments' => 120, 'loan_max_installment_percent' => 50,
            'work_hours_per_day' => 12, 'overtime_extra_percent' => 200, 'overtime_max_hours_year' => 2000,
        ];
        $data = $request->validate([
            'dual_control' => ['sometimes', 'boolean'], 'attendance_selfie' => ['sometimes', 'boolean'], 'whatsapp_notifications' => ['sometimes', 'boolean'],
            ...array_map(fn (int $max): array => ['sometimes', 'integer', 'min:0', 'max:'.$max], $numbers),
        ]);

        foreach ($data as $key => $value) {
            $settings->set($key, array_key_exists($key, $numbers) ? (int) $value : (bool) $value);
        }

        $this->toast(__('hr.changes.settings_saved'));

        return back();
    }
}

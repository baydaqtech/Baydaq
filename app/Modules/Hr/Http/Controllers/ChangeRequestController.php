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
        $data = $request->validate(['dual_control' => ['required', 'boolean']]);

        foreach ($data as $key => $value) {
            $settings->set($key, $value);
        }

        $this->toast(__('hr.changes.settings_saved'));

        return back();
    }
}

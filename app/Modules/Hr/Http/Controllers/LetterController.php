<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Letters\IssueLetter;
use App\Modules\Hr\Actions\Letters\RenderLetterPdf;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Enums\LetterKind;
use App\Modules\Hr\Http\Controllers\Concerns\ScopesEmployees;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Letter;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Verified letters (H12): HR issues salary and experience letters; anyone holding the printed
 * QR code can check them on a public page.
 */
class LetterController extends Controller
{
    use RendersSuitePages, ScopesEmployees;

    public function store(Request $request, Employee $employee, IssueLetter $issue): RedirectResponse
    {
        $this->authorizeEmployee($employee, HrFeature::Documents, FeatureAction::Create);
        $data = $request->validate([
            'kind' => ['required', Rule::enum(LetterKind::class)],
            'language' => ['required', 'in:ar,en'],
            'addressed_to' => ['nullable', 'string', 'max:150'],
        ]);
        $kind = LetterKind::from($data['kind']);

        if ($kind === LetterKind::Salary) {
            abort_unless($this->able($employee, HrFeature::Employees, 'view_salaries'), 403);
        }

        $letter = $issue->handle($employee, $kind, $data['language'], $data['addressed_to'] ?? null, $request->user());
        $this->toast(__('hr.letters.issued', ['reference' => $letter->reference]));

        return back();
    }

    public function pdf(Employee $employee, Letter $letter, RenderLetterPdf $render): HttpResponse
    {
        $this->authorizeEmployee($employee, HrFeature::Documents, FeatureAction::View);
        abort_unless($letter->employee_id === $employee->id, 404);

        return response($render->handle($letter), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$letter->reference.'.pdf"',
        ]);
    }

    public function revoke(Employee $employee, Letter $letter): RedirectResponse
    {
        $this->authorizeEmployee($employee, HrFeature::Documents, FeatureAction::Update);
        abort_unless($letter->employee_id === $employee->id, 404);
        $letter->forceFill(['revoked_at' => $letter->revoked_at ?? now()])->save();
        $this->toast(__('hr.letters.revoked'));

        return back();
    }

    /**
     * The public check behind the QR code: who issued it, for whom, and whether it still stands.
     * Salary figures show only on salary letters, as printed.
     */
    public function verify(string $token): Response
    {
        $letter = Letter::withoutGlobalScopes()->where('token', $token)->firstOrFail();
        $snapshot = $letter->snapshot;
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
        $pick = fn (array $data, string $base): ?string => ($data["{$base}_{$locale}"] ?? null) ?: ($data[$base.'_'.($locale === 'ar' ? 'en' : 'ar')] ?? null);

        return Inertia::render('Hr/LetterVerify', [
            'letter' => [
                'reference' => $letter->reference,
                'kind' => $letter->kind->value,
                'valid' => $letter->isValid(),
                'issued_at' => $letter->issued_at->toDateString(),
                'revoked_at' => $letter->revoked_at?->toDateString(),
                'company' => $pick($snapshot['company'], 'name'),
                'employee' => $pick($snapshot['employee'], 'name'),
                'job' => $pick($snapshot['employee'], 'job'),
                'hired_on' => $snapshot['employee']['hired_on'],
                'left_on' => $snapshot['employee']['left_on'],
                'salary_total' => $snapshot['salary']['total'] ?? null,
            ],
            'copy' => ['hr' => ['letters' => trans('hr.letters'), 'letter_kind' => trans('hr.letter_kind')]],
        ]);
    }
}

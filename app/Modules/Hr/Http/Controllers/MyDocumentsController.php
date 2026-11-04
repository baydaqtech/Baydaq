<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Letters\IssueLetter;
use App\Modules\Hr\Actions\Letters\RenderLetterPdf;
use App\Modules\Hr\Actions\Payroll\Payslips;
use App\Modules\Hr\Enums\LetterKind;
use App\Modules\Hr\Http\Controllers\Api\Concerns\ResolvesEmployee;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Letter;
use App\Modules\Hr\Models\PayrollLine;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * An employee's own paperwork: payslips of approved payroll runs, and salary or experience letters
 * issued at once on request (QR-verified like any other; HR can revoke them). The app opens the
 * PDFs through short signed links, since a phone browser can't carry the app's token.
 */
class MyDocumentsController extends Controller
{
    use ResolvesEmployee;

    /** How long a signed PDF link from the app works. */
    private const LINK_MINUTES = 10;

    public function payslip(Request $request, int $line, Payslips $payslips): Response
    {
        $payslip = $payslips->find($this->employee($request), $line) ?? abort(404);

        return $this->pdf($payslips->pdf($payslip, app()->getLocale()), Payslips::filename($payslip));
    }

    public function requestLetter(Request $request, IssueLetter $issue): RedirectResponse
    {
        $letter = self::issue($request, $this->employee($request), $issue);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('hr.letters.ready', ['reference' => $letter->reference])]);

        return back();
    }

    public function letter(Request $request, Letter $letter, RenderLetterPdf $render): Response
    {
        abort_unless($letter->employee_id === $this->employee($request)->id, 404);

        return $this->pdf($render->handle($letter), $letter->reference);
    }

    /** The app's signed link to a payslip (no session; the signature names the line). */
    public function signedPayslip(int $line, CurrentTenant $tenant, Payslips $payslips): Response
    {
        $payslip = PayrollLine::query()->with(['run' => fn ($query) => $query->withoutGlobalScopes(), 'employee' => fn ($query) => $query->withoutGlobalScopes()])->findOrFail($line);

        return $tenant->runFor($payslip->run->client_id, fn (): Response => $this->pdf(
            $payslips->pdf($payslips->find($payslip->employee, $payslip->id) ?? abort(404), request()->query('lang', 'ar')),
            Payslips::filename($payslip),
        ));
    }

    public function signedLetter(int $letter, CurrentTenant $tenant, RenderLetterPdf $render): Response
    {
        $letter = Letter::withoutGlobalScopes()->findOrFail($letter);

        return $tenant->runFor($letter->client_id, fn (): Response => $this->pdf($render->handle($letter), $letter->reference));
    }

    /**
     * @return array{kind: LetterKind, language: string, addressed_to: ?string}
     */
    public static function letterRequest(Request $request): array
    {
        $data = $request->validate([
            'kind' => ['required', Rule::enum(LetterKind::class)],
            'language' => ['required', 'in:ar,en'],
            'addressed_to' => ['nullable', 'string', 'max:150'],
        ]);

        return ['kind' => LetterKind::from($data['kind']), 'language' => $data['language'], 'addressed_to' => $data['addressed_to'] ?? null];
    }

    public static function issue(Request $request, Employee $employee, IssueLetter $issue): Letter
    {
        $data = self::letterRequest($request);

        return $issue->handle($employee, $data['kind'], $data['language'], $data['addressed_to'], $request->user());
    }

    public static function payslipLink(int $lineId): string
    {
        return URL::temporarySignedRoute('hr.files.payslip', now()->addMinutes(self::LINK_MINUTES), ['line' => $lineId, 'lang' => app()->getLocale()]);
    }

    public static function letterLink(int $letterId): string
    {
        return URL::temporarySignedRoute('hr.files.letter', now()->addMinutes(self::LINK_MINUTES), ['letter' => $letterId]);
    }

    /**
     * @return array{id: int, reference: string, kind: string, language: string, addressed_to: ?string, issued_at: string, valid: bool}
     */
    public static function letterArray(Letter $letter): array
    {
        return [
            'id' => $letter->id, 'reference' => $letter->reference, 'kind' => $letter->kind->value, 'language' => $letter->language,
            'addressed_to' => $letter->addressed_to, 'issued_at' => $letter->issued_at->toDateString(), 'valid' => $letter->isValid(),
        ];
    }

    private function pdf(string $content, string $name): Response
    {
        return response($content, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$name.'.pdf"']);
    }
}

<?php

namespace App\Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Actions\Documents\DocumentRadar;
use App\Modules\Hr\Actions\Documents\ReadDocumentScan;
use App\Modules\Hr\Actions\Documents\SaveEmployeeDocument;
use App\Modules\Hr\Enums\HrFeature;
use App\Modules\Hr\Http\Controllers\Concerns\ScopesEmployees;
use App\Modules\Hr\Http\Requests\SaveEmployeeDocumentRequest;
use App\Modules\Hr\Models\DocumentRenewal;
use App\Modules\Hr\Models\DocumentType;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeDocument;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\Intelligence;
use App\Modules\Platform\Ports\IntelligenceUnavailable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeDocumentController extends Controller
{
    use RendersSuitePages, ScopesEmployees;

    public function store(SaveEmployeeDocumentRequest $request, Employee $employee, SaveEmployeeDocument $save): RedirectResponse
    {
        $this->authorizeEmployee($employee, HrFeature::Documents, FeatureAction::Create);

        $save->handle($employee, $request->documentType(), new EmployeeDocument, $request->safe()->except(['document_type_id', 'files']), $request->file('files', []), $request->user());
        $this->toast(__('hr.documents.saved'));

        return back();
    }

    /**
     * Read a scanned document with AI and return the values for the form (H7). Saves nothing.
     */
    public function scan(Request $request, Employee $employee, ReadDocumentScan $read, Intelligence $intelligence): JsonResponse
    {
        $this->authorizeEmployee($employee, HrFeature::Documents, FeatureAction::Create);
        abort_unless($intelligence->isEnabled(), 404);

        $data = $request->validate([
            'document_type_id' => ['required', 'integer', Rule::exists('hr__document_types', 'id')->where('client_id', $employee->client_id)],
            'files' => ['required', 'array', 'min:1', 'max:4'],
            'files.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        try {
            return response()->json($read->handle(DocumentType::query()->findOrFail($data['document_type_id']), $request->file('files')));
        } catch (IntelligenceUnavailable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function update(SaveEmployeeDocumentRequest $request, Employee $employee, EmployeeDocument $document, SaveEmployeeDocument $save): RedirectResponse
    {
        $this->authorizeEmployee($employee, HrFeature::Documents, FeatureAction::Update);
        abort_unless($document->employee_id === $employee->id, 404);

        $save->handle($employee, $document->type, $document, $request->safe()->except(['document_type_id', 'files']), $request->file('files', []), $request->user());
        $this->toast(__('hr.documents.saved'));

        return back();
    }

    public function destroy(Employee $employee, EmployeeDocument $document): RedirectResponse
    {
        $this->authorizeEmployee($employee, HrFeature::Documents, FeatureAction::Delete);
        abort_unless($document->employee_id === $employee->id, 404);

        foreach ($document->files as $file) {
            Storage::disk($file->disk)->delete($file->path);
        }

        $document->delete();
        $this->toast(__('hr.documents.deleted'));

        return back();
    }

    /**
     * Stream a file after checking the viewer may see this employee's documents. Files never get
     * a public URL.
     */
    public function file(Employee $employee, EmployeeDocument $document, int $file): StreamedResponse
    {
        $this->authorizeEmployee($employee, HrFeature::Documents, FeatureAction::View);
        abort_unless($document->employee_id === $employee->id, 404);
        $file = $document->files()->findOrFail($file);

        return Storage::disk($file->disk)->download($file->path, $file->name);
    }

    public function deleteFile(Request $request, Employee $employee, EmployeeDocument $document, int $file, SaveEmployeeDocument $save): RedirectResponse
    {
        $this->authorizeEmployee($employee, HrFeature::Documents, FeatureAction::Update);
        abort_unless($document->employee_id === $employee->id, 404);

        $save->deleteFile($document, $file);

        return back();
    }

    /** Documents running out in the next 90 days, with renewal costs and tasks (H6). */
    public function radar(DocumentRadar $radar): Response
    {
        return $this->suitePage('Hr/Documents/Radar', $radar->handle($this->reach(HrFeature::Documents, FeatureAction::View)), ['hr']);
    }

    /** Close a renewal task by hand (renewed outside the system, or no longer needed). */
    public function renewal(Request $request, DocumentRenewal $renewal): RedirectResponse
    {
        $document = $renewal->document()->with('employee')->firstOrFail();
        $this->authorizeEmployee($document->employee, HrFeature::Documents, FeatureAction::Update);
        $data = $request->validate(['status' => ['required', Rule::in(['done', 'cancelled', 'open'])]]);

        $renewal->forceFill(['status' => $data['status'], 'done_by' => $data['status'] === 'open' ? null : $request->user()->id, 'done_at' => $data['status'] === 'open' ? null : now()])->save();
        $this->toast(__('hr.documents.radar.updated'));

        return back();
    }
}

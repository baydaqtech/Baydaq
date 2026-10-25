<?php

namespace App\Modules\Hr\Actions\Documents;

use App\Modules\Hr\Models\DocumentRenewal;
use App\Modules\Hr\Models\DocumentType;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeDocument;
use App\Modules\Platform\Ports\Audit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SaveEmployeeDocument
{
    public function __construct(private Audit $audit) {}

    /**
     * Save a document of a type, with its dynamic field values and any new files on the private
     * HR disk, and plan the next expiry alert.
     *
     * @param  array{number?: ?string, issued_on?: ?string, expires_on?: ?string, data?: array<string, mixed>, notes?: ?string}  $data
     * @param  list<UploadedFile>  $files
     */
    public function handle(Employee $employee, DocumentType $type, EmployeeDocument $document, array $data, array $files = [], ?Authenticatable $actor = null): EmployeeDocument
    {
        return DB::transaction(function () use ($employee, $type, $document, $data, $files, $actor): EmployeeDocument {
            $document->fill($data);
            $document->forceFill(['employee_id' => $employee->id, 'client_id' => $employee->client_id, 'document_type_id' => $type->id]);
            $document->setRelation('type', $type);

            $renewed = $document->exists && $document->isDirty('expires_on') && $document->expires_on !== null;

            if ($document->isDirty('expires_on')) {
                $document->alerted = [];
            }

            $document->scheduleNextAlert()->save();

            // A new expiry date means the renewal happened: close the open renewal tasks.
            if ($renewed) {
                DocumentRenewal::query()->where('employee_document_id', $document->id)->where('status', 'open')->whereDate('expires_on', '<', $document->expires_on)
                    ->update(['status' => 'done', 'done_by' => $actor?->getAuthIdentifier(), 'done_at' => now()]);
            }

            $disk = config('baydaq.hr.disk');

            foreach ($files as $file) {
                $path = $file->store("clients/{$employee->client_id}/employees/{$employee->id}/documents/{$document->id}", $disk);

                $document->files()->make()->forceFill([
                    'disk' => $disk,
                    'path' => $path,
                    'name' => $file->getClientOriginalName(),
                    'mime' => $file->getClientMimeType() ?: 'application/octet-stream',
                    'size' => $file->getSize() ?: 0,
                ])->save();
            }

            $this->audit->record($document, $document->wasRecentlyCreated ? 'created' : 'updated', ['type' => $type->name_en], $actor);

            return $document->load('files');
        });
    }

    public function deleteFile(EmployeeDocument $document, int $fileId): void
    {
        $file = $document->files()->findOrFail($fileId);
        Storage::disk($file->disk)->delete($file->path);
        $file->delete();
    }
}

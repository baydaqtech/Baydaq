<?php

namespace App\Modules\Platform\Actions\Suite;

use App\Modules\Platform\Actions\Audit\RecordAuditEvent;
use App\Modules\Platform\Models\Branch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteBranch
{
    public function __construct(private RecordAuditEvent $audit) {}

    /**
     * Remove a branch and the grants that pointed at it. The head office cannot be deleted;
     * modules that keep rows on a branch block the delete through their own foreign keys.
     */
    public function handle(Branch $branch): void
    {
        if ($branch->is_head_office) {
            throw ValidationException::withMessages(['branch' => __('suite.structure.errors.head_office_delete')]);
        }

        DB::transaction(function () use ($branch): void {
            $this->audit->handle($branch, 'deleted', ['code' => $branch->code, 'name_en' => $branch->name_en]);
            $branch->delete();
        });
    }
}

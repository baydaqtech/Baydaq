<?php

namespace App\Modules\Platform\Actions\Suite;

use App\Modules\Platform\Actions\Audit\RecordAuditEvent;
use App\Modules\Platform\Models\Branch;
use Illuminate\Support\Facades\DB;

class SaveBranch
{
    public function __construct(private RecordAuditEvent $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Branch $branch, array $data): Branch
    {
        return DB::transaction(function () use ($branch, $data): Branch {
            $branch->fill($data)->save();
            $this->audit->handle($branch, $branch->wasRecentlyCreated ? 'created' : 'updated');

            return $branch;
        });
    }
}

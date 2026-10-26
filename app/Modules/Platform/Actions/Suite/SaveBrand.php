<?php

namespace App\Modules\Platform\Actions\Suite;

use App\Modules\Platform\Actions\Audit\RecordAuditEvent;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Brand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveBrand
{
    public function __construct(private RecordAuditEvent $audit) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $branchIds  branches of the same company that serve this brand
     */
    public function handle(Brand $brand, array $data, array $branchIds): Brand
    {
        $companyId = (int) ($data['company_id'] ?? $brand->company_id);
        $branchIds = array_values(array_unique(array_map('intval', $branchIds)));

        if (Branch::query()->where('company_id', $companyId)->whereIn('id', $branchIds)->count() !== count($branchIds)) {
            throw ValidationException::withMessages(['branch_ids' => __('suite.structure.errors.brand_branches')]);
        }

        return DB::transaction(function () use ($brand, $data, $companyId, $branchIds): Brand {
            $brand->fill([...$data, 'company_id' => $companyId, 'slug' => ($data['slug'] ?? null) ?: Str::slug($data['name_en'])]);

            if (! $brand->exists && empty($brand->client_id)) {
                $brand->client_id = Branch::query()->where('company_id', $companyId)->value('client_id');
            }

            $brand->save();
            $brand->branches()->sync($branchIds);
            $this->audit->handle($brand, $brand->wasRecentlyCreated ? 'created' : 'updated', ['branch_ids' => $branchIds]);

            return $brand->load('branches');
        });
    }
}

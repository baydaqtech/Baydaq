<?php

namespace App\Modules\Platform\Actions\Suite;

use App\Modules\Platform\Actions\Audit\RecordAuditEvent;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaveCompany
{
    public function __construct(private RecordAuditEvent $audit) {}

    /**
     * Create or update a company. A new company gets its head office branch at once, so
     * every employee and every grant always has a branch to point at.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Company $company, array $data): Company
    {
        return DB::transaction(function () use ($company, $data): Company {
            $company->fill([
                ...$data,
                'slug' => ($data['slug'] ?? null) ?: Str::slug($data['name_en']),
            ]);

            // Set explicitly rather than through the tenant hook: seeders run without model events.
            if (! $company->exists && empty($company->client_id)) {
                $company->client_id = Organization::query()->findOrFail($company->organization_id)->client_id;
            }

            $company->save();

            if ($company->wasRecentlyCreated) {
                $company->branches()->make([
                    'code' => 'HQ',
                    'name_ar' => __('suite.structure.head_office', [], 'ar'),
                    'name_en' => __('suite.structure.head_office', [], 'en'),
                ])->forceFill(['client_id' => $company->client_id, 'is_head_office' => true])->save();
            }

            $this->audit->handle($company, $company->wasRecentlyCreated ? 'created' : 'updated');

            return $company;
        });
    }
}

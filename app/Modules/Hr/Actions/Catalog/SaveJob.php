<?php

namespace App\Modules\Hr\Actions\Catalog;

use App\Modules\Hr\Models\Job;
use App\Modules\Hr\Models\Section;
use App\Modules\Platform\Ports\Access;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveJob
{
    public function __construct(private Access $access) {}

    /**
     * @param  array{name_ar: string, name_en: string, is_active?: bool, sort_order?: int, default_role_id?: ?int}  $data
     * @param  list<int>  $sectionIds  sections the job may sit under (at least one)
     * @param  list<int>  $blockedCompanyIds
     */
    public function handle(Job $job, array $data, array $sectionIds, array $blockedCompanyIds = []): Job
    {
        SaveSection::ensureUniqueNames(Job::query(), $data, $job->id);

        $sectionIds = array_values(array_unique(array_map('intval', $sectionIds)));

        if ($sectionIds === [] || Section::query()->whereIn('id', $sectionIds)->count() !== count($sectionIds)) {
            throw ValidationException::withMessages(['section_ids' => __('hr.catalog.errors.sections_required')]);
        }

        if (($data['default_role_id'] ?? null) !== null && ! in_array((int) $data['default_role_id'], array_column($this->access->roles(), 'id'), true)) {
            throw ValidationException::withMessages(['default_role_id' => __('hr.catalog.errors.role_unknown')]);
        }

        return DB::transaction(function () use ($job, $data, $sectionIds, $blockedCompanyIds): Job {
            $job->fill($data)->normalizeNames()->save();
            $job->sections()->sync($sectionIds);
            $job->getConnection()->table('hr__job_company_blocks')->where('job_id', $job->id)->delete();
            $job->getConnection()->table('hr__job_company_blocks')->insert(
                array_map(fn (int $companyId): array => ['job_id' => $job->id, 'company_id' => $companyId], array_values(array_unique($blockedCompanyIds))),
            );

            return $job->load('sections');
        });
    }
}

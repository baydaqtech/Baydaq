<?php

namespace App\Modules\Platform\Actions\Suite;

use App\Modules\Platform\Access\EnsureWithinLimit;
use App\Modules\Platform\Actions\Audit\RecordAuditEvent;
use App\Modules\Platform\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaveOrganization
{
    public function __construct(private RecordAuditEvent $audit) {}

    /**
     * @param  array{name_ar: string, name_en: string, slug?: string|null, status?: string}  $data
     */
    public function handle(Organization $organization, array $data): Organization
    {
        return DB::transaction(function () use ($organization, $data): Organization {
            if (! $organization->exists) {
                app(EnsureWithinLimit::class)->handle('organizations', Organization::query()->count());
            }

            $organization->fill([
                ...$data,
                'slug' => ($data['slug'] ?? null) ?: Str::slug($data['name_en']),
            ])->save();

            $this->audit->handle($organization, $organization->wasRecentlyCreated ? 'created' : 'updated');

            return $organization;
        });
    }
}

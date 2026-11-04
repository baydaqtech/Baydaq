<?php

namespace App\Modules\Hr\Actions\Catalog;

use App\Modules\Hr\Models\Section;
use App\Modules\Hr\Support\ArabicText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveSection
{
    /**
     * @param  array{name_ar: string, name_en: string, is_active?: bool, sort_order?: int}  $data
     * @param  list<int>  $blockedCompanyIds  companies where the section may not be used
     */
    public function handle(Section $section, array $data, array $blockedCompanyIds = []): Section
    {
        $this->ensureUniqueNames(Section::query(), $data, $section->id);

        return DB::transaction(function () use ($section, $data, $blockedCompanyIds): Section {
            $section->fill($data)->normalizeNames()->save();
            $section->getConnection()->table('hr__section_company_blocks')->where('section_id', $section->id)->delete();
            $section->getConnection()->table('hr__section_company_blocks')->insert(
                array_map(fn (int $companyId): array => ['section_id' => $section->id, 'company_id' => $companyId], array_values(array_unique($blockedCompanyIds))),
            );

            return $section;
        });
    }

    /**
     * Same normalized name in either language means the same thing; refuse it with a clear message.
     *
     * @param  Builder<Model>  $query
     * @param  array{name_ar: string, name_en: string}  $data
     */
    public static function ensureUniqueNames($query, array $data, ?int $ignoreId): void
    {
        foreach (['ar', 'en'] as $lang) {
            $taken = (clone $query)->where("name_{$lang}_key", ArabicText::key($data["name_{$lang}"]))
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages(["name_{$lang}" => __('hr.catalog.errors.name_taken')]);
            }
        }
    }
}

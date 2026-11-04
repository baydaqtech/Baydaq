<?php

namespace App\Modules\Hr\Actions\Shifts;

use App\Modules\Hr\Models\Holiday;
use App\Modules\Platform\Tenancy\CurrentTenant;

/**
 * Fill in Saudi Arabia's official holidays and Ramadan for a year (H9). The Hijri ones are the
 * expected dates; the government announces the exact days by moon sighting, so HR adjusts
 * them here when it does. Existing entries with the same name and year are left alone.
 */
class LoadSaudiHolidays
{
    /**
     * Expected dates (Umm al-Qura). Eid holidays follow the private-sector rule of four days.
     *
     * @var array<int, list<array{0: string, 1: string, 2: string, 3: string, 4: string}>>
     */
    public const CALENDAR = [
        2026 => [
            ['يوم التأسيس', 'Founding Day', 'public', '2026-02-22', '2026-02-22'],
            ['رمضان', 'Ramadan', 'ramadan', '2026-02-18', '2026-03-19'],
            ['عيد الفطر', 'Eid al-Fitr', 'public', '2026-03-20', '2026-03-23'],
            ['عيد الأضحى', 'Eid al-Adha', 'public', '2026-05-26', '2026-05-29'],
            ['اليوم الوطني', 'National Day', 'public', '2026-09-23', '2026-09-23'],
        ],
        2027 => [
            ['رمضان', 'Ramadan', 'ramadan', '2027-02-08', '2027-03-09'],
            ['يوم التأسيس', 'Founding Day', 'public', '2027-02-22', '2027-02-22'],
            ['عيد الفطر', 'Eid al-Fitr', 'public', '2027-03-10', '2027-03-13'],
            ['عيد الأضحى', 'Eid al-Adha', 'public', '2027-05-16', '2027-05-19'],
            ['اليوم الوطني', 'National Day', 'public', '2027-09-23', '2027-09-23'],
        ],
    ];

    public function __construct(private CurrentTenant $tenant) {}

    /** Returns how many entries were added. */
    public function handle(int $year): int
    {
        $added = 0;

        foreach (self::CALENDAR[$year] ?? [] as [$ar, $en, $kind, $starts, $ends]) {
            $exists = Holiday::query()->where('name_en', $en)->whereYear('starts_on', $year)->exists();

            if (! $exists) {
                (new Holiday(['name_ar' => $ar, 'name_en' => $en, 'kind' => $kind, 'starts_on' => $starts, 'ends_on' => $ends]))
                    ->forceFill(['client_id' => $this->tenant->client()->id])->save();
                $added++;
            }
        }

        return $added;
    }
}

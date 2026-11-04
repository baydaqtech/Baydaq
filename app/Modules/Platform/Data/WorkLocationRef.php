<?php

namespace App\Modules\Platform\Data;

final readonly class WorkLocationRef
{
    /**
     * @param  list<int>  $branchIds
     * @param  list<string>  $wifiBssids
     */
    public function __construct(
        public int $id,
        public string $name,
        public float $latitude,
        public float $longitude,
        public int $radiusM,
        public array $branchIds,
        public array $wifiBssids,
    ) {}

    /** Metres from a point to the centre (haversine). */
    public function distanceTo(float $latitude, float $longitude): float
    {
        $earth = 6371000;
        $dLat = deg2rad($latitude - $this->latitude);
        $dLng = deg2rad($longitude - $this->longitude);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($this->latitude)) * cos(deg2rad($latitude)) * sin($dLng / 2) ** 2;

        return 2 * $earth * asin(min(1, sqrt($a)));
    }
}

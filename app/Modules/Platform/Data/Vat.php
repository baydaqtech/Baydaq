<?php

namespace App\Modules\Platform\Data;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\Rule;

/**
 * Saudi VAT, the same in every module that prices something: rates are percentages (15, 5 or 0)
 * and the VAT inside a VAT-inclusive price is split out the same way, to the halala.
 */
final class Vat
{
    public const STANDARD = '15';

    /** @var list<string> */
    public const RATES = ['0', '5', '15'];

    /**
     * Validation for a rate field ("15", "15.00", "5", "0"…).
     *
     * @return list<mixed>
     */
    public static function rateRules(): array
    {
        return ['numeric', Rule::in(array_merge(...array_map(fn (string $rate): array => [$rate, "{$rate}.0", "{$rate}.00"], self::RATES)))];
    }

    /**
     * A VAT-inclusive amount split into net and VAT; the two always add back up to it.
     *
     * @return array{net: BigDecimal, vat: BigDecimal}
     */
    public static function splitInclusive(BigDecimal|string $gross, BigDecimal|string $rate): array
    {
        $gross = BigDecimal::of($gross)->toScale(2, RoundingMode::HalfUp);
        $net = $gross->dividedBy(BigDecimal::one()->plus(BigDecimal::of($rate)->dividedBy(100, 6, RoundingMode::HalfUp)), 2, RoundingMode::HalfUp);

        return ['net' => $net, 'vat' => $gross->minus($net)];
    }
}

<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Enums;

/**
 * The unit of a custom subscription interval (`interval_unit` + `interval_count`).
 *
 * @see https://docs.acquiring.alif.tj/subscription
 */
enum IntervalUnit: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    /** The largest `interval_count` the gateway accepts for this unit. */
    public function maxCount(): int
    {
        return match ($this) {
            self::Day => 90,
            self::Week => 52,
            self::Month => 24,
            self::Year => 3,
        };
    }
}

<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Enums;

/**
 * A preset subscription schedule — one unit of time between charges.
 *
 * @see https://docs.acquiring.alif.tj/subscription
 */
enum SubscriptionPeriod: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
}

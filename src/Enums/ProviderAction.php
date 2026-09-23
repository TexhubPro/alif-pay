<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Enums;

/**
 * The three requests Alif sends to a provider's endpoint.
 *
 * @see https://alifcapital.github.io/providers
 */
enum ProviderAction: string
{
    /** Does this subscriber exist and can the payment be taken? */
    case Check = 'check';

    /** Credit the subscriber's balance. */
    case Pay = 'pay';

    /** What happened to a payment that was sent earlier? */
    case Status = 'status';
}

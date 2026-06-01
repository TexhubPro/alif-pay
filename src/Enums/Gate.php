<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Enums;

/**
 * Payment gateways (the `gate` header / body field) supported by Alif WebCheckout.
 *
 * @see https://docs.acquiring.alif.tj/payments
 */
enum Gate: string
{
    /** Korti Milli national card (default gateway). */
    case KortiMilli = 'korti_milli';

    /** Alif mobi wallet. */
    case Wallet = 'wallet';

    /** Salom credit line / installment. */
    case Salom = 'salom';

    /** Cash invoice payment. */
    case Invoice = 'invoice';

    /** Visa (international card). */
    case Visa = 'vsa';

    /** Mastercard (international card). */
    case Mastercard = 'mcr';

    /** Cybersource hosted checkout. */
    case CybersourceCheckout = 'cybersource_checkout';

    public function isInstallment(): bool
    {
        return $this === self::Salom;
    }

    public function requiresInvoices(): bool
    {
        return $this === self::Salom || $this === self::Invoice;
    }
}

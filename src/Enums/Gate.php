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

    /**
     * Visa (international card), paid on Alif's own form.
     *
     * The docs name it `visa`. Older Alif examples used `vsa`; that value is still reachable
     * through {@see \TexHub\AlifPay\Requests\PaymentRequest::gateValue()} for terminals set up
     * before the rename.
     */
    case Visa = 'visa';

    /**
     * Mastercard (international card), paid on Alif's own form. Legacy value: `mcr`.
     */
    case Mastercard = 'mastercard';

    /** International cards processed and shown on Cybersource's hosted checkout form. */
    case CybersourceCheckout = 'cybersource_checkout';

    public function isInstallment(): bool
    {
        return $this === self::Salom;
    }

    /**
     * Whether the goods list (`invoices`) is expected with this gate.
     *
     * The docs make `invoices` mandatory only where the terminal is set up to demand it, and show
     * it on Salom alone; a cash invoice is sent without one.
     */
    public function requiresInvoices(): bool
    {
        return $this === self::Salom;
    }

    /** Whether `info_hash` may accompany the request — the docs allow it on Korti Milli only. */
    public function acceptsInfoHash(): bool
    {
        return $this === self::KortiMilli;
    }
}

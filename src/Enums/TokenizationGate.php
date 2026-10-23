<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Enums;

/**
 * Tokenization gateways (the `gate` field used when binding a payment method).
 *
 * @see https://docs.acquiring.alif.tj/tokenization
 */
enum TokenizationGate: string
{
    case KortiMilli = 'tokenization_korti_milli';
    case Wallet = 'tokenization_wallet';
    case Salom = 'tokenization_salom';
    case Tcell = 'tokenization_tcell';
    case Megafon = 'tokenization_megafon';
    case Babilon = 'tokenization_babilon';
    case ZetMobile = 'tokenization_zetmobile';

    /** Visa card, confirmed by the issuer's 3-D Secure rather than a one-time code. */
    case Visa = 'tokenization_visa';

    /** Mastercard, confirmed by the issuer's 3-D Secure rather than a one-time code. */
    case Mastercard = 'tokenization_mastercard';

    /**
     * Visa / Mastercard through Procard.
     *
     * @deprecated The current docs bind international cards with {@see self::Visa} and
     *             {@see self::Mastercard} and do not mention Procard. Kept for terminals that were
     *             set up with it.
     */
    case Procard = 'tokenization_procard';

    /** Whether the binding is confirmed by 3-D Secure at the card's bank. */
    public function isInternationalCard(): bool
    {
        return $this === self::Visa || $this === self::Mastercard || $this === self::Procard;
    }
}

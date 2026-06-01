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

    /** Visa / Mastercard via Procard. */
    case Procard = 'tokenization_procard';
}

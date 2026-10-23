<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Enums;

/**
 * The `event` field every callback carries.
 *
 * A payment callback (standard, marketplace or a subscription charge) says `payment` and describes
 * the whole order. A delivery confirmation or a cancellation says `confirm` or `cancel` and
 * describes one transaction — and is signed with a different formula, so the event decides how a
 * callback is verified. A tokenization callback carries `tokenize` inside its `Payload`.
 *
 * @see https://docs.acquiring.alif.tj/payments
 * @see https://docs.acquiring.alif.tj/marketplace
 * @see https://docs.acquiring.alif.tj/tokenization
 */
enum CallbackEvent: string
{
    /** A payment reached a result; signed over orderId . status . transactionId. */
    case Payment = 'payment';

    /** Delivery was confirmed and the held funds were released to the seller terminal. */
    case Confirm = 'confirm';

    /** The transaction was cancelled, fully or partially. */
    case Cancel = 'cancel';

    /** A payment method was bound (tokenization callback). */
    case Tokenize = 'tokenize';

    /** Whether the callback describes one transaction and is signed over terminal_id . transaction_id . status. */
    public function isTransactionEvent(): bool
    {
        return $this === self::Confirm || $this === self::Cancel;
    }
}

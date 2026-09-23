<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Enums;

/**
 * The `event` field that marks a marketplace sub-transaction callback.
 *
 * A payment callback carries the whole transaction and has no `event`; a delivery confirmation
 * or a cancellation carries one sub-transaction and names the event that produced it.
 *
 * @see https://docs.acquiring.alif.tj/marketplace
 */
enum CallbackEvent: string
{
    /** Delivery was confirmed and the held funds were released to the partner terminal. */
    case Confirm = 'confirm';

    /** The sub-transaction was cancelled, fully or partially. */
    case Cancel = 'cancel';
}

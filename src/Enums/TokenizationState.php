<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Enums;

/**
 * The `status` string of a tokenization, as carried by the tokenization callback payload and by
 * the status lookup (`POST /tokenization/status`).
 *
 * Not to be confused with {@see TokenizationStatus}, which is the numeric `code` at the root of
 * the callback body.
 *
 * @see https://docs.acquiring.alif.tj/tokenization
 */
enum TokenizationState: string
{
    /** Accepted, still being processed. */
    case Accepted = 'accepted';

    /** Finished: the token in the payload can be charged. */
    case Approved = 'approved';

    /** A token for this orderId already exists — use the existing one. */
    case Duplicate = 'duplicate';

    /** The binding failed; offer the customer another attempt. */
    case Failed = 'failed';

    /** The token was removed and can no longer be charged. */
    case Removed = 'removed';

    /** Whether a token bound in this state can be charged. */
    public function isUsable(): bool
    {
        return $this === self::Approved || $this === self::Duplicate;
    }

    public function isFinal(): bool
    {
        return $this !== self::Accepted;
    }
}

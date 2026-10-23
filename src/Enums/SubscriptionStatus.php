<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Enums;

/**
 * The state of a subscription (`payload.status` / `payload.subscription_status`).
 *
 * A new subscription is `accepted` and becomes `approved` only once its first charge succeeds; if
 * that first charge fails it ends `failed` with no retry.
 *
 * @see https://docs.acquiring.alif.tj/subscription
 */
enum SubscriptionStatus: string
{
    /** Created, waiting for the first charge. */
    case Accepted = 'accepted';

    /** Active: charges run on schedule. */
    case Approved = 'approved';

    /** The first charge failed; the subscription was never activated. */
    case Failed = 'failed';

    /** Cancelled by the merchant. */
    case Canceled = 'canceled';

    /** The end date passed and every charge is closed. */
    case Completed = 'completed';

    public function isFinal(): bool
    {
        return $this === self::Failed || $this === self::Canceled || $this === self::Completed;
    }

    public function isActive(): bool
    {
        return $this === self::Approved;
    }

    /** Whether `POST /subscription/cancel` still accepts it. */
    public function isCancelable(): bool
    {
        return $this === self::Accepted || $this === self::Approved;
    }
}

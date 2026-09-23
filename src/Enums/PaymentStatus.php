<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Enums;

/**
 * Transaction statuses delivered through callbacks and status checks.
 *
 * Standard payments move between pending, ok, failed, canceled and partially_canceled;
 * marketplace payments add the hold states to_approve, partially_approved and approved.
 *
 * @see https://docs.acquiring.alif.tj/payments
 * @see https://docs.acquiring.alif.tj/marketplace
 */
enum PaymentStatus: string
{
    /** Transaction created, awaiting payment by the user. */
    case Pending = 'pending';

    /** Funds held, awaiting capture confirmation. */
    case ToApprove = 'to_approve';

    /** Payment completed successfully (funds captured). */
    case Ok = 'ok';

    /** Funds settled with the merchant; still cancellable. */
    case Settled = 'settled';

    /** Delivery confirmed / captured (marketplace). */
    case Approved = 'approved';

    /** Payment failed (insufficient funds, declined, …). */
    case Failed = 'failed';

    /** Payment cancelled by user or system. */
    case Canceled = 'canceled';

    /** Partially approved (marketplace). */
    case PartiallyApproved = 'partially_approved';

    /** Partially approved — alternate spelling used in some marketplace callbacks. */
    case PartialApproved = 'partial_approved';

    /** Partially cancelled (marketplace). */
    case PartiallyCanceled = 'partially_canceled';

    /** A final status means no further state changes are expected. */
    public function isFinal(): bool
    {
        return match ($this) {
            self::Ok, self::Settled, self::Approved, self::Failed, self::Canceled => true,
            default => false,
        };
    }

    /** Whether this status represents a successful, money-captured outcome. */
    public function isSuccessful(): bool
    {
        return $this === self::Ok || $this === self::Settled || $this === self::Approved;
    }

    public function isFailedOrCanceled(): bool
    {
        return $this === self::Failed || $this === self::Canceled;
    }
}

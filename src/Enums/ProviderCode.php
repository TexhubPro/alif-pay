<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Enums;

/**
 * Result codes a provider endpoint must answer with.
 *
 * Fatal means Alif gives up on the payment immediately; non-fatal means it will keep retrying at
 * growing intervals until the payment's 24-hour life runs out. Returning a fatal code for a
 * temporary problem throws the payment away, so {@see isFatal()} is worth reading before picking
 * one.
 *
 * @see https://alifcapital.github.io/providers
 */
enum ProviderCode: int
{
    /** Payment registered (pay / status). */
    case Success = 200;

    /** In progress — Alif will ask again (pay / status). */
    case InProgress = 201;

    /** Payment rejected (pay / status). */
    case Rejected = 203;

    /** Subscriber found and payable (check). */
    case AccountFound = 302;

    /** Subscriber id not found — wrong number. */
    case AccountNotFound = 404;

    /** No such transaction (status). */
    case TransactionNotFound = 104;

    /** Transaction already exists (pay). */
    case TransactionExists = 107;

    /** Already completed, this is a duplicate (pay). */
    case DuplicateSuccess = 108;

    /** The service is temporarily unavailable. */
    case ServiceUnavailable = 303;

    /** Not enough funds on the account. */
    case InsufficientFunds = 305;

    /** Malformed request: bad data or format. */
    case BadRequest = 400;

    /** Authorization failed. */
    case Unauthorized = 401;

    /** Amount outside the allowed range. */
    case AmountOutOfRange = 405;

    /** Internal error on the provider's side. */
    case InternalError = 500;

    /** The provider's service is down. */
    case Unavailable = 503;

    /** Unknown error — Alif retries. */
    case UnknownError = 520;

    /**
     * Whether Alif stops on this code instead of retrying the same request later.
     */
    public function isFatal(): bool
    {
        return match ($this) {
            self::InProgress,
            self::TransactionExists,
            self::InsufficientFunds,
            self::UnknownError => false,
            default => true,
        };
    }

    public function isSuccess(): bool
    {
        return $this === self::Success || $this === self::DuplicateSuccess;
    }
}

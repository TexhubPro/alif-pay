<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Enums;

/**
 * API response codes returned in the `code` field of every response.
 *
 * The `code` mirrors the HTTP status of the answer — a duplicate order arrives as HTTP 208 with
 * `code: 208`, a rejected token as HTTP 401 with `code: 401` — so either one can be read. Status
 * lookups are the exception: a transaction that was found is returned as itself and carries no
 * `code` at all.
 *
 * Only 500 is marked as worth retrying in the docs. 202 answers a subscription that was accepted
 * for processing; 203 is how `/pay/token` says the gateway declined the charge, and must be
 * treated as a failure despite looking like a 2xx.
 *
 * @see https://docs.acquiring.alif.tj/intro
 */
enum ResponseCode: int
{
    case Success = 200;

    /** The request was accepted and is being processed (subscription creation). */
    case Accepted = 202;

    /** The payment gateway declined a token charge. A failure, despite the 2xx number. */
    case Declined = 203;

    case Duplicate = 208;
    case BadRequest = 400;
    case Unauthorized = 401;
    /** Not in the current docs; kept so an older gateway answer still parses. */
    case Forbidden = 403;
    case NotFound = 404;
    case InternalError = 500;

    public function isSuccess(): bool
    {
        return $this === self::Success;
    }

    /** Whether retrying the same request later may succeed — per the docs, only a 500. */
    public function isRetryable(): bool
    {
        return $this === self::InternalError;
    }

    public static function tryFromInt(?int $code): ?self
    {
        return $code === null ? null : self::tryFrom($code);
    }
}

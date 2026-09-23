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
 * @see https://docs.acquiring.alif.tj/intro
 */
enum ResponseCode: int
{
    case Success = 200;
    case Duplicate = 208;
    case BadRequest = 400;
    case Unauthorized = 401;
    case Forbidden = 403;
    case NotFound = 404;
    case InternalError = 500;

    public function isSuccess(): bool
    {
        return $this === self::Success;
    }

    /** Whether retrying the same request later may succeed. */
    public function isRetryable(): bool
    {
        return $this === self::NotFound || $this === self::InternalError;
    }

    public static function tryFromInt(?int $code): ?self
    {
        return $code === null ? null : self::tryFrom($code);
    }
}

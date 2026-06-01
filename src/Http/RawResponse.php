<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Http;

/**
 * Raw transport result: the HTTP status code and the undecoded response body.
 */
final class RawResponse
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $body,
    ) {
    }
}

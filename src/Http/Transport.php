<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Http;

use TexHub\AlifPay\Exceptions\TransportException;

/**
 * Minimal HTTP transport abstraction so the SDK has no hard dependency on a
 * specific HTTP client and can be fully unit-tested with a fake.
 */
interface Transport
{
    /**
     * Perform a JSON POST request.
     *
     * @param string                $url      Absolute URL.
     * @param array<string, mixed>  $body     Request body (will be JSON-encoded).
     * @param array<string, string> $headers  Extra headers (Content-Type is added automatically).
     *
     * @return RawResponse  Status code and raw body.
     *
     * @throws TransportException On connection/network failures.
     */
    public function post(string $url, array $body, array $headers = []): RawResponse;
}

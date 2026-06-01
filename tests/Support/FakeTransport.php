<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Tests\Support;

use TexHub\AlifPay\Http\RawResponse;
use TexHub\AlifPay\Http\Transport;

/**
 * In-memory transport for tests: records the last request and returns a
 * queued/canned response without touching the network.
 */
final class FakeTransport implements Transport
{
    public ?string $lastUrl = null;

    /** @var array<string, mixed>|null */
    public ?array $lastBody = null;

    /** @var array<string, string> */
    public array $lastHeaders = [];

    public int $calls = 0;

    public function __construct(
        private int $statusCode = 200,
        private string $body = '{"code":200,"message":"Успешно","url":"https://web.alif.tj/abc123"}',
    ) {
    }

    public function willReturn(int $statusCode, string $body): self
    {
        $this->statusCode = $statusCode;
        $this->body = $body;

        return $this;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function willReturnJson(array $payload, int $statusCode = 200): self
    {
        return $this->willReturn($statusCode, (string) json_encode($payload));
    }

    public function post(string $url, array $body, array $headers = []): RawResponse
    {
        $this->calls++;
        $this->lastUrl = $url;
        $this->lastBody = $body;
        $this->lastHeaders = $headers;

        return new RawResponse($this->statusCode, $this->body);
    }
}

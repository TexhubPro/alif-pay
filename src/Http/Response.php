<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Http;

use TexHub\AlifPay\Enums\ResponseCode;

/**
 * A decoded, successful Alif API response: `{ code, message, url }` plus the
 * full raw payload for any additional fields.
 */
final class Response
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly int $code,
        public readonly string $message,
        public readonly ?string $url,
        public readonly array $raw = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $url = $data['url'] ?? null;

        return new self(
            code: (int) ($data['code'] ?? 0),
            message: (string) ($data['message'] ?? ''),
            url: ($url === null || $url === '') ? null : (string) $url,
            raw: $data,
        );
    }

    public function responseCode(): ?ResponseCode
    {
        return ResponseCode::tryFromInt($this->code);
    }

    public function isSuccessful(): bool
    {
        return $this->code === ResponseCode::Success->value;
    }

    /**
     * The redirect URL the customer must be sent to (alias of {@see $url}).
     */
    public function redirectUrl(): ?string
    {
        return $this->url;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->raw[$key] ?? $default;
    }
}

<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Webhook;

use TexHub\AlifPay\Enums\TokenizationStatus;

/**
 * Parsed tokenization callback payload. Note its structure differs from a
 * normal payment callback: the result code lives at the root and the data is
 * nested under `payload`.
 *
 * @see https://docs.acquiring.alif.tj/tokenization
 */
final class TokenizationCallback
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly int $code,
        public readonly ?TokenizationStatus $statusCode,
        public readonly string $message,
        public readonly string $reasonCode,
        public readonly ?int $transactionId,
        public readonly string $orderId,
        public readonly string $token,
        public readonly ?string $account,
        public readonly string $status,
        public readonly ?string $transactionType,
        public readonly array $payload,
        public readonly array $raw,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $payload = (array) ($data['payload'] ?? []);
        $code = (int) ($data['code'] ?? 0);

        return new self(
            code: $code,
            statusCode: TokenizationStatus::tryFrom($code),
            message: (string) ($data['message'] ?? ''),
            reasonCode: (string) ($data['reason_code'] ?? ''),
            transactionId: isset($payload['transactionId']) ? (int) $payload['transactionId'] : null,
            orderId: (string) ($payload['orderId'] ?? ''),
            token: (string) ($payload['token'] ?? ''),
            account: isset($payload['account']) ? (string) $payload['account'] : null,
            status: (string) ($payload['status'] ?? ''),
            transactionType: isset($payload['transaction_type']) ? (string) $payload['transaction_type'] : null,
            payload: $payload,
            raw: $data,
        );
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode === TokenizationStatus::Success;
    }

    public function isDuplicate(): bool
    {
        return $this->statusCode === TokenizationStatus::Duplicate;
    }
}

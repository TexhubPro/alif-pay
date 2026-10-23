<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Webhook;

use TexHub\AlifPay\Enums\TokenizationState;
use TexHub\AlifPay\Enums\TokenizationStatus;

/**
 * Parsed tokenization callback payload. Its structure differs from a payment callback: the result
 * code lives at the root and the data is nested under `Payload`.
 *
 * Alif sends the root keys in PascalCase — `Code`, `Message`, `Messages`, `ReasonCode`,
 * `Payload` — and `Payload.transaction_type` without the `tokenization_` prefix (`wallet`,
 * `korti_milli`, `visa`, …). Lowercase keys are still read so that older payloads parse.
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
        $pick = static fn (string $pascal, string $lower, mixed $default = null): mixed => $data[$pascal] ?? $data[$lower] ?? $default;

        $payload = (array) $pick('Payload', 'payload', []);
        $code = (int) $pick('Code', 'code', 0);

        return new self(
            code: $code,
            statusCode: TokenizationStatus::tryFrom($code),
            message: (string) $pick('Message', 'message', ''),
            reasonCode: (string) ($data['ReasonCode'] ?? $data['reason_code'] ?? $data['reasonCode'] ?? ''),
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

    /**
     * The bound method without the `tokenization_` prefix, however the payload spelled it.
     */
    public function method(): ?string
    {
        return $this->transactionType === null ? null : preg_replace('/^tokenization_/', '', $this->transactionType);
    }

    /**
     * The payload status as an enum: approved, failed, duplicate, accepted or removed.
     */
    public function state(): ?TokenizationState
    {
        return TokenizationState::tryFrom($this->status);
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

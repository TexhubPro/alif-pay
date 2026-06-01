<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Webhook;

use TexHub\AlifPay\Enums\PaymentStatus;

/**
 * A marketplace sub-transaction entry (one partner terminal).
 */
final class SubTransaction
{
    public function __construct(
        public readonly string $terminalId,
        public readonly string $transactionId,
        public readonly ?PaymentStatus $status,
        public readonly string $rawStatus,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rawStatus = (string) ($data['status'] ?? '');

        return new self(
            terminalId: (string) ($data['terminal_id'] ?? ''),
            transactionId: (string) ($data['transaction_id'] ?? ''),
            status: PaymentStatus::tryFrom($rawStatus),
            rawStatus: $rawStatus,
        );
    }
}

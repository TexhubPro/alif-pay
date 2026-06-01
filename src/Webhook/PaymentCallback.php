<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Webhook;

use TexHub\AlifPay\Enums\PaymentStatus;

/**
 * Parsed payment (and marketplace) callback payload.
 *
 * @see https://docs.acquiring.alif.tj/payments
 * @see https://docs.acquiring.alif.tj/marketplace
 */
final class PaymentCallback
{
    /**
     * @param array<int, SubTransaction> $subTransactions
     * @param array<string, mixed>       $raw
     */
    public function __construct(
        public readonly string $orderId,
        public readonly string $transactionId,
        public readonly ?PaymentStatus $status,
        public readonly string $rawStatus,
        public readonly string $token,
        public readonly float $amount,
        public readonly ?string $account,
        public readonly ?string $phone,
        public readonly ?string $transactionType,
        public readonly array $subTransactions,
        public readonly array $raw,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rawStatus = (string) ($data['status'] ?? '');

        $subs = [];
        foreach ((array) ($data['sub_transactions'] ?? []) as $sub) {
            if (is_array($sub)) {
                $subs[] = SubTransaction::fromArray($sub);
            }
        }

        return new self(
            orderId: (string) ($data['orderId'] ?? ''),
            transactionId: (string) ($data['transactionId'] ?? ''),
            status: PaymentStatus::tryFrom($rawStatus),
            rawStatus: $rawStatus,
            token: (string) ($data['token'] ?? ''),
            amount: (float) ($data['amount'] ?? 0),
            account: isset($data['account']) ? (string) $data['account'] : null,
            phone: isset($data['phone']) ? (string) $data['phone'] : null,
            transactionType: isset($data['transaction_type']) ? (string) $data['transaction_type'] : null,
            subTransactions: $subs,
            raw: $data,
        );
    }

    public function isSuccessful(): bool
    {
        return $this->status?->isSuccessful() ?? false;
    }

    public function isMarketplace(): bool
    {
        return $this->subTransactions !== [];
    }
}

<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Webhook;

use TexHub\AlifPay\Enums\CallbackEvent;
use TexHub\AlifPay\Enums\PaymentStatus;

/**
 * The callback Alif sends after a marketplace delivery confirmation or a cancellation.
 *
 * It describes one sub-transaction, not the whole order: there is no `orderId` and no `token`,
 * so it cannot be verified by signature. Treat it as a notification that something moved, and
 * confirm with a status lookup before acting on it.
 *
 * @see https://docs.acquiring.alif.tj/marketplace
 */
final class DeliveryCallback
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly string $terminalId,
        public readonly string $transactionId,
        public readonly ?PaymentStatus $status,
        public readonly string $rawStatus,
        public readonly ?CallbackEvent $event,
        public readonly string $rawEvent,
        public readonly string $parentTransactionId,
        public readonly ?PaymentStatus $parentStatus,
        public readonly string $rawParentStatus,
        public readonly array $raw,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rawStatus = (string) ($data['status'] ?? '');
        $rawEvent = (string) ($data['event'] ?? '');
        $rawParentStatus = (string) ($data['parent_status'] ?? '');

        return new self(
            terminalId: (string) ($data['terminal_id'] ?? ''),
            transactionId: (string) ($data['transaction_id'] ?? ''),
            status: PaymentStatus::tryFrom($rawStatus),
            rawStatus: $rawStatus,
            event: CallbackEvent::tryFrom($rawEvent),
            rawEvent: $rawEvent,
            parentTransactionId: (string) ($data['parent_transaction_id'] ?? ''),
            parentStatus: PaymentStatus::tryFrom($rawParentStatus),
            rawParentStatus: $rawParentStatus,
            raw: $data,
        );
    }

    /**
     * Whether a payload is a delivery/cancellation event rather than a payment callback.
     *
     * @param array<string, mixed> $data
     */
    public static function matches(array $data): bool
    {
        return isset($data['event']) || isset($data['parent_transaction_id']);
    }

    public function isConfirmation(): bool
    {
        return $this->event === CallbackEvent::Confirm;
    }

    public function isCancellation(): bool
    {
        return $this->event === CallbackEvent::Cancel;
    }

    /** Whether the parent order is now fully resolved, not just this one sub-transaction. */
    public function isParentFinal(): bool
    {
        return $this->parentStatus?->isFinal() ?? false;
    }
}

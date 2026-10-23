<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Webhook;

use TexHub\AlifPay\Enums\CallbackEvent;
use TexHub\AlifPay\Enums\PaymentStatus;

/**
 * The callback Alif sends after a delivery confirmation (`event: confirm`) or a cancellation
 * (`event: cancel`).
 *
 * It describes one transaction, not the whole order, and has no `orderId`. On a marketplace it is
 * a sub-transaction and comes with `parent_transaction_id` and `parent_status`; the cancellation of
 * a standard payment has neither. It carries a `token` signed over
 * `terminal_id . transaction_id . status` — a different formula from the payment callback — which
 * {@see WebhookHandler::verifyDeliveryCallback()} checks.
 *
 * @see https://docs.acquiring.alif.tj/payments
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
        public readonly string $token = '',
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
            token: (string) ($data['token'] ?? ''),
        );
    }

    /**
     * Whether a payload is a confirmation or cancellation event rather than a payment callback.
     *
     * Every payment callback now carries `"event": "payment"` too, so the presence of `event` alone
     * proves nothing: only `confirm` / `cancel`, a parent transaction, or a `terminal_id` without
     * an `orderId` mark this shape.
     *
     * @param array<string, mixed> $data
     */
    public static function matches(array $data): bool
    {
        $event = CallbackEvent::tryFrom((string) ($data['event'] ?? ''));

        if ($event !== null) {
            return $event->isTransactionEvent();
        }

        return isset($data['parent_transaction_id'])
            || (isset($data['terminal_id']) && ! isset($data['orderId']));
    }

    /** Whether this is a parent transaction's sub-transaction (marketplace) rather than a whole payment. */
    public function isSubTransaction(): bool
    {
        return $this->parentTransactionId !== '';
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

<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Requests;

use DateTimeInterface;
use TexHub\AlifPay\Enums\IntervalUnit;
use TexHub\AlifPay\Enums\SubscriptionPeriod;
use TexHub\AlifPay\Exceptions\ConfigurationException;

/**
 * Fluent builder for `POST /subscription/create`: a fixed amount charged on a schedule from a
 * payment method bound earlier through tokenization.
 *
 * The schedule is set exactly one way — a preset {@see period()} or a custom {@see every()} — and
 * the request refuses to be built with neither or both. `terminal_id` and `hash` are added by the
 * client.
 *
 * @see https://docs.acquiring.alif.tj/subscription
 */
final class SubscriptionRequest
{
    private Amount $amount;
    private ?SubscriptionPeriod $period = null;
    private ?IntervalUnit $intervalUnit = null;
    private ?int $intervalCount = null;
    private ?string $endDate = null;
    private ?string $callbackUrl = null;

    /**
     * @param string $orderId         Your id for the subscription, unique per terminal, up to 64 characters.
     * @param string $tokenRequestId  `Payload.transactionId` of the approved tokenization callback.
     * @param string $partnerClientId The `clientID` sent when the payment method was bound.
     */
    public function __construct(
        private readonly string $orderId,
        private readonly string $tokenRequestId,
        private readonly string $partnerClientId,
        int|float|string $amount,
    ) {
        if (trim($orderId) === '' || mb_strlen($orderId) > 64) {
            throw new ConfigurationException('Subscription order_id must be 1 to 64 characters.');
        }

        if (! preg_match('/^\d+$/', $tokenRequestId)) {
            throw new ConfigurationException('Subscription token_request_id must be the numeric tokenization id.');
        }

        if (trim($partnerClientId) === '') {
            throw new ConfigurationException('Subscription partner_client_id must not be empty.');
        }

        $this->amount = Amount::of($amount);

        if ((float) $this->amount->value <= 0) {
            throw new ConfigurationException('Subscription amount must be positive.');
        }
    }

    public static function make(string $orderId, string $tokenRequestId, string $partnerClientId, int|float|string $amount): self
    {
        return new self($orderId, $tokenRequestId, $partnerClientId, $amount);
    }

    public function period(SubscriptionPeriod $period): self
    {
        $this->period = $period;

        return $this;
    }

    /**
     * Charge every `$count` units — every 3 months, every 2 weeks.
     */
    public function every(int $count, IntervalUnit $unit): self
    {
        if ($count < 1 || $count > $unit->maxCount()) {
            throw new ConfigurationException(sprintf(
                'interval_count for "%s" must be between 1 and %d.',
                $unit->value,
                $unit->maxCount(),
            ));
        }

        $this->intervalUnit = $unit;
        $this->intervalCount = $count;

        return $this;
    }

    /**
     * When the charges stop. Without it the subscription runs until cancelled.
     */
    public function endDate(DateTimeInterface|string $date): self
    {
        $this->endDate = $date instanceof DateTimeInterface ? $date->format(DATE_RFC3339) : $date;

        return $this;
    }

    public function callbackUrl(string $url): self
    {
        $this->callbackUrl = $url;

        return $this;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getAmount(): string
    {
        return $this->amount->value;
    }

    public function getTokenRequestId(): string
    {
        return $this->tokenRequestId;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(?string $defaultCallbackUrl = null): array
    {
        $callbackUrl = $this->callbackUrl ?? $defaultCallbackUrl;

        if ($callbackUrl === null || $callbackUrl === '') {
            throw new ConfigurationException('A callback_url is required (set it on the request or in config).');
        }

        if ($this->period === null && $this->intervalUnit === null) {
            throw new ConfigurationException('A subscription needs a schedule: period() or every().');
        }

        if ($this->period !== null && $this->intervalUnit !== null) {
            throw new ConfigurationException('A subscription takes either period() or every(), not both.');
        }

        $body = [
            'order_id' => $this->orderId,
            'token_request_id' => $this->tokenRequestId,
            'partner_client_id' => $this->partnerClientId,
            'amount' => (float) $this->amount->value,
            'callback_url' => $callbackUrl,
        ];

        if ($this->period !== null) {
            $body['period'] = $this->period->value;
        } else {
            $body['interval_unit'] = $this->intervalUnit?->value;
            $body['interval_count'] = $this->intervalCount;
        }

        if ($this->endDate !== null) {
            $body['end_date'] = $this->endDate;
        }

        return $body;
    }
}

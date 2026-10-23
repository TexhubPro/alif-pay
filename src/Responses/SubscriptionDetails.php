<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Responses;

use TexHub\AlifPay\Enums\IntervalUnit;
use TexHub\AlifPay\Enums\SubscriptionPeriod;
use TexHub\AlifPay\Enums\SubscriptionStatus;
use TexHub\AlifPay\Http\Response;

/**
 * A subscription as `POST /subscription/status` reports it. The schedule comes back the way it was
 * created: either `period`, or `interval_unit` with `interval_count`. `endDate` is null for an
 * open-ended subscription.
 *
 * @see https://docs.acquiring.alif.tj/subscription
 */
final class SubscriptionDetails
{
    public function __construct(
        public readonly string $orderId,
        public readonly ?SubscriptionStatus $status,
        public readonly string $rawStatus,
        public readonly string $amount,
        public readonly string $currency,
        public readonly ?SubscriptionPeriod $period,
        public readonly ?IntervalUnit $intervalUnit,
        public readonly ?int $intervalCount,
        public readonly ?string $endDate,
        public readonly Response $response,
    ) {
    }

    public static function fromResponse(Response $response): self
    {
        $payload = (array) $response->get('payload', []);
        $rawStatus = (string) ($payload['status'] ?? '');

        return new self(
            orderId: (string) ($payload['order_id'] ?? ''),
            status: SubscriptionStatus::tryFrom($rawStatus),
            rawStatus: $rawStatus,
            amount: number_format((float) ($payload['amount'] ?? 0), 2, '.', ''),
            currency: (string) ($payload['currency'] ?? ''),
            period: isset($payload['period']) ? SubscriptionPeriod::tryFrom((string) $payload['period']) : null,
            intervalUnit: isset($payload['interval_unit']) ? IntervalUnit::tryFrom((string) $payload['interval_unit']) : null,
            intervalCount: isset($payload['interval_count']) ? (int) $payload['interval_count'] : null,
            endDate: isset($payload['end_date']) ? (string) $payload['end_date'] : null,
            response: $response,
        );
    }
}

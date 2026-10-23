<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Responses;

use TexHub\AlifPay\Enums\SubscriptionStatus;
use TexHub\AlifPay\Http\Response;

/**
 * The answer to `POST /subscription/create` (HTTP 202). The subscription exists but is only
 * `accepted`: it is active once the first charge succeeds — wait for that callback or look the
 * status up.
 *
 * @see https://docs.acquiring.alif.tj/subscription
 */
final class SubscriptionCreated
{
    public function __construct(
        public readonly int $subscriptionId,
        public readonly ?SubscriptionStatus $status,
        public readonly Response $response,
    ) {
    }

    public static function fromResponse(Response $response): self
    {
        $payload = (array) $response->get('payload', []);

        return new self(
            subscriptionId: (int) ($payload['subscription_id'] ?? 0),
            status: SubscriptionStatus::tryFrom((string) ($payload['subscription_status'] ?? '')),
            response: $response,
        );
    }
}

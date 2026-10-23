<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Clients;

use TexHub\AlifPay\Exceptions\ConfigurationException;
use TexHub\AlifPay\Http\Response;
use TexHub\AlifPay\Requests\SubscriptionRequest;
use TexHub\AlifPay\Responses\SubscriptionCreated;
use TexHub\AlifPay\Responses\SubscriptionDetails;
use TexHub\AlifPay\Signature;

/**
 * Subscriptions: regular charges from a bound payment method — create, look up, cancel.
 *
 * These endpoints name the terminal `terminal_id` (not `key`) and carry the signature in a body
 * field called `hash`. Every charge is reported to the callback URL as an ordinary payment callback
 * (`event: payment`, `info: "Оплата по подписке"`).
 *
 * @see https://docs.acquiring.alif.tj/subscription
 */
final class SubscriptionClient extends AbstractClient
{
    /**
     * Create a subscription and start its first charge (`POST /subscription/create`, answers 202).
     *
     * Signing string: terminal_id . order_id . amount . token_request_id, amount with two decimals.
     */
    public function create(SubscriptionRequest $request): SubscriptionCreated
    {
        $body = $request->toArray($this->config->callbackUrl);
        $body['terminal_id'] = $this->config->terminalId;
        $body['hash'] = $this->token(Signature::concat(
            $this->config->terminalId,
            $request->getOrderId(),
            $request->getAmount(),
            $request->getTokenRequestId(),
        ));

        return SubscriptionCreated::fromResponse($this->send('/subscription/create', $body, [], true, [200, 202]));
    }

    /**
     * Look a subscription up (`POST /subscription/status`).
     *
     * Signing string: terminal_id . order_id
     */
    public function status(string $orderId): SubscriptionDetails
    {
        return SubscriptionDetails::fromResponse($this->send('/subscription/status', $this->body($orderId)));
    }

    /**
     * Stop a subscription for good (`POST /subscription/cancel`). Charges already made are not
     * refunded by this — cancel those payments separately.
     *
     * Signing string: terminal_id . order_id
     */
    public function cancel(string $orderId): Response
    {
        return $this->send('/subscription/cancel', $this->body($orderId));
    }

    /**
     * @return array<string, string>
     */
    private function body(string $orderId): array
    {
        if (trim($orderId) === '' || mb_strlen($orderId) > 64) {
            throw new ConfigurationException('Subscription order_id must be 1 to 64 characters.');
        }

        return [
            'order_id' => $orderId,
            'terminal_id' => $this->config->terminalId,
            'hash' => $this->token(Signature::concat($this->config->terminalId, $orderId)),
        ];
    }
}

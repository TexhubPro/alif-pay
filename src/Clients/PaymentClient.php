<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Clients;

use TexHub\AlifPay\Http\Response;
use TexHub\AlifPay\Requests\Amount;
use TexHub\AlifPay\Requests\PaymentRequest;
use TexHub\AlifPay\Signature;

/**
 * Standard payments: initialize, check status and cancel.
 *
 * @see https://docs.acquiring.alif.tj/payments
 */
final class PaymentClient extends AbstractClient
{
    /**
     * Initialize a payment and obtain the redirect URL.
     *
     * Signing string: terminalId . order_id . amount . callback_url
     */
    public function initiate(PaymentRequest $request): Response
    {
        $body = $request->toArray($this->config->callbackUrl, $this->config->returnUrl);

        $body['key'] = $this->config->terminalId;
        $body['token'] = $this->token(Signature::concat(
            $this->config->terminalId,
            $body['order_id'],
            $body['amount'],
            $body['callback_url'],
        ));

        return $this->send('/v2/', $body, ['gate' => (string) $body['gate']]);
    }

    /**
     * Check the status of a standard transaction (`POST /checktxn`).
     *
     * The field is `orderId`, not `order_id`: this endpoint is the one place in the API that
     * spells it in camel case, and the request is rejected as an invalid order identifier if it
     * is sent any other way.
     *
     * A found transaction is returned as itself — `orderId`, `transactionId`, `status`, `token`,
     * `amount` — with no `code` field, so this response is not judged by one. Verify the `token`
     * it carries with {@see \TexHub\AlifPay\Webhook\WebhookHandler::verifyStatusToken()} before
     * acting on the status.
     *
     * Signing string: terminalId . orderId
     */
    public function checkStatus(string $orderId): Response
    {
        $body = [
            'key' => $this->config->terminalId,
            'orderId' => $orderId,
            'token' => $this->token(Signature::concat($this->config->terminalId, $orderId)),
        ];

        return $this->send('/checktxn', $body, [], false);
    }

    /**
     * Cancel / refund a standard transaction (`POST /cancel/standard`).
     *
     * Only a whole transaction can be cancelled here — the amount must equal the transaction's
     * own, and Salom payments can only be cancelled within 14 days of being created.
     *
     * Signing string: terminalId . transaction_id . amount
     */
    public function cancel(string $transactionId, int|float|string $amount, ?string $reason = null): Response
    {
        $normalized = Amount::of($amount)->value;

        $body = [
            'key' => $this->config->terminalId,
            'transaction_id' => $transactionId,
            'amount' => $normalized,
            'token' => $this->token(Signature::concat($this->config->terminalId, $transactionId, $normalized)),
        ];

        if ($reason !== null && trim($reason) !== '') {
            $body['reason'] = $reason;
        }

        return $this->send('/cancel/standard', $body);
    }
}

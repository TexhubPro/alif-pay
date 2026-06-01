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
     * Signing string: terminalId . order_id
     */
    public function checkStatus(string $orderId): Response
    {
        $body = [
            'key' => $this->config->terminalId,
            'order_id' => $orderId,
            'token' => $this->token(Signature::concat($this->config->terminalId, $orderId)),
        ];

        return $this->send('/checktxn', $body);
    }

    /**
     * Cancel / refund a standard transaction (`POST /cancel/standard`).
     *
     * Signing string: terminalId . transaction_id . amount
     */
    public function cancel(string $transactionId, int|float|string $amount): Response
    {
        $normalized = Amount::of($amount)->value;

        $body = [
            'key' => $this->config->terminalId,
            'transaction_id' => $transactionId,
            'amount' => $normalized,
            'token' => $this->token(Signature::concat($this->config->terminalId, $transactionId, $normalized)),
        ];

        return $this->send('/cancel/standard', $body);
    }
}

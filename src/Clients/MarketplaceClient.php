<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Clients;

use TexHub\AlifPay\Http\Response;
use TexHub\AlifPay\Requests\Amount;
use TexHub\AlifPay\Requests\MarketplaceRequest;
use TexHub\AlifPay\Signature;

/**
 * Marketplace split-payments: initialize, confirm delivery (release held funds)
 * and cancel.
 *
 * @see https://docs.acquiring.alif.tj/marketplace
 */
final class MarketplaceClient extends AbstractClient
{
    /**
     * Initialize a marketplace payment (header `isMarketPlace: true`).
     *
     * Signing string: terminalId . order_id . amount . callback_url
     */
    public function initiate(MarketplaceRequest $request): Response
    {
        $body = $request->toArray($this->config->callbackUrl, $this->config->returnUrl);

        $body['key'] = $this->config->terminalId;
        $body['token'] = $this->token(Signature::concat(
            $this->config->terminalId,
            $body['order_id'],
            $body['amount'],
            $body['callback_url'],
        ));

        return $this->send('/v2/', $body, [
            'gate' => (string) $body['gate'],
            'isMarketPlace' => 'true',
        ]);
    }

    /**
     * Confirm delivery for a held sub-transaction, releasing funds to the
     * partner terminal — for every method except Visa/Mastercard.
     *
     * Signing string: terminalId . transaction_id . amount
     */
    public function confirmDelivery(string $transactionId, int|float|string $amount): Response
    {
        $normalized = Amount::of($amount)->value;

        $body = [
            'key' => $this->config->terminalId,
            'transaction_id' => $transactionId,
            'amount' => $normalized,
            'token' => $this->token(Signature::concat($this->config->terminalId, $transactionId, $normalized)),
        ];

        return $this->send('/confirm-delivery', $body);
    }

    /**
     * Confirm delivery for Visa/Mastercard marketplace payments.
     *
     * Signing string: terminalId . parent_transaction_id
     */
    public function confirmVsaMcrDelivery(string $parentTransactionId): Response
    {
        $body = [
            'key' => $this->config->terminalId,
            'parent_transaction_id' => $parentTransactionId,
            'token' => $this->token(Signature::concat($this->config->terminalId, $parentTransactionId)),
        ];

        return $this->send('/confirm-vsa-and-mcr-delivery', $body);
    }

    /**
     * Check the status of a marketplace transaction (`POST /checktxn`).
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

        return $this->send('/checktxn', $body, ['isMarketPlace' => 'true']);
    }

    /**
     * Cancel / refund a marketplace transaction (`POST /cancel/marketplace`).
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

        return $this->send('/cancel/marketplace', $body);
    }
}

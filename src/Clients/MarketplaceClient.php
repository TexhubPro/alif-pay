<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Clients;

use TexHub\AlifPay\Exceptions\ConfigurationException;
use TexHub\AlifPay\Http\Response;
use TexHub\AlifPay\Requests\Amount;
use TexHub\AlifPay\Requests\DeliveryConfirmation;
use TexHub\AlifPay\Requests\MarketplaceRequest;
use TexHub\AlifPay\Signature;

/**
 * Marketplace split-payments: initialize, confirm delivery (release held funds) and cancel.
 *
 * Funds sit on hold until delivery is confirmed. Cancellation applies to a child transaction —
 * the parent cannot be cancelled directly.
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
     * Confirm delivery for a held sub-transaction, releasing funds to the partner terminal — for
     * every method except Visa/Mastercard.
     *
     * An amount below the held one confirms partially: the transaction moves to
     * `partially_approved` and the remainder can be confirmed by a further call.
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
     * Confirm delivery for Visa/Mastercard marketplace payments, where several child transactions
     * are confirmed in one call through the `extra` array.
     *
     * Signing string: terminalId . parent_transaction_id — the children are not signed.
     */
    public function confirmVsaMcrDelivery(string $parentTransactionId, DeliveryConfirmation ...$confirmations): Response
    {
        if ($confirmations === []) {
            throw new ConfigurationException(
                'A Visa/Mastercard delivery confirmation needs at least one child transaction.',
            );
        }

        $body = [
            'key' => $this->config->terminalId,
            'parent_transaction_id' => $parentTransactionId,
            'token' => $this->token(Signature::concat($this->config->terminalId, $parentTransactionId)),
            'extra' => array_map(static fn (DeliveryConfirmation $c) => $c->toArray(), $confirmations),
        ];

        return $this->send('/confirm-vsa-and-mcr-delivery', $body);
    }

    /**
     * Check the status of a marketplace transaction (`POST /checktxn`).
     *
     * The answer carries the parent transaction plus a `sub_transactions` array, and is verified
     * the same way a standard lookup is — see
     * {@see \TexHub\AlifPay\Webhook\WebhookHandler::verifyStatusResponse()}.
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
     * Cancel / refund a child transaction (`POST /cancel/marketplace`).
     *
     * A held (`to_approve`) transaction can only be cancelled in full; a captured (`ok`) one can
     * be cancelled partially, leaving it `partially_canceled`.
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

        return $this->send('/cancel/marketplace', $body);
    }
}

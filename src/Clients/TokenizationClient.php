<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Clients;

use TexHub\AlifPay\Exceptions\ConfigurationException;
use TexHub\AlifPay\Http\Response;
use TexHub\AlifPay\Requests\TokenChargeRequest;
use TexHub\AlifPay\Requests\TokenizationRequest;
use TexHub\AlifPay\Signature;

/**
 * Tokenization: bind a payment method, charge it, look it up and remove it.
 *
 * Only the first of those signs the request in the body. The other three carry the signature in a
 * `hash` header instead, and the `token` in their body is the UUID of the bound payment method.
 *
 * @see https://docs.acquiring.alif.tj/tokenization
 */
final class TokenizationClient extends AbstractClient
{
    /**
     * Initialize a tokenization session and obtain the redirect URL.
     *
     * Signing string: terminalId . phone . gate
     * The payload is wrapped in a `data` object.
     */
    public function initiate(TokenizationRequest $request): Response
    {
        $data = $request->toArray($this->config->callbackUrl, $this->config->returnUrl);

        $data['key'] = $this->config->terminalId;
        $data['token'] = $this->token(Signature::concat(
            $this->config->terminalId,
            $data['phone'],
            $data['gate'],
        ));

        return $this->send('/v2/', ['data' => $data], ['gate' => (string) $data['gate']]);
    }

    /**
     * Charge a bound payment method (`POST /pay/token`).
     *
     * Signing string: terminalId . order_id . amount . token, in the `hash` header. The amount is
     * signed in its two-decimal string form even though the body sends it as a number.
     */
    public function charge(TokenChargeRequest $request): Response
    {
        $body = $request->toArray($this->config->callbackUrl, $this->config->returnUrl);

        $body['key'] = $this->config->terminalId;

        $hash = $this->token(Signature::concat(
            $this->config->terminalId,
            $request->getOrderId(),
            $request->getAmount(),
            $request->getToken(),
        ));

        return $this->send('/pay/token', $body, ['hash' => $hash]);
    }

    /**
     * Look up the state of a tokenization (`POST /tokenization/status`).
     *
     * `$requestId` is the orderId used when the tokenization was initiated. The answer carries the
     * bound token and its state under `payload`.
     *
     * Signing string: terminalId . request_id, in the `hash` header.
     */
    public function status(string $requestId): Response
    {
        if (trim($requestId) === '') {
            throw new ConfigurationException('A tokenization status lookup needs the request id (orderId).');
        }

        $body = [
            'request_id' => $requestId,
            'key' => $this->config->terminalId,
            'token' => '',
        ];

        $hash = $this->token(Signature::concat($this->config->terminalId, $requestId));

        return $this->send('/tokenization/status', $body, ['hash' => $hash]);
    }

    /**
     * Unbind a payment method (`POST /tokenization/remove`). The token stops working for charges.
     *
     * Signing string: terminalId . token, in the `hash` header — the request id is sent but not
     * signed.
     */
    public function remove(string $requestId, string $token): Response
    {
        if (trim($requestId) === '') {
            throw new ConfigurationException('A tokenization removal needs the request id (orderId).');
        }

        if (trim($token) === '') {
            throw new ConfigurationException('A tokenization removal needs the token to unbind.');
        }

        $body = [
            'request_id' => $requestId,
            'key' => $this->config->terminalId,
            'token' => $token,
        ];

        $hash = $this->token(Signature::concat($this->config->terminalId, $token));

        return $this->send('/tokenization/remove', $body, ['hash' => $hash]);
    }
}

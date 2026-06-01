<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Clients;

use TexHub\AlifPay\Http\Response;
use TexHub\AlifPay\Requests\TokenizationRequest;
use TexHub\AlifPay\Signature;

/**
 * Tokenization (binding a payment method for repeat charges).
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
}

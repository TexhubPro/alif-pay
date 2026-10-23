<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Clients;

use TexHub\AlifPay\Config;
use TexHub\AlifPay\Enums\ResponseCode;
use TexHub\AlifPay\Exceptions\ApiException;
use TexHub\AlifPay\Exceptions\ConfigurationException;
use TexHub\AlifPay\Http\RawResponse;
use TexHub\AlifPay\Http\Response;
use TexHub\AlifPay\Http\Transport;
use TexHub\AlifPay\Responses\CurrencyRates;
use TexHub\AlifPay\Signature;

/**
 * Shared request/response plumbing for the service clients: it signs nothing
 * by itself (callers build the signing string) but turns a raw transport
 * result into a {@see Response} and raises {@see ApiException} on failures.
 */
abstract class AbstractClient
{
    public function __construct(
        protected readonly Config $config,
        protected readonly Transport $transport,
        protected readonly Signature $signature,
    ) {
    }

    /**
     * Send a signed POST request and decode the response.
     *
     * Most endpoints answer `{ code, message, url }` and say what happened in `code`. The status
     * lookups do not: a found transaction comes back as the transaction itself, with no `code`
     * anywhere in it, and only a failure carries one. Insisting on `code: 200` there would turn
     * every successful lookup into an error — hence `$expectsCode`. Subscription creation answers
     * 202, so the accepted codes can be widened with `$okCodes`.
     *
     * @param array<string, mixed>  $body
     * @param array<string, string> $headers
     * @param list<int>             $okCodes
     *
     * @throws ApiException
     */
    protected function send(string $path, array $body, array $headers = [], bool $expectsCode = true, array $okCodes = [200]): Response
    {
        return $this->decode($this->transport->post($this->config->url($path), $body, $headers), $expectsCode, $okCodes);
    }

    /**
     * Send a signed GET request and decode the response.
     *
     * @param array<string, string> $headers
     *
     * @throws ApiException
     */
    protected function sendGet(string $path, array $headers = []): Response
    {
        return $this->decode($this->transport->get($this->config->url($path), $headers), true, [200]);
    }

    /**
     * @param list<int> $okCodes
     *
     * @throws ApiException
     */
    private function decode(RawResponse $raw, bool $expectsCode, array $okCodes): Response
    {
        $decoded = json_decode($raw->body, true);

        if (! is_array($decoded)) {
            throw new ApiException(
                $raw->statusCode,
                $raw->statusCode,
                'Unexpected non-JSON response from Alif: ' . substr($raw->body, 0, 200),
            );
        }

        $response = Response::fromArray($decoded);
        $codeOk = in_array($response->code, $okCodes, true);
        $failed = $expectsCode ? ! $codeOk : ($response->code !== 0 && ! $codeOk);

        if (! in_array($raw->statusCode, $okCodes, true) || $failed) {
            throw new ApiException(
                $raw->statusCode,
                $response->code !== 0 ? $response->code : $raw->statusCode,
                $response->message !== '' ? $response->message : 'Unknown Alif API error',
                $decoded,
            );
        }

        return $response;
    }

    /**
     * The terminal's currency and its rate to the somoni (`POST /currency`), shared by the payment
     * and the marketplace clients, whose pages document the same endpoint.
     *
     * Signing string: terminalId . order_id. The order id only has to match what was signed; it
     * does not have to belong to an existing payment.
     *
     * @throws ApiException
     */
    protected function fetchCurrency(string $orderId): CurrencyRates
    {
        if (trim($orderId) === '') {
            throw new ConfigurationException('A currency lookup needs an order id to sign.');
        }

        return CurrencyRates::fromResponse($this->send('/currency', [
            'order_id' => $orderId,
            'key' => $this->config->terminalId,
            'token' => $this->token(Signature::concat($this->config->terminalId, $orderId)),
        ]));
    }

    protected function token(string $dataToSign): string
    {
        return $this->signature->generate($dataToSign);
    }

    protected function isSuccessCode(int $code): bool
    {
        return $code === ResponseCode::Success->value;
    }
}

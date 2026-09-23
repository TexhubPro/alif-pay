<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Clients;

use TexHub\AlifPay\Config;
use TexHub\AlifPay\Enums\ResponseCode;
use TexHub\AlifPay\Exceptions\ApiException;
use TexHub\AlifPay\Http\Response;
use TexHub\AlifPay\Http\Transport;
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
     * every successful lookup into an error — hence `$expectsCode`.
     *
     * @param array<string, mixed>  $body
     * @param array<string, string> $headers
     *
     * @throws ApiException
     */
    protected function send(string $path, array $body, array $headers = [], bool $expectsCode = true): Response
    {
        $raw = $this->transport->post($this->config->url($path), $body, $headers);

        $decoded = json_decode($raw->body, true);

        if (! is_array($decoded)) {
            throw new ApiException(
                $raw->statusCode,
                $raw->statusCode,
                'Unexpected non-JSON response from Alif: ' . substr($raw->body, 0, 200),
            );
        }

        $response = Response::fromArray($decoded);
        $failed = $expectsCode
            ? ! $response->isSuccessful()
            : ($response->code !== 0 && ! $response->isSuccessful());

        if ($raw->statusCode !== 200 || $failed) {
            throw new ApiException(
                $raw->statusCode,
                $response->code !== 0 ? $response->code : $raw->statusCode,
                $response->message !== '' ? $response->message : 'Unknown Alif API error',
                $decoded,
            );
        }

        return $response;
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

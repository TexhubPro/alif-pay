<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Http;

use TexHub\AlifPay\Exceptions\TransportException;

/**
 * Default {@see Transport} implementation built on the cURL extension.
 *
 * Certificate verification, the protocol allowlist and the refusal to follow redirects are set
 * explicitly rather than left to the defaults: a signed payload carrying a terminal token must
 * not be replayable to whatever host a redirect names.
 */
final class CurlTransport implements Transport
{
    public function __construct(
        private readonly int $timeout = 30,
        private readonly string $userAgent = 'texhub-alif-pay/3.0 (+https://texhub.pro)',
    ) {
    }

    public function post(string $url, array $body, array $headers = []): RawResponse
    {
        $payload = json_encode(
            $body,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
        );

        if ($payload === false) {
            throw new TransportException('Failed to JSON-encode the Alif request body: ' . json_last_error_msg());
        }

        $headers['Content-Type'] = 'application/json';

        return $this->request($url, $headers, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
        ]);
    }

    public function get(string $url, array $headers = []): RawResponse
    {
        return $this->request($url, $headers, [CURLOPT_HTTPGET => true]);
    }

    /**
     * @param array<string, string> $headers
     * @param array<int, mixed>     $method
     */
    private function request(string $url, array $headers, array $method): RawResponse
    {
        $headers['Accept'] = 'application/json';

        $headerLines = [];

        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $options = $method + [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_FAILONERROR => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        if (defined('CURLOPT_PROTOCOLS_STR')) {
            $options[CURLOPT_PROTOCOLS_STR] = 'https';
        }

        $ch = curl_init();
        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $errorNo = curl_errno($ch);
        $error = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        unset($ch);

        if ($errorNo !== 0 || $response === false) {
            throw new TransportException(sprintf('Alif request to %s failed: %s', $url, $error ?: 'unknown cURL error'));
        }

        return new RawResponse($statusCode, (string) $response);
    }
}

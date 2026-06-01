<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Http;

use TexHub\AlifPay\Exceptions\TransportException;

/**
 * Default {@see Transport} implementation built on the cURL extension.
 */
final class CurlTransport implements Transport
{
    public function __construct(
        private readonly int $timeout = 30,
        private readonly string $userAgent = 'texhub-alif-pay/1.0 (+https://texhub.pro)',
    ) {
    }

    public function post(string $url, array $body, array $headers = []): RawResponse
    {
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            throw new TransportException('Failed to JSON-encode the Alif request body: ' . json_last_error_msg());
        }

        $headers['Content-Type'] = 'application/json';
        $headers['Accept'] = 'application/json';

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_FAILONERROR => false,
        ]);

        $response = curl_exec($ch);
        $errorNo = curl_errno($ch);
        $error = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($errorNo !== 0 || $response === false) {
            throw new TransportException(sprintf('Alif request to %s failed: %s', $url, $error ?: 'unknown cURL error'));
        }

        return new RawResponse($statusCode, (string) $response);
    }
}

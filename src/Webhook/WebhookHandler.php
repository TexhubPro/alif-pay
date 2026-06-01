<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Webhook;

use TexHub\AlifPay\Exceptions\AlifPayException;
use TexHub\AlifPay\Exceptions\InvalidSignatureException;
use TexHub\AlifPay\Signature;

/**
 * Parses and verifies incoming Alif callbacks (webhooks).
 *
 * Verification note
 * -----------------
 * Alif signs callbacks with the same HMAC scheme used for requests, but the
 * exact concatenation order for the callback token is not published. The
 * helpers below use the most common scheme (terminalId . orderId . amount) and
 * accept a custom signing string so you can match your terminal's behaviour if
 * it differs. Always verify in production.
 */
final class WebhookHandler
{
    public function __construct(
        private readonly Signature $signature,
    ) {
    }

    /**
     * Decode a raw JSON callback body into an associative array.
     *
     * @return array<string, mixed>
     *
     * @throws AlifPayException
     */
    public function decode(string $rawBody): array
    {
        $data = json_decode($rawBody, true);

        if (! is_array($data)) {
            throw new AlifPayException('Callback body is not valid JSON.');
        }

        return $data;
    }

    /**
     * Parse a standard / marketplace payment callback.
     *
     * @param string|array<string, mixed> $body
     */
    public function paymentCallback(string|array $body): PaymentCallback
    {
        return PaymentCallback::fromArray(is_string($body) ? $this->decode($body) : $body);
    }

    /**
     * Parse a tokenization callback.
     *
     * @param string|array<string, mixed> $body
     */
    public function tokenizationCallback(string|array $body): TokenizationCallback
    {
        return TokenizationCallback::fromArray(is_string($body) ? $this->decode($body) : $body);
    }

    /**
     * Verify a payment callback token.
     *
     * @param string|null $dataToSign Custom signing string; defaults to
     *                                terminalId . orderId . amount.
     */
    public function verifyPaymentCallback(PaymentCallback $callback, ?string $dataToSign = null): bool
    {
        if ($callback->token === '') {
            return false;
        }

        $dataToSign ??= Signature::concat(
            $callback->orderId,
            number_format($callback->amount, 2, '.', ''),
        );

        return $this->signature->verify($dataToSign, $callback->token);
    }

    /**
     * Verify a payment callback token, throwing on mismatch.
     *
     * @throws InvalidSignatureException
     */
    public function assertValidPaymentCallback(PaymentCallback $callback, ?string $dataToSign = null): void
    {
        if (! $this->verifyPaymentCallback($callback, $dataToSign)) {
            throw new InvalidSignatureException('Alif payment callback signature verification failed.');
        }
    }

    /**
     * Low-level token check against a known signing string.
     */
    public function verifyToken(string $dataToSign, string $token): bool
    {
        return $this->signature->verify($dataToSign, $token);
    }
}

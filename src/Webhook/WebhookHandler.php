<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Webhook;

use TexHub\AlifPay\Exceptions\AlifPayException;
use TexHub\AlifPay\Exceptions\InvalidSignatureException;
use TexHub\AlifPay\Http\Response;
use TexHub\AlifPay\Signature;

/**
 * Parses and verifies incoming Alif callbacks (webhooks).
 *
 * Verification
 * ------------
 * Alif signs what it sends back with the same HMAC scheme it expects on the way in. The string
 * depends on the event:
 *
 *   - `payment` (standard, marketplace, subscription charge): `orderId . status . transactionId`,
 *     the same string as a status lookup answer;
 *   - `confirm` / `cancel`: `terminal_id . transaction_id . status`.
 *
 * {@see verifyCallback()} picks the right one; the specific helpers are there too.
 *
 * A verified callback is not the same as a settled payment. A callback is a public endpoint:
 * confirm the outcome with a status lookup before handing anything over, answer exactly HTTP 200
 * once it is handled, and keep the handler idempotent — Alif retries anything else up to 15 times.
 *
 * @see https://docs.acquiring.alif.tj/payments
 * @see https://docs.acquiring.alif.tj/marketplace
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
     * Parse any payment-side callback, choosing the right shape for the body.
     *
     * Alif posts two shapes to the same URL: the payment callback (`event: payment`) and the
     * single-transaction event raised by a delivery confirmation or a cancellation
     * (`event: confirm` / `cancel`).
     *
     * @param string|array<string, mixed> $body
     */
    public function callback(string|array $body): PaymentCallback|DeliveryCallback
    {
        $data = is_string($body) ? $this->decode($body) : $body;

        return DeliveryCallback::matches($data)
            ? DeliveryCallback::fromArray($data)
            : PaymentCallback::fromArray($data);
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
     * Parse a marketplace delivery confirmation / cancellation event.
     *
     * @param string|array<string, mixed> $body
     */
    public function deliveryCallback(string|array $body): DeliveryCallback
    {
        return DeliveryCallback::fromArray(is_string($body) ? $this->decode($body) : $body);
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
     * @param string|null $dataToSign Custom signing string; defaults to the documented
     *                                orderId . status . transactionId.
     */
    public function verifyPaymentCallback(PaymentCallback $callback, ?string $dataToSign = null): bool
    {
        if ($callback->token === '') {
            return false;
        }

        $dataToSign ??= Signature::concat(
            $callback->orderId,
            $callback->rawStatus,
            $callback->transactionId,
        );

        return $this->signature->verify($dataToSign, $callback->token);
    }

    /**
     * Verify the token carried by a status lookup (`POST /checktxn`).
     *
     * @param array<string, mixed>|Response $response The decoded response, or the SDK object.
     */
    public function verifyStatusResponse(array|Response $response): bool
    {
        $data = $response instanceof Response ? $response->raw : $response;

        return $this->verifyStatusToken(
            (string) ($data['orderId'] ?? ''),
            (string) ($data['status'] ?? ''),
            (string) ($data['transactionId'] ?? ''),
            (string) ($data['token'] ?? ''),
        );
    }

    /**
     * The documented check: token = HMAC(orderId . status . transactionId).
     */
    public function verifyStatusToken(string $orderId, string $status, string $transactionId, string $token): bool
    {
        if ($token === '' || $orderId === '' || $status === '') {
            return false;
        }

        return $this->signature->verify(Signature::concat($orderId, $status, $transactionId), $token);
    }

    /**
     * Verify a status lookup, throwing on mismatch.
     *
     * @param array<string, mixed>|Response $response
     *
     * @throws InvalidSignatureException
     */
    public function assertValidStatusResponse(array|Response $response): void
    {
        if (! $this->verifyStatusResponse($response)) {
            throw new InvalidSignatureException('Alif status response signature verification failed.');
        }
    }

    /**
     * Verify a confirmation or cancellation callback: token = HMAC(terminal_id . transaction_id . status).
     */
    public function verifyDeliveryCallback(DeliveryCallback $callback): bool
    {
        if ($callback->token === '' || $callback->terminalId === '' || $callback->transactionId === '' || $callback->rawStatus === '') {
            return false;
        }

        return $this->signature->verify(
            Signature::concat($callback->terminalId, $callback->transactionId, $callback->rawStatus),
            $callback->token,
        );
    }

    /**
     * Verify a confirmation or cancellation callback, throwing on mismatch.
     *
     * @throws InvalidSignatureException
     */
    public function assertValidDeliveryCallback(DeliveryCallback $callback): void
    {
        if (! $this->verifyDeliveryCallback($callback)) {
            throw new InvalidSignatureException('Alif delivery/cancel callback signature verification failed.');
        }
    }

    /**
     * Verify any payment-side callback with the formula its event calls for.
     */
    public function verifyCallback(PaymentCallback|DeliveryCallback $callback): bool
    {
        return $callback instanceof DeliveryCallback
            ? $this->verifyDeliveryCallback($callback)
            : $this->verifyPaymentCallback($callback);
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

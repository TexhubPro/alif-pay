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
 * Alif signs what it sends back with the same HMAC scheme it expects on the way in, over
 * `orderId . status . transactionId`. Both the acquiring documentation ("Верификация токена
 * ответа") and the WebCheckout protocol specification give that string, and a payment callback
 * carries exactly those fields — so that is what these helpers check by default. A custom signing
 * string can still be passed if a terminal is set up differently.
 *
 * A verified callback is not the same as a settled payment. A callback is a public endpoint:
 * confirm the outcome with a status lookup before handing anything over.
 *
 * Not every callback can be verified. Marketplace delivery and cancellation events describe a
 * single sub-transaction and carry no token at all — {@see DeliveryCallback}.
 *
 * @see https://docs.acquiring.alif.tj/payments
 * @see https://alifcapital.github.io/webcheckout
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
     * Alif posts three different structures to the same URL: the full payment callback, and the
     * two sub-transaction events raised by a delivery confirmation or a cancellation.
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

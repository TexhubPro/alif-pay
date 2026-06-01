<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Requests;

use TexHub\AlifPay\Enums\TokenizationGate;
use TexHub\AlifPay\Exceptions\ConfigurationException;

/**
 * Fluent builder for a tokenization (card/wallet binding) request (`POST /v2/`).
 *
 * The body is wrapped in a `data` object; `key` and `token` are injected by the
 * client. The signing string is `terminalId . phone . gate`.
 *
 * @see https://docs.acquiring.alif.tj/tokenization
 */
final class TokenizationRequest
{
    private ?string $callbackUrl = null;
    private ?string $returnUrl = null;
    private ?string $clientId = null;

    public function __construct(
        private readonly string $orderId,
        private readonly string $phone,
        private readonly TokenizationGate $gate,
    ) {
        if (trim($orderId) === '') {
            throw new ConfigurationException('Tokenization orderId must not be empty.');
        }

        if (trim($phone) === '') {
            throw new ConfigurationException('Tokenization phone must not be empty.');
        }
    }

    public static function make(string $orderId, string $phone, TokenizationGate $gate): self
    {
        return new self($orderId, $phone, $gate);
    }

    public function callbackUrl(string $url): self
    {
        $this->callbackUrl = $url;

        return $this;
    }

    public function returnUrl(string $url): self
    {
        $this->returnUrl = $url;

        return $this;
    }

    public function clientId(string $clientId): self
    {
        $this->clientId = $clientId;

        return $this;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function getGate(): TokenizationGate
    {
        return $this->gate;
    }

    /**
     * Build the inner `data` payload (without key/token, which the client adds).
     *
     * @return array<string, mixed>
     */
    public function toArray(?string $defaultCallbackUrl = null, ?string $defaultReturnUrl = null): array
    {
        $callbackUrl = $this->callbackUrl ?? $defaultCallbackUrl;
        $returnUrl = $this->returnUrl ?? $defaultReturnUrl;

        if ($callbackUrl === null || $callbackUrl === '') {
            throw new ConfigurationException('A callbackURL is required (set it on the request or in config).');
        }

        if ($returnUrl === null || $returnUrl === '') {
            throw new ConfigurationException('A returnURL is required (set it on the request or in config).');
        }

        $data = [
            'orderId' => $this->orderId,
            'callbackURL' => $callbackUrl,
            'returnURL' => $returnUrl,
            'phone' => $this->phone,
            'gate' => $this->gate->value,
        ];

        if ($this->clientId !== null) {
            $data['clientID'] = $this->clientId;
        }

        return $data;
    }
}

<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Requests;

use TexHub\AlifPay\Exceptions\ConfigurationException;

/**
 * Fluent builder for charging a payment method that was bound earlier through tokenization
 * (`POST /pay/token`).
 *
 * The `token` here is the UUID handed over by the tokenization callback, not an HMAC signature —
 * the signature of this request travels in the `hash` header and the client adds it.
 *
 * Splits turn the charge into a marketplace charge; their amounts must add up to the total.
 *
 * @see https://docs.acquiring.alif.tj/tokenization
 */
final class TokenChargeRequest
{
    private Amount $amount;
    private ?string $callbackUrl = null;
    private ?string $returnUrl = null;
    private ?string $info = null;
    private ?string $email = null;

    /** @var array<int, TerminalSplit> */
    private array $splits = [];

    public function __construct(
        private readonly string $orderId,
        int|float|string $amount,
        private readonly string $token,
    ) {
        if (trim($orderId) === '') {
            throw new ConfigurationException('Token charge order_id must not be empty.');
        }

        if (mb_strlen($orderId) > 40) {
            throw new ConfigurationException('Token charge order_id must not exceed 40 characters.');
        }

        if (trim($token) === '') {
            throw new ConfigurationException('Token charge needs the payment token from the tokenization callback.');
        }

        $this->amount = Amount::of($amount);
    }

    public static function make(string $orderId, int|float|string $amount, string $token): self
    {
        return new self($orderId, $amount, $token);
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

    public function info(string $info): self
    {
        $this->info = $info;

        return $this;
    }

    public function email(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function addSplit(TerminalSplit $split): self
    {
        $this->splits[] = $split;

        return $this;
    }

    public function splitTo(string $terminalId, int|float|string $amount, int $conditionId = 0): self
    {
        return $this->addSplit(new TerminalSplit($terminalId, $amount, $conditionId));
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getAmount(): string
    {
        return $this->amount->value;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(?string $defaultCallbackUrl = null, ?string $defaultReturnUrl = null): array
    {
        $callbackUrl = $this->callbackUrl ?? $defaultCallbackUrl;
        $returnUrl = $this->returnUrl ?? $defaultReturnUrl;

        if ($callbackUrl === null || $callbackUrl === '') {
            throw new ConfigurationException('A callback_url is required (set it on the request or in config).');
        }

        if ($this->splits !== []) {
            $splitTotal = array_sum(array_map(static fn (TerminalSplit $s) => (float) $s->amount, $this->splits));

            if (number_format($splitTotal, 2, '.', '') !== $this->amount->value) {
                throw new ConfigurationException(sprintf(
                    'Token charge split total (%s) must equal the charged amount (%s).',
                    number_format($splitTotal, 2, '.', ''),
                    $this->amount->value,
                ));
            }
        }

        $this->callbackUrl = $callbackUrl;

        $body = [
            'order_id' => $this->orderId,
            'amount' => (float) $this->amount->value,
            'token' => $this->token,
            'callback_url' => $callbackUrl,
        ];

        if ($returnUrl !== null && $returnUrl !== '') {
            $body['return_url'] = $returnUrl;
        }

        if ($this->email !== null) {
            $body['email'] = $this->email;
        }

        if ($this->info !== null) {
            $body['info'] = $this->info;
        }

        if ($this->splits !== []) {
            $body['mpTerminalInfo'] = array_map(static fn (TerminalSplit $s) => $s->toArray(), $this->splits);
        }

        return $body;
    }
}

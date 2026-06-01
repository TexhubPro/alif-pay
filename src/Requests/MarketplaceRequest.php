<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Requests;

use TexHub\AlifPay\Enums\Gate;
use TexHub\AlifPay\Exceptions\ConfigurationException;

/**
 * Fluent builder for a marketplace split-payment (`POST /v2/`, header
 * `isMarketPlace: true`). The total `amount` must equal the sum of all
 * partner terminal amounts.
 *
 * @see https://docs.acquiring.alif.tj/marketplace
 */
final class MarketplaceRequest
{
    private Amount $amount;
    private Gate $gate = Gate::KortiMilli;
    private ?string $callbackUrl = null;
    private ?string $returnUrl = null;
    private ?string $info = null;
    private ?string $email = null;
    private ?string $phone = null;

    /** @var array<int, TerminalAmount> */
    private array $terminals = [];

    public function __construct(
        private readonly string $orderId,
        int|float|string $amount,
    ) {
        if (trim($orderId) === '') {
            throw new ConfigurationException('Marketplace order_id must not be empty.');
        }

        $this->amount = Amount::of($amount);
    }

    public static function make(string $orderId, int|float|string $amount): self
    {
        return new self($orderId, $amount);
    }

    public function gate(Gate $gate): self
    {
        $this->gate = $gate;

        return $this;
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

    public function phone(string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function addTerminal(TerminalAmount $terminal): self
    {
        $this->terminals[] = $terminal;

        return $this;
    }

    public function splitTo(string $terminalId, int|float|string $amount): self
    {
        return $this->addTerminal(new TerminalAmount($terminalId, $amount));
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getAmount(): string
    {
        return $this->amount->value;
    }

    public function getGate(): Gate
    {
        return $this->gate;
    }

    public function getCallbackUrl(): ?string
    {
        return $this->callbackUrl;
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

        if ($returnUrl === null || $returnUrl === '') {
            throw new ConfigurationException('A return_url is required (set it on the request or in config).');
        }

        if ($this->terminals === []) {
            throw new ConfigurationException('A marketplace payment requires at least one partner terminal split.');
        }

        $splitTotal = array_sum(array_map(static fn (TerminalAmount $t) => (float) $t->amount, $this->terminals));

        if (number_format($splitTotal, 2, '.', '') !== $this->amount->value) {
            throw new ConfigurationException(sprintf(
                'Marketplace split total (%s) must equal the order amount (%s).',
                number_format($splitTotal, 2, '.', ''),
                $this->amount->value,
            ));
        }

        $this->callbackUrl = $callbackUrl;

        $body = [
            'order_id' => $this->orderId,
            'callback_url' => $callbackUrl,
            'return_url' => $returnUrl,
            'amount' => $this->amount->value,
            'gate' => $this->gate->value,
            'mp_terminal_amounts' => array_map(static fn (TerminalAmount $t) => $t->toArray(), $this->terminals),
        ];

        if ($this->info !== null) {
            $body['info'] = $this->info;
        }

        if ($this->email !== null) {
            $body['email'] = $this->email;
        }

        if ($this->phone !== null) {
            $body['phone'] = $this->phone;
        }

        return $body;
    }
}

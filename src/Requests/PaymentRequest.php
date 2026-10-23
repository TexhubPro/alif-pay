<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Requests;

use TexHub\AlifPay\Enums\Gate;
use TexHub\AlifPay\Exceptions\ConfigurationException;

/**
 * Fluent builder for a standard payment initialization (`POST /v2/`).
 *
 * Only domain fields are set here — `key` and `token` are injected by the
 * client right before the request is sent.
 *
 * @see https://docs.acquiring.alif.tj/payments
 */
final class PaymentRequest
{
    private Amount $amount;
    private string $gate = Gate::KortiMilli->value;
    private ?string $callbackUrl = null;
    private ?string $returnUrl = null;
    private ?string $info = null;
    private ?string $email = null;
    private ?string $phone = null;
    private ?string $deadline = null;
    private bool $signInfo = false;

    /** @var array<int, InvoiceItem> */
    private array $invoices = [];

    public function __construct(
        private readonly string $orderId,
        int|float|string $amount,
    ) {
        if (trim($orderId) === '') {
            throw new ConfigurationException('Payment order_id must not be empty.');
        }

        $this->amount = Amount::of($amount);
    }

    public static function make(string $orderId, int|float|string $amount): self
    {
        return new self($orderId, $amount);
    }

    public function gate(Gate $gate): self
    {
        $this->gate = $gate->value;

        return $this;
    }

    /**
     * A gate by its raw value, for a value {@see Gate} does not name — the legacy `vsa` / `mcr`
     * spellings of Visa and Mastercard, or a method Alif enabled on a terminal before documenting
     * it. What the gateway does with an unlisted value is not documented; use one only when Alif
     * gave it to you.
     */
    public function gateValue(string $gate): self
    {
        $gate = trim($gate);

        if ($gate === '' || ! preg_match('/^[a-z0-9_]+$/', $gate)) {
            throw new ConfigurationException(sprintf('Invalid Alif gate: "%s".', $gate));
        }

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

    /**
     * Send `info_hash` — the signature of `info` — alongside it.
     *
     * The docs ask for it when `info` carries tax details, and allow it on Korti Milli only. The
     * client computes the hash with the terminal password at send time, so the request never
     * holds a secret.
     */
    public function signInfo(): self
    {
        $this->signInfo = true;

        return $this;
    }

    public function shouldSignInfo(): bool
    {
        return $this->signInfo;
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

    /**
     * Deadline for cash invoice payments (RFC3339, e.g. "2025-11-29T07:59:59Z").
     */
    public function deadline(string $deadline): self
    {
        $this->deadline = $deadline;

        return $this;
    }

    public function addInvoiceItem(InvoiceItem $item): self
    {
        $this->invoices[] = $item;

        return $this;
    }

    /**
     * @param iterable<InvoiceItem> $items
     */
    public function invoices(iterable $items): self
    {
        foreach ($items as $item) {
            $this->addInvoiceItem($item);
        }

        return $this;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getAmount(): string
    {
        return $this->amount->value;
    }

    public function getGate(): string
    {
        return $this->gate;
    }

    public function getCallbackUrl(): ?string
    {
        return $this->callbackUrl;
    }

    /**
     * Build the request body. Defaults from config (callback/return URL) are
     * applied by the client and may be passed here.
     *
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

        if ($this->signInfo && ($this->info === null || $this->info === '')) {
            throw new ConfigurationException('signInfo() needs an info text to sign.');
        }

        if ($this->signInfo && $this->gate !== Gate::KortiMilli->value) {
            throw new ConfigurationException('info_hash is only accepted with the korti_milli gate.');
        }

        $this->callbackUrl = $callbackUrl;

        $body = [
            'order_id' => $this->orderId,
            'callback_url' => $callbackUrl,
            'return_url' => $returnUrl,
            'amount' => $this->amount->value,
            'gate' => $this->gate,
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

        if ($this->deadline !== null) {
            $body['deadline'] = $this->deadline;
        }

        if ($this->invoices !== []) {
            $body['invoices'] = [
                'invoices' => array_map(static fn (InvoiceItem $i) => $i->toArray(), $this->invoices),
            ];
        }

        return $body;
    }
}

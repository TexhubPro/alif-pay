<?php

declare(strict_types=1);

namespace TexHub\AlifPay;

use TexHub\AlifPay\Clients\MarketplaceClient;
use TexHub\AlifPay\Clients\PaymentClient;
use TexHub\AlifPay\Clients\TokenizationClient;
use TexHub\AlifPay\Enums\Environment;
use TexHub\AlifPay\Http\CurlTransport;
use TexHub\AlifPay\Http\Transport;
use TexHub\AlifPay\Webhook\WebhookHandler;

/**
 * Entry point of the Alif Acquiring SDK.
 *
 * Framework-agnostic: construct it directly, or resolve it from the container
 * in Laravel via the {@see \TexHub\AlifPay\Laravel\AlifPay} facade.
 *
 * ```php
 * $alif = AlifPay::make('terminal_id', 'terminal_password', Environment::Test);
 *
 * $response = $alif->payments()->initiate(
 *     PaymentRequest::make('ORDER_1', '100.50')
 *         ->gate(Gate::KortiMilli)
 *         ->callbackUrl('https://shop.tj/callback')
 *         ->returnUrl('https://shop.tj/success')
 * );
 *
 * header('Location: ' . $response->redirectUrl());
 * ```
 *
 * @see https://docs.acquiring.alif.tj/intro
 */
final class AlifPay
{
    private readonly Signature $signature;
    private readonly Transport $transport;

    private ?PaymentClient $payments = null;
    private ?TokenizationClient $tokenization = null;
    private ?MarketplaceClient $marketplace = null;
    private ?WebhookHandler $webhooks = null;

    public function __construct(
        private readonly Config $config,
        ?Transport $transport = null,
    ) {
        $this->signature = new Signature($config->terminalId, $config->terminalPassword);
        $this->transport = $transport ?? new CurlTransport($config->timeout);
    }

    /**
     * Convenience constructor.
     */
    public static function make(
        string $terminalId,
        string $terminalPassword,
        Environment $environment = Environment::Test,
        ?Transport $transport = null,
    ): self {
        return new self(
            new Config($terminalId, $terminalPassword, $environment),
            $transport,
        );
    }

    /**
     * Build from a config array (terminal_id, terminal_password, environment, …).
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config, ?Transport $transport = null): self
    {
        return new self(Config::fromArray($config), $transport);
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function signature(): Signature
    {
        return $this->signature;
    }

    public function payments(): PaymentClient
    {
        return $this->payments ??= new PaymentClient($this->config, $this->transport, $this->signature);
    }

    public function tokenization(): TokenizationClient
    {
        return $this->tokenization ??= new TokenizationClient($this->config, $this->transport, $this->signature);
    }

    public function marketplace(): MarketplaceClient
    {
        return $this->marketplace ??= new MarketplaceClient($this->config, $this->transport, $this->signature);
    }

    public function webhooks(): WebhookHandler
    {
        return $this->webhooks ??= new WebhookHandler($this->signature);
    }
}

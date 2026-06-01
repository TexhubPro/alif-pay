<?php

declare(strict_types=1);

namespace TexHub\AlifPay;

use TexHub\AlifPay\Enums\Environment;
use TexHub\AlifPay\Exceptions\ConfigurationException;

/**
 * Immutable SDK configuration: terminal credentials, environment and defaults.
 */
final class Config
{
    /**
     * @param string       $terminalId        Terminal key (`key` field / `terminal_id`).
     * @param string       $terminalPassword  Secret terminal password.
     * @param Environment  $environment        Target environment (test / production).
     * @param string|null  $callbackUrl        Default webhook URL (overridable per request).
     * @param string|null  $returnUrl          Default browser return URL (overridable per request).
     * @param int          $timeout            HTTP timeout in seconds.
     * @param string|null  $baseUrl            Override the environment base URL (advanced).
     */
    public function __construct(
        public readonly string $terminalId,
        public readonly string $terminalPassword,
        public readonly Environment $environment = Environment::Test,
        public readonly ?string $callbackUrl = null,
        public readonly ?string $returnUrl = null,
        public readonly int $timeout = 30,
        private readonly ?string $baseUrl = null,
    ) {
        if (trim($this->terminalId) === '') {
            throw new ConfigurationException('Alif Pay terminal id (key) must not be empty.');
        }

        if (trim($this->terminalPassword) === '') {
            throw new ConfigurationException('Alif Pay terminal password must not be empty.');
        }

        if ($this->timeout < 1) {
            throw new ConfigurationException('Alif Pay timeout must be a positive number of seconds.');
        }
    }

    /**
     * Build configuration from a plain array (e.g. a Laravel config entry).
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $environment = $config['environment'] ?? Environment::Test;

        if (is_string($environment)) {
            $environment = Environment::fromString($environment);
        }

        return new self(
            terminalId: (string) ($config['terminal_id'] ?? ''),
            terminalPassword: (string) ($config['terminal_password'] ?? ''),
            environment: $environment instanceof Environment ? $environment : Environment::Test,
            callbackUrl: isset($config['callback_url']) ? (string) $config['callback_url'] : null,
            returnUrl: isset($config['return_url']) ? (string) $config['return_url'] : null,
            timeout: (int) ($config['timeout'] ?? 30),
            baseUrl: isset($config['base_url']) && $config['base_url'] !== '' ? (string) $config['base_url'] : null,
        );
    }

    /**
     * Resolved base URL for the configured environment (without trailing slash).
     */
    public function baseUrl(): string
    {
        return rtrim($this->baseUrl ?? $this->environment->baseUrl(), '/');
    }

    /**
     * Build an absolute URL for an API path.
     */
    public function url(string $path): string
    {
        return $this->baseUrl() . '/' . ltrim($path, '/');
    }
}

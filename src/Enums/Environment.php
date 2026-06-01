<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Enums;

/**
 * Alif Acquiring API environments.
 *
 * @see https://docs.acquiring.alif.tj/intro
 */
enum Environment: string
{
    case Test = 'test';
    case Production = 'production';

    /**
     * Base URL of the API for this environment.
     */
    public function baseUrl(): string
    {
        return match ($this) {
            self::Test => 'https://test-web.alif.tj',
            self::Production => 'https://web.alif.tj',
        };
    }

    public function isProduction(): bool
    {
        return $this === self::Production;
    }

    /**
     * Build an Environment from a loose string (env var, config value, …).
     */
    public static function fromString(string $value): self
    {
        return match (strtolower(trim($value))) {
            'production', 'prod', 'live' => self::Production,
            default => self::Test,
        };
    }
}

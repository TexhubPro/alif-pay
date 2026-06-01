<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Requests;

use TexHub\AlifPay\Exceptions\ConfigurationException;

/**
 * Normalizes monetary amounts to the canonical string form Alif expects
 * (e.g. "100.50"). The very same string must be used both in the request body
 * and in the HMAC signing string, so all amount handling goes through here.
 */
final class Amount
{
    private function __construct(public readonly string $value)
    {
    }

    public static function of(int|float|string $amount): self
    {
        if (is_string($amount)) {
            $amount = trim($amount);

            if ($amount === '' || ! is_numeric($amount)) {
                throw new ConfigurationException(sprintf('Invalid Alif amount: "%s".', $amount));
            }

            $amount = (float) $amount;
        }

        if ($amount <= 0) {
            throw new ConfigurationException('Alif amount must be greater than zero.');
        }

        return new self(number_format((float) $amount, 2, '.', ''));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

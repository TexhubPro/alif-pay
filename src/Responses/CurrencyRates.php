<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Responses;

use TexHub\AlifPay\Http\Response;

/**
 * The terminal's currency and its rate to the somoni, with the rates of the other supported
 * currencies (`POST /currency`). The rate refreshes every ten minutes; a somoni terminal always
 * reports 1.
 *
 * @see https://docs.acquiring.alif.tj/payments
 */
final class CurrencyRates
{
    /**
     * @param array<string, float> $rates Other currencies, keyed by code, each to the somoni.
     */
    public function __construct(
        public readonly string $currency,
        public readonly float $rate,
        public readonly array $rates,
        public readonly Response $response,
    ) {
    }

    public static function fromResponse(Response $response): self
    {
        $payload = (array) $response->get('payload', []);
        $rates = [];

        foreach ((array) ($payload['rates'] ?? []) as $entry) {
            if (is_array($entry) && isset($entry['type'])) {
                $rates[(string) $entry['type']] = (float) ($entry['rate'] ?? 0);
            }
        }

        return new self(
            currency: (string) ($payload['type'] ?? ''),
            rate: (float) ($payload['rate'] ?? 0),
            rates: $rates,
            response: $response,
        );
    }

    /** The rate of any supported currency to the somoni, the terminal's own included. */
    public function rateFor(string $currency): ?float
    {
        $currency = strtoupper($currency);

        if ($currency === strtoupper($this->currency)) {
            return $this->rate;
        }

        return $this->rates[$currency] ?? null;
    }

    /** What the payer will see on the form, in somoni, for an amount in the terminal's currency. */
    public function toSomoni(int|float|string $amount): string
    {
        return number_format(round((float) $amount * $this->rate, 2), 2, '.', '');
    }
}

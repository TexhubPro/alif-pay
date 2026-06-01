<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Requests;

/**
 * A partner terminal and its share of a marketplace split-payment.
 *
 * @see https://docs.acquiring.alif.tj/marketplace
 */
final class TerminalAmount
{
    public readonly string $amount;

    public function __construct(
        public readonly string $terminalId,
        int|float|string $amount,
    ) {
        $this->amount = Amount::of($amount)->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'terminal_id' => $this->terminalId,
            'amount' => $this->amount,
        ];
    }
}

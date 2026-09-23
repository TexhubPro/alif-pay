<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Requests;

use TexHub\AlifPay\Exceptions\ConfigurationException;

/**
 * One entry of the `extra` array sent to `/confirm-vsa-and-mcr-delivery`: the child transaction
 * being confirmed and the amount released to it.
 *
 * @see https://docs.acquiring.alif.tj/marketplace
 */
final class DeliveryConfirmation
{
    public readonly string $amount;

    public function __construct(
        public readonly string $transactionId,
        int|float|string $amount,
    ) {
        if (trim($this->transactionId) === '') {
            throw new ConfigurationException('A delivery confirmation needs a child transaction_id.');
        }

        $this->amount = Amount::of($amount)->value;
    }

    public static function make(string $transactionId, int|float|string $amount): self
    {
        return new self($transactionId, $amount);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'transaction_id' => $this->transactionId,
            'amount' => (float) $this->amount,
        ];
    }
}

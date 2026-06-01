<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Requests;

/**
 * A single line item of a Salom (installment) / cash invoice.
 *
 * @see https://docs.acquiring.alif.tj/payments
 */
final class InvoiceItem
{
    public function __construct(
        public readonly string $name,
        public readonly string $category,
        public readonly int $quantity,
        public readonly string $price,
        public readonly string $vatPercent = '0',
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'category' => $this->category,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'vat_percent' => $this->vatPercent,
        ];
    }
}

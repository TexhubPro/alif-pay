<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Requests;

/**
 * A single line item of a Salom (installment) / cash invoice, and of a marketplace split.
 *
 * The two places spell it differently. A payment's `invoices` carries `vat_percent` and sends
 * the price as a string; an `mpTerminalInfo` entry's `invoices` omits the VAT and sends the price
 * as a number. {@see toArray()} builds the first, {@see toSplitArray()} the second.
 *
 * @see https://docs.acquiring.alif.tj/payments
 * @see https://docs.acquiring.alif.tj/marketplace
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
     * The shape a payment's `invoices.invoices[]` takes.
     *
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

    /**
     * The shape an `mpTerminalInfo[].invoices[]` entry takes.
     *
     * @return array<string, mixed>
     */
    public function toSplitArray(): array
    {
        return [
            'name' => $this->name,
            'category' => $this->category,
            'quantity' => $this->quantity,
            'price' => (float) $this->price,
        ];
    }
}

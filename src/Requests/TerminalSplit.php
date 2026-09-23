<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Requests;

use TexHub\AlifPay\Exceptions\ConfigurationException;

/**
 * One partner terminal's share of a split payment — an entry of `mpTerminalInfo`.
 *
 * `conditionId` is the Salom installment condition and stays 0 for every other method. The
 * invoice lines describe what that partner is selling; Alif requires them on terminals that
 * have itemization switched on, and on Salom always.
 *
 * @see https://docs.acquiring.alif.tj/marketplace
 */
final class TerminalSplit
{
    public readonly string $amount;

    /** @var array<int, InvoiceItem> */
    private array $invoices = [];

    public function __construct(
        public readonly string $terminalId,
        int|float|string $amount,
        public readonly int $conditionId = 0,
    ) {
        if (trim($this->terminalId) === '') {
            throw new ConfigurationException('A marketplace split needs a partner terminal_id.');
        }

        if ($this->conditionId < 0) {
            throw new ConfigurationException('A Salom condition_id must not be negative.');
        }

        $this->amount = Amount::of($amount)->value;
    }

    public static function make(string $terminalId, int|float|string $amount, int $conditionId = 0): self
    {
        return new self($terminalId, $amount, $conditionId);
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

    /**
     * @return array<int, InvoiceItem>
     */
    public function getInvoices(): array
    {
        return $this->invoices;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'terminal_id' => $this->terminalId,
            'amount' => (float) $this->amount,
            'condition_id' => $this->conditionId,
            'invoices' => array_map(static fn (InvoiceItem $i) => $i->toSplitArray(), $this->invoices),
        ];
    }
}

<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TexHub\AlifPay\Exceptions\ConfigurationException;
use TexHub\AlifPay\Requests\InvoiceItem;
use TexHub\AlifPay\Requests\MarketplaceRequest;
use TexHub\AlifPay\Requests\TerminalSplit;

final class MarketplaceRequestTest extends TestCase
{
    public function test_it_builds_split_payment_body(): void
    {
        $body = MarketplaceRequest::make('MP_1', 500)
            ->callbackUrl('https://shop.tj/cb')
            ->returnUrl('https://shop.tj/ok')
            ->splitTo('partner_1', 300)
            ->splitTo('partner_2', 200)
            ->toArray();

        $this->assertSame('500.00', $body['amount']);
        $this->assertCount(2, $body['mpTerminalInfo']);
        $this->assertSame('partner_1', $body['mpTerminalInfo'][0]['terminal_id']);
        $this->assertSame(300.00, $body['mpTerminalInfo'][0]['amount']);
        $this->assertSame(0, $body['mpTerminalInfo'][0]['condition_id']);
        $this->assertSame([], $body['mpTerminalInfo'][0]['invoices']);
    }

    public function test_a_split_carries_its_salom_condition_and_invoice_lines(): void
    {
        $split = TerminalSplit::make('SELLER_005', '1200.00', conditionId: 12)
            ->addInvoiceItem(new InvoiceItem(
                name: 'Смартфон Samsung Galaxy A54',
                category: 'smartphones',
                quantity: 1,
                price: '1200.00',
            ));

        $body = MarketplaceRequest::make('MP_SALOM_1', '1200.00')
            ->callbackUrl('https://shop.tj/cb')
            ->returnUrl('https://shop.tj/ok')
            ->addSplit($split)
            ->toArray();

        $entry = $body['mpTerminalInfo'][0];

        $this->assertSame(12, $entry['condition_id']);
        $this->assertCount(1, $entry['invoices']);
        $this->assertSame('smartphones', $entry['invoices'][0]['category']);
        $this->assertSame(1200.00, $entry['invoices'][0]['price']);
        $this->assertArrayNotHasKey('vat_percent', $entry['invoices'][0]);
    }

    public function test_it_rejects_when_split_does_not_match_total(): void
    {
        $this->expectException(ConfigurationException::class);

        MarketplaceRequest::make('MP_2', 500)
            ->callbackUrl('https://shop.tj/cb')
            ->returnUrl('https://shop.tj/ok')
            ->splitTo('partner_1', 300)
            ->toArray();
    }

    public function test_it_requires_at_least_one_terminal(): void
    {
        $this->expectException(ConfigurationException::class);

        MarketplaceRequest::make('MP_3', 100)
            ->callbackUrl('https://shop.tj/cb')
            ->returnUrl('https://shop.tj/ok')
            ->toArray();
    }

    public function test_a_split_without_a_terminal_is_refused(): void
    {
        $this->expectException(ConfigurationException::class);

        new TerminalSplit('   ', 100);
    }
}
